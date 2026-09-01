<?php

namespace App\Services;

/**
 * "Thay Đổi Người Mẫu" (Click-to-Swap).
 *
 * The DashScope virtual-try-on endpoints are NOT available on this account (they always return
 * "Model not exist"), so the whole swap is driven by the Qwen image-edit model (qwen-image-edit /
 * qwen-image-edit-plus…): we re-render the person in the design image to match the chosen model
 * face, keeping the garment 100% unchanged. Optional inputs: background, pose reference.
 */
class VirtualTryOnService
{
    /** The image model that actually produced the last swap (may differ from the configured swap_model). */
    public ?string $lastModel = null;

    /** Number of real model calls made for the last swap (2 for a face-ref 2-step swap, 1 otherwise). */
    protected int $calls = 0;

    public function lastModel(): ?string
    {
        return $this->lastModel;
    }

    public function calls(): int
    {
        return $this->calls;
    }

    public function modelCatalog(): array
    {
        // Manageable face presets (DB) — the swap picker + backend both read from here, so presets can
        // be added/edited in Studio Settings. When the table is empty we fall back to built-in presets.
        $db = \App\Models\FacePreset::where('enabled', true)->orderBy('sort')->orderBy('id')->get();
        if ($db->isNotEmpty()) {
            return $db->map(function ($p) {
                return [
                    'id' => 'fp'.$p->id,
                    'name' => $p->name,
                    'ethnicity' => $p->ethnicity ?: 'Vietnamese female',
                    'image' => $p->image, // may be null (text-only preset)
                    'desc' => $p->description,
                    'preset' => true,
                ];
            })->values()->all();
        }

        return $this->builtinFacePresets();
    }

    /**
     * Built-in fallback presets (10 young Vietnamese female looks) — used only when the DB is empty.
     */
    public function builtinFacePresets(): array
    {
        return [
            ['id' => 'vp01', 'name' => 'Nhẹ nhàng tự nhiên', 'ethnicity' => 'Vietnamese female', 'image' => '/storage/studio/khuon-mat/model-01.png', 'desc' => 'young Vietnamese woman, 22, light natural everyday makeup, shoulder-length straight black hair, fair skin, gentle warm smile, soft feminine features'],
            ['id' => 'vp02', 'name' => 'Tóc dài lượn sóng', 'ethnicity' => 'Vietnamese female', 'image' => '/storage/studio/khuon-mat/model-02.png', 'desc' => 'young Vietnamese woman, 24, long wavy black hair with soft curls, radiant clear skin, subtle Korean-style makeup, elegant and graceful'],
            ['id' => 'vp03', 'name' => 'Cá tính tóc bob', 'ethnicity' => 'Vietnamese female', 'image' => '/storage/studio/khuon-mat/model-03.png', 'desc' => 'young Vietnamese woman, 23, chic short black bob haircut, bold natural lipstick, confident modern look, sharp jawline, almond eyes'],
            ['id' => 'vp04', 'name' => 'Thanh lịch tóc búi', 'ethnicity' => 'Vietnamese female', 'image' => '/storage/studio/khuon-mat/model-04.png', 'desc' => 'young Vietnamese woman, 25, elegant low bun hairstyle, minimalist makeup, classic Vietnamese beauty, refined and sophisticated'],
            ['id' => 'vp05', 'name' => 'Năng động tóc đuôi ngựa', 'ethnicity' => 'Vietnamese female', 'image' => '/storage/studio/khuon-mat/model-05.png', 'desc' => 'young Vietnamese woman, 22, high ponytail, fresh sporty energetic look, clear glowing skin, bright happy smile, youthful'],
            ['id' => 'vp06', 'name' => 'Ngọt ngào tóc xoăn', 'ethnicity' => 'Vietnamese female', 'image' => '/storage/studio/khuon-mat/model-06.png', 'desc' => 'young Vietnamese woman, 21, soft loose curls, sweet innocent face, rosy cheeks, gentle dreamy eyes, cute and charming'],
            ['id' => 'vp07', 'name' => 'Sang trọng mái lệch', 'ethnicity' => 'Vietnamese female', 'image' => null, 'desc' => 'young Vietnamese woman, 26, side-swept bangs, sophisticated editorial makeup, elegant high-fashion look, striking features'],
            ['id' => 'vp08', 'name' => 'Thời trang mắt khói', 'ethnicity' => 'Vietnamese female', 'image' => null, 'desc' => 'young Vietnamese woman, 24, sleek center-parted straight hair, trendy smoky-eye makeup, fashion-forward street style, confident gaze'],
            ['id' => 'vp09', 'name' => 'Dịu dàng tóc đen thẳng', 'ethnicity' => 'Vietnamese female', 'image' => null, 'desc' => 'young Vietnamese woman, 23, long straight jet-black hair, fresh natural no-makeup look, serene calm expression, classic beauty'],
            ['id' => 'vp10', 'name' => 'Hiện đại mái ngố', 'ethnicity' => 'Vietnamese female', 'image' => null, 'desc' => 'young Vietnamese woman, 22, modern curtain bangs, fresh glass-skin makeup, trendy K-pop inspired look, sparkling eyes'],
        ];
    }

