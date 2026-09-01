<?php

namespace App\Jobs;

use App\Http\Controllers\StudioController;
use App\Models\Generation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * "Thay Đổi Người Mẫu" runs the long AI pipeline (try-on + optional face-swap, ~2-3 min) in the
 * background queue so the HTTP request returns immediately — long synchronous requests get cut by
 * the hosting proxy timeout. The worker processes this job and updates the generation row.
 */
class SwapModelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    /** 10 min — the try-on + face-swap passes can each take ~1 min plus retries/backoffs. */
    public int $timeout = 600;

    public function __construct(public int $generationId) {}

    public function handle(): void
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

            app(StudioController::class)->executeSwapFromGeneration($generation);

            logger()->info('Swap job completed', [
                'generation_id' => $this->generationId,
                'status' => $generation->fresh()->status,
                'total_s' => round(microtime(true) - $t0, 2),
            ]);
        } catch (\Throwable $e) {
            logger()->error('Swap job failed', ['generation_id' => $this->generationId, 'error' => $e->getMessage()]);
            $generation->update(['status' => 'failed', 'error' => $e->getMessage()]);
        }
    }
}
