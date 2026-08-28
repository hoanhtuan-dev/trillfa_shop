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

    public function generate(string $prompt, ?string $baseImage = null, ?string $maskImage = null): string
    {
        // Inpainting / updates reuse the source image for the stub.
        if ($baseImage && $maskImage) {
            return $this->copySample($prompt, $baseImage);
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

    protected function placeholder(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    }
}