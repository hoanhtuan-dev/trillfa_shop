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
