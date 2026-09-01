<?php

namespace Tests\Feature;

use App\Services\ImageAIService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImageFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_qwen_key_failure_falls_back_to_gemini(): void
    {
        config(['studio.image_provider' => 'qwen']);
        config(['studio.qwen_key' => 'sk-bad-key']);
        config(['studio.gemini_key' => 'gem-good-key']);

        Http::fake([
            'dashscope-intl.aliyuncs.com*' => Http::response(['error' => ['message' => 'InvalidApiKey']], 401),
            'token-plan*' => Http::response(['error' => ['message' => 'InvalidApiKey']], 401),
            'generativelanguage.googleapis.com*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [
                        ['inlineData' => ['mimeType' => 'image/png', 'data' => base64_encode('fake-image-bytes')]],
                    ]],
                ]],
            ], 200),
        ]);

        $service = app(ImageAIService::class);
        $url = $service->generate('photo of a red silk dress', null, null, null, null);

        $this->assertIsString($url);
        $this->assertStringStartsWith('/storage/studio/', $url);
        $this->assertSame('gemini', $service->lastProvider());
        $this->assertSame('gemini-2.5-flash-image', $service->lastModel());

        Storage::disk('public')->assertExists(str_replace('/storage/', '', $url));
    }

    public function test_all_providers_fail_throws_clear_message(): void
    {
        config(['studio.image_provider' => 'qwen']);
        config(['studio.qwen_key' => 'sk-bad-key']);
        config(['studio.gemini_key' => null]);

        Http::fake([
            'dashscope-intl.aliyuncs.com*' => Http::response(['error' => ['message' => 'InvalidApiKey']], 401),
            'token-plan*' => Http::response(['error' => ['message' => 'InvalidApiKey']], 401),
        ]);

        $service = app(ImageAIService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/InvalidApiKey|Khoá API|Pay-As-You-Go/');

        $service->generate('photo of a red silk dress', null, null, null, null);
    }
}
