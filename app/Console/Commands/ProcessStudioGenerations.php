<?php

namespace App\Console\Commands;

use App\Jobs\RenderImageJob;
use App\Jobs\RenderVideoJob;
use App\Models\Generation;
use Illuminate\Console\Command;

class ProcessStudioGenerations extends Command
{
    protected $signature = 'studio:process {--limit=10}';

    protected $description = 'Process pending Studio generations manually (fallback for queue mode).';

    public function handle(): int
    {
        $pending = Generation::where('status', 'pending')
            ->limit((int) $this->option('limit'))
            ->get();

        foreach ($pending as $generation) {
            if ($generation->type === 'video') {
                RenderVideoJob::dispatchSync($generation->id);
            } else {
                RenderImageJob::dispatchSync($generation->id);
            }
            $this->info('Processed #'.$generation->id);
        }

        $this->info('Done ('.$pending->count().' processed).');

        return 0;
    }
}