    /**
     * Fall back to the built-in sample photo for a DB face preset that has no image, so the face
     * reference is still sent to the edit model (face fidelity from text alone is poor).
     */
    protected function builtinFaceImageByName(?string $name): ?string
    {
        foreach ($this->builtinFacePresets() as $bp) {
            if (($bp['name'] ?? '') === $name) {
                return $bp['image'] ?? null;
            }
        }
        return null;
    }

    public function poseCatalog(): array
    {
        // Manageable pose presets (DB) — read by the swap picker + backend. Falls back to built-ins.
        $db = \App\Models\PosePreset::where('enabled', true)->orderBy('sort')->orderBy('id')->get();
        if ($db->isNotEmpty()) {
            return $db->map(function ($p) {
                return [
                    'id' => 'pp'.$p->id,
                    'name' => $p->name,
                    'skeleton' => $p->description,
                    'image' => $p->image ?: $this->builtinPoseImageByName($p->name),
                    'preset' => true,
                ];
            })->values()->all();
        }

        return $this->builtinPosePresets();
    }

    /**
     * Fall back to the built-in sample image for a DB pose preset that has no image, so the pose
     * reference is still sent to the edit model (a pose described only in text is rarely applied).
     */
    protected function builtinPoseImageByName(?string $name): ?string
    {
        foreach ($this->builtinPosePresets() as $bp) {
            if (($bp['name'] ?? '') === $name) {
                return $bp['image'] ?? null;
            }
        }
        return null;
    }

