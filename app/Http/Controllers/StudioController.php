<?php

namespace App\Http\Controllers;

use App\Jobs\RenderImageJob;
use App\Jobs\RenderVideoJob;
use App\Models\Generation;
use App\Models\Preset;
use App\Models\Product;
use App\Services\GeminiService;
use App\Services\StyleSuggestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

class StudioController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $this->reconcileStuckCredits($user);

        $projects = $user->projects()->withCount('generations')->latest()->get();
        $presets = Preset::orderBy('sort_order')->get()->groupBy('category');
        $latest = $user->generations()->with('project')->latest()->limit(30)->get();
        $creditsUsed = (int) $user->generations()->where('status', 'completed')->sum('credits_cost');
        $pendingCount = (int) $user->generations()->whereIn('status', ['pending', 'processing'])->count();

        return view('studio.index', compact('projects', 'presets', 'latest', 'creditsUsed', 'pendingCount'));
    }

    public function storeProject(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'base_concept' => ['nullable', 'string', 'max:1000'],
        ]);

        $project = auth()->user()->projects()->create($data);

        if ($request->wantsJson()) {
            return response()->json(['project_id' => $project->id, 'name' => $project->name]);
        }

        return redirect()->route('studio.index')->with('success', 'Đã tạo dự án.');
    }

    /**
     * Ideation — Gemini turns idea + presets into English image/video prompts.
     */
    public function ideate(Request $request)
    {
        $data = $request->validate([
            'idea' => ['required', 'string', 'max:1000'],
            'preset_ids' => ['nullable', 'array'],
            'preset_ids.*' => ['integer', 'exists:presets,id'],
            'creative_level' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $creativeLevel = (int) ($data['creative_level'] ?? studio_config('creative_level', 6));
        $injections = $this->resolveInjectedPresets($data['preset_ids'] ?? []);
        $result = app(GeminiService::class)->generateCreativeDirector($data['idea'], $injections, $creativeLevel);

        $history = auth()->user()->prompts()->create([
            'idea' => $data['idea'],
            'image_prompt_en' => $result['image_prompt_en'],
            'video_prompt_en' => $result['video_prompt_en'],
            'json_response' => $result,
        ]);

        return response()->json([
            'history_id' => $history->id,
            'image_prompt_en' => $result['image_prompt_en'],
            'video_prompt_en' => $result['video_prompt_en'],
            'keywords' => $result['keywords'] ?? [],
            'creative_level' => $result['creative_level'] ?? $creativeLevel,
            'adherence' => $result['adherence'] ?? null,
        ]);
    }

    /**
     * 2D image generation — async via queue.
     */
    public function generate(Request $request)
    {
        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:4000'],
            'resolution' => ['nullable', 'string', 'in:1K,2K'],
            'ratio' => ['nullable', 'string', 'in:1:1,4:3,3:4,16:9,9:16,4:5,21:9,19:6'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'history_id' => ['nullable', 'integer', 'exists:prompts_history,id'],
        ]);

        $cost = (int) studio_config('image_credits', 1);

        return $this->queueGeneration('image', $data, $cost);
    }

    /**
     * Video catwalk render — async via queue.
     */
    public function renderVideo(Request $request)
    {
        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:4000'],
            'base_image' => ['nullable', 'string', 'max:2048'],
            'camera' => ['nullable', 'string', 'max:255'],
            'resolution' => ['nullable', 'string', 'in:480,720,1080'],
            'duration' => ['nullable', 'string', 'in:5,8,10,15,20'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'history_id' => ['nullable', 'integer', 'exists:prompts_history,id'],
        ]);

        $cost = (int) studio_config('video_credits', 10);

        return $this->queueGeneration('video', $data, $cost);
    }

    /**
     * Inpainting / refinement — reuses the source image as base (stub).
     */
    public function inpaint(Request $request, Generation $generation)
    {
        abort_unless($generation->user_id === auth()->id(), 403);

        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:4000'],
        ]);

        $data['prompt'] = 'Edit this fashion image precisely. Only apply the requested change and keep the '
            .'model, pose, outfit, fabric, colours, lighting, composition and background exactly as in the '
            .'original. Requested edit: '.$request->input('prompt');

        if ($generation->media_url) {
            $data['base_image'] = $generation->media_url;
        }
        $data['edit'] = true;

        $cost = (int) studio_config('image_credits', 1);

        return $this->queueGeneration('image', $data, $cost, $generation);
    }

    /**
     * Mark a generation failed and refund its credits (used for stuck / aborted jobs).
     */
    protected function failStuck(Generation $generation, string $message): void
    {
        $generation->update(['status' => 'failed', 'error' => $message]);
        if ($generation->credits_cost > 0) {
            $generation->user?->increment('credits_balance', $generation->credits_cost);
        }
    }

    /**
     * Polling endpoint for a single generation.
     */
    public function show(Generation $generation)
    {
        abort_unless($generation->user_id === auth()->id(), 403);

        // A generation left 'processing' by a killed web request (client disconnect) is healed here
        // so polling resolves instead of spinning "Đang tạo" forever.
        $stuckWindow = $generation->type === 'video' ? 12 : 6; // minutes; job timeout is 10m (video) / 5m (image)
        if ($generation->status === 'processing'
            && $generation->updated_at->lt(now()->subMinutes($stuckWindow))) {
            $this->failStuck($generation, 'Hết thời gian xử lý (có thể request đã bị ngắt). Bấm “Xử lý ngay” hoặc thử lại.');
        } elseif ($generation->status === 'pending') {
            // Lazy worker: if this generation is still pending (not picked up by a worker), process it
            // inline now so the polling request returns the completed result. Keep running even if the
            // polling client disconnects (ignore_user_abort) so a slow provider isn't killed mid-run.
            set_time_limit(600);
            ignore_user_abort(true);
            $generation->update(['status' => 'processing']);
            try {
                if ($generation->type === 'video') {
                    RenderVideoJob::dispatchSync($generation->id);
                } else {
                    RenderImageJob::dispatchSync($generation->id);
                }
            } catch (\Throwable $e) {
                logger()->error('Lazy process failed for generation #'.$generation->id.': '.$e->getMessage());
                $generation->update(['status' => 'failed', 'error' => $e->getMessage()]);
            }
        }

        $g = $generation->fresh();

        return response()->json([
            'id' => $g->id,
            'type' => $g->type,
            'status' => $g->status,
            'model' => $g->model,
            'provider' => $g->provider,
            'media_url' => $g->media_url,
            'error' => $g->error,
            'credits_cost' => $g->credits_cost,
        ]);
    }

    /**
     * Resolve the provider + model for a generation type.
     *
     * @return array{0: string, 1: string}
     */
    protected function defaultProviderModel(string $type): array
    {
        if ($type === 'video') {
            return ['wan', (string) studio_config('video_model', 'wan2.5-t2v')];
        }

        $provider = (string) studio_config('image_provider', 'flux');
        $model = match ($provider) {
            'gemini' => (string) studio_config('gemini_image_model', 'gemini-2.5-flash-image'),
            'wan' => (string) studio_config('wan_model', 'wan2.7-image-pro'),
            'qwen' => (string) studio_config('qwen_model', 'qwen-image-3.0-pro'),
            default => (string) studio_config('image_model', 'flux-1.1-schnell'),
        };

        return [$provider, $model];
    }

    /**
     * Cancel a pending/processing generation and refund its credits.
     */
    public function cancel(Generation $generation)
    {
        abort_unless($generation->user_id === auth()->id(), 403);

        if (! in_array($generation->status, ['pending', 'processing'])) {
            return response()->json(['message' => 'Nhiệm vụ đã kết thúc.'], 422);
        }

        $generation->update(['status' => Generation::STATUS_CANCELLED]);

        if ($generation->credits_cost > 0) {
            $generation->user?->increment('credits_balance', $generation->credits_cost);
        }

        return response()->json(['status' => 'cancelled']);
    }

    /**
     * Delete a generation (and its stored media).
     */
    public function destroy(Generation $generation)
    {
        abort_unless($generation->user_id === auth()->id(), 403);

        $generation->delete();

        return response()->json(['message' => 'Đã xóa nhiệm vụ.']);
    }

    /**
     * Refund credits held by jobs stuck in 'processing' (e.g. the web request was killed mid-run),
     * so the balance reflects reality and creation is not blocked by phantom usage.
     */
    protected function reconcileStuckCredits($user): void
    {
        $stuck = $user->generations()
            ->where('status', 'processing')
            ->where('updated_at', '<', now()->subMinutes(30))
            ->get();

        foreach ($stuck as $g) {
            $g->update(['status' => 'failed', 'error' => 'Hết thời gian xử lý (job bị ngắt).']);
            if ($g->credits_cost > 0) {
                $g->user?->increment('credits_balance', $g->credits_cost);
            }
        }
    }

    protected function queueGeneration(string $type, array $data, int $cost, ?Generation $source = null)
    {
        $user = auth()->user();

        // Internal admin tool: never hard-block on credits. Track usage (balance may go negative).
        $this->reconcileStuckCredits($user);
        $user->decrement('credits_balance', $cost);

        if (! empty($data['edit'])) {
            $provider = 'qwen';
            $model = (string) studio_config('qwen_edit_model', 'qwen-image-edit');
        } else {
            [$provider, $model] = $this->defaultProviderModel($type);
        }

        $generation = $user->generations()->create([
            'project_id' => $data['project_id'] ?? null,
            'prompts_history_id' => $data['history_id'] ?? null,
            'type' => $type,
            'status' => 'pending',
            'prompt' => $data['prompt'] ?? null,
            'provider' => $provider,
            'model' => $model,
            'resolution' => $data['resolution'] ?? null,
            'ratio' => $data['ratio'] ?? null,
            'duration' => $data['duration'] ?? null,
            'base_image' => $data['base_image'] ?? $source?->media_url,
            'mask_image' => $data['mask_image'] ?? null,
            'credits_cost' => $cost,
        ]);

        // The job is processed lazily when the client polls this generation (show()), or via the
        // "Xử lý ngay" button / studio:process. The create request returns fast (pending) so the
        // Canvas shows "Đang tạo" immediately.
        $fresh = $generation->fresh();

        return response()->json([
            'generation_id' => $fresh->id,
            'status' => $fresh->status,
            'model' => $fresh->model,
            'provider' => $fresh->provider,
            'media_url' => $fresh->media_url,
            'error' => $fresh->error,
            'credits_left' => $user->fresh()->credits_balance,
        ]);
    }

    /**
     * Map selected preset ids to a category => prompt_injection map.
     */
    protected function resolveInjectedPresets(array $ids): array
    {
        $presets = Preset::whereIn('id', $ids)->get()->groupBy('category');

        return $presets->map(function ($group) {
            return $group->pluck('prompt_injection')->filter()->implode(', ');
        })->all();
    }

    /**
     * Reverse-prompt: analyse a reference image and suggest style/prompt.
     */
    public function suggest(Request $request)
    {
        $data = $request->validate([
            'image' => ['nullable', 'image', 'max:8192'],
            'reference_url' => ['nullable', 'string', 'max:2048'],
            'creative_level' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $imagePath = null;

        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $path = $request->file('image')->store('studio/ref', 'public');
            $imagePath = storage_path('app/public/'.$path);
        } elseif (! empty($data['reference_url'])) {
            $imagePath = $this->resolveReferencePath($data['reference_url']);
        }

        if (! $imagePath || ! is_file($imagePath)) {
            return response()->json(['message' => 'Không đọc được ảnh nguồn. Vui lòng tải ảnh hoặc chọn ảnh sản phẩm.'], 422);
        }

        $creativeLevel = (int) ($data['creative_level'] ?? studio_config('creative_level', 6));
        $result = app(StyleSuggestService::class)->suggest($imagePath, $creativeLevel);

        return response()->json($result);
    }

    protected function resolveReferencePath(string $url): ?string
    {
        $path = ltrim((string) parse_url($url, PHP_URL_PATH), '/');

        foreach ([public_path($path), storage_path('app/public/'.$path)] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Test a provider API key with a lightweight, non-generating request.
     */
    public function testApi(string $service)
    {
        $key = studio_api_key($service);

        if (! $key) {
            return response()->json(['ok' => false, 'message' => 'Chưa cấu hình khoá cho '.$service.'.'], 422);
        }

        try {
            $result = match ($service) {
                'gemini' => $this->testGemini($key),
                'replicate' => $this->testReplicate($key),
                'fal' => ['ok' => true, 'message' => 'Fal.ai: khoá đã lưu (không có endpoint ping miễn phí).'],
                'wan', 'qwen', 'dashscope' => $this->testDashscope($key),
                default => ['ok' => false, 'message' => 'Không hỗ trợ test '.$service.'.'],
            };
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }

        return response()->json($result);
    }

    protected function testGemini(string $key): array
    {
        $resp = Http::timeout(20)->get('https://generativelanguage.googleapis.com/v1beta/models?key='.$key);

        return $resp->successful()
            ? ['ok' => true, 'message' => 'Gemini: kết nối OK ('.count($resp->json('models', []) ?: []).' models).']
            : ['ok' => false, 'message' => 'Gemini: HTTP '.$resp->status().' — '.data_get($resp->json(), 'error.message', 'key không hợp lệ')];
    }

    protected function testReplicate(string $key): array
    {
        $resp = Http::withToken($key)->timeout(20)->get('https://api.replicate.com/v1/models');

        return $resp->successful()
            ? ['ok' => true, 'message' => 'Replicate: kết nối OK.']
            : ['ok' => false, 'message' => 'Replicate: HTTP '.$resp->status().' — key không hợp lệ'];
    }

    protected function testDashscope(string $key): array
    {
        // Wan (image & video) + Qwen run on a DashScope-compatible endpoint. Try every
        // candidate host (classic region + QwenCloud Token/Coding Plan) because a
        // QwenCloud key is bound to a specific base URL by key type.
        $configured = rtrim((string) studio_config('dashscope_base', 'https://dashscope-intl.aliyuncs.com'), '/');
        $candidates = array_unique([
            $configured,
            str_contains($configured, 'intl') ? 'https://dashscope.aliyuncs.com' : 'https://dashscope-intl.aliyuncs.com',
            'https://token-plan.ap-southeast-1.maas.aliyuncs.com',
            'https://coding-intl.dashscope.aliyuncs.com',
        ]);

        // Use a REAL image model (matching the generation fallback chain) instead of a made-up
        // name: on plan hosts a non-existent model can return 401 and wrongly look like a bad key.
        $models = ['qwen-image-3.0-pro', 'qwen-image-max', 'qwen-image-plus', 'qwen-image', 'wan2.7-image-pro'];

        $last = null;
        foreach ($candidates as $host) {
            $unpurchased = [];
            foreach ($models as $model) {
                try {
                    $resp = Http::withToken($key)->timeout(25)
                        ->post($host.'/api/v1/services/aigc/multimodal-generation/generation', [
                            'model' => $model,
                            'input' => ['messages' => [['role' => 'user', 'content' => [['text' => 'a minimalist premium fashion editorial photo']]]]],
                            'parameters' => ['n' => 1, 'size' => '1328*1328', 'watermark' => false],
                        ]);
                } catch (\Throwable $e) {
                    continue;
                }

                if ($resp->successful()) {
                    return ['ok' => true, 'message' => 'DashScope: khóa hợp lệ tại '.$host.' — model '.$model.' dùng được (đã tạo thử 1 ảnh).'];
                }
                $status = $resp->status();
                $body = strtolower((string) $resp->body());

                if (in_array($status, [400, 422])) {
                    return ['ok' => true, 'message' => 'DashScope: khóa hợp lệ tại '.$host.' — model '.$model.' dùng được.'];
                }
                if ($status === 403 || str_contains($body, 'unpurchased') || str_contains($body, 'eligible')) {
                    $unpurchased[] = $model;

                    continue;
                }
                // 401 / other auth issues on this host — try the next host/model.
                $last = ['status' => $status, 'host' => $host];
            }

            if ($unpurchased) {
                return ['ok' => false, 'message' => 'DashScope: khóa hợp lệ tại '.$host.' — nhưng model ảnh ('.implode(', ', $unpurchased).') CHƯA được mua trên tài khoản (403 Unpurchased). Hãy bật/mua một model Qwen-Image trong QwenCloud Model Center, hoặc dùng Gemini.'];
            }
        }

        return ['ok' => false, 'message' => 'DashScope: key chưa được chấp nhận (HTTP '.($last['status'] ?? '…').' tại '.($last['host'] ?? '…').'). Tạo key mới tại https://home.qwencloud.com/api-keys và dán đầy đủ. Gợi ý ổn định: dùng Gemini (Google AI Studio key) để tạo ảnh.'];
    }

    /**
     * Library — browse & manage all generated assets.
     */
    public function library(Request $request)
    {
        $query = auth()->user()->generations()->with('project')->latest();

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->input('project_id'));
        }
        if ($request->filled('q')) {
            $query->where('prompt', 'like', '%'.$request->input('q').'%');
        }

        $generations = $query->paginate(24)->withQueryString();
        $projects = auth()->user()->projects()->orderBy('name')->get();

        return view('studio.library', compact('generations', 'projects'));
    }

    /**
     * Download a generated asset.
     */
    public function download(Generation $generation)
    {
        abort_unless($generation->user_id === auth()->id(), 403);

        if (! $generation->media_url) {
            abort(404);
        }

        $path = ltrim((string) parse_url($generation->media_url, PHP_URL_PATH), '/');
        $path = str_replace('storage/', '', $path);
        $abs = storage_path('app/public/'.$path);

        if (! is_file($abs)) {
            abort(404);
        }

        return response()->download($abs, basename($abs));
    }

    /**
     * Extract dominant colors from a generated image (for the color palette).
     */
    public function palette(Generation $generation)
    {
        abort_unless($generation->user_id === auth()->id(), 403);

        if (! $generation->media_url) {
            return response()->json(['colors' => []]);
        }

        $rel = ltrim((string) parse_url($generation->media_url, PHP_URL_PATH), '/');
        $rel = str_replace('storage/', '', $rel);
        $abs = storage_path('app/public/'.$rel);

        if (! is_file($abs)) {
            return response()->json(['colors' => []]);
        }

        return response()->json(['colors' => $this->extractPalette($abs, 6)]);
    }

    protected function extractPalette(string $file, int $count = 6): array
    {
        $src = @imagecreatefromstring((string) file_get_contents($file));
        if (! $src) {
            return [];
        }

        $W = imagesx($src);
        $H = imagesy($src);
        if ($W <= 0 || $H <= 0) {
            return [];
        }

        $w = 64;
        $h = max(1, (int) round($H * ($w / $W)));
        $thumb = imagecreatetruecolor($w, $h);
        imagecopyresampled($thumb, $src, 0, 0, 0, 0, $w, $h, $W, $H);

        $buckets = [];
        for ($y = 0; $y < $h; $y += 2) {
            for ($x = 0; $x < $w; $x += 2) {
                $c = imagecolorat($thumb, $x, $y);
                $r = ($c >> 16) & 0xFF;
                $g = ($c >> 8) & 0xFF;
                $b = $c & 0xFF;
                $key = ((int) ($r / 32)).','.((int) ($g / 32)).','.((int) ($b / 32));
                if (! isset($buckets[$key])) {
                    $buckets[$key] = ['n' => 0, 'r' => 0, 'g' => 0, 'b' => 0];
                }
                $buckets[$key]['n']++;
                $buckets[$key]['r'] += $r;
                $buckets[$key]['g'] += $g;
                $buckets[$key]['b'] += $b;
            }
        }

        imagedestroy($thumb);
        imagedestroy($src);

        uasort($buckets, fn ($a, $b) => $b['n'] <=> $a['n']);

        $colors = [];
        foreach (array_slice($buckets, 0, $count) as $bk) {
            $r = (int) round($bk['r'] / $bk['n']);
            $g = (int) round($bk['g'] / $bk['n']);
            $b = (int) round($bk['b'] / $bk['n']);
            $colors[] = sprintf('#%02X%02X%02X', $r, $g, $b);
        }

        return $colors;
    }

    public function settings()
    {
        return view('studio.settings', [
            'image_credits' => setting('studio_image_credits', config('studio.image_credits')),
            'video_credits' => setting('studio_video_credits', config('studio.video_credits')),
            'max_generations' => setting('studio_max_generations', 50),
            'image_provider' => setting('studio_image_provider', config('studio.image_provider')),
            'prompt_provider' => setting('studio_prompt_provider', config('studio.prompt_provider')),
            'vision_provider' => setting('studio_vision_provider', config('studio.vision_provider')),
            'prompt_model' => setting('studio_prompt_model', config('studio.prompt_model')),
            'image_model' => setting('studio_image_model', config('studio.image_model')),
            'wan_model' => setting('studio_wan_model', config('studio.wan_model')),
            'qwen_model' => setting('studio_qwen_model', config('studio.qwen_model')),
            'qwen_edit_model' => setting('studio_qwen_edit_model', config('studio.qwen_edit_model')),
            'gemini_image_model' => setting('studio_gemini_image_model', config('studio.gemini_image_model')),
            'video_model' => setting('studio_video_model', config('studio.video_model')),
            'vision_model' => setting('studio_vision_model', config('studio.vision_model')),
            'dashscope_base' => setting('studio_dashscope_base', config('studio.dashscope_base')),
            'processing' => setting('studio_processing', config('studio.processing')),
            'image_resolution' => setting('studio_image_resolution', config('studio.image_resolution')),
            'video_resolution' => setting('studio_video_resolution', config('studio.video_resolution')),
            'image_ratio' => setting('studio_image_ratio', config('studio.image_ratio')),
            'video_duration' => setting('studio_video_duration', config('studio.video_duration')),
            'brand_logo' => setting('studio_brand_logo', ''),
            'face_ref' => setting('studio_face_ref', ''),
            'pending_count' => auth()->user()->generations()->whereIn('status', ['pending', 'processing'])->count(),
            'queue_driver' => config('queue.default'),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'image_credits' => ['required', 'integer', 'min:0', 'max:1000'],
            'video_credits' => ['required', 'integer', 'min:0', 'max:1000'],
            'max_generations' => ['nullable', 'integer', 'min:1', 'max:500'],
            'image_provider' => ['required', 'string', 'in:flux,wan,qwen,gemini'],
            'prompt_provider' => ['required', 'string', 'in:gemini,qwen'],
            'vision_provider' => ['required', 'string', 'in:gemini,qwen'],
            'prompt_model' => ['required', 'string', 'max:255'],
            'image_model' => ['nullable', 'string', 'max:255'],
            'wan_model' => ['required', 'string', 'max:255'],
            'qwen_model' => ['required', 'string', 'max:255'],
            'qwen_edit_model' => ['nullable', 'string', 'max:255'],
            'gemini_image_model' => ['nullable', 'string', 'max:255'],
            'video_model' => ['required', 'string', 'max:255'],
            'vision_model' => ['required', 'string', 'max:255'],
            'dashscope_base' => ['required', 'string', 'max:255', 'regex:/^https?:\/\/[^\/]+$/'],
            'processing' => ['required', 'string', 'in:sync,queue'],
            'image_resolution' => ['required', 'string', 'in:1K,2K'],
            'video_resolution' => ['required', 'string', 'in:480,720,1080'],
            'image_ratio' => ['required', 'string', 'in:1:1,4:3,3:4,16:9,9:16,4:5,21:9,19:6'],
            'video_duration' => ['required', 'string', 'in:5,8,10,15,20'],
        ]);

        set_setting('studio_image_credits', (string) $data['image_credits']);
        set_setting('studio_video_credits', (string) $data['video_credits']);
        set_setting('studio_max_generations', (string) ($data['max_generations'] ?? 50));
        set_setting('studio_image_provider', $data['image_provider']);
        set_setting('studio_prompt_provider', $data['prompt_provider']);
        set_setting('studio_vision_provider', $data['vision_provider']);
        set_setting('studio_prompt_model', $data['prompt_model']);
        set_setting('studio_image_model', $data['image_model'] ?? '');
        set_setting('studio_wan_model', $data['wan_model']);
        set_setting('studio_qwen_model', $data['qwen_model']);
        set_setting('studio_qwen_edit_model', $data['qwen_edit_model'] ?? '');
        set_setting('studio_gemini_image_model', $data['gemini_image_model'] ?? '');
        set_setting('studio_video_model', $data['video_model']);
        set_setting('studio_vision_model', $data['vision_model']);
        set_setting('studio_dashscope_base', $data['dashscope_base']);
        set_setting('studio_processing', $data['processing']);
        set_setting('studio_image_resolution', $data['image_resolution']);
        set_setting('studio_video_resolution', $data['video_resolution']);
        set_setting('studio_image_ratio', $data['image_ratio']);
        set_setting('studio_video_duration', $data['video_duration']);

        if ($request->hasFile('brand_logo') && $request->file('brand_logo')->isValid()) {
            set_setting('studio_brand_logo', '/storage/'.$request->file('brand_logo')->store('studio/logo', 'public'));
        }
        if ($request->hasFile('face_ref') && $request->file('face_ref')->isValid()) {
            set_setting('studio_face_ref', '/storage/'.$request->file('face_ref')->store('studio/ref', 'public'));
            // New face -> invalidate the cached face description so it is re-described once.
            set_setting('studio_face_desc', '');
            set_setting('studio_face_desc_hash', '');
        }

        return back()->with('success', 'Đã lưu cài đặt Studio.');
    }

    public function api()
    {
        $providers = [
            'gemini' => ['label' => 'Gemini — Giám đốc sáng tạo', 'hint' => 'GEMINI_API_KEY', 'configured' => (bool) studio_api_key('gemini')],
            'fal' => ['label' => 'Fal.ai — Flux (ảnh)', 'hint' => 'FAL_KEY', 'configured' => (bool) studio_api_key('fal')],
            'replicate' => ['label' => 'Replicate — Flux (ảnh)', 'hint' => 'REPLICATE_API_TOKEN', 'configured' => (bool) studio_api_key('replicate')],
            'wan' => ['label' => 'Wan AI — video (dùng DASHSCOPE_API_KEY)', 'hint' => 'WAN_API_KEY / DASHSCOPE_API_KEY', 'configured' => (bool) (studio_api_key('wan') ?: studio_api_key('dashscope'))],
            'veo' => ['label' => 'Google Veo — video', 'hint' => 'GOOGLE_VEO_KEY', 'configured' => (bool) studio_api_key('veo')],
            'qwen' => ['label' => 'Qwen — ảnh (QwenCloud, dùng endpoint DashScope)', 'hint' => 'QWEN_API_KEY (home.qwencloud.com/api-keys) · model qwen-image', 'configured' => (bool) studio_api_key('qwen')],
            'dashscope' => ['label' => 'DashScope — Wan/Qwen image & video (Alibaba)', 'hint' => 'DASHSCOPE_API_KEY', 'configured' => (bool) studio_api_key('dashscope')],
        ];

        return view('studio.api', compact('providers'));
    }

    public function updateApi(Request $request)
    {
        $services = ['gemini', 'fal', 'replicate', 'wan', 'veo', 'qwen', 'dashscope'];

        foreach ($services as $service) {
            // Clear if requested, else store a new encrypted key, else keep.
            if ($request->boolean('clear_'.$service)) {
                set_setting('api_'.$service.'_key', '');

                continue;
            }

            $value = trim((string) $request->input('key_'.$service, ''));

            if ($value !== '') {
                set_setting('api_'.$service.'_key', Crypt::encryptString($value));
            }
        }

        return back()->with('success', 'Đã lưu cấu hình API.');
    }

    /**
     * Active products with an image — used as reference-image sources in Studio.
     */
    public function references()
    {
        $items = Product::where('is_active', true)->whereNotNull('image')
            ->latest()->limit(40)->get(['id', 'name', 'image'])
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'url' => $p->image_url])
            ->filter(fn ($i) => ! empty($i['url']))
            ->values();

        return response()->json(['items' => $items]);
    }

    /**
     * Prompt template (preset) manager — admin CRUD.
     */
    public function presets()
    {
        $presets = Preset::orderBy('sort_order')->get()->groupBy('category');
        $categories = ['fabric', 'silhouette', 'style', 'background', 'pose', 'camera'];

        return view('studio.presets', compact('presets', 'categories'));
    }

    public function storePreset(Request $request)
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'max:40'],
            'ui_label' => ['required', 'string', 'max:120'],
            'prompt_injection' => ['required', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        Preset::create($data + ['sort_order' => $data['sort_order'] ?? 0]);

        return back()->with('success', 'Đã thêm preset.');
    }

    public function updatePreset(Request $request, Preset $preset)
    {
        $data = $request->validate([
            'ui_label' => ['required', 'string', 'max:120'],
            'prompt_injection' => ['required', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $preset->update($data + ['sort_order' => $data['sort_order'] ?? 0]);

        return back()->with('success', 'Đã cập nhật preset.');
    }

    public function destroyPreset(Preset $preset)
    {
        $preset->delete();

        return back()->with('success', 'Đã xóa preset.');
    }

    /**
     * Pattern Maker — generate a seamless fabric pattern via the configured image provider.
     */
    public function patternPage()
    {
        return view('studio.pattern', [
            'latest' => auth()->user()->generations()->where('type', 'image')->latest()->limit(8)->get(),
        ]);
    }

    public function tryonPage()
    {
        return view('studio.tryon', [
            'latest' => auth()->user()->generations()->where('type', 'image')->latest()->limit(8)->get(),
        ]);
    }

    public function pattern(Request $request)
    {
        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:2000'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'history_id' => ['nullable', 'integer', 'exists:prompts_history,id'],
        ]);
        $data['prompt'] = 'Seamless textile fabric pattern, '.$data['prompt'].', high detail, repeatable tile, premium fashion, 4k';
        $cost = (int) studio_config('image_credits', 1);

        return $this->queueGeneration('image', $data, $cost);
    }

    /**
     * Virtual Try-On — best-effort try-on using the image provider (upload a person photo).
     */
    public function tryon(Request $request)
    {
        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'max:8192'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'history_id' => ['nullable', 'integer', 'exists:prompts_history,id'],
        ]);
        $cost = (int) studio_config('image_credits', 1);

        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $data['base_image'] = '/storage/'.$request->file('image')->store('studio/ref', 'public');
        }

        return $this->queueGeneration('image', $data, $cost);
    }

    /**
     * Latest generations (JSON) — used to re-sync the Studio output grid reliably.
     */
    public function latest()
    {
        $items = auth()->user()->generations()->with('project')->latest()->limit(30)->get()
            ->map(fn ($g) => [
                'id' => $g->id, 'type' => $g->type, 'status' => $g->status,
                'model' => $g->model, 'provider' => $g->provider,
                'media_url' => $g->media_url, 'error' => $g->error,
                'credits_cost' => $g->credits_cost, 'project_id' => $g->project_id,
                'created_at' => $g->created_at?->format('d/m H:i'),
            ])->values();

        return response()->json(['items' => $items]);
    }

    /**
     * Process the user's queued generations synchronously (no worker / cron needed).
     * Best for quick jobs (stub / Gemini / short renders); long async jobs (Wan/Qwen)
     * are better handled by the queue worker via cron.
     */
    public function processQueue()
    {
        $pending = auth()->user()->generations()
            ->whereIn('status', ['pending', 'processing'])
            ->orderBy('id')->limit(5)->get();

        $n = 0;
        foreach ($pending as $gen) {
            try {
                if ($gen->type === 'video') {
                    RenderVideoJob::dispatchSync($gen->id);
                } else {
                    RenderImageJob::dispatchSync($gen->id);
                }
                $n++;
            } catch (Throwable $e) {
                logger()->error('Process queue failed for generation #'.$gen->id.': '.$e->getMessage());
            }
        }

        return response()->json(['processed' => $n, 'message' => 'Đã xử lý '.$n.' công việc đang chờ.']);
    }
}
