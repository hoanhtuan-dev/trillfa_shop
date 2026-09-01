<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * 2D image generation (Flux / Fal.ai / Replicate, or Wan / Qwen via DashScope).
 *
 * Stub: when no real provider key is configured we reuse a bundled, clean sample
 * fashion image so the flow looks real offline. For an edit (inpaint) we reuse the
 * source image itself so the result stays consistent with the selected item.
 */
class ImageAIService
{
    protected ?string $dashscopeError = null;
    /** Last HTTP status from an edit attempt (0 = exception). */
    protected int $dashscopeStatus = 0;

    /** Which provider/model actually produced the last successful image (may differ from the requested one). */
    protected ?string $lastProvider = null;
    protected ?string $lastModel = null;


    public function lastProvider(): ?string
    {
        return $this->lastProvider;
    }

    public function lastModel(): ?string
    {
        return $this->lastModel;
    }

    protected function falKey(): ?string
    {
        return studio_api_key('fal');
    }

    protected function provider(): string
    {
        return (string) studio_config('image_provider', 'flux');
    }

    protected function providerKey(): ?string
    {
        return match ($this->provider()) {
            'gemini' => studio_api_key('gemini'),
            'wan' => studio_api_key('wan') ?: studio_api_key('dashscope'),
            'qwen' => studio_api_key('qwen') ?: studio_api_key('dashscope'),
            default => studio_api_key('fal') ?: studio_api_key('replicate'),
        };
    }

    public function generate(string $prompt, ?string $baseImage = null, ?string $maskImage = null, ?string $resolution = null, ?string $ratio = null, ?string $faceRef = null, ?string $providerOverride = null, ?string $modelOverride = null): string
    {
        $dashscopeKey = studio_api_key('dashscope');

        // Inpaint: when a source (base) image is supplied, use the dedicated Qwen image-edit model
        // WITH that image as input so the change applies to it (real editing), not a fresh text2image.
        if ($baseImage && (studio_api_key('qwen_edit') || $this->providerKey() || $dashscopeKey)) {
            $edited = $this->editImage($prompt, $baseImage);
            if ($edited) {
                return $edited;
            }
            // Inpaint must NOT silently fall through to text2image — that produces a brand-new image
            // instead of editing the source. Surface the real error so the user can fix the edit model.
            logger()->warning('Inpaint failed: edit model returned no result (no text2image fallback)', [
                'model' => studio_config('qwen_edit_model', 'qwen-image-edit'),
                'err' => $this->dashscopeError,
            ]);
            throw new \RuntimeException($this->dashscopeError ?: 'Không thể chỉnh sửa ảnh (model edit không trả kết quả). Kiểm tra model “Qwen Edit” trong Cài đặt và khoá “Qwen Edit” trong Quản lý API.');
        }

        // Unified, priority-driven model list: the requested (override) model first, then the default
        // settings model, then the registered models of the group by their saved priority. The key is
        // chosen by the same registered priority (never by key type). We call each in order until one
        // returns an image — this is the single mechanism for "Tạo Ảnh 2D", and it can never disagree
        // with the settings "check" because both use the same candidates list.
        $candidates = collect(studio_model_candidates('image'))->values();
        if ($providerOverride && $modelOverride) {
            $candidates = collect([['provider' => $providerOverride, 'model' => $modelOverride]])
                ->merge($candidates)
                ->unique(function ($c) {
                    return ($c['provider'] ?? '').':'.($c['model'] ?? '');
                })
                ->values();
        }

        $triedReal = false;
        foreach ($candidates as $c) {
            $provider = (string) ($c['provider'] ?? '');
            $model = (string) ($c['model'] ?? '');
            if (! $provider || ! $model) {
                continue;
            }

            // Try the candidate's keys in registered-priority order (ignoring key type); if the
            // top-priority key fails (e.g. a plan key routed to a host without this model), the next
            // key is tried before moving to the next candidate.
            $keys = studio_candidate_key($c, 'image');
            if (! $keys) {
                continue;
            }
            $triedReal = true;

            foreach ($keys as $key) {
                $url = $this->attemptProvider($provider, $model, $prompt, $key, $resolution, $ratio, $faceRef);
                if ($url) {
                    $this->lastProvider = $provider;
                    if (! $this->lastModel) {
                        $this->lastModel = $model;
                    }
                    return $url;
                }
            }
        }

        // A real provider was attempted but every one failed — surface the error rather than a stub.
        if ($triedReal) {
            throw new \RuntimeException($this->providerErrorMessage());
        }

        // No real key configured -> stub (reuse the source image for edits, sample otherwise).
        return $this->copySample($prompt, $baseImage);
    }

