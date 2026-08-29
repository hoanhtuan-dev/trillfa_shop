<?php

namespace App\Jobs;

use App\Models\Generation;
use App\Services\VideoAIService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RenderVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    /**
     * Allow generous time for video render (1-3 min on real providers).
     */
    public int $timeout = 900; // 15 min — must exceed the 420s provider poll deadline

    public function __construct(public int $generationId) {}

    public function handle(VideoAIService $videos): void
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

            // Simulate the async nature of a real video provider for the demo.
            sleep(2);

            // Camera + coherent video prompt come from the same Creative Direction so the
            // video matches the rendered image (consolidated image -> video workflow).
            $pr = $generation->promptsHistory?->json_response ?? [];
            // Camera action comes from the selected "Kịch bản quay" (stored in meta.camera); fall back to
            // a neutral tracking shot. Image camera angles (category.camera) must NOT leak into video.
            $camera = (string) ($generation->meta['camera'] ?? '');
            if ($camera === '') {
                $camera = (string) (data_get($pr, 'camera', '') ?: 'slow tracking');
            }
            $prompt = (string) $generation->prompt;
            if (trim($prompt) === '') {
                $prompt = (string) data_get($pr, 'video_prompt_en', 'một video catwalk thời trang');
            }

            $url = $videos->render(
                $prompt,
                (string) $generation->base_image,
                $camera,
                $generation->resolution,
                $generation->duration,
            );

            $generation->update([
                'status' => 'completed',
                'media_url' => $url,
                'elapsed_ms' => (int) round((microtime(true) - $t0) * 1000),
                'meta' => [
                    'type' => 'video',
                    'provider' => $generation->provider,
                    'model' => $generation->model,
                    'resolution' => $generation->resolution,
                    'duration' => $generation->duration,
                    'camera' => $camera,
                    'creative_level' => $pr['creative_level'] ?? null,
                    'adherence' => $pr['adherence'] ?? null,
                    'negative_prompt' => $pr['negative_prompt'] ?? null,
                    'base_image' => $generation->base_image,
                ],
            ]);
            logger()->info('Video generation completed', [
                'generation_id' => $generation->id, 'provider' => $generation->provider,
                'model' => $generation->model, 'total_s' => round(microtime(true) - $t0, 2),
                'elapsed_ms' => (int) round((microtime(true) - $t0) * 1000),
            ]);
        } catch (\Throwable $e) {
            $generation->update(['status' => 'failed', 'error' => $e->getMessage(), 'elapsed_ms' => (int) round((microtime(true) - $t0) * 1000)]);
            $this->refund($generation);
        }
    }

    protected function refund(Generation $generation): void
    {
        if ($generation->credits_cost > 0) {
            $generation->user?->increment('credits_balance', $generation->credits_cost);
        }
    }
}
