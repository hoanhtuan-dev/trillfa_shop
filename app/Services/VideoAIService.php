<?php

namespace App\Services;

/**
 * Video catwalk render (Wan AI / Google Veo).
 * Stub: returns the bundled demo MP4 URL. Swap in a real provider call when
 * WAN_API_KEY / GOOGLE_VEO_KEY is configured.
 */
class VideoAIService
{
    public function render(string $prompt, string $imageUrl, string $cameraPreset): string
    {
        return '/samples/studio-catwalk.mp4';
    }
}