    /**
     * Call ONE (provider, model) candidate with its resolved key. Returns the image URL or null.
     */
    protected function attemptProvider(string $provider, string $model, string $prompt, string $key, ?string $resolution = null, ?string $ratio = null, ?string $faceRef = null): ?string
    {
        if ($provider === 'gemini') {
            return $this->tryGeminiImage($prompt, $key, $resolution, $ratio, $model);
        }

        if (in_array($provider, ['qwen', 'wan', 'dashscope'], true)) {
            return $this->tryDashscope($prompt, $model, $key, $resolution, $ratio, $faceRef);
        }

        // 'fal' / 'replicate' are not wired into this service (no Fal client), so skip them.
        return null;
    }

    protected function providerErrorMessage(): string
    {
        $msg = $this->dashscopeError ?: 'Không thể gọi nhà cung cấp AI. Kiểm tra key / model / độ phân giải trong Cài đặt.';
        $lower = strtolower((string) $msg);

        if (str_contains($lower, 'allocationquota') || str_contains($lower, 'throttling') || str_contains($msg, '(429)')) {
            $msg = 'Hạn mức tài khoản QwenCloud đã hết (Throttling.AllocationQuota). '
                .'Vào https://home.qwencloud.com → kiểm tra / gia hạn hạn mức — quota sẽ reset theo chu kỳ (resets theo thông báo).';
        } elseif (str_contains($lower, 'model not exist') || str_contains($lower, 'invalidparameter')
            || str_contains($lower, 'model_not_supported')) {
            $msg = 'Model ảnh không tồn tại trên host QwenCloud hiện tại. Token/Coding Plan có bộ model tạo ảnh riêng '
                .'(thường là wan2.7-image / happyhorse-1.1-t2v); Pay-As-You-Go dùng qwen-image-3.0-pro/qwen-image. '
                .'Đổi lại “Ảnh Qwen” / base URL trong Cài đặt cho đúng loại key.';
        } elseif (str_contains($lower, 'invalidapikey') || str_contains($msg, '(401)')) {
            $msg = 'Khoá API không hợp lệ (InvalidApiKey / 401). Với tạo ảnh & chỉnh sửa ảnh, hãy dùng key '
                .'Pay-As-You-Go (bắt đầu bằng sk-… hoặc sk-ws-…), KHÔNG dùng key Token/Coding Plan (sk-sp-…) vì gói plan '
                .'chỉ hỗ trợ model text/code. Tạo key mới tại Model Studio → API-KEY rồi nhập vào Quản lý API.';
        } elseif (str_contains($lower, 'unpurchased') || str_contains($lower, 'eligible')) {
            $msg = 'Khóa QwenCloud hợp lệ, nhưng model ảnh chưa được kích hoạt/mua trên tài khoản (AccessDenied.Unpurchased). '
                .'Vào https://home.qwencloud.com → Model Center → bật / mua một model Qwen-Image (Qwen-Image, Qwen-Image-Max, Qwen-Image-Plus, Qwen-Image-3.0). '
                .'Tài khoản này hiện chỉ có model giọng nói (ASR/TTS). Sau khi bật, chọn lại “Ảnh Qwen” trong Cài đặt.';
        }

        return $msg;
    }

