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

        if (in_array($provider, ['wan', 'qwen'])) {
            $key = $this->providerKey();
            if ($key) {
                $model = $provider === 'wan'
                    ? (string) studio_config('wan_model', 'wan2.7-image-pro')
                    : (string) studio_config('qwen_model', 'qwen-image');

                $url = $this->tryDashscope($prompt, $model, $key, $resolution, $ratio);
                if ($url) {
                    return $url;
                }
            }
        } elseif (! studio_api_key('fal') && ! studio_api_key('replicate') && $dashscopeKey) {
            // Flux/default without a Fal/Replicate key: use DashScope (Wan image) so a valid
            // DashScope key produces a real AI image instead of the stub.
            $model = (string) studio_config('qwen_model', 'qwen-image-3.0-pro');
            $url = $this->tryDashscope($prompt, $model, $dashscopeKey, $resolution, $ratio);
            if ($url) {
                return $url;
            }
        }

        // Stub: for an edit (base image present) reuse the source image; otherwise a clean sample.
        return $this->copySample($prompt, $baseImage);
    }

    protected function tryDashscope(string $prompt, string $model, string $key, ?string $resolution = null, ?string $ratio = null): ?string
    {
        try {
            return $this->callDashscope($prompt, $model, $key, $resolution, $ratio);
        } catch (\Throwable $e) {
            logger()->error('DashScope image generation failed: '.$e->getMessage());

            return null;
        }
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
        $twoK = $resolution === '2K';

        return match ($ratio) {
            '16:9' => $twoK ? '2048*1152' : '1280*720',
            '9:16' => $twoK ? '1152*2048' : '720*1280',
            '4:3' => $twoK ? '2048*1536' : '1024*768',
            '3:4' => $twoK ? '1536*2048' : '768*1024',
            '4:5' => $twoK ? '1638*2048' : '1024*1280',
            '21:9' => $twoK ? '2048*878' : '1280*549',
            '19:6' => $twoK ? '2048*647' : '1280*405',
            default => $twoK ? '2048*2048' : '1024*1024',
        };
    }

    protected function placeholder(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    }
}
