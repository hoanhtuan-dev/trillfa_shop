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

    public function test_model_check_uses_paygo_key_even_when_plan_has_higher_priority(): void
    {
        config(['studio.image_provider' => 'qwen']);
        \App\Models\StudioModel::create([
            'group' => 'image', 'name' => 'Qwen Image 3.0 Pro', 'provider' => 'qwen',
            'model_id' => 'qwen-image-3.0-pro', 'api_key_ref' => 'qwen', 'priority' => 8, 'enabled' => true,
        ]);
        // The Token/Coding-Plan key has the HIGHER priority, but it is FORBIDDEN for image generation
        // (plan host has no image model), so the check must still pick the Pay-As-You-Go key.
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

        // The checked model's key must be the Pay-As-You-Go one, NOT the higher-priority plan key.
        $this->assertSame('qwen', $res['provider']);
        $this->assertSame('qwen-image-3.0-pro', $res['model_id']);
        $this->assertStringStartsWith('sk-ws', (string) $res['key_prefix']);
        $this->assertStringContainsString('dashscope-intl', (string) $res['base_url']);
        $this->assertStringStartsWith('sk-ws', (string) $res['keys'][0]);
    }

    public function test_model_check_reports_no_usable_key_when_only_plan_registered(): void
    {
        config(['studio.image_provider' => 'qwen']);
        \App\Models\StudioModel::create([
            'group' => 'image', 'name' => 'Qwen Image 3.0 Pro', 'provider' => 'qwen',
            'model_id' => 'qwen-image-3.0-pro', 'api_key_ref' => 'qwen', 'priority' => 8, 'enabled' => true,
        ]);
        // Only a Token/Coding Plan key exists -> not usable for image, so no key is selected.
        \App\Models\StudioApiKey::create([
            'provider' => 'qwen', 'label' => 'plan', 'value' => 'sk-sp-TESTPLANKEY', 'priority' => 10, 'enabled' => true, 'scopes' => ['*'],
        ]);

        $admin = \App\Models\User::where('email', 'admin@trillfa.com')->first();
        $this->actingAs($admin);

        $model = \App\Models\StudioModel::first();
        $res = $this->getJson('/studio/models/'.$model->id.'/test')->assertOk()->json();

        $this->assertNull($res['key_prefix']);
        $this->assertSame('', (string) $res['base_url']);
        $this->assertStringContainsString('Chưa có KEY', (string) $res['note']);
    }

    public function test_inpaint_uses_requested_edit_capable_image_model(): void
    {
        // Sửa ảnh với model tạo ảnh qwen-image-3.0-pro (do người dùng chọn) phải gửi ĐÚNG
        // model đó tới DashScope thay vì luôn dùng qwen_edit_model cấu hình.
        config(['studio.image_provider' => 'qwen']);
        config(['studio.qwen_key' => 'sk-ws-test-paygo']);

        // Ảnh nguồn + ảnh "kết quả từ provider" phải tồn tại trên đĩa (imageDataUri /
        // storeRemoteImage đọc qua filesystem thật, không qua Http fake).
        $srcRel = 'studio/edit-src-'.uniqid().'.png';
        $srcAbs = storage_path('app/public/'.$srcRel);
        $outAbs = storage_path('app/public/studio/edit-out-'.uniqid().'.png');
        foreach ([$srcAbs, $outAbs] as $abs) {
            if (! is_dir(dirname($abs))) {
                mkdir(dirname($abs), 0777, true);
            }
            $im = imagecreatetruecolor(64, 64);
            imagepng($im, $abs);
            imagedestroy($im);
        }

        try {
            Http::fake([
                'dashscope-intl.aliyuncs.com*' => Http::response([
                    'output' => ['choices' => [['message' => ['content' => [
                        ['image' => 'file://'.$outAbs],
                    ]]]]],
                ], 200),
            ]);

            $service = app(ImageAIService::class);
            $url = $service->generate(
                'change the dress color to red',
                '/storage/'.$srcRel,
                null, null, null, null,
                'qwen', 'qwen-image-3.0-pro',
            );

            $this->assertIsString($url);
            $this->assertSame('qwen-image-3.0-pro', $service->lastModel());
            Http::assertSent(function ($request) {
                return str_contains($request->url(), 'multimodal-generation/generation')
                    && $request['model'] === 'qwen-image-3.0-pro';
            });
        } finally {
            foreach ([$srcAbs, $outAbs] as $abs) {
                if (is_file($abs)) {
                    @unlink($abs);
                }
            }
        }
    }
}
