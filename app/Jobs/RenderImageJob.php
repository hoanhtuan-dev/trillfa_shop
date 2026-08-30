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
        $t0 = microtime(true);
        $generation = Generation::find($this->generationId);

        if (! $generation) {
            return;
        }

        $generation->update(['status' => 'processing']);

        try {
            if ($generation->fresh()->status === 'cancelled') {
                return;
            }

            // Face sync (no edit model): describe the reference face via the vision provider, then
            // inject that description into the prompt so the model reproduces the face. The face is
            // static, so the description is cached — a ~90s vision call should NOT run on every image.
            $prompt = (string) $generation->prompt;
            $faceRef = (string) setting('studio_face_ref', '');
            $faceSyncOn = filter_var(studio_config('face_sync_enabled', true), FILTER_VALIDATE_BOOL);
            $faceDesc = '';
            // Chỉ nhúng mô tả khuôn mặt cho lần TẠO MỚI (không có ảnh nguồn). Với Inpaint (có base_image),
            // ảnh nguồn đã chứa khuôn mặt → mô tả lại làm model vẽ lại toàn bộ, gây trôi/không bảo toàn.
            if ($faceSyncOn && $faceRef && str_starts_with($faceRef, '/storage/') && ! $generation->base_image) {
                $hash = md5($faceRef);
                $faceDesc = ((string) setting('studio_face_desc_hash', '') === $hash)
                    ? (string) setting('studio_face_desc', '')
                    : '';
                if ($faceDesc === '') {
                    $faceDesc = (string) ($images->describeFace($faceRef) ?? '');
                    if ($faceDesc !== '') {
                        set_setting('studio_face_desc', $faceDesc);
                        set_setting('studio_face_desc_hash', $hash);
                    }
                }
                if ($faceDesc !== '') {
                    $prompt = 'The model has this face: '.$faceDesc.'. '.$prompt;
                    $generation->update(['prompt' => $prompt]);
                }
            }

            $url = $images->generate(
                $prompt,
                $generation->base_image,
                $generation->mask_image,
                $generation->resolution,
                $generation->ratio,
            );

            // Face swap (model chỉnh sửa) is OPT-IN — only runs when "Đồng bộ khuôn mặt (model chỉnh sửa)"
            // is turned on in Settings, so the default surgical edit stays fast (~20s). When enabled it swaps
            // the reference face onto the result (a second qwen-edit call, ~40s total). Falls back to the
            // original image if it fails (keeps the result usable). Fresh generations keep the prompt-injection
            // path above (describeFace) unless this opt-in is on too.
            $faceSwap = filter_var(studio_config('face_edit_sync', false), FILTER_VALIDATE_BOOL);
            if ($faceSwap && $faceSyncOn && $faceRef && str_starts_with($faceRef, '/storage/')) {
                logger()->info('Applying face sync (edit model)', ['generation_id' => $generation->id, 'face' => $faceRef]);
                $edited = $images->applyFace($url, $faceRef);
                if ($edited && $edited !== $url) {
                    $url = $edited;
                }
            }

            // Brand logo stamping is disabled for now (opt-in via studio.brand_logo_enabled).
            if (studio_config('brand_logo_enabled', false)) {
                $url = $this->applyBrandLogo($url);
            }

            $pr = (array) ($generation->promptsHistory?->json_response ?? []);
            $generation->update([
                'status' => 'completed',
                'media_url' => $url,
                'elapsed_ms' => (int) round((microtime(true) - $t0) * 1000),
                'meta' => [
                    'type' => 'image',
                    'provider' => $generation->provider,
                    'model' => $generation->model,
                    'resolution' => $generation->resolution,
                    'ratio' => $generation->ratio,
                    'creative_level' => $pr['creative_level'] ?? null,
                    'adherence' => $pr['adherence'] ?? null,
                    'negative_prompt' => $pr['negative_prompt'] ?? null,
                    'face_sync' => ($faceDesc !== ''),
                ],
            ]);
            logger()->info('Image generation completed', [
                'generation_id' => $generation->id, 'provider' => $generation->provider,
                'model' => $generation->model, 'total_s' => round(microtime(true) - $t0, 2),
                'elapsed_ms' => (int) round((microtime(true) - $t0) * 1000),
            ]);
        } catch (\Throwable $e) {
            $generation->update(['status' => 'failed', 'error' => $e->getMessage(), 'elapsed_ms' => (int) round((microtime(true) - $t0) * 1000)]);
            $this->refund($generation);
            logger()->warning('Image generation failed', [
                'generation_id' => $generation->id, 'total_s' => round(microtime(true) - $t0, 2),
                'error' => $e->getMessage(),
            ]);
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
