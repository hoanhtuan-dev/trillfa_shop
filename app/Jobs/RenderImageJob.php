<?php

namespace App\Jobs;

use App\Models\Generation;
use App\Services\ImageAIService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RenderImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300;

    public function __construct(public int $generationId) {}

    public function handle(ImageAIService $images): void
    {
        $generation = Generation::find($this->generationId);

        if (! $generation) {
            return;
        }

        $generation->update(['status' => 'processing']);

        try {
            if ($generation->fresh()->status === 'cancelled') {
                return;
            }

            $url = $images->generate(
                (string) $generation->prompt,
                $generation->base_image,
                $generation->mask_image,
                $generation->resolution,
                $generation->ratio,
            );

            // Brand logo stamping is disabled for now (opt-in via studio.brand_logo_enabled).
            if (studio_config('brand_logo_enabled', false)) {
                $url = $this->applyBrandLogo($url);
            }

            // Best-effort face consistency: apply the reference face to the generated image.
            if (! $generation->base_image || ! $generation->mask_image) {
                $faceRef = (string) setting('studio_face_ref', '');
                if ($faceRef && str_starts_with($faceRef, '/storage/')) {
                    $faced = $images->applyFace($url, $faceRef);
                    if ($faced) {
                        $url = $faced;
                    }
                }
            }

            $generation->update(['status' => 'completed', 'media_url' => $url]);
        } catch (\Throwable $e) {
            $generation->update(['status' => 'failed', 'error' => $e->getMessage()]);
            $this->refund($generation);
        }
    }

    protected function applyBrandLogo(string $url): string
    {
        $logoUrl = (string) setting('studio_brand_logo', '');
        if (! $logoUrl || ! str_starts_with($logoUrl, '/storage/')) {
            return $url;
        }

        $rel = ltrim((string) parse_url($logoUrl, PHP_URL_PATH), '/');
        $logoPath = storage_path('app/public/'.str_replace('storage/', '', $rel));
        if (! is_file($logoPath)) {
            return $url;
        }

        $relImg = ltrim((string) parse_url($url, PHP_URL_PATH), '/');
        $imgPath = storage_path('app/public/'.str_replace('storage/', '', $relImg));
        if (! is_file($imgPath)) {
            return $url;
        }

        $img = @imagecreatefromstring((string) file_get_contents($imgPath));
        $logo = @imagecreatefromstring((string) file_get_contents($logoPath));
        if (! $img || ! $logo) {
            return $url;
        }

        $iw = imagesx($img);
        $lh = imagesy($logo);
        $lw = imagesx($logo);
        $targetW = max(36, (int) round($iw * 0.13));
        $targetH = max(1, (int) round($lh * ($targetW / $lw)));
        $pad = max(14, (int) round($iw * 0.04));
        // Top-centre (background wall behind the model) reads as a natural backdrop sign.
        $dx = (int) (($iw - $targetW) / 2);
        imagecopyresampled($img, $logo, $dx, $pad, 0, 0, $targetW, $targetH, $lw, $lh);

        if (str_ends_with($relImg, '.png')) {
            imagepng($img, $imgPath);
        } else {
            imagejpeg($img, $imgPath, 92);
        }
        imagedestroy($img);
        imagedestroy($logo);

        return $url;
    }

    protected function refund(Generation $generation): void
    {
        if ($generation->credits_cost > 0) {
            $generation->user?->increment('credits_balance', $generation->credits_cost);
        }
    }
}
