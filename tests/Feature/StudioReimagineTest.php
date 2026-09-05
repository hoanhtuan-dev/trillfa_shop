<?php

namespace Tests\Feature;

use App\Models\Generation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reimagine (Render đa góc / Tạo lại ảnh): endpoint tạo generation pending và tôn trọng
 * model chỉnh sửa do người dùng chọn trên card Sửa ảnh.
 */
class StudioReimagineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::where('email', 'admin@trillfa.com')->firstOrFail());
    }

    private function sourceUrl(): string
    {
        return '/storage/test-src.png';
    }

    public function test_reimagine_creates_pending_generation(): void
    {
        $res = $this->postJson('/studio/reimagine', [
            'image' => $this->sourceUrl(),
            'prompt' => 'full-body front view',
            'similarity' => 85,
            'variants' => 1,
        ]);
        $res->assertStatus(200);
        $this->assertNotEmpty($res->json('items'));
        $this->assertNotEmpty($res->json('items.0.generation_id'));
    }

    public function test_render_da_goc_creates_one_generation_per_angle(): void
    {
        // Giả lập vòng lặp runMultiView: 4 lượt reimagine (variants=1, không process).
        $angles = ['front view', 'back view', 'side view', 'detail view'];
        foreach ($angles as $v) {
            $this->postJson('/studio/reimagine', [
                'image' => $this->sourceUrl(),
                'prompt' => 'render this fashion product at a new camera angle — '.$v,
                'similarity' => 85,
                'variants' => 1,
            ])->assertStatus(200);
        }

        $count = Generation::where('user_id', auth()->id())
            ->where('prompt', 'like', '%new camera angle%')
            ->count();

        // 4 góc -> đúng 4 generation (không trùng lặp do xử lý nhiều lần).
        $this->assertSame(4, $count);
    }

    public function test_reimagine_honors_selected_edit_capable_model(): void
    {
        $res = $this->postJson('/studio/reimagine', [
            'image' => $this->sourceUrl(),
            'prompt' => 'render at new angle',
            'similarity' => 85,
            'variants' => 1,
            'provider' => 'qwen',
            'model' => 'qwen-image-3.0-pro',
        ]);
        $res->assertStatus(200);
        $this->assertSame('qwen-image-3.0-pro', $res->json('items.0.model'));
        $this->assertSame('qwen', $res->json('items.0.provider'));
    }
}
