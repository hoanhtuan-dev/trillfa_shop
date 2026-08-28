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
                $model = $provider === 'wan'
                    ? (string) studio_config('wan_model', 'wan2.7-image-pro')
                    : (string) studio_config('qwen_model', 'qwen-image-3.0-pro');

                $url = $this->tryDashscope($prompt, $model, $key, $resolution, $ratio);
                if ($url) {
                    return $url;
                }
            }
        } elseif (! studio_api_key('fal') && ! studio_api_key('replicate') && $dashscopeKey) {
            $triedReal = true;
            // Flux/default without a Fal/Replicate key: use the Qwen image model so a valid
            // QwenCloud / DashScope key produces a real AI image.
            $model = (string) studio_config('qwen_model', 'qwen-image-3.0-pro');
            $url = $this->tryDashscope($prompt, $model, $dashscopeKey, $resolution, $ratio);
            if ($url) {
                return $url;
            }
        }

        if ($triedReal) {
            // A real key was configured but the provider call failed: surface the reason
            // instead of silently returning a stub image.
            throw new \RuntimeException($this->dashscopeError ?: 'Không thể gọi nhà cung cấp AI. Kiểm tra key / model / độ phân giải trong Cài đặt.');
        }

        // No real key configured -> stub (reuse the source image for edits, sample otherwise).
        return $this->copySample($prompt, $baseImage);
    }

    protected function tryDashscope(string $prompt, string $model, string $key, ?string $resolution = null, ?string $ratio = null): ?string
    {
        try {
            $url = $this->callDashscope($prompt, $model, $key, $resolution, $ratio);
            if ($url) {
                return $url;
            }
            $this->dashscopeError = 'Nhà cung cấp AI không trả về ảnh. Kiểm tra model / độ phân giải (Qwen-Image chỉ hỗ trợ size cố định như 1328*1328, 928*1664…).';
        } catch (\Throwable $e) {
            $this->dashscopeError = $e->getMessage();
            logger()->error('DashScope image generation failed: '.$e->getMessage());
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
        $size = $this->sizeFor($resolution, $ratio);
        $resp = Http::withToken($key)
            ->timeout(180)
            ->post(studio_config('dashscope_base', 'https://dashscope-intl.aliyuncs.com').'/api/v1/services/aigc/multimodal-generation/generation', [
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

        if ($url) {
            $contents = @file_get_contents($url);
            if ($contents) {
                $name = Str::uuid().'.png';
                Storage::disk('public')->put('studio/'.$name, $contents);

                return '/storage/studio/'.$name;
            }

            return $url;
        }

        return null;
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