    protected function tryGeminiImage(string $prompt, string $key, ?string $resolution = null, ?string $ratio = null, ?string $modelOverride = null): ?string
    {
        try {
            $url = $this->callGeminiImage($prompt, $key, $ratio, $modelOverride);
            if ($url) {
                $this->lastProvider = 'gemini';
                $this->lastModel = $modelOverride ?: (string) studio_config('gemini_image_model', 'gemini-2.5-flash-image');
                return $url;
            }
            $this->dashscopeError = 'Gemini không trả về ảnh. Kiểm tra model ảnh (Cài đặt → Ảnh Gemini) và hạn mức.';
        } catch (\Throwable $e) {
            $this->dashscopeError = $e->getMessage();
            logger()->error('Gemini image generation failed: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Gemini image generation via generateContent with responseModalities IMAGE.
     * The image is returned as a base64 inlineData part.
     */
    protected function callGeminiImage(string $prompt, string $key, ?string $ratio = null, ?string $modelOverride = null): ?string
    {
        $model = $modelOverride ?: (string) studio_config('gemini_image_model', 'gemini-2.5-flash-image');

        if (! $this->isGeminiImageModel($model)) {
            throw new \RuntimeException('Model “'.$model.'” không phải model tạo ảnh. Trong Cài đặt → “Ảnh Gemini”, hãy dùng gemini-2.5-flash-image (hoặc gemini-2.0-flash-preview-image-generation / imagen-4.0-generate-001).');
        }

        $config = ['responseModalities' => ['TEXT', 'IMAGE']];
        $aspect = $this->geminiAspectRatio($ratio);
        if ($aspect) {
            $config['imageConfig'] = ['aspectRatio' => $aspect];
        }

        $resp = $this->geminiGenerate($model, $prompt, $key, $config);

        // Some image models don't support the aspect-ratio config — retry without it.
        if (! $resp->successful() && isset($config['imageConfig'])
            && $resp->status() === 400 && str_contains(strtolower((string) $resp->body()), 'aspect ratio')) {
            unset($config['imageConfig']);
            $resp = $this->geminiGenerate($model, $prompt, $key, $config);
        }

        if (! $resp->successful()) {
            $msg = 'Gemini ('.$resp->status().'): '.Str::limit((string) $resp->body(), 240);
            if ($resp->status() === 404 || str_contains(strtolower((string) $resp->body()), 'not found')) {
                $msg .= ' — Model ảnh không đúng. Model Gemini hợp lệ: gemini-2.5-flash-image (hoặc gemini-2.0-flash-preview-image-generation / imagen-4.0-generate-001). Đổi trong Cài đặt → “Ảnh Gemini”.';
            }
            throw new \RuntimeException($msg);
        }

        $parts = collect(data_get($resp->json(), 'candidates.0.content.parts', []));

        foreach ($parts as $part) {
            $inline = $part['inlineData'] ?? null;
            if (! is_array($inline) || empty($inline['data'])) {
                continue;
            }

            $data = base64_decode((string) $inline['data'], true);
            if ($data === false) {
                continue;
            }

            $mime = $inline['mimeType'] ?? 'image/png';
            $ext = str_contains($mime, 'jpeg') ? 'jpg' : (str_contains($mime, 'webp') ? 'webp' : 'png');
            $name = Str::uuid().'.'.$ext;
            Storage::disk('public')->put('studio/'.$name, $data);

            return '/storage/studio/'.$name;
        }

        return null;
    }

    protected function geminiGenerate(string $model, string $prompt, string $key, array $config): Response
    {
        return Http::withHeaders(['x-goog-api-key' => $key])->timeout(180)
            ->post('https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent', [
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => $config,
            ]);
    }

    protected function isGeminiImageModel(string $model): bool
    {
        return str_contains($model, 'image') || str_contains($model, 'imagen');
    }

    protected function geminiAspectRatio(?string $ratio): ?string
    {
        return match ($ratio) {
            '1:1', '3:4', '4:3', '9:16', '16:9', '21:9' => $ratio,
            '4:5' => '3:4',
            '19:6' => '21:9',
            default => null,
        };
    }

    protected function tryDashscope(string $prompt, string $model, string $key, ?string $resolution = null, ?string $ratio = null, ?string $faceRef = null): ?string
    {
        try {
            $url = $this->callDashscope($prompt, $model, $key, $resolution, $ratio);
            if ($url) {
                $this->lastModel = $model;
                return $url;
            }
            $this->dashscopeError = 'Nhà cung cấp AI không trả về ảnh. Kiểm tra model / độ phân giải.';
        } catch (\Throwable $e) {
            $this->dashscopeError = $e->getMessage();
            capture_provider_quota_reset($e->getMessage());
            logger()->error('DashScope '.$model.' failed: '.$e->getMessage());
        }

        return null;
    }

    protected function copySample(string $prompt, ?string $preferred): string
    {
        $path = $this->resolveSamplePath($preferred);
        $contents = $path ? @file_get_contents($path) : null;
        $name = Str::uuid().'.jpg';

        Storage::disk('public')->put('studio/'.$name, $contents ?: $this->placeholder());

        return '/storage/studio/'.$name;
    }

    protected function resolveSamplePath(?string $preferred): ?string
    {
        if ($preferred && str_starts_with($preferred, '/storage/')) {
            $p = public_path(ltrim((string) parse_url($preferred, PHP_URL_PATH), '/'));
            if (is_file($p)) {
                return $p;
            }
        }

        $files = array_values(array_filter(glob(public_path('samples/2aOboQq*.jpg')) ?: [], 'is_file'));

        return $files ? $files[array_rand($files)] : null;
    }

    /**
     * QwenCloud keys are bound to a base URL by key type. A "sk-sp-…" key (Token /
     * Coding Plan) cannot be used on the classic pay-as-you-go DashScope host, so
     * auto-switch to the plan host unless the admin set a custom base URL.
     */
    protected function dashscopeBase(string $key): string
    {
        return dashscope_base_url($key);
    }

    protected function callDashscope(string $prompt, string $model, string $key, ?string $resolution = null, ?string $ratio = null, ?string $faceRef = null): ?string
    {
        // qwen-image / qwen-image-plus are async-only (submit a task, then poll).
        if (in_array($model, ['qwen-image', 'qwen-image-plus'], true)) {
            return $this->callDashscopeAsync($prompt, $model, $key, $resolution, $ratio);
        }

        $base = $base = rtrim($this->dashscopeBase($key), '/').'/api/v1';
        $size = $this->sizeFor($resolution, $ratio);
        $resp = Http::withToken($key)
            ->timeout(180)
            ->post($base.'/services/aigc/multimodal-generation/generation', [
                'model' => $model,
                'input' => ['messages' => [['role' => 'user', 'content' => [['text' => $prompt]]]]],
                'parameters' => [
                    'negative_prompt' => '',
                    'prompt_extend' => true,
                    'watermark' => false,
                    'size' => $size,
                ],
            ]);

        if (! $resp->successful()) {
            throw new \RuntimeException('DashScope ('.$resp->status().'): '.Str::limit((string) $resp->body(), 240));
        }

        $url = collect(data_get($resp->json(), 'output.choices.0.message.content', []))
            ->pluck('image')->first();

        return $url ? $this->storeRemoteImage($url) : null;
    }

    protected function dashscopeContent(string $prompt, ?string $faceRef): array
    {
        $parts = [];
        // Face reference injection ("Khuôn mặt mẫu") was removed — generation no longer pins a face image.
        $parts[] = ['text' => $prompt];

        return $parts;
    }

    protected function callDashscopeAsync(string $prompt, string $model, string $key, ?string $resolution = null, ?string $ratio = null): ?string
    {
        $base = $base = rtrim($this->dashscopeBase($key), '/').'/api/v1';
        $size = $this->sizeFor($resolution, $ratio);

        $submit = Http::withToken($key)->withHeaders(['X-DashScope-Async' => 'enable'])->timeout(60)
            ->post($base.'/services/aigc/text2image/image-synthesis', [
                'model' => $model,
                'input' => ['prompt' => $prompt],
                'parameters' => ['negative_prompt' => '', 'size' => $size, 'n' => 1, 'prompt_extend' => true, 'watermark' => false],
            ]);

        if (! $submit->successful()) {
            throw new \RuntimeException('DashScope ('.$submit->status().'): '.Str::limit((string) $submit->body(), 240));
        }

        $taskId = data_get($submit->json(), 'output.task_id');
        if (! $taskId) {
            throw new \RuntimeException('DashScope không trả về task_id.');
        }

        $deadline = microtime(true) + 180;

        while (microtime(true) < $deadline) {
            sleep(4);

            $q = Http::withToken($key)->timeout(30)->get($base.'/tasks/'.$taskId);

            if (! $q->successful()) {
                throw new \RuntimeException('DashScope ('.$q->status().'): '.Str::limit((string) $q->body(), 240));
            }

            $status = data_get($q->json(), 'output.task_status');

            if ($status === 'SUCCEEDED') {
                $url = data_get($q->json(), 'output.results.0.url');
                if (! $url) {
                    throw new \RuntimeException('DashScope hoàn tất nhưng không trả ảnh.');
                }

                return $this->storeRemoteImage($url);
            }

            if ($status === 'FAILED') {
                throw new \RuntimeException('DashScope: '.(string) data_get($q->json(), 'output.message', 'Tạo ảnh thất bại.'));
            }
        }

        throw new \RuntimeException('Hết thời gian chờ tạo ảnh (task '.$taskId.').');
    }

    /**
     * Re-edit a generated image so the model's face matches the reference face
     * (best-effort via the qwen-edit image model). Returns null on failure so the
     * original image is kept.
     */
    protected function downscaleImageBase64(string $path, int $max = 768): array
    {
        $img = @imagecreatefromstring((string) file_get_contents($path));
        if (! $img) {
            return ['', 'image/jpeg'];
        }
        $w = imagesx($img);
        $h = imagesy($img);
        if ($w > $max || $h > $max) {
            $scale = min($max / $w, $max / $h);
            $nw = max(1, (int) ($w * $scale));
            $nh = max(1, (int) ($h * $scale));
            $tmp = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($tmp, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($img);
            $img = $tmp;
        }
        ob_start();
        imagejpeg($img, null, 85);
        $data = ob_get_clean();
        imagedestroy($img);

        return [base64_encode((string) $data), 'image/jpeg'];
    }

    /**
     * Inpaint using the Qwen image-edit model with the source image as input.
     * Returns null on failure so the caller falls back to normal generation.
     */
    /**
     * Return the source image as a base64 data URI so the edit model is guaranteed to receive it
     * (a URL the provider can't fetch makes the model fall back to text2image -> creates a new image).
     */
    protected function imageDataUri(string $url): ?string
    {
        $path = ltrim((string) parse_url($url, PHP_URL_PATH), '/');
        $file = null;
        foreach ([public_path($path), storage_path('app/public/'.str_replace('storage/', '', $path))] as $c) {
            if (is_file($c)) {
                $file = $c;
                break;
            }
        }
        if (! $file) {
            return null;
        }
        [$b64, $mime] = $this->downscaleImageBase64($file, 1600);
        if ($b64 === '') {
            return null;
        }

        return 'data:'.$mime.';base64,'.$b64;
    }

    protected function editImage(string $prompt, string $imageUrl, ?string $modelOverride = null, ?string $faceRefUrl = null): ?string
    {
        $model = $modelOverride ?: (string) studio_config('qwen_edit_model', 'qwen-image-edit');
        if (! $this->isImageEditCapableModel($model)) {
            $this->dashscopeError = 'Model “'.$model.'” có vẻ KHÔNG phải model chỉnh sửa ảnh. '
                .'Chọn model Qwen Edit chuyên dụng (vd: qwen-image-edit, qwen-image-edit-plus, qwen-image-3.0-pro…) trong Cài đặt — không dùng model văn bản/thị giác (qwen3.8-flash, qwen-vl-…).';
            logger()->warning('Edit model không phải model edit', ['model' => $model]);
            return null;
        }
        $source = $this->imageDataUri($imageUrl);
        if (! $source) {
            logger()->warning('Edit: cannot read source image', ['url' => $imageUrl]);
            return null;
        }
        $faceRef = $faceRefUrl ? $this->imageDataUri($faceRefUrl) : null;

        // Edit (Inpaint) prioritises the Pay-As-You-Go credential (edit models usually live on the pay-go
        // host), then falls back to Token Plan — via studio_qwen_credentials('edit').
        $keys = studio_qwen_credentials('edit');

        $last = null;
        foreach ($keys as $key) {
            $base = dashscope_base_url($key).'/api/v1';
            logger()->info('Edit attempt', ['model' => $model, 'key_prefix' => substr($key, 0, 8), 'base' => $base, 'face_ref' => (bool) $faceRef]);

            // Content: optional face reference FIRST, then the design image to edit, then the instruction.
            $content = [];
            if ($faceRef) { $content[] = ['image' => $faceRef]; }
            $content[] = ['image' => $source];
            $content[] = ['text' => $prompt];

            $editUrl = $this->postMultimodalEdit($model, $base, $key, $content);
            if ($editUrl) {
                $this->lastModel = $model;
                logger()->info('Edit succeeded', ['model' => $model, 'key_prefix' => substr($key, 0, 8)]);
                return $this->storeRemoteImage($editUrl);
            }

            // The image-edit model may not accept a second reference image -> retry with just the design
            // image (the prompt still describes the model, so the swap still works).
            if ($faceRef && $this->editModelRejectsMultiImage()) {
                logger()->info('Edit retry without face ref', ['model' => $model, 'key_prefix' => substr($key, 0, 8)]);
                $editUrl = $this->postMultimodalEdit($model, $base, $key, [['image' => $source], ['text' => $prompt]]);
                if ($editUrl) {
                    $this->lastModel = $model;
                    logger()->info('Edit succeeded (no face ref)', ['model' => $model, 'key_prefix' => substr($key, 0, 8)]);
                    return $this->storeRemoteImage($editUrl);
                }
            }

            $last = $this->dashscopeError;
            $status = $this->dashscopeStatus;
            if ($status === 404) {
                $this->dashscopeError = 'Model “'.$model.'” không tồn tại trên host '.$base.' (404). '
                    .'Model Qwen-Edit thường CHỈ khả dụng trên host Pay-As-You-Go (key sk-…/sk-ws-…); host '
                    .'Token/Coding Plan (key sk-sp-…) thường không có model chỉnh sửa ảnh (chỉ có model tạo ảnh/văn bản). '
                    .'Dùng key Pay-As-You-Go cho Inpaint (Quản lý API → “Qwen Edit”), hoặc chọn model edit có trên gói.';
                break;
            }
            if ($status === 403) {
                $this->dashscopeError = 'Model “'.$model.'” chưa được mua/kích hoạt trên tài khoản (403 AccessDenied.Unpurchased). '
                    .'Bật/mua model Qwen-Image-Edit (vd qwen-image-edit, qwen-image-edit-plus) trong QwenCloud Model Center, '
                    .'hoặc dùng Gemini. Sau khi bật, chọn lại “Qwen Edit” trong Cài đặt.';
                break;
            }
            if ($status === 401) {
                continue; // invalid key -> try the next one
            }
            break; // 429/… are host/model-level -> don't hammer other keys
        }

        logger()->warning('Edit model ultimately failed', ['model' => $model, 'err' => $last]);

        return null;
    }

    /** POST a multimodal image-edit request. Returns the image URL or null (status/error kept). */
    protected function postMultimodalEdit(string $model, string $base, string $key, array $content): ?string
    {
        try {
            $resp = Http::withToken($key)->timeout(240)
                ->post($base.'/services/aigc/multimodal-generation/generation', [
                    'model' => $model,
                    'input' => ['messages' => [['role' => 'user', 'content' => $content]]],
                    'parameters' => ['watermark' => false],
                ]);

            $this->dashscopeStatus = $resp->status();
            if ($resp->successful()) {
                $editUrl = collect(data_get($resp->json(), 'output.choices.0.message.content', []))
                    ->pluck('image')->first();
                if ($editUrl) {
                    return $editUrl;
                }
                $this->dashscopeError = 'model returned no image in content';
                return null;
            }
            $body = Str::limit((string) $resp->body(), 240);
            $this->dashscopeError = 'HTTP '.$resp->status().': '.$body;
            logger()->warning('Edit model failed', ['model' => $model, 'status' => $resp->status(), 'body' => $body, 'key_prefix' => substr($key, 0, 8)]);
            return null;
        } catch (\Throwable $e) {
            $this->dashscopeStatus = 0;
            $this->dashscopeError = $e->getMessage();
            logger()->warning('Edit model threw', ['model' => $model, 'error' => $this->dashscopeError, 'key_prefix' => substr($key, 0, 8)]);
            return null;
        }
    }

    /** Whether the last edit failure looks like "too many / unsupported reference images". */
    protected function editModelRejectsMultiImage(): bool
    {
        $lower = strtolower((string) $this->dashscopeError);
        return $this->dashscopeStatus === 400
            || str_contains($lower, 'not support') || str_contains($lower, 'unsupported')
            || str_contains($lower, 'invalidparameter') || str_contains($lower, 'only one')
            || str_contains($lower, 'number of image') || str_contains($lower, 'too many');
    }

    /**
     * Whether a model is an image-edit / image-generation model (so we don't send image content to a
     * text/vision model). Allows Qwen image models (qwen-image-3.0-pro etc.) which also do editing.
     */
    protected function isImageEditCapableModel(string $model): bool
    {
        $m = strtolower($model);
        return str_contains($m, 'edit') || str_contains($m, 'qwen-image') || str_contains($m, 'imagen')
            || str_contains($m, 'wanx') || str_contains($m, 'imageedit') || str_contains($m, '-i2v');
    }

    /**
     * Edit an image with a SPECIFIC model (used by "Thay Đổi Người Mẫu" swap). Retries a couple of
     * times on a 429 rate-limit so a busy model still produces a result.
     */
    public function swapEdit(string $prompt, string $imageUrl, ?string $modelOverride = null, ?string $faceRefUrl = null): ?string
    {
        // For a face-reference swap, QwenCloud's recommended multi-image fusion model is qwen-image-3.0-pro
        // (subject + garment + pose). Try it first for best face fidelity, then the configured swap model,
        // then the base Qwen edit models. qwen-image-edit-max accepts the images but often ignores the
        // face reference, so a model that understands subject-driven editing is preferred.
        $models = $faceRefUrl
            ? array_values(array_unique(array_filter([
                'qwen-image-3.0-pro', $modelOverride, 'qwen-image-edit-max', 'qwen-image-edit-plus', 'qwen-image-edit',
            ])))
            : array_values(array_unique(array_filter([
                $modelOverride, 'qwen-image-edit-max', 'qwen-image-edit-plus', 'qwen-image-edit',
            ])));

        // Rate-limit on these models can take ~20-30s to clear; back off progressively (5/10/20/35s).
        $waits = [5, 10, 20, 35];
        foreach ($models as $model) {
            $url = $this->editImage($prompt, $imageUrl, $model, $faceRefUrl);
            if ($url) {
                logger()->info('Swap edit succeeded', ['model' => $model]);
                return $url;
            }
            if (str_contains(strtolower((string) $this->dashscopeError), '429') || str_contains(strtolower((string) $this->dashscopeError), 'ratelimit')) {
                logger()->warning('swapEdit rate-limited on '.$model.', backing off '.$waits[0].'s');
                sleep(array_shift($waits));
                continue;
            }
            // Non-rate-limit error -> try the next model (e.g. model not available / not supported).
            logger()->warning('Swap edit model failed, trying next', ['model' => $model, 'err' => $this->dashscopeError]);
        }
        return null;
    }

    protected function storeRemoteImage(string $url): ?string
    {
        $contents = @file_get_contents($url);
        if (! $contents) {
            return null;
        }

        $name = Str::uuid().'.png';
        Storage::disk('public')->put('studio/'.$name, $contents);

        return '/storage/studio/'.$name;
    }

    protected function sizeFor(?string $resolution, ?string $ratio): string
    {
        // Qwen-Image only accepts a fixed set of sizes; map the ratio (and the extra
        // ratios) onto the nearest supported one so the provider call succeeds.
        return match ($ratio) {
            '16:9', '21:9', '19:6' => '1664*928',
            '4:3' => '1472*1104',
            '1:1' => '1328*1328',
            '3:4', '4:5' => '1104*1472',
            '9:16' => '928*1664',
            default => '1328*1328',
        };
    }

    protected function placeholder(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    }
}
