<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for "Tinh chỉnh & Nâng cấp ảnh" (upscale):
 * the fabric-weave pass must never touch the face or detail boundaries (jagged/blurry edges),
 * while still texturing the garment interior.
 */
class StudioUpscaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::where('email', 'admin@trillfa.com')->firstOrFail());
    }

    private function ctrl(): \App\Http\Controllers\StudioController
    {
        return app(\App\Http\Controllers\StudioController::class);
    }

    private function invoke(string $method, ...$args): mixed
    {
        return (new \ReflectionMethod($this->ctrl(), $method))->invoke($this->ctrl(), ...$args);
    }

    /** Face (skin) on top, garment (mid-tone green) below, bright background in between. */
    private function makeScene(int $w = 96, int $h = 96): \GdImage
    {
        $img = imagecreatetruecolor($w, $h);
        $bg = imagecolorallocate($img, 245, 245, 245);
        imagefilledrectangle($img, 0, 0, $w - 1, $h - 1, $bg);
        // face: skin-coloured rectangle (satisfies isSkinPixel)
        imagefilledrectangle($img, 24, 8, 71, 39, imagecolorallocate($img, 228, 178, 158));
        // garment: mid-tone green (NOT skin per isSkinPixel, lum ~115, inside 70..205)
        imagefilledrectangle($img, 24, 48, 71, 87, imagecolorallocate($img, 96, 130, 104));
        return $img;
    }

    private function meanDelta(\GdImage $a, \GdImage $b, int $x0, int $y0, int $x1, int $y1): float
    {
        $sum = 0.0; $n = 0;
        for ($y = $y0; $y <= $y1; $y++) {
            for ($x = $x0; $x <= $x1; $x++) {
                $pa = imagecolorat($a, $x, $y);
                $pb = imagecolorat($b, $x, $y);
                $sum += abs((($pa >> 16) & 255) - (($pb >> 16) & 255))
                      + abs((($pa >> 8) & 255) - (($pb >> 8) & 255))
                      + abs(($pa & 255) - ($pb & 255));
                $n += 3;
            }
        }
        return $sum / max(1, $n);
    }

    public function test_fabric_pass_protects_face_and_edges_but_textures_garment(): void
    {
        $orig = $this->makeScene();
        $img = $this->makeScene();
        $skinMask = $this->invoke('buildSkinMask', $img);
        $this->invoke('fabricTexturePass', $img, 10, $skinMask);

        // 1) Face region: must stay pixel-identical (weave never bleeds onto skin).
        $this->assertSame(0.0, $this->meanDelta($orig, $img, 26, 10, 69, 37), 'face must stay clean');

        // 2) Detail-boundary band (top/left edge of the garment): must stay clean too.
        $this->assertSame(0.0, $this->meanDelta($orig, $img, 24, 48, 71, 49), 'garment top edge band must stay clean');
        $this->assertSame(0.0, $this->meanDelta($orig, $img, 24, 50, 25, 87), 'garment left edge band must stay clean');

        // 3) Garment interior (far from any boundary): weave must still be applied.
        $d = $this->meanDelta($orig, $img, 32, 56, 63, 83);
        $this->assertGreaterThan(0.2, $d, "garment interior should get weave texture (mean delta {$d})");

        imagedestroy($orig);
        imagedestroy($img);
    }

    public function test_upscale_endpoint_accepts_fabric_detail(): void
    {
        $src = $this->makeScene(64, 64);
        imagepng($src, storage_path('app/public/upscale-src.png'));
        imagedestroy($src);

        $res = $this->postJson('/studio/upscale', [
            'image' => '/storage/upscale-src.png',
            'scale' => 2,
            'photoreal' => 6,
            'skin_detail' => 8,
            'fabric_detail' => 10,
            'light_shadow' => 5,
        ]);
        $res->assertStatus(200);
        $this->assertNotEmpty($res->json('media_url'));
        $this->assertNotEmpty($res->json('generation_id'));
        $this->assertDatabaseHas('generations', ['id' => $res->json('generation_id'), 'model' => 'upscale']);

        $file = public_path(ltrim((string) parse_url($res->json('media_url'), PHP_URL_PATH), '/'));
        if (is_file($file)) { @unlink($file); }
        @unlink(storage_path('app/public/upscale-src.png'));
    }

    public function test_smart_upscale_still_resizes(): void
    {
        $src = $this->makeScene(48, 48);
        $out = $this->invoke('smartUpscale', $src, 4);
        $this->assertSame(192, imagesx($out));
        $this->assertSame(192, imagesy($out));
        imagedestroy($src);
        imagedestroy($out);
    }
}
