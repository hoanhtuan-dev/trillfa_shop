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
        $this->seed();
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

    public function test_model_check_reports_the_key_generation_actually_uses(): void
    {
        \App\Models\StudioModel::create([
            'group' => 'image', 'name' => 'Qwen Image 3.0 Pro', 'provider' => 'qwen',
            'model_id' => 'qwen-image-3.0-pro', 'api_key_ref' => 'qwen', 'priority' => 8, 'enabled' => true,
        ]);
        // A Token/Coding Plan key registered with HIGHER priority than the Pay-As-You-Go key.
        \App\Models\StudioApiKey::create([
            'provider' => 'qwen', 'label' => 'plan', 'value' => 'sk-sp-TESTPLANKEY', 'priority' => 10, 'enabled' => true, 'scopes' => ['*'],
        ]);
        \App\Models\StudioApiKey::create([
            'provider' => 'qwen', 'label' => 'paygo', 'value' => 'sk-ws-TESTPAYGO', 'priority' => 5, 'enabled' => true, 'scopes' => ['*'],
        ]);

        $admin = \App\Models\User::where('email', 'admin@trillfa.com')->first();
        $this->actingAs($admin);

        $model = \App\Models\StudioModel::first();
        $res = $this->getJson('/studio/models/'.$model->id.'/test')->assertOk()->json();

        // The check must report the Pay-As-You-Go key (what image generation actually uses), NOT the plan key.
        $this->assertSame('qwen', $res['provider']);
        $this->assertSame('qwen-image-3.0-pro', $res['model_id']);
        $this->assertStringStartsWith('sk-ws', (string) $res['key_prefix']);
        $this->assertStringContainsString('dashscope-intl', (string) $res['base_url']);
        $this->assertStringContainsString('Pay-As-You-Go', (string) $res['note']);
    }

    public function test_model_check_warns_when_only_plan_key_registered(): void
    {
        \App\Models\StudioModel::create([
            'group' => 'image', 'name' => 'Qwen Image 3.0 Pro', 'provider' => 'qwen',
            'model_id' => 'qwen-image-3.0-pro', 'api_key_ref' => 'qwen', 'priority' => 8, 'enabled' => true,
        ]);
        // Only a Token/Coding Plan key registered.
        \App\Models\StudioApiKey::create([
            'provider' => 'qwen', 'label' => 'plan', 'value' => 'sk-sp-TESTPLANKEY', 'priority' => 10, 'enabled' => true, 'scopes' => ['*'],
        ]);

        $admin = \App\Models\User::where('email', 'admin@trillfa.com')->first();
        $this->actingAs($admin);

        $model = \App\Models\StudioModel::first();
        $res = $this->getJson('/studio/models/'.$model->id.'/test')->assertOk()->json();

        $this->assertStringStartsWith('sk-sp', (string) $res['key_prefix']);
        $this->assertStringContainsString('token-plan', (string) $res['base_url']);
        $this->assertStringContainsString('Token/Coding Plan', (string) $res['note']);
    }
}
