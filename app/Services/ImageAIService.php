<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * 2D image generation (Flux / Fal.ai / Replicate).
 * Stub: reuses a bundled sample fashion image so the flow looks real offline.
 * Swap in a real provider call when FAL_KEY / REPLICATE_API_TOKEN is set.
 */
class ImageAIService
{
    protected array $sampleFiles = [
        '2aOboQqOBTR5uosVCsNhbUXA5FrAsBRBPGV455LU.jpg',
        '2aOboQqYvkXKiGyUiupCkfAkexFC5tnuYVTfH4Ns.jpg',
        '2aOboQqrxsGkdZ6pa9L6NUdw4tpnrB3zWyUdfTk8G.jpg',
    ];

    protected function falKey(): ?string
    {
        return studio_api_key('fal');
    }

    /**
     * Which image generator to use (flux | wan | qwen). Keys read from the
     * Studio API page / env. The stub returns a bundled sample regardless.
     */
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

    public function generate(string $prompt, ?string $baseImage = null, ?string $maskImage = null): string
    {
        // Inpainting / updates reuse the source image for the stub.
        if ($baseImage && $maskImage) {
            return $this->copySample($prompt, $baseImage);
        }

        $provider = $this->provider();

        // Wan / Qwen run on Alibaba DashScope (multimodal-generation).
        if (in_array($provider, ['wan', 'qwen'])) {
            $key = $this->providerKey();
            if ($key) {
                try {
                    $url = $this->callDashscope($prompt, $provider === 'wan' ? 'wan2.7-image-pro' : 'qwen-image', $key);
                    if ($url) {
                        return $url;
                    }
                } catch (\Throwable $e) {
                    logger()->error('DashScope image generation failed: '.$e->getMessage());
                }
            }
        }

        return $this->copySample($prompt, null);
    }

    protected function copySample(string $prompt, ?string $preferred): string
    {
        $source = $preferred && str_starts_with($preferred, '/storage/') ? $preferred : null;

        if (! $source) {
            $source = 'samples/'.$this->sampleFiles[array_rand($this->sampleFiles)];
        }

        $contents = @file_get_contents(public_path($source));
        $name = Str::uuid().'.jpg';

        Storage::disk('public')->put('studio/'.$name, $contents ?: $this->placeholder());

        // Relative URL so it resolves against whatever host serves the page.
        return '/storage/studio/'.$name;
    }

    /**
     * Alibaba DashScope multimodal image generation (Wan / Qwen image).
     */
    protected function callDashscope(string $prompt, string $provider, string $key): ?string
    {
        $model = $provider === 'wan'
            ? studio_config('wan_model', 'wan2.7-image-pro')
            : studio_config('qwen_model', 'qwen-image');
        $resp = \Illuminate\Support\Facades\Http::withToken($key)
            ->timeout(180)
            ->post(studio_config('dashscope_base', 'https://dashscope-intl.aliyuncs.com').'/api/v1/services/aigc/multimodal-generation/generation', [
                'model' => $model,
                'input' => ['messages' => [['role' => 'user', 'content' => [['text' => $prompt]]]]],
                'parameters' => ['n' => 1, 'size' => '2K'],
            ]);

        $url = collect(data_get($resp->json(), 'output.choices.0.message.content', []))
            ->pluck('image')->first();

        if ($url) {
            $contents = @file_get_contents($url);
            if ($contents) {
                $name = \Illuminate\Support\Str::uuid().'.png';
                \Illuminate\Support\Facades\Storage::disk('public')->put('studio/'.$name, $contents);

                return '/storage/studio/'.$name;
            }

            return $url;
        }

        return null;
    }

    protected function placeholder(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    }
}