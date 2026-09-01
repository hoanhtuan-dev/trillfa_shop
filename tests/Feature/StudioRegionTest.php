<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 🧹 Region Tools ("xóa theo vùng chọn trên canvas"):
 * endpoint /studio/generations/{id}/region — mask builder + AI/local + validation.
 */
class StudioRegionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::where('email', 'admin@trillfa.com')->firstOrFail());
    }

    private function makeSourceGen(): \App\Models\Generation
    {
        $img = imagecreatetruecolor(80, 80);
        for ($y = 0; $y < 80; $y++) {
            for ($x = 0; $x < 80; $x++) {
                imagesetpixel($img, $x, $y, imagecolorallocate($img, ($x * 3) % 256, ($y * 3) % 256, (($x + $y) * 2) % 256));
            }
        }
        imagepng($img, storage_path('app/public/region-src.png'));
        imagedestroy($img);

        return auth()->user()->generations()->create([
            'type' => 'image', 'status' => 'completed', 'prompt' => 'nguồn',
            'model' => 'flux', 'provider' => 'flux',
            'media_url' => '/storage/region-src.png', 'credits_cost' => 1,
        ]);
    }

    public function test_region_erase_local_completes_and_changes_image(): void
    {
        $src = $this->makeSourceGen();
        $res = $this->postJson('/studio/generations/'.$src->id.'/region', [
            'op' => 'erase', 'region' => ['x' => 0.3, 'y' => 0.3, 'w' => 0.4, 'h' => 0.4],
        ]);
        $res->assertStatus(200);
        $this->assertEquals('completed', $res->json('status'));
        $this->assertNotEmpty($res->json('media_url'));
        $this->assertDatabaseHas('generations', ['id' => $res->json('generation_id'), 'model' => 'erase', 'provider' => 'local']);

        $out = public_path(ltrim((string) parse_url($res->json('media_url'), PHP_URL_PATH), '/'));
        $this->assertFileExists($out);
        $this->assertNotEquals(md5_file(storage_path('app/public/region-src.png')), md5_file($out));
        // output phải là ảnh hợp lệ cùng kích thước
        $outImg = @imagecreatefrompng($out);
        $this->assertNotFalse($outImg);
        $this->assertSame(80, imagesx($outImg));
        imagedestroy($outImg);

        @unlink($out);
        @unlink(storage_path('app/public/region-src.png'));
    }

    public function test_region_rejects_unknown_op(): void
    {
        $src = $this->makeSourceGen();
        $this->postJson('/studio/generations/'.$src->id.'/region', ['op' => 'explode', 'region' => ['x' => 0.1, 'y' => 0.1, 'w' => 0.3, 'h' => 0.3]])->assertStatus(422);
        @unlink(storage_path('app/public/region-src.png'));
    }

    public function test_region_replace_requires_prompt(): void
    {
        $src = $this->makeSourceGen();
        $this->postJson('/studio/generations/'.$src->id.'/region', ['op' => 'replace', 'region' => ['x' => 0.1, 'y' => 0.1, 'w' => 0.3, 'h' => 0.3]])->assertStatus(422);
        @unlink(storage_path('app/public/region-src.png'));
    }

    public function test_region_rejects_tiny_selection(): void
    {
        $src = $this->makeSourceGen();
        $this->postJson('/studio/generations/'.$src->id.'/region', ['op' => 'erase', 'region' => ['x' => 0.5, 'y' => 0.5, 'w' => 0.001, 'h' => 0.001]])->assertStatus(422);
        @unlink(storage_path('app/public/region-src.png'));
    }

    public function test_region_denies_other_users_generation(): void
    {
        $other = User::factory()->create();
        $gen = $other->generations()->create([
            'type' => 'image', 'status' => 'completed', 'prompt' => 'x',
            'model' => 'flux', 'provider' => 'flux', 'media_url' => '/storage/none.png', 'credits_cost' => 0,
        ]);
        $this->postJson('/studio/generations/'.$gen->id.'/region', ['op' => 'erase', 'region' => ['x' => 0.1, 'y' => 0.1, 'w' => 0.3, 'h' => 0.3]])->assertStatus(403);
    }
}