    public function builtinPosePresets(): array
    {
        return [
            ['id' => 'pose01', 'name' => 'Đứng thẳng', 'skeleton' => 'standing straight, arms relaxed, full body', 'image' => '/storage/studio/dang-nguoi-mau/pose-01.png'],
            ['id' => 'pose02', 'name' => 'Tay chống hông', 'skeleton' => 'standing, one hand on hip, one leg crossed', 'image' => '/storage/studio/dang-nguoi-mau/pose-02.png'],
            ['id' => 'pose03', 'name' => 'Hai tay chống hông', 'skeleton' => 'standing, both hands on hips', 'image' => '/storage/studio/dang-nguoi-mau/pose-03.png'],
            ['id' => 'pose04', 'name' => 'Chống hông chéo chân', 'skeleton' => 'standing, hands on hips, legs crossed', 'image' => '/storage/studio/dang-nguoi-mau/pose-04.png'],
            ['id' => 'pose05', 'name' => 'Chống hông (trắng-đen)', 'skeleton' => 'standing, both hands on hips', 'image' => '/storage/studio/dang-nguoi-mau/pose-05.png'],
            ['id' => 'pose06', 'name' => 'Ngồi ghế', 'skeleton' => 'sitting on a high stool, one leg extended', 'image' => '/storage/studio/dang-nguoi-mau/pose-06.png'],
            ['id' => 'pose07', 'name' => 'Tay đút túi', 'skeleton' => 'side view, hand in pocket, relaxed', 'image' => '/storage/studio/dang-nguoi-mau/pose-07.png'],
            ['id' => 'pose08', 'name' => 'Ngồi xổm', 'skeleton' => 'stylish squat pose, knees apart', 'image' => '/storage/studio/dang-nguoi-mau/pose-08.png'],
            ['id' => 'pose09', 'name' => 'Sải bước', 'skeleton' => 'walking mid-stride catwalk, hand on hip', 'image' => '/storage/studio/dang-nguoi-mau/pose-09.png'],
            ['id' => 'pose10', 'name' => 'Xoay lưng', 'skeleton' => 'back view, turned away', 'image' => '/storage/studio/dang-nguoi-mau/pose-10.png'],
            ['id' => 'pose11', 'name' => 'Tựa ghế', 'skeleton' => 'leaning on a stool, hand to head', 'image' => '/storage/studio/dang-nguoi-mau/pose-11.png'],
            ['id' => 'pose12', 'name' => 'Bước ngang', 'skeleton' => 'walking, side profile, dynamic', 'image' => '/storage/studio/dang-nguoi-mau/pose-12.png'],
        ];
    }

    /**
     * Resolve a model by id. Custom faces added in /studio/assets (type=model) are looked up first,
     * so a personally-added face actually works (not silently replaced by catalog[0]).
     */
    public function pickModel(string $id): ?array
    {
        // Manageable DB preset (id like "fp3").
        if (str_starts_with($id, 'fp')) {
            $p = \App\Models\FacePreset::find((int) substr($id, 2));
            if ($p) {
                return [
                    'id' => 'fp'.$p->id,
                    'name' => $p->name,
                    'image' => $p->image ?: $this->builtinFaceImageByName($p->name),
                    'ethnicity' => $p->ethnicity ?: 'Vietnamese female',
                    'desc' => $p->description,
                    'preset' => true,
                ];
            }
        }
        // Custom asset (added via the swap card's "➕ Thêm khuôn mặt").
        if ($id !== '' && is_numeric($id)) {
            $asset = \App\Models\StudioAsset::where('type', 'model')->where('id', $id)->first();
            if ($asset) {
                return [
                    'id' => (string) $asset->id,
                    'name' => $asset->name ?: 'model',
                    'image' => $asset->path,
                    'ethnicity' => '',
                    'desc' => 'a person matching the provided reference photo',
                ];
            }
        }
        // Built-in presets (vp01..vp10).
        foreach ($this->builtinFacePresets() as $m) {
            if (($m['id'] ?? '') === $id) {
                return $m;
            }
        }
        return null;
    }

    /**
     * Resolve a pose by id. Custom poses added in /studio/assets (type=pose) are looked up first.
     */
    public function pickPose(string $id): ?array
    {
        // Manageable DB preset (id like "pp3").
        if (str_starts_with($id, 'pp')) {
            $p = \App\Models\PosePreset::find((int) substr($id, 2));
            if ($p) {
                return [
                    'id' => 'pp'.$p->id,
                    'name' => $p->name,
                    'skeleton' => $p->description,
                    'image' => $p->image ?: $this->builtinPoseImageByName($p->name),
                    'preset' => true,
                ];
            }
        }
        // Custom asset (added via the swap card's "➕ Thêm dáng").
        if ($id !== '' && is_numeric($id)) {
            $asset = \App\Models\StudioAsset::where('type', 'pose')->where('id', $id)->first();
            if ($asset) {
                return [
                    'id' => (string) $asset->id,
                    'name' => $asset->name ?: 'pose',
                    'image' => $asset->path,
                    'skeleton' => ($asset->name ?: 'in a confident pose'),
                ];
            }
        }
        foreach ($this->builtinPosePresets() as $p) {
            if (($p['id'] ?? '') === $id) {
                return $p;
            }
        }
        return null;
    }

