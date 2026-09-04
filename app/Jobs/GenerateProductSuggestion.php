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

        $understanding = null;
        if ($this->imagePath && is_file($this->imagePath)) {
            $understanding = $service->analyzeImage($this->imagePath, $this->force);
        }

        $result = $service->generate($this->input, $understanding);
        $result['image_analyzed'] = $understanding !== null;
        $result['analysis_cached'] = $this->imagePath
            ? Cache::has('product_ai_img:'.sha1_file($this->imagePath).'|'.(string) studio_config('qwen_prompt_model', 'qwen3.8-flash'))
            : false;
        $result['source'] ??= 'stub';

        Cache::put('product_ai:'.$this->token, $result, 600);
    }
}
