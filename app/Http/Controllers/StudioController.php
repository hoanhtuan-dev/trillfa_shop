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
use Illuminate\Support\Str;

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

        $stylistTypes = app(\App\Services\StylistService::class)->garmentTypes();
        return view('studio.index', compact('projects', 'presets', 'latest', 'creditsUsed', 'pendingCount', 'stylistTypes'));
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
            'variants' => ['nullable', 'integer', 'min:1', 'max:4'],
            'base_image' => ['nullable', 'string', 'max:2048'], // edit path: change bg/pose keeping exact pixels
            'edit' => ['nullable', 'string', 'in:1,true'],
        ]);

        // Ensure a shared prompt-history so all variants group as one "generation run".
        if (empty($data['history_id'])) {
            $history = auth()->user()->prompts()->create([
                'idea' => null,
                'image_prompt_en' => $data['prompt'],
                'video_prompt_en' => null,
                'json_response' => ['image_prompt_en' => $data['prompt']],
            ]);
            $data['history_id'] = $history->id;
        }

        $cost = (int) studio_config('image_credits', 1);
        $variants = max(1, min(4, (int) ($data['variants'] ?? 1)));

        $items = [];
        for ($i = 0; $i < $variants; $i++) {
            $items[] = $this->queueGeneration('image', $data, $cost)->getData(true);
        }

        return response()->json([
            'items' => $items,
            'credits_left' => auth()->user()->fresh()->credits_balance,
        ]);
    }

    /**
     * Video catwalk render — async via queue.
     */
    public function renderVideo(Request $request)
    {
        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:4000'],
            'base_image' => ['nullable', 'string', 'max:2048'],
            'camera' => ['nullable', 'string', 'max:1000'], // Kịch bản quay (video_scene) injection có thể dài
            'model' => ['nullable', 'string', 'max:120'], // video-model override (multi-model selector)
            'model_registry_id' => ['nullable', 'integer'],
            'provenance' => ['nullable', 'string', 'max:20'],
            'resolution' => ['nullable', 'string', 'in:480,720,1080'],
            'duration' => ['nullable', 'string', 'in:5,8,10,15,20'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'history_id' => ['nullable', 'integer', 'exists:prompts_history,id'],
        ]);

        $cost = (int) studio_config('video_credits', 10);

        // Multi-model selector: resolve the chosen registered model (unique id) so the render uses
        // exactly that provider + model_id (not the highest-priority default). Avoids model_id collisions.
        if (! empty($data['model_registry_id'])) {
            $reg = \App\Models\StudioModel::find($data['model_registry_id']);
            if ($reg) {
                $data['provider'] = $reg->provider;
                $data['model'] = $reg->model_id;
                $data['api_key_ref'] = $reg->api_key_ref;
            }
        } elseif (! empty($data['model'])) {
            set_setting('studio_video_model', $data['model']);
        }

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
            'preserve_background' => ['nullable', 'boolean'],
            'preserve_face' => ['nullable', 'boolean'],
        ]);

        $preserveBg = ! empty($data['preserve_background']);
        $preserveFace = ! empty($data['preserve_face']);

        $data['prompt'] = 'Using the provided image as the exact base, edit it surgically. Change ONLY: '.$request->input('prompt')
            .'. Preserve everything else exactly as in the original image — '
            .($preserveFace ? 'the model\'s face and identity, skin tone and hair, ' : '')
            .'pose, body proportions, garment structure and fit, fabric, all colours except the edited element, lighting, shadows, camera angle, composition'
            .($preserveBg ? ', and background' : '')
            .'. Do not restyle, do not add new elements, do not change the setting.';

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
        $stuckWindow = $generation->type === 'video' ? 8 : 6; // minutes; job poll deadline is 5m (video) / ~5m (image)
        if ($generation->status === 'processing'
            && $generation->updated_at->lt(now()->subMinutes($stuckWindow))) {
            $this->failStuck($generation, 'Hết thời gian xử lý (có thể request đã bị ngắt). Đã hoàn tiền vào tài khoản. Vui lòng thử lại bằng cách tạo mới, hoặc bấm “Xử lý ngay” ở thanh công cụ nếu còn nhiệm vụ chờ.');
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
            'resolution' => $g->resolution,
            'ratio' => $g->ratio,
            'duration' => $g->duration,
            'elapsed_ms' => $g->elapsed_ms,
            'meta' => $g->meta,
        ]);
    }

    /**
     * Resolve the provider + model for a generation type.
     *
     * @return array{0: string, 1: string}
     */
    protected function defaultProviderModel(string $type): array
    {
        // Prefer the registered model registry (highest-priority enabled model for the group).
        $group = in_array($type, ['video', 'inference', 'text']) ? $type : 'image';
        $reg = resolve_studio_model($group);
        if ($reg) return [$reg['provider'], $reg['model']];

        // Registry empty / no enabled -> fall back to legacy configured values.
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

    /**
     * JSON: registered models grouped by capability, for the Studio UI (dropdowns)
     * and for dynamic model selection.
     */
    public function models()
    {
        $groups = ['image', 'video', 'inference'];
        $rows = \App\Models\StudioModel::orderBy('priority', 'desc')->orderBy('id')->get();
        $out = [];
        foreach ($groups as $g) {
            $out[$g] = $rows->where('group', $g)->where('enabled', true)->values()->map(fn ($m) => [
                'id' => $m->id, 'key' => $m->model_id, 'label' => $m->name,
                'provider' => $m->provider, 'priority' => $m->priority,
            ])->all();
        }
        return response()->json(['groups' => $out]);
    }

    /**
     * JSON: report how a registered model resolves — provider, model_id, api_key_ref,
     * whether a key exists, the base URL, and a hint if the model_id looks invalid.
     */
    public function testModel(\App\Models\StudioModel $model)
    {
        $key = studio_api_keys_for($model->provider, $model->model_id, $model->group)->first();
        $keyVal = $key ? studio_api_key_value($key) : null;
        $knownVideo = ['wan2.5-t2v', 'wan2.2-i2v', 'wan2.5-i2v', 'wan2.1-i2v-turbo', 'happyhorse-1.1-i2v', 'wanx2.1-t2v-turbo', 'wanx2.1-i2v-turbo'];

        $note = '';
        if ($model->group === 'video' && ! in_array($model->model_id, $knownVideo)) {
            $note = '⚠️ Model_id này KHÔNG nằm trong nhóm model video phổ biến của DashScope/Wan — dễ gặp lỗi "Model not exist". ';
        }
        if (! $keyVal) {
            $note .= 'Chưa có KEY cho provider "'.$model->provider.'" — thêm key trong API Keys Registry (hoặc env).';
        } elseif ($key && $key->scopes && ! in_array('*', $key->scopes) && ! in_array($model->model_id, $key->scopes)) {
            $note .= 'Key hiện có scope hạn chế ('.implode(', ', $key->scopes).') — key này có thể KHÔNG phục vụ model "'.$model->model_id.'".';
        }

        return response()->json([
            'provider' => $model->provider,
            'model_id' => $model->model_id,
            'model_name' => $model->name,
            'group' => $model->group,
            'api_key_ref' => $model->api_key_ref,
            'key_exists' => (bool) $keyVal,
            'key_prefix' => $keyVal ? substr($keyVal, 0, 8).'…' : null,
            'base_url' => $keyVal ? dashscope_base_url($keyVal) : '',
            'note' => $note ?: 'OK — provider + key + model_id đã cấu hình hợp lý.',
        ]);
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
            // Explicit provider/model from the registry selector wins; else resolve the default.
            [$provider, $model] = (! empty($data['provider']) && ! empty($data['model']))
                ? [(string) $data['provider'], (string) $data['model']]
                : $this->defaultProviderModel($type);
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
            'meta' => ($type === 'video' && ! empty($data['camera'])) ? ['camera' => $data['camera']] : null,
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
            'prompts_history_id' => $fresh->prompts_history_id,
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

    /**
     * Upload a reference image (from a local blob) and return a public storage URL so it can be
     * used as a base_image for the pixel-preserving edit flow.
     */
    public function uploadRef(Request $request)
    {
        $data = $request->validate(['image' => ['required', 'image', 'max:8192']]);
        $path = $request->file('image')->store('studio/ref', 'public');

        return response()->json(['url' => '/storage/'.$path]);
    }

    /**
     * Translate a prompt between Vietnamese and English (used by the "Chỉnh sửa prompt tiếng Việt" popup).
     */
    /**
     * Custom Model/Pose library assets (uploaded by the user).
     */
    public function assetIndex(): \Illuminate\Http\JsonResponse
    {
        try {
            $assets = \App\Models\StudioAsset::orderBy('type')->orderBy('sort')->get(['id', 'type', 'name', 'path']);
            return response()->json(['items' => $assets]);
        } catch (\Throwable $e) {
            logger()->warning('assetIndex failed: '.$e->getMessage());
            return response()->json(['items' => []]);
        }
    }

    public function assetStore(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'in:model,pose'],
            'name' => ['required', 'string', 'max:80'],
            'image' => ['required', 'image', 'max:8192'],
        ]);
        $path = '/storage/'.$request->file('image')->store('studio/assets', 'public');
        $asset = \App\Models\StudioAsset::create([
            'type' => $data['type'], 'name' => $data['name'], 'path' => $path, 'sort' => 0,
        ]);
        return response()->json(['id' => $asset->id, 'type' => $asset->type, 'name' => $asset->name, 'path' => $asset->path]);
    }

    public function assetDestroy(\App\Models\StudioAsset $asset)
    {
        $asset->delete();
        return response()->json(['ok' => true]);
    }

    /**
     * Serve a garment avatar via a Laravel route (Cache-Control: no-store) so the
     * Hostinger hcdn / LiteSpeed static cache never serves a stale clone.
     */
    /**
     * Public garment-avatar endpoint — serves the avatar from a fixed location with a
     * correct immutable cache header (versioned URL => safe to cache). Public, no auth.
     */
    public function garmentAvatar(string $id)
    {
        if (! preg_match('/^[a-z0-9-]+$/', $id)) {
            return response()->json(['error' => 'invalid'], 404);
        }
        $path = public_path('assets/garments/garment-'.$id.'.png');
        if (! is_file($path)) {
            return response()->json(['error' => 'not found'], 404);
        }
        return response()->file($path, ['Cache-Control' => 'public, max-age=31536000, immutable']);
    }

    /**
     * Nâng cấp & tinh chỉnh ảnh — upscale (GD) + optional AI refine (edit model).
     */
    public function upscale(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'string', 'max:2048'],
            'scale' => ['nullable', 'integer', 'min:1', 'max:4'],
            'refine' => ['nullable', 'integer', 'min:0', 'max:10'],
        ]);
        $scale = max(1, min(4, (int) ($data['scale'] ?? 2)));
        $refine = max(0, min(10, (int) ($data['refine'] ?? 0)));
        $srcUrl = (string) $data['image'];

        if ($refine > 0) {
            try {
                $out = app(\App\Services\ImageAIService::class)->generate(
                    'Enhance this image: increase sharpness, clarity and fine detail, keep the exact subject, garment and composition unchanged, high resolution, photorealistic.',
                    $srcUrl
                );
                if ($out) { $srcUrl = $out; }
            } catch (\Throwable $e) { logger()->warning('Upscale refine failed: '.$e->getMessage()); }
        }

        $rel = ltrim((string) parse_url($srcUrl, PHP_URL_PATH), '/');
        $file = null;
        foreach ([public_path($rel), storage_path('app/public/'.str_replace('storage/', '', $rel))] as $cand) {
            if (is_file($cand)) { $file = $cand; break; }
        }
        if (! $file) { return response()->json(['message' => 'Không đọc được ảnh nguồn.'], 422); }

        $src = @imagecreatefromstring((string) file_get_contents($file));
        if (! $src) { return response()->json(['message' => 'Ảnh nguồn không hợp lệ.'], 422); }
        $w = (int) (imagesx($src) * $scale);
        $h = (int) (imagesy($src) * $scale);
        $dst = imagecreatetruecolor($w, $h);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, imagesx($src), imagesy($src));
        $name = 'studio/upscale-'.Str::uuid().'.png';
        \Illuminate\Support\Facades\Storage::disk('public')->put($name, $this->pngBytes($dst));
        imagedestroy($src); imagedestroy($dst);

        $gen = auth()->user()->generations()->create([
            'type' => 'image', 'status' => 'completed',
            'media_url' => '/storage/'.$name,
            'prompt' => 'Nâng cấp ảnh ('.$scale.'x'.($refine ? ', refine '.$refine : '').')',
            'model' => 'upscale', 'provider' => 'upscale', 'credits_cost' => 0,
        ]);

        return response()->json(['media_url' => '/storage/'.$name, 'generation_id' => $gen->id]);
    }

    protected function pngBytes(\GdImage $img): string
    {
        ob_start(); imagepng($img); return (string) ob_get_clean();
    }

    /**
     * ✨ Thuật sỹ ảo — guided fashion-stylist wizard.
     */
    public function stylistTypes(): \Illuminate\Http\JsonResponse
    {
        return response()->json(['types' => app(\App\Services\StylistService::class)->garmentTypes()]);
    }

    public function stylist(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'max:40'],
            'history' => ['nullable', 'array'],
            'history.*.label' => ['nullable', 'string', 'max:500'],
            'history.*.answer' => ['nullable', 'string', 'max:300'],
        ]);
        $svc = app(\App\Services\StylistService::class);
        $step = $svc->next((string) $data['type'], $data['history'] ?? []);
        return response()->json($step);
    }

    /**
     * ✨ Thuật sỹ — cluster (xương sườn) + build prompt.
     */
    public function stylistCluster(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate(['type' => ['required', 'string', 'max:40']]);
        return response()->json(['questions' => app(\App\Services\StylistService::class)->cluster((string) $data['type'])]);
    }

    public function stylistRefine(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'max:40'],
            'prompt_en' => ['required', 'string', 'max:4000'],
            'answers' => ['nullable', 'array'],
            'answers.*' => ['nullable', 'string', 'max:500'],
        ]);
        return response()->json(app(\App\Services\StylistService::class)->refine((string) $data['type'], (string) $data['prompt_en'], (array) ($data['answers'] ?? [])));
    }

    public function stylistPrompt(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'max:40'],
            'answers' => ['nullable', 'array'],
            'answers.*' => ['nullable', 'string', 'max:500'],
        ]);
        $svc = app(\App\Services\StylistService::class);
        $answers = array_filter((array) ($data['answers'] ?? []), fn ($v) => ! empty($v));
        $type = (string) $data['type'];
        $promptEn = $svc->buildPrompt($type, $answers);
        $promptVi = $svc->buildPromptVi($type, $answers);
        return response()->json(['prompt_en' => $promptEn, 'prompt_vi' => $promptVi]);
    }

    /**
     * "Thay Đổi Người Mẫu" (Click-to-Swap) — virtual try-on with a chosen model + pose.
     */
    public function swapModel(Request $request)
    {
        $data = $request->validate([
            'image' => ['required', 'string', 'max:2048'],   // design image URL (generation media_url or /storage)
            'model_id' => ['required', 'string', 'max:40'],
            'pose_id' => ['required', 'string', 'max:40'],
            'background' => ['nullable', 'string', 'max:400'],
            'texture' => ['nullable', 'integer', 'min:0', 'max:10'],
        ]);

        $svc = app(\App\Services\VirtualTryOnService::class);
        $model = $svc->pickModel($data['model_id']);
        $pose = $svc->pickPose($data['pose_id']);
        if (! $model) {
            return response()->json(['message' => 'Không tìm thấy người mẫu.'], 422);
        }

        // garment_image_url: the design image (person in the outfit) — for best results use a clean
        // flat-lay / background-removed garment, but the design image is a working default.
        $garmentUrl = url($data['image']);
        // VTON bắt buộc POSE dạng ẢNH -> model_image_url = ảnh ma-nơ-canh đang ở dáng đã chọn (pose.image).
        $poseImage = $pose['image'] ?? null;
        if (! $poseImage) {
            return response()->json(['message' => 'Pose "'.($pose['name'] ?? '?').'" chưa có ảnh (model_image_url). Thêm ảnh dáng trong catalog.'], 422);
        }
        $modelUrl = url($poseImage);

        $category = (string) studio_config('tryon_category', 'dress');
        $res = $svc->submit($modelUrl, $garmentUrl, $category);
        if (! empty($res['error'])) {
            // Try-on không khả dụng (vùng/intl hoặc free-trial hết) -> fallback qwen-image-edit
            // đổi người mẫu/dáng, giữ nguyên 100% trang phục.
            logger()->warning('Try-on khả dụng, fallback qwen-edit: '.($res['error'] ?? ''));
            $fallback = $svc->fallbackEdit($data['image'], $model['desc'] ?? ($model['ethnicity'] ?? 'a model'), $pose['skeleton'] ?? ($pose['name'] ?? 'standing'), (string) ($data['background'] ?? ''), (int) ($data['texture'] ?? 5));
            if ($fallback) {
                $gen = auth()->user()->generations()->create([
                    'type' => 'image', 'status' => 'completed', 'media_url' => $fallback,
                    'prompt' => 'Thay đổi người mẫu (qwen-edit) · '.($model['name'] ?? 'model'),
                    'model' => (string) studio_config('swap_model', 'qwen-image-edit-plus-2025-12-15'), 'provider' => 'qwen', 'credits_cost' => 1,
                    'meta' => ['type' => 'image', 'provider' => 'qwen', 'model' => (string) studio_config('swap_model', 'qwen-image-edit-plus-2025-12-15'), 'fallback' => true],
                ]);
                return response()->json(['generation_id' => $gen->id, 'media_url' => $fallback, 'provider' => 'qwen', 'task_id' => null]);
            }
            return response()->json(['message' => $res['error']], 422);
        }

        // Record a pending Generation so it appears in Outputs + the frontend can poll by task_id.
        $gen = auth()->user()->generations()->create([
            'type' => 'image',
            'status' => 'pending',
            'prompt' => 'Thay đổi người mẫu (virtual try-on) · '.($model['name'] ?? 'model').' · '.($pose['name'] ?? 'pose'),
            'model' => (string) studio_config('tryon_model', 'wanx-virtualmodel'),
            'provider' => 'tryon',
            'base_image' => $data['image'],
            'job_id' => $res['task_id'],
            'credits_cost' => 1,
            'meta' => ['type' => 'image', 'provider' => 'tryon', 'model' => (string) studio_config('tryon_model', 'wanx-virtual-try-on'), 'tryon' => true, 'task_id' => $res['task_id'], 'model_id' => $data['model_id'], 'pose_id' => $data['pose_id']],
        ]);

        return response()->json(['task_id' => $res['task_id'], 'generation_id' => $gen->id]);
    }

    /**
     * Poll a virtual try-on task and complete the matching Generation when the image is ready.
     */
    public function swapStatus(Request $request, string $taskId)
    {
        $svc = app(\App\Services\VirtualTryOnService::class);
        $res = $svc->status($taskId);

        $gen = auth()->user()->generations()->where('job_id', $taskId)->latest()->first();

        if (($res['status'] ?? '') === 'succeeded' && ! empty($res['url'])) {
            $stored = $svc->storeRemoteImage($res['url']);
            if ($stored) {
                if ($gen) {
                    $gen->update(['status' => 'completed', 'media_url' => $stored]);
                }
                return response()->json(['status' => 'completed', 'media_url' => $stored, 'generation_id' => $gen?->id]);
            }
        }

        if (($res['status'] ?? '') === 'failed') {
            if ($gen) { $gen->update(['status' => 'failed', 'error' => $res['error'] ?? 'Thay đổi người mẫu thất bại.']); }
            return response()->json(['status' => 'failed', 'message' => $res['error'] ?? 'Thất bại.']);
        }

        return response()->json(['status' => $res['status'] ?? 'pending']);
    }

    public function translate(Request $request)
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:4000'],
            'direction' => ['required', 'in:en,vi'],
        ]);
        $text = trim((string) $data['text']);
        $target = $data['direction'] === 'vi' ? 'Vietnamese' : 'English';
        $qwenKey = studio_api_key('qwen') ?: studio_api_key('dashscope');
        $geminiKey = studio_api_key('gemini');
        $qwenModel = (string) studio_config('prompt_model', 'qwen3.8-flash'); // Qwen chat (fallback)
        $translateModel = (string) studio_config('translate_model', 'gemini-3.6-flash-image'); // Model dịch chuyên dụng
        $instruction = 'You are a professional fashion prompt translator. Translate the following image-generation prompt to '.$target.'. '
            .'Keep all technical descriptors (fabric, silhouette, camera, lighting) precise. Return ONLY the translated prompt, nothing else.';

        // Gemini translation model candidates — try the configured one, then a safe fallback.
        $gemModels = array_values(array_unique(array_filter([
            $translateModel, 'gemini-2.5-flash', 'gemini-2.0-flash',
        ])));
        if ($geminiKey) {
            foreach ($gemModels as $gm) {
                logger()->info('Translate via GEMINI', ['model' => $gm, 'dir' => $data['direction']]);
                try {
                    $resp = Http::withHeaders(['x-goog-api-key' => $geminiKey])->timeout(60)
                        ->post('https://generativelanguage.googleapis.com/v1beta/models/'.$gm.':generateContent', [
                            'contents' => [['parts' => [['text' => $instruction."\n\n".$text]]]],
                            'generationConfig' => ['responseMimeType' => 'text/plain'],
                        ]);
                    if ($resp->successful()) {
                        $out = trim((string) data_get($resp->json(), 'candidates.0.content.parts.0.text'));
                        if ($out !== '') { return response()->json(['text' => $out, 'provider' => 'gemini', 'model' => $gm]); }
                    }
                    // 404 / model-not-found -> try the next Gemini model; other errors -> log & stop.
                    if ($resp->status() === 404 || str_contains(strtolower((string) $resp->body()), 'not found')) {
                        logger()->warning('Translate (gemini) model not found: '.$gm.' - '.substr((string) $resp->body(), 0, 160));
                        continue;
                    }
                    logger()->warning('Translate (gemini) HTTP '.$resp->status().' '.substr((string) $resp->body(), 0, 180));
                } catch (\Throwable $e) {
                    logger()->error('Translate (gemini) failed: '.$e->getMessage());
                }
            }
        }

        // Qwen chat fallback (if no Gemini key / Gemini failed).
        if ($qwenKey) {
            logger()->info('Translate via QWEN', ['model' => $qwenModel, 'dir' => $data['direction']]);
            try {
                $resp = Http::withToken($qwenKey)->timeout(60)
                    ->post(dashscope_base_url($qwenKey).'/compatible-mode/v1/chat/completions', [
                        'model' => $qwenModel, 'messages' => [
                            ['role' => 'system', 'content' => $instruction],
                            ['role' => 'user', 'content' => $text],
                        ],
                    ]);
                if ($resp->successful()) {
                    $out = trim((string) data_get($resp->json(), 'choices.0.message.content'));
                    if ($out !== '') { return response()->json(['text' => $out, 'provider' => 'qwen', 'model' => $qwenModel]); }
                }
                logger()->warning('Translate (qwen) HTTP '.$resp->status().' '.substr((string) $resp->body(), 0, 180));
            } catch (\Throwable $e) {
                logger()->error('Translate (qwen) failed: '.$e->getMessage());
            }
        }

        return response()->json(['text' => $text, 'provider' => 'none', 'model' => null]); // no key / failed -> keep as-is
    }

    /**
     * Upload a face reference (Fitting Room face-sync) — sets the global face so the edit/surgery applies it.
     */
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
                'qwen_edit' => $this->testQwenEdit($key),
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

    /**
     * Lightweight eligibility probe for the dedicated Qwen image-edit model (auth/eligibility only).
     */
    protected function testQwenEdit(string $key): array
    {
        $model = (string) studio_config('qwen_edit_model', 'qwen-image-edit');
        $base = dashscope_base_url($key).'/api/v1';
        $onePx = 'data:image/png;base64,'.base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));

        try {
            $resp = Http::withToken($key)->timeout(25)
                ->post($base.'/services/aigc/multimodal-generation/generation', [
                    'model' => $model,
                    'input' => ['messages' => [['role' => 'user', 'content' => [
                        ['image' => $onePx],
                        ['text' => 'no change'],
                    ]]]],
                    'parameters' => ['watermark' => false],
                ]);

            if ($resp->successful()) {
                return ['ok' => true, 'message' => 'Qwen Edit “'.$model.'” khả dụng (kết nối OK).'];
            }

            if ($resp->status() === 403) {
                return ['ok' => false, 'message' => 'Model edit “'.$model.'” CHƯA được mua/kích hoạt (403 AccessDenied.Unpurchased). '
                    .'Bật/mua model Qwen-Image-Edit trong QwenCloud Model Center, hoặc dùng Gemini.'];
            }
            if ($resp->status() === 404) {
                return ['ok' => false, 'message' => 'Model edit “'.$model.'” không tồn tại trên host này. Chọn model edit đúng gói/QwenCloud.'];
            }
            if ($resp->status() === 401) {
                return ['ok' => false, 'message' => 'Khoá không hợp lệ (401 InvalidApiKey). Dùng key Pay-As-You-Go (sk-…/sk-ws-…).'];
            }

            return ['ok' => false, 'message' => 'HTTP '.$resp->status().': '.substr((string) $resp->body(), 0, 180)];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Đã gửi yêu cầu nhưng chưa có phản hồi ('.$e->getMessage().'). Model có thể đang xử lý — thử lại sau.'];
        }
    }

    protected function testDashscope(string $key): array
    {
        // Wan (image & video) + Qwen run on a DashScope-compatible endpoint. Try every
        // candidate host (classic region + QwenCloud Token/Coding Plan) because a
        // QwenCloud key is bound to a specific base URL by key type.
        $configured = dashscope_base_url($key);
        $candidates = array_unique([
            $configured,
            'https://dashscope.aliyuncs.com',
            'https://dashscope-intl.aliyuncs.com',
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

        try {
            return response()->json(['colors' => $this->extractPalette($abs, 6)]);
        } catch (\Throwable $e) {
            logger()->warning('palette failed: '.$e->getMessage());
            return response()->json(['colors' => []]);
        }
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
            'translate_model' => setting('studio_translate_model', config('studio.translate_model')),
            'swap_model' => setting('studio_swap_model', config('studio.swap_model')),
            'stylist_model' => setting('studio_stylist_model', config('studio.stylist_model')),
            'image_model' => setting('studio_image_model', config('studio.image_model')),
            'wan_model' => setting('studio_wan_model', config('studio.wan_model')),
            'qwen_model' => setting('studio_qwen_model', config('studio.qwen_model')),
            'qwen_edit_model' => setting('studio_qwen_edit_model', config('studio.qwen_edit_model')),
            'gemini_image_model' => setting('studio_gemini_image_model', config('studio.gemini_image_model')),
            'video_model' => setting('studio_video_model', config('studio.video_model')),
            'vision_model' => setting('studio_vision_model', config('studio.vision_model')),
            'dashscope_base' => setting('studio_dashscope_base', config('studio.dashscope_base')),
            'dashscope_token_plan_base' => setting('studio_dashscope_token_plan_base', config('studio.dashscope_token_plan_base')),
            'processing' => setting('studio_processing', config('studio.processing')),
            'image_resolution' => setting('studio_image_resolution', config('studio.image_resolution')),
            'video_resolution' => setting('studio_video_resolution', config('studio.video_resolution')),
            'image_ratio' => setting('studio_image_ratio', config('studio.image_ratio')),
            'video_duration' => setting('studio_video_duration', config('studio.video_duration')),
            'pending_count' => auth()->user()->generations()->whereIn('status', ['pending', 'processing'])->count(),
            'queue_driver' => config('queue.default'),
            'usage' => studio_usage(auth()->user()),
            'models' => studio_models(), // registry models (grouped by category)
            'api_keys' => \App\Models\StudioApiKey::orderBy('provider')->orderByDesc('priority')->orderBy('id')->get(),
            'providers' => $this->providerStatus(),
        ]);
    }

    protected function providerStatus(): array
    {
        return [
            'gemini' => ['label' => 'Gemini — Giám đốc sáng tạo', 'hint' => 'GEMINI_API_KEY', 'configured' => (bool) studio_api_key('gemini')],
            'fal' => ['label' => 'Fal.ai — Flux (ảnh)', 'hint' => 'FAL_KEY', 'configured' => (bool) studio_api_key('fal')],
            'replicate' => ['label' => 'Replicate — Flux (ảnh)', 'hint' => 'REPLICATE_API_TOKEN', 'configured' => (bool) studio_api_key('replicate')],
            'wan' => ['label' => 'Wan AI — video', 'hint' => 'WAN_API_KEY / DASHSCOPE_API_KEY', 'configured' => (bool) (studio_api_key('wan') ?: studio_api_key('dashscope'))],
            'veo' => ['label' => 'Google Veo — video', 'hint' => 'GOOGLE_VEO_KEY', 'configured' => (bool) studio_api_key('veo')],
            'qwen' => ['label' => 'Qwen — ảnh (QwenCloud)', 'hint' => 'QWEN_API_KEY (home.qwencloud.com/api-keys)', 'configured' => (bool) studio_api_key('qwen')],
            'qwen_edit' => ['label' => 'Qwen Edit — chỉnh sửa ảnh / Inpaint', 'hint' => 'QWEN_EDIT_KEY', 'configured' => (bool) studio_api_key('qwen_edit')],
            'dashscope' => ['label' => 'DashScope — Wan/Qwen image & video', 'hint' => 'DASHSCOPE_API_KEY', 'configured' => (bool) studio_api_key('dashscope')],
        ];
    }

    public function storeModel(Request $request)
    {
        $data = $request->validate([
            'group' => ['required', 'string', 'in:image,video,inference,text'],
            'name' => ['required', 'string', 'max:120'],
            'provider' => ['required', 'string', 'max:40'],
            'model_id' => ['required', 'string', 'max:160'],
            'api_key_ref' => ['nullable', 'string', 'max:80'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $data['priority'] = (int) ($data['priority'] ?? 0);
        $data['enabled'] = true;
        \App\Models\StudioModel::create($data);
        if ($request->wantsJson()) return response()->json(['ok' => true]);
        return back()->with('success', 'Đã thêm model.');
    }

    public function updateModel(Request $request, \App\Models\StudioModel $model)
    {
        $data = $request->validate([
            'group' => ['required', 'string', 'in:image,video,inference,text'],
            'name' => ['required', 'string', 'max:120'],
            'provider' => ['required', 'string', 'max:40'],
            'model_id' => ['required', 'string', 'max:160'],
            'api_key_ref' => ['nullable', 'string', 'max:80'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100'],
            'enabled' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $data['priority'] = (int) ($data['priority'] ?? 0);
        $model->update($data);
        if ($request->wantsJson()) return response()->json(['ok' => true]);
        return back()->with('success', 'Đã cập nhật model.');
    }

    public function deleteModel(\App\Models\StudioModel $model)
    {
        $model->delete();
        if (request()->wantsJson()) return response()->json(['ok' => true]);
        return back()->with('success', 'Đã xóa model.');
    }

    public function storeApiKey(Request $request)
    {
        $data = $request->validate([
            'provider' => ['required', 'string', 'max:40'],
            'label' => ['required', 'string', 'max:120'],
            'value' => ['required', 'string', 'max:500'],
            'kind' => ['nullable', 'string', 'max:20'],
            'scopes' => ['nullable', 'string'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $data['priority'] = (int) ($data['priority'] ?? 0);
        $data['enabled'] = true;
        $data['value'] = \Illuminate\Support\Facades\Crypt::encryptString(trim($data['value']));
        $data['scopes'] = ['*']; // key dùng chung (độc lập model)
        \App\Models\StudioApiKey::create($data);
        return redirect()->back()->with('success', 'Đã thêm API key.');
    }

    public function updateApiKey(Request $request, \App\Models\StudioApiKey $key)
    {
        $data = $request->validate([
            'provider' => ['required', 'string', 'max:40'],
            'label' => ['required', 'string', 'max:120'],
            'value' => ['nullable', 'string', 'max:500'],
            'kind' => ['nullable', 'string', 'max:20'],
            'scopes' => ['nullable', 'string'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100'],
            'enabled' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $data['priority'] = (int) ($data['priority'] ?? 0);
        $data['scopes'] = ['*']; // key dùng chung (độc lập model)
        if (! empty($data['value'])) $data['value'] = \Illuminate\Support\Facades\Crypt::encryptString(trim($data['value']));
        else unset($data['value']);
        $key->update($data);
        return redirect()->back()->with('success', 'Đã cập nhật API key.');
    }

    public function deleteApiKey(\App\Models\StudioApiKey $key)
    {
        $key->delete();
        return redirect()->back()->with('success', 'Đã xóa API key.');
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'image_credits' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'video_credits' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'max_generations' => ['nullable', 'integer', 'min:1', 'max:500'],
            'image_provider' => ['required', 'string', 'in:flux,wan,qwen,gemini'],
            'prompt_provider' => ['required', 'string', 'in:gemini,qwen'],
            'vision_provider' => ['required', 'string', 'in:gemini,qwen'],
            'prompt_model' => ['required', 'string', 'max:255'],
            'translate_model' => ['nullable', 'string', 'max:255'],
            'swap_model' => ['nullable', 'string', 'max:255'],
            'stylist_model' => ['nullable', 'string', 'max:255'],
            'image_model' => ['nullable', 'string', 'max:255'],
            'wan_model' => ['required', 'string', 'max:255'],
            'qwen_model' => ['required', 'string', 'max:255'],
            'qwen_edit_model' => ['nullable', 'string', 'max:255'],
            'gemini_image_model' => ['nullable', 'string', 'max:255'],
            'video_model' => ['required', 'string', 'max:255'],
            'vision_model' => ['required', 'string', 'max:255'],
            'dashscope_base' => ['required', 'string', 'max:255', 'regex:/^https?:\/\/[^\/]+$/'],
            'dashscope_token_plan_base' => ['nullable', 'string', 'max:255', 'regex:/^https?:\/\/[^\/]+$/'],
            'processing' => ['required', 'string', 'in:sync,queue'],
            'image_resolution' => ['required', 'string', 'in:1K,2K'],
            'video_resolution' => ['required', 'string', 'in:480,720,1080'],
            'image_ratio' => ['required', 'string', 'in:1:1,4:3,3:4,16:9,9:16,4:5,21:9,19:6'],
            'video_duration' => ['required', 'string', 'in:5,8,10,15,20'],
        ]);

        if (isset($data['image_credits'])) set_setting('studio_image_credits', (string) $data['image_credits']);
        if (isset($data['video_credits'])) set_setting('studio_video_credits', (string) $data['video_credits']);
        if (isset($data['max_generations'])) set_setting('studio_max_generations', (string) ($data['max_generations'] ?? 50));
        set_setting('studio_image_provider', $data['image_provider']);
        set_setting('studio_prompt_provider', $data['prompt_provider']);
        set_setting('studio_vision_provider', $data['vision_provider']);
        set_setting('studio_prompt_model', $data['prompt_model']);
        if (isset($data['translate_model'])) set_setting('studio_translate_model', $data['translate_model']);
        if (isset($data['swap_model'])) set_setting('studio_swap_model', $data['swap_model']);
        if (isset($data['stylist_model'])) set_setting('studio_stylist_model', $data['stylist_model']);
        set_setting('studio_image_model', $data['image_model'] ?? '');
        set_setting('studio_wan_model', $data['wan_model']);
        set_setting('studio_qwen_model', $data['qwen_model']);
        set_setting('studio_qwen_edit_model', $data['qwen_edit_model'] ?? '');
        set_setting('studio_gemini_image_model', $data['gemini_image_model'] ?? '');
        set_setting('studio_video_model', $data['video_model']);
        set_setting('studio_vision_model', $data['vision_model']);
        set_setting('studio_dashscope_base', $data['dashscope_base']);
        set_setting('studio_dashscope_token_plan_base', $data['dashscope_token_plan_base'] ?? config('studio.dashscope_token_plan_base'));
        set_setting('studio_processing', $data['processing']);
        set_setting('studio_image_resolution', $data['image_resolution']);
        set_setting('studio_video_resolution', $data['video_resolution']);
        set_setting('studio_image_ratio', $data['image_ratio']);
        set_setting('studio_video_duration', $data['video_duration']);


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
            'qwen_edit' => ['label' => 'Qwen Edit — chỉnh sửa ảnh / Inpaint', 'hint' => 'QWEN_EDIT_KEY · model edit (qwen-image-edit, wanx2.1-imageedit…)', 'configured' => (bool) studio_api_key('qwen_edit')],
            'dashscope' => ['label' => 'DashScope — Wan/Qwen image & video (Alibaba)', 'hint' => 'DASHSCOPE_API_KEY', 'configured' => (bool) studio_api_key('dashscope')],
            'deepseek' => ['label' => 'DeepSeek — ngôn ngữ / suy luận (prompt, chat)', 'hint' => 'DEEPSEEK_API_KEY · model deepseek-chat', 'configured' => (bool) studio_api_key('deepseek')],
        ];

        return view('studio.api', compact('providers'));
    }

    public function updateApi(Request $request)
    {
        $services = ['gemini', 'fal', 'replicate', 'wan', 'veo', 'qwen', 'qwen_edit', 'dashscope'];

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
        $categories = ['fabric', 'silhouette', 'style', 'background', 'pose', 'camera', 'lens', 'video_scene'];

        return view('studio.presets', compact('presets', 'categories'));
    }

    public function storePreset(Request $request)
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'max:40'],
            'ui_label' => ['required', 'string', 'max:120'],
            'prompt_injection' => ['required', 'string', 'max:1000'],
            'note' => ['nullable', 'string', 'max:600'],
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
            'note' => ['nullable', 'string', 'max:600'],
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
                'prompts_history_id' => $g->prompts_history_id,
                'created_at' => $g->created_at?->format('d/m H:i'),
                'resolution' => $g->resolution, 'ratio' => $g->ratio, 'duration' => $g->duration,
                'elapsed_ms' => $g->elapsed_ms, 'meta' => $g->meta, 'prompt' => $g->prompt,
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
