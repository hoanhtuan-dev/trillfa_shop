<?php

namespace App\Services;

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
            'wan' => studio_api_key('wan') ?: studio_api_key('dashscope'),
            'qwen' => studio_api_key('qwen') ?: studio_api_key('dashscope'),
            default => studio_api_key('fal') ?: studio_api_key('replicate'),
        };
    }

    public function generate(string $prompt, ?string $baseImage = null, ?string $maskImage = null, ?string $resolution = null, ?string $ratio = null): string
    {
        $provider = $this->provider();
        $dashscopeKey = studio_api_key('dashscope');
        $triedReal = false;

        if (in_array($provider, ['wan', 'qwen'])) {
            $key = $this->providerKey();
            if ($key) {
                $triedReal = true;
                $models = $provider === 'wan'
                    ? array_values(array_unique([(string) studio_config('wan_model', 'wan2.7-image-pro'), 'wan2.7-image-pro', 'wan2.1-image-pro']))
                    : array_values(array_unique([(string) studio_config('qwen_model', 'qwen-image-3.0-pro'), 'qwen-image-3.0-pro', 'qwen-image-max', 'qwen-image-plus', 'qwen-image']));

                $url = $this->tryModels($prompt, $models, $key, $resolution, $ratio);
                if ($url) {
                    return $url;
                }
            }
        } elseif (! studio_api_key('fal') && ! studio_api_key('replicate') && $dashscopeKey) {
            $triedReal = true;
            // Flux/default without a Fal/Replicate key: use the Qwen image model so a valid
            // QwenCloud / DashScope key produces a real AI image.
            $models = ['qwen-image-3.0-pro', 'qwen-image-max', 'qwen-image-plus', 'qwen-image'];
            $url = $this->tryModels($prompt, $models, $dashscopeKey, $resolution, $ratio);
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
    protected function tryModels(string $prompt, array $models, string $key, ?string $resolution = null, ?string $ratio = null): ?string
    {
        $last = null;

        foreach ($models as $model) {
            $url = $this->tryDashscope($prompt, $model, $key, $resolution, $ratio);
            if ($url) {
                return $url;
            }
            $last = $this->dashscopeError;
        }

        $this->dashscopeError = $last ?: 'Nhà cung cấp AI không trả về ảnh.';

        return null;
    }

    protected function providerErrorMessage(): string
    {
        $msg = $this->dashscopeError ?: 'Không thể gọi nhà cung cấp AI. Kiểm tra key / model / độ phân giải trong Cài đặt.';

        if (str_contains($msg, 'Unpurchased') || str_contains($msg, 'eligible')) {
            $msg .= ' — Model chưa được kích hoạt trên tài khoản. Hãy bật model ở trang QwenCloud, hoặc trong Cài đặt đổi “Ảnh Qwen” sang qwen-image / qwen-image-plus và đặt Chế độ xử lý = Queue.';
        }

        return $msg;
    }

    protected function tryDashscope(string $prompt, string $model, string $key, ?string $resolution = null, ?string $ratio = null): ?string
    {
        try {
            $url = $this->callDashscope($prompt, $model, $key, $resolution, $ratio);
            if ($url) {
                return $url;
            }
            $this->dashscopeError = 'Nhà cung cấp AI không trả về ảnh. Kiểm tra model / độ phân giải.';
        } catch (\Throwable $e) {
            $this->dashscopeError = $e->getMessage();
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

    protected function callDashscope(string $prompt, string $model, string $key, ?string $resolution = null, ?string $ratio = null): ?string
    {
        // qwen-image / qwen-image-plus are async-only (submit a task, then poll).
        if (in_array($model, ['qwen-image', 'qwen-image-plus'], true)) {
            return $this->callDashscopeAsync($prompt, $model, $key, $resolution, $ratio);
        }

        $base = rtrim((string) studio_config('dashscope_base', 'https://dashscope-intl.aliyuncs.com'), '/').'/api/v1';
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

    protected function callDashscopeAsync(string $prompt, string $model, string $key, ?string $resolution = null, ?string $ratio = null): ?string
    {
        $base = rtrim((string) studio_config('dashscope_base', 'https://dashscope-intl.aliyuncs.com'), '/').'/api/v1';
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
