<?php

namespace App\Http\Controllers;

use App\Jobs\RenderImageJob;
use App\Jobs\RenderVideoJob;
use App\Models\Generation;
use App\Models\Preset;
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

        $projects = $user->projects()->withCount('generations')->latest()->get();
        $presets = Preset::orderBy('sort_order')->get()->groupBy('category');
        $latest = $user->generations()->with('project')->latest()->limit(12)->get();

        return view('studio.index', compact('projects', 'presets', 'latest'));
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
        ]);

        $injections = $this->resolveInjectedPresets($data['preset_ids'] ?? []);
        $result = app(GeminiService::class)->generateCreativeDirector($data['idea'], $injections);

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
        ]);
    }

    /**
     * 2D image generation — async via queue.
     */
    public function generate(Request $request)
    {
        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:4000'],
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
            'base_image' => ['required', 'string', 'max:2048'],
            'camera' => ['nullable', 'string', 'max:255'],
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

        if ($generation->media_url) {
            $data['base_image'] = $generation->media_url;
        }

        $cost = (int) studio_config('image_credits', 1);

        return $this->queueGeneration('image', $data, $cost, $generation);
    }

    /**
     * Polling endpoint for a single generation.
     */
    public function show(Generation $generation)
    {
        abort_unless($generation->user_id === auth()->id(), 403);

        return response()->json([
            'id' => $generation->id,
            'type' => $generation->type,
            'status' => $generation->status,
            'model' => $generation->model,
            'provider' => $generation->provider,
            'media_url' => $generation->media_url,
            'error' => $generation->error,
            'credits_cost' => $generation->credits_cost,
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
            'wan' => (string) studio_config('wan_model', 'wan2.7-image-pro'),
            'qwen' => (string) studio_config('qwen_model', 'qwen-image'),
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

    protected function queueGeneration(string $type, array $data, int $cost, ?Generation $source = null)
    {
        $user = auth()->user();

        if ($user->credits_balance < $cost) {
            return response()->json(['message' => 'Bạn không đủ tín dụng. Yêu cầu '.$cost.' tín dụng.'], 422);
        }

        $user->decrement('credits_balance', $cost);

        [$provider, $model] = $this->defaultProviderModel($type);

        $generation = $user->generations()->create([
            'project_id' => $data['project_id'] ?? null,
            'prompts_history_id' => $data['history_id'] ?? null,
            'type' => $type,
            'status' => 'pending',
            'prompt' => $data['prompt'] ?? null,
            'provider' => $provider,
            'model' => $model,
            'base_image' => $data['base_image'] ?? $source?->media_url,
            'mask_image' => $data['mask_image'] ?? null,
            'credits_cost' => $cost,
        ]);

        // Processing mode: 'sync' (default, no worker) or 'queue' (async + worker).
        if (studio_config('processing', 'sync') === 'queue') {
            if ($type === 'video') {
                RenderVideoJob::dispatch($generation->id);
            } else {
                RenderImageJob::dispatch($generation->id);
            }
        } else {
            if ($type === 'video') {
                RenderVideoJob::dispatchSync($generation->id);
            } else {
                RenderImageJob::dispatchSync($generation->id);
            }
        }

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
        $request->validate([
            'image' => ['required', 'image', 'max:8192'],
        ]);

        $path = $request->file('image')->store('studio/ref', 'public');

        $result = app(StyleSuggestService::class)->suggest(storage_path('app/public/'.$path));

        return response()->json($result);
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
        // Wan (image & video) + Qwen run on Alibaba DashScope. Auto-detect the
        // region: try the configured base, then the other one. An INVALID model
        // makes the request fail at validation (no cost).
        $base = rtrim((string) studio_config('dashscope_base', 'https://dashscope-intl.aliyuncs.com'), '/');
        $alt = str_contains($base, 'dashscope-intl') ? 'https://dashscope.aliyuncs.com' : 'https://dashscope-intl.aliyuncs.com';

        $lastStatus = null;
        foreach (array_unique([$base, $alt]) as $host) {
            $resp = Http::withToken($key)->timeout(20)
                ->post($host.'/api/v1/services/aigc/multimodal-generation/generation', [
                    'model' => '__auth_check__',
                    'input' => ['messages' => [['role' => 'user', 'content' => [['text' => 'test']]]]],
                    'parameters' => [],
                ]);

            if ($resp->successful()) {
                return ['ok' => true, 'message' => 'DashScope: kết nối OK ('.$host.').'];
            }
            if (in_array($resp->status(), [400, 422])) {
                $region = str_contains($host, 'intl') ? 'quốc tế' : 'Trung Quốc';
                $extra = $host !== $base ? ' (đặt DashScope base URL = '.$host.')' : '';

                return ['ok' => true, 'message' => 'DashScope: khoá hợp lệ — vùng '.$region.' ('.$host.')'.$extra];
            }
            if ($resp->status() === 404) {
                return ['ok' => false, 'message' => 'DashScope: HTTP 404 ở '.$host.' — sai đường dẫn base URL (chỉ gồm host, không thêm /apps/...).'];
            }
            $lastStatus = $resp->status();
        }

        return ['ok' => false, 'message' => 'DashScope: key bị từ chối (HTTP '.$lastStatus.'). Lưu ý: key từ home.qwencloud.com là key QwenCloud — nền tảng riêng, KHÔNG chạy trên endpoint DashScope. Hãy dùng DASHSCOPE_API_KEY lấy từ Alibaba Cloud Model Studio (Bailian console), bật model Wan/Qwen.'];
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
            'prompt_model' => setting('studio_prompt_model', config('studio.prompt_model')),
            'image_model' => setting('studio_image_model', config('studio.image_model')),
            'wan_model' => setting('studio_wan_model', config('studio.wan_model')),
            'qwen_model' => setting('studio_qwen_model', config('studio.qwen_model')),
            'video_model' => setting('studio_video_model', config('studio.video_model')),
            'vision_model' => setting('studio_vision_model', config('studio.vision_model')),
            'dashscope_base' => setting('studio_dashscope_base', config('studio.dashscope_base')),
            'processing' => setting('studio_processing', config('studio.processing')),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'image_credits' => ['required', 'integer', 'min:0', 'max:1000'],
            'video_credits' => ['required', 'integer', 'min:0', 'max:1000'],
            'max_generations' => ['nullable', 'integer', 'min:1', 'max:500'],
            'image_provider' => ['required', 'string', 'in:flux,wan,qwen'],
            'prompt_model' => ['required', 'string', 'max:255'],
            'image_model' => ['nullable', 'string', 'max:255'],
            'wan_model' => ['required', 'string', 'max:255'],
            'qwen_model' => ['required', 'string', 'max:255'],
            'video_model' => ['required', 'string', 'max:255'],
            'vision_model' => ['required', 'string', 'max:255'],
            'dashscope_base' => ['required', 'string', 'max:255', 'regex:/^https?:\/\/[^\/]+$/'],
            'processing' => ['required', 'string', 'in:sync,queue'],
        ]);

        set_setting('studio_image_credits', (string) $data['image_credits']);
        set_setting('studio_video_credits', (string) $data['video_credits']);
        set_setting('studio_max_generations', (string) ($data['max_generations'] ?? 50));
        set_setting('studio_image_provider', $data['image_provider']);
        set_setting('studio_prompt_model', $data['prompt_model']);
        set_setting('studio_image_model', $data['image_model'] ?? '');
        set_setting('studio_wan_model', $data['wan_model']);
        set_setting('studio_qwen_model', $data['qwen_model']);
        set_setting('studio_video_model', $data['video_model']);
        set_setting('studio_vision_model', $data['vision_model']);
        set_setting('studio_dashscope_base', $data['dashscope_base']);
        set_setting('studio_processing', $data['processing']);

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
}
