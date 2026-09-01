<?php

namespace Tests\Feature;

use App\Models\StudioAsset;
use App\Services\VirtualTryOnService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudioSwapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_pick_model_pose_support_custom_assets(): void
    {
        $asset = StudioAsset::create(['type' => 'model', 'name' => 'Mặt riêng A', 'path' => '/storage/custom-face.png', 'sort' => 0]);
        $pose = StudioAsset::create(['type' => 'pose', 'name' => 'Dáng riêng B', 'path' => '/storage/custom-pose.png', 'sort' => 0]);

        $svc = app(VirtualTryOnService::class);

        // Custom assets are resolved by id (NOT silently replaced by catalog[0]).
        $picked = $svc->pickModel((string) $asset->id);
        $this->assertNotNull($picked);
        $this->assertSame('Mặt riêng A', $picked['name']);
        $this->assertSame('/storage/custom-face.png', $picked['image']);

        $pickedPose = $svc->pickPose((string) $pose->id);
        $this->assertNotNull($pickedPose);
        $this->assertSame('Dáng riêng B', $pickedPose['name']);
        $this->assertSame('/storage/custom-pose.png', $pickedPose['image']);

        // Catalog ids still resolve.
        $this->assertSame('model01', $svc->pickModel('model01')['id'] ?? null);
        $this->assertSame('pose01', $svc->pickPose('pose01')['id'] ?? null);
    }

    public function test_pick_returns_null_for_unknown_id(): void
    {
        $svc = app(VirtualTryOnService::class);
        $this->assertNull($svc->pickModel('fake-model-id'));
        $this->assertNull($svc->pickPose('fake-pose-id'));
    }

    public function test_swap_model_requires_both_face_and_pose(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $this->actingAs($admin);

        // Missing pose -> 422 validation (P2: pose is required).
        $this->postJson('/studio/swap-model', [
            'image' => '/storage/design.png', 'model_id' => 'model01', 'pose_id' => '',
        ])->assertStatus(422);
    }
}