    /**
     * Swap via a SINGLE-pass, TEXT-DRIVEN Qwen image-edit call.
     *
     * The face and pose are described in WORDS only (modelDesc + pose skeleton). The face/pose
     * reference IMAGES are intentionally NOT sent to the model — they stay in the picker as visual
     * labels. Deep-evaluation finding: sending full-body reference photos confuses the qwen edit
     * models (several human bodies in one request) and makes them IGNORE the pose; the earlier
     * text-driven version demonstrably produced the correct pose, and it broke exactly when the
     * reference images were introduced. $tone is prompt-level.
     */
    public function fallbackEdit(string $designImage, string $modelDesc, string $pose, string $background = '', ?string $faceRefUrl = null, string $tone = 'none', ?string $poseRefUrl = null): ?string
    {
        $swapModel = (string) studio_config('swap_model', 'qwen-image-edit-plus-2025-12-15');
        $this->calls = 1;
        $this->lastModel = null;

        // Fixed body-proportion descriptor at the default build=6 (the slider was removed; this is the
        // EXACT clause from the confirmed-good version where the pose was applied correctly).
        $proportion = $this->buildEnglish(6);
        $wantBg = $background && strtolower($background) !== 'keep' && strtolower($background) !== 'original';
        $toneS = $this->toneInstruction($tone, $background);

        // Garment = PRODUCT to preserve EXACTLY (dominant directive), then replace the person with
        // the text-described model in the text-described pose. Single image + clear text = reliable.
        // A background-replacement clause is intentionally NOT here: inside the same call it competes
        // with the pose instruction — with a complex background the model redraws the scene and
        // IGNORES the pose (simple/white backgrounds don't trigger this because they are trivial to
        // regenerate). The background is applied in a separate PASS 2 below.
        $instr = 'The garment worn by the person in the image is the PRODUCT of this edit: it must appear in the result EXACTLY as it is — identical garment, same colors, patterns, prints, seams, folds, silhouette, length and fabric. Do NOT redesign, replace, reimagine or restyle the outfit; never change its colors or pattern. '
            .'Replace the person in the image with a full-body '.$modelDesc.' in the pose: '.$pose.', keeping the EXACT garment unchanged. '
            .'Render a vertically-balanced figure: '.$proportion.', with natural, elongated model proportions (long legs, about 1:7.5 head-to-body) — do NOT make the figure short, squat, compressed or stubby. '
            .'Keep the background of the original image unchanged. '
            .($wantBg ? '' : $toneS).' Photorealistic, full body, studio quality, high fashion, consistent lighting.';

        $imageSvc = app(ImageAIService::class);
        $url = $imageSvc->swapEdit($instr, $designImage, $swapModel, null, null);
        if (! $url) {
            return null;
        }
        $this->lastModel = $imageSvc->lastModel() ?: $swapModel;

        // PASS 2 — replace ONLY the background (person / pose / garment untouched). CRITICAL: never
        // say "consistent lighting" here — with a dark background the model darkens the person into a
        // silhouette. Explicitly force the person to keep their original, fully-lit exposure.
        if ($wantBg) {
            $bgInstr = 'Replace the ENTIRE background of the scene with: '.$background.'. '
                .'Keep the person, their pose, the garment and body shape 100% unchanged. '
                .'Do NOT change the person brightness, exposure or lighting — the person must keep their original fully-lit look and stay clearly visible; do NOT darken or shade them into a silhouette to match the new background. '
                .'Frame the person at about 75-80% of the image height, with the background clearly visible all around them: pull the camera back a little and place the person slightly deeper into the scene so they are naturally scaled, not oversized or dominating the frame. '
                .'Blend the person into the scene: the HAIR and its edges, the clothing silhouette and the body outline must merge naturally with the background — NO hard cut-out outline, halo, white fringe or aliasing around the hair; soften the hair edge so it melts into the flowers, wall and light behind it. '
                .'Unify the color grading, warmth and lighting of the person and the new background so they blend into ONE cohesive photograph with no visible seam, halo or separation between the person and the scene. '
                .$toneS.' Photorealistic.';
            $final = $imageSvc->swapEdit($bgInstr, $url, $swapModel, null, null);
            if ($final) {
                $this->calls = 2;
                $this->lastModel = $imageSvc->lastModel() ?: $swapModel;
                logger()->info('Swap 2-pass done (person+pose, then background)');
                return $final;
            }
            logger()->warning('Swap background pass failed; keeping the person-swap result');
            $this->calls = 1;
        }

        return $url;
    }

