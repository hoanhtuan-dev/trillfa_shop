<?php

namespace Tests\Feature;

use App\Models\Generation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ✏️ Sửa ảnh (Inpaint): endpoint tạo generation pending + poll trả về terminal status.
 */
class StudioInpaintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::where('email', 'admin@trillfa.com')->firstOrFail());
    }

    private function makeSourceGen(): Generation
    {
        return auth()->user()->generations()->create([
            'type' => 'image', 'status' => 'completed',
            'prompt' => 'nguồn', 'model' => 'flux', 'provider' => 'flux',
            'media_url' => '/storage/test-src.png', 'credits_cost' => 1,
        ]);
    }

    public function test_inpaint_creates_pending_generation(): void
    {
        $src = $this->makeSourceGen();
        $res = $this->postJson('/studio/generations/'.$src->id.'/inpaint', ['prompt' => 'đổi màu áo thành đỏ']);
        $res->assertStatus(200);
        $this->assertEquals('pending', $res->json('status'));
        $this->assertNotEmpty($res->json('generation_id'));
        $this->assertDatabaseHas('generations', [
            'id' => $res->json('generation_id'),
            'type' => 'image',
            'provider' => 'qwen',
            'credits_cost' => 1,
        ]);
    }

    public function test_inpaint_requires_prompt(): void
    {
        $src = $this->makeSourceGen();
        $this->postJson('/studio/generations/'.$src->id.'/inpaint', ['prompt' => ''])->assertStatus(422);
    }

    public function test_inpaint_denies_other_users_generation(): void
    {
        $other = User::factory()->create();
        $gen = $other->generations()->create([
            'type' => 'image', 'status' => 'completed', 'prompt' => 'x',
            'model' => 'flux', 'provider' => 'flux', 'credits_cost' => 0,
        ]);
        $this->postJson('/studio/generations/'.$gen->id.'/inpaint', ['prompt' => 'test'])->assertStatus(403);
    }

    public function test_show_resolves_to_terminal_status(): void
    {
        $src = $this->makeSourceGen();
        $res = $this->postJson('/studio/generations/'.$src->id.'/inpaint', ['prompt' => 'sửa nhẹ']);
        $id = $res->json('generation_id');
        // Poll endpoint must resolve the pending job (lazy worker) to a terminal status.
        $poll = $this->getJson('/studio/generations/'.$id);
        $poll->assertStatus(200);
        $this->assertContains($poll->json('status'), ['completed', 'failed']);
        $this->assertNotNull($poll->json('media_url'));
    }
}

