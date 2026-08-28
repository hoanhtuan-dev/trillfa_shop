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
    public int $timeout = 600;

    public function __construct(public int $generationId)
    {
    }

    public function handle(VideoAIService $videos): void
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

            // Simulate the async nature of a real video provider for the demo.
            sleep(2);

            $camera = data_get($generation->promptsHistory?->json_response ?? [], 'camera', 'slow tracking');
            $url = $videos->render(
                (string) $generation->prompt,
                (string) $generation->base_image,
                (string) $camera,
            );

            $generation->update(['status' => 'completed', 'media_url' => $url]);
        } catch (\Throwable $e) {
            $generation->update(['status' => 'failed', 'error' => $e->getMessage()]);
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