    /**
     * Body-proportion descriptor (fixed at the default build=6 — the UI slider was removed, the
     * clause stays because it was part of the confirmed-good prompt).
     */
    protected function buildEnglish(int $v): string
    {
        return match (true) {
            $v >= 9 => 'tall, slender runway-model build with long legs and an ~1:8 head-to-body ratio',
            $v >= 7 => 'tall, slim fashion-model build with long legs and an ~1:7.5 head-to-body ratio',
            $v >= 5 => 'average height, slim fitness-model build with an ~1:7 head-to-body ratio',
            $v >= 3 => 'slightly shorter, curvy/athletic build with an ~1:6.5 head-to-body ratio',
            default => 'shorter, fuller curvy build with an ~1:6 head-to-body ratio',
        };
    }

    /**
     * Color-tone instruction for the "Tông màu" effect. "auto" picks a grade that matches the chosen
     * background (dark/moody -> dramatic, street/urban -> warm, beige/neutral studio -> warm, else none).
     */
    protected function toneInstruction(string $tone, string $background = ''): string
    {
        $tone = strtolower(trim($tone));
        if (in_array($tone, ['none', ''], true)) {
            return '';
        }
        if ($tone === 'auto') {
            $tone = $this->autoToneForBackground($background);
            if ($tone === 'none') { return ''; }
        }
        return match ($tone) {
            'warm' => ' Color-grade the whole image with a warm, golden studio light that suits the scene.',
            'cool' => ' Color-grade the whole image with a cool, clean, soft tone that suits the scene.',
            'film' => ' Apply a gentle vintage film color grade: soft warm highlights, muted mid-tones, fine grain — keep the exposure balanced so highlights are NOT blown out.',
            'cinematic' => ' Apply a cinematic movie color grade: warm golden highlights with teal shadows, smooth contrast and rich color — like a feature film frame.',
            'dramatic' => ' Apply a dramatic, moody high-contrast color grade with deeper shadows that suits the scene.',
            'mono' => ' Render the final image in elegant black-and-white monochrome with soft contrast.',
            default => '',
        };
    }

    protected function autoToneForBackground(string $background): string
    {
        $b = strtolower((string) $background);
        if ($b === '' || in_array($b, ['keep', 'original'], true)) { return 'none'; }
        if (str_contains($b, 'dark') || str_contains($b, 'moody') || str_contains($b, 'tối') || str_contains($b, 'đêm')) { return 'dramatic'; }
        if (str_contains($b, 'street') || str_contains($b, 'urban') || str_contains($b, 'đường') || str_contains($b, 'phố')) { return 'warm'; }
        if (str_contains($b, 'beige') || str_contains($b, 'neutral') || str_contains($b, 'warm') || str_contains($b, 'cream') || str_contains($b, 'seamless')) { return 'warm'; }
        if (str_contains($b, 'white') || str_contains($b, 'trắng')) { return 'cool'; }
        return 'none';
    }

}
