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

    public function generate(string $prompt, ?string $baseImage = null, ?string $maskImage = null, ?string $resolution = null, ?string $ratio = null, ?string $faceRef = null): string
    {
        $provider = $this->provider();
        $dashscopeKey = studio_api_key('dashscope');
        $triedReal = false;

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

        if ($provider === 'gemini') {
            $key = $this->providerKey();
            if ($key) {
                $triedReal = true;
                $url = $this->tryGeminiImage($prompt, $key, $resolution, $ratio);
                if ($url) {
                    return $url;
                }
            }
        } elseif (in_array($provider, ['wan', 'qwen'])) {
            $key = $this->providerKey();
            if ($key) {
                $triedReal = true;
                $models = $provider === 'wan'
                    ? array_values(array_unique([(string) studio_config('wan_model', 'wan2.7-image-pro'), 'wan2.7-image-pro', 'wan2.1-image-pro']))
                    : array_values(array_unique([(string) studio_config('qwen_model', 'qwen-image-3.0-pro'), 'qwen-image-3.0-pro', 'qwen-image-max', 'qwen-image-plus', 'qwen-image']));

                $url = $this->tryModels($prompt, $models, studio_qwen_credentials('image'), $resolution, $ratio, $faceRef);
                if ($url) {
                    return $url;
                }
            }
        } elseif (! studio_api_key('fal') && ! studio_api_key('replicate') && $dashscopeKey) {
            $triedReal = true;
            // Flux/default without a Fal/Replicate key: use the Qwen image model so a valid
            // QwenCloud / DashScope key produces a real AI image.
            $models = ['qwen-image-3.0-pro', 'qwen-image-max', 'qwen-image-plus', 'qwen-image'];
            $url = $this->tryModels($prompt, $models, studio_qwen_credentials('image'), $resolution, $ratio);
            if ($url) {
                return $url;
            }
        }

        if ($triedReal) {
            throw new \RuntimeException($this->providerErrorMessage());
        }

        // No real key configured -> stub (reuse the source image for edits, sample otherwise).
        return $this->copySample($prompt, $baseImage);
    }

    /**
     * Try each model in order until one returns an image (handles model eligibility
     * differences between accounts). The last error is kept for the user.
     */
    protected function tryModels(string $prompt, array $models, array $keys, ?string $resolution = null, ?string $ratio = null, ?string $faceRef = null): ?string
    {
        $last = null;

        foreach ($keys as $key) {
            $keyQuota = false;
            foreach ($models as $model) {
                $t = microtime(true);
                $url = $this->tryDashscope($prompt, $model, $key, $resolution, $ratio, $faceRef);
                logger()->info('Image model attempt', [
                    'model' => $model, 'ok' => (bool) $url, 's' => round(microtime(true) - $t, 2),
                    'key_prefix' => substr($key, 0, 8),
                    'err' => $url ? null : $this->dashscopeError,
                ]);
                if ($url) {
                    return $url;
                }
                $last = $this->dashscopeError;

                // Quota exhausted on this key -> fail over to the next credential (Pay-As-You-Go).
                if (is_qwen_quota_error($this->dashscopeError)) {
                    $keyQuota = true;
                    break;
                }

                // Host/model-level failure (404/401/not exist/url error) — don't rotate keys on those.
                $lower = strtolower((string) $this->dashscopeError);
                if (str_contains($lower, 'model not exist') || str_contains($lower, 'invalidparameter')
                    || str_contains($lower, 'model_not_supported') || str_contains($lower, 'url error')
                    || str_contains($lower, 'invalidapikey')) {
                    break 2;
                }
            }
            if ($keyQuota) {
                continue; // try the next key (Token Plan -> Pay-As-You-Go)
            }
            break;
        }

        $this->dashscopeError = $last ?: 'Nhà cung cấp AI không trả về ảnh.';

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

    protected function tryGeminiImage(string $prompt, string $key, ?string $resolution = null, ?string $ratio = null): ?string
    {
        try {
            $url = $this->callGeminiImage($prompt, $key, $ratio);
            if ($url) {
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
    protected function callGeminiImage(string $prompt, string $key, ?string $ratio = null): ?string
    {
        $model = (string) studio_config('gemini_image_model', 'gemini-2.5-flash-image');

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
        $face = $faceRef ?: (string) setting('studio_face_ref', '');
        if ($face && str_starts_with($face, '/storage/')) {
            $parts[] = ['image' => url($face)];
        }
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
    public function applyFace(string $imageUrl, string $faceRef): ?string
    {
        $model = (string) studio_config('qwen_edit_model', 'qwen-image-edit');
        $key = studio_api_key('qwen') ?: studio_api_key('dashscope');
        if (! $key) {
            return null;
        }
        $base = dashscope_base_url($key).'/api/v1';
        // Self-contained images (base64 data URIs) so the host can read them even though /storage is internal.
        $main = $this->imageDataUri($imageUrl);
        $face = $this->imageDataUri($faceRef);
        if (! $main || ! $face) {
            logger()->warning('applyFace: cannot read source/face image', ['imageUrl' => $imageUrl, 'face' => $faceRef]);
            return null;
        }

        try {
            $resp = Http::withToken($key)->timeout(180)
                ->post($base.'/services/aigc/multimodal-generation/generation', [
                    'model' => $model,
                    'input' => ['messages' => [['role' => 'user', 'content' => [
                        ['image' => $main],
                        ['image' => $face],
                        ['text' => 'Keep the outfit, pose, background, colours and lighting exactly as in the first image. Replace the person\'s face with the face from the second reference image, naturally and consistently.'],
                    ]]]],
                    'parameters' => ['watermark' => false],
                ]);

            if (! $resp->successful()) {
                logger()->warning('applyFace: HTTP '.$resp->status().' '.substr((string) $resp->body(), 0, 220));
                return null;
            }
            $editUrl = collect(data_get($resp->json(), 'output.choices.0.message.content', []))
                ->pluck('image')->first();
            if (! $editUrl) {
                logger()->warning('applyFace: no image in response', ['resp' => substr((string) $resp->body(), 0, 220)]);
                return null;
            }

            logger()->info('applyFace succeeded (face swapped)');
            return $this->storeRemoteImage($editUrl);
        } catch (\Throwable $e) {
            logger()->error('Face sync failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Describe the reference face's features using the configured vision provider, so the
     * generated image can reproduce that face via the prompt (no edit model required).
     */
    public function describeFace(string $faceRef): ?string
    {
        $rel = ltrim((string) parse_url($faceRef, PHP_URL_PATH), '/');
        $path = storage_path('app/public/'.str_replace('storage/', '', $rel));
        if (! is_file($path)) {
            return null;
        }

        $model = studio_vision_model('gemini');
        [$b64, $mime] = $this->downscaleImageBase64($path);
        $text = 'Describe this person\'s face in detail for an AI fashion image so it can be reproduced: '
            .'give ethnicity hint, hair (colour, length, style, texture), face shape, eyebrows, eyes, nose, lips, '
            .'skin tone, makeup, and any distinguishing features. Return a short paragraph (max 80 words), plain text, '
            .'no intro.';

        if ((string) studio_config('vision_provider', 'gemini') === 'qwen') {
            $key = studio_api_key('qwen') ?: studio_api_key('dashscope');
            if ($key) {
                $base = dashscope_base_url($key).'/compatible-mode/v1';
                foreach (studio_qwen_vision_models() as $vm) {
                    try {
                        $resp = Http::withToken($key)->timeout(90)->post($base.'/chat/completions', [
                            'model' => $vm,
                            'messages' => [['role' => 'user', 'content' => [
                                ['type' => 'text', 'text' => $text],
                                ['type' => 'image_url', 'image_url' => ['url' => 'data:'.$mime.';base64,'.$b64]],
                            ]]],
                        ]);
                        $desc = trim((string) data_get($resp->json(), 'choices.0.message.content'));
                        if ($resp->successful() && $desc) {
                            return $desc;
                        }
                        $body = (string) $resp->body();
                        if (! (str_contains(strtolower($body), 'model_not_found') || str_contains(strtolower($body), 'model not exist') || $resp->status() === 404)) {
                            break; // non-404 error -> stop trying models
                        }
                    } catch (\Throwable $e) {
                        logger()->error('Qwen describeFace ('.$vm.') failed: '.$e->getMessage());
                    }
                }
            }
        }

        $key = studio_api_key('gemini');
        if ($key) {
            try {
                $resp = Http::withHeaders(['x-goog-api-key' => $key])->timeout(90)
                    ->post('https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent', [
                        'contents' => [['parts' => [
                            ['inlineData' => ['mimeType' => $mime, 'data' => $b64]],
                            ['text' => $text],
                        ]]],
                    ]);
                $desc = trim((string) data_get($resp->json(), 'candidates.0.content.parts.0.text'));
                if ($resp->successful() && $desc) {
                    return $desc;
                }
            } catch (\Throwable $e) {
                logger()->error('Gemini describeFace failed: '.$e->getMessage());
            }
        }

        return null;
    }

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

    protected function editImage(string $prompt, string $imageUrl): ?string
    {
        $model = (string) studio_config('qwen_edit_model', 'qwen-image-edit');
        if (! str_contains(strtolower($model), 'edit')) {
            $this->dashscopeError = 'Model “'.$model.'” có vẻ KHÔNG phải model chỉnh sửa ảnh (tên không chứa “edit”). '
                .'Chọn model Qwen Edit chuyên dụng (vd: qwen-image-edit, qwen-image-edit-plus…) trong Cài đặt — không dùng chung model tạo ảnh (qwen-image-3.0-pro) cho Inpaint.';
            logger()->warning('Edit model không phải model edit', ['model' => $model]);
            return null;
        }
        $source = $this->imageDataUri($imageUrl);
        if (! $source) {
            logger()->warning('Edit: cannot read source image', ['url' => $imageUrl]);
            return null;
        }

        // Edit (Inpaint) prioritises the Pay-As-You-Go credential (edit models usually live on the pay-go
        // host), then falls back to Token Plan — via studio_qwen_credentials('edit').
        $keys = studio_qwen_credentials('edit');

        $last = null;
        foreach ($keys as $key) {
            $base = dashscope_base_url($key).'/api/v1';
            logger()->info('Edit attempt', ['model' => $model, 'key_prefix' => substr($key, 0, 8), 'base' => $base]);
            try {
                $resp = Http::withToken($key)->timeout(240)
                    ->post($base.'/services/aigc/multimodal-generation/generation', [
                        'model' => $model,
                        'input' => ['messages' => [['role' => 'user', 'content' => [
                            ['image' => $source],
                            ['text' => $prompt],
                        ]]]],
                        'parameters' => ['watermark' => false],
                    ]);

                if ($resp->successful()) {
                    $editUrl = collect(data_get($resp->json(), 'output.choices.0.message.content', []))
                        ->pluck('image')->first();
                    if ($editUrl) {
                        logger()->info('Edit succeeded', ['model' => $model, 'key_prefix' => substr($key, 0, 8)]);
                        return $this->storeRemoteImage($editUrl);
                    }
                    $last = 'model returned no image in content';
                    continue;
                }

                $body = Str::limit((string) $resp->body(), 240);
                logger()->warning('Edit model failed', ['model' => $model, 'status' => $resp->status(), 'body' => $body, 'key_prefix' => substr($key, 0, 8)]);
                $last = 'HTTP '.$resp->status().': '.$body;
                if ($resp->status() === 404) {
                    $this->dashscopeError = 'Model “'.$model.'” không tồn tại trên host '.$base.' (404). '
                        .'Model Qwen-Edit thường CHỈ khả dụng trên host Pay-As-You-Go (key sk-…/sk-ws-…); host '
                        .'Token/Coding Plan (key sk-sp-…) thường không có model chỉnh sửa ảnh (chỉ có model tạo ảnh/văn bản). '
                        .'Dùng key Pay-As-You-Go cho Inpaint (Quản lý API → “Qwen Edit”), hoặc chọn model edit có trên gói.';
                    break;
                }
                if ($resp->status() === 403) {
                    $this->dashscopeError = 'Model “'.$model.'” chưa được mua/kích hoạt trên tài khoản (403 AccessDenied.Unpurchased). '
                        .'Bật/mua model Qwen-Image-Edit (vd qwen-image-edit, qwen-image-edit-plus) trong QwenCloud Model Center, '
                        .'hoặc dùng Gemini. Sau khi bật, chọn lại “Qwen Edit” trong Cài đặt.';
                    break;
                }
                if ($resp->status() === 401) {
                    continue; // invalid key -> try the next one
                }
                break; // 429/… are host/model-level -> don't hammer other keys
            } catch (\Throwable $e) {
                $last = $e->getMessage();
                logger()->warning('Edit model threw', ['model' => $model, 'error' => $last, 'key_prefix' => substr($key, 0, 8)]);
            }
        }

        logger()->warning('Edit model ultimately failed', ['model' => $model, 'err' => $last]);

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
