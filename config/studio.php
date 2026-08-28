<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Studio credits
    |--------------------------------------------------------------------------
    | Number of credits deducted per generation. Configurable so you can tune
    | the pricing for a SaaS model later.
    */
    'image_credits' => (int) env('STUDIO_IMAGE_CREDITS', 1),
    'video_credits' => (int) env('STUDIO_VIDEO_CREDITS', 10),
    'image_provider' => env('STUDIO_IMAGE_PROVIDER', 'flux'), // flux | wan | qwen
    'image_model' => env('STUDIO_IMAGE_MODEL', ''),

    /*
    |--------------------------------------------------------------------------
    | AI providers (optional)
    |--------------------------------------------------------------------------
    | Set these in .env when you have real API keys. When empty the services
    | fall back to deterministic stubs so the whole flow works offline.
    */
    'gemini_key' => env('GEMINI_API_KEY', ''),
    'fal_key' => env('FAL_KEY', ''),
    'replicate_token' => env('REPLICATE_API_TOKEN', ''),
    'wan_key' => env('WAN_API_KEY', ''),
    'veo_key' => env('GOOGLE_VEO_KEY', ''),
    'qwen_key' => env('QWEN_API_KEY', ''),
];