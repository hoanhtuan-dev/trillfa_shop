<?php

namespace App\Jobs;

use App\Services\ProductAIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Runs the product-content AI suggestion (vision + text) in a background queue
 * worker so the admin request never blocks on (potentially slow / rate-limited)
 * LLM calls on shared hosting — avoiding gateway 504 timeouts.
 */
class GenerateProductSuggestion implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    public function __construct(
        public string $token,
        public array $input,
        public ?string $imagePath = null,
        public bool $force = false,
    ) {
    }

    public function handle(ProductAIService $service): void
    {
        @set_time_limit(300);

        if ($this->imagePath && is_file($this->imagePath)) {
            $result = $service->generateFromImage($this->input, $this->imagePath, $this->force);
        } else {
            $result = $service->generate($this->input, null);
        }
        $result['source'] ??= 'stub';

        Cache::put('product_ai:'.$this->token, $result, 600);
    }
}
