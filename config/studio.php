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
    'processing' => env('STUDIO_PROCESSING', 'sync'), // sync | queue (async + worker)
    'image_provider' => env('STUDIO_IMAGE_PROVIDER', 'flux'), // flux | wan | qwen

    /*
    |--------------------------------------------------------------------------
    | Models per task
    |--------------------------------------------------------------------------
    | You can override each on the Studio Settings page; the values below are
    | the defaults and are also read from env.
    */
    'prompt_model' => env('STUDIO_PROMPT_MODEL', 'gemini-1.5-flash'),
    'image_model' => env('STUDIO_IMAGE_MODEL', 'flux-1.1-schnell'),
    'wan_model' => env('STUDIO_WAN_MODEL', 'wan2.7-image-pro'),
    'qwen_model' => env('STUDIO_QWEN_MODEL', 'qwen-image'),
    'video_model' => env('STUDIO_VIDEO_MODEL', 'wan2.5-t2v'),
    'vision_model' => env('STUDIO_VISION_MODEL', 'gemini-1.5-flash'),
    'image_resolution' => env('STUDIO_IMAGE_RESOLUTION', '2K'), // 1K | 2K
    'video_resolution' => env('STUDIO_VIDEO_RESOLUTION', '720'), // 480 | 720 | 1080

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
    'dashscope_key' => env('DASHSCOPE_API_KEY', ''),
    'dashscope_base' => env('DASHSCOPE_BASE', 'https://dashscope-intl.aliyuncs.com'), // intl = quốc tế; dashscope.aliyuncs.com = Trung Quốc
];
