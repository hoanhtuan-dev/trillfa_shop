<?php

namespace App\Services;

/**
 * "Thay Đổi Người Mẫu" (Click-to-Swap).
 *
 * The DashScope virtual-try-on endpoints are NOT available on this account (they always return
 * "Model not exist"), so the whole swap is driven by the Qwen image-edit model (qwen-image-edit /
 * qwen-image-edit-plus…): we re-render the person in the design image to match the chosen model
 * face, keeping the garment 100% unchanged. Optional inputs: background, texture, face reference.
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
            ['id' => 'vp01', 'name' => 'Nhẹ nhàng tự nhiên', 'ethnicity' => 'Vietnamese female', 'image' => null, 'desc' => 'young Vietnamese woman, 22, light natural everyday makeup, shoulder-length straight black hair, fair skin, gentle warm smile, soft feminine features'],
            ['id' => 'vp02', 'name' => 'Tóc dài lượn sóng', 'ethnicity' => 'Vietnamese female', 'image' => null, 'desc' => 'young Vietnamese woman, 24, long wavy black hair with soft curls, radiant clear skin, subtle Korean-style makeup, elegant and graceful'],
            ['id' => 'vp03', 'name' => 'Cá tính tóc bob', 'ethnicity' => 'Vietnamese female', 'image' => null, 'desc' => 'young Vietnamese woman, 23, chic short black bob haircut, bold natural lipstick, confident modern look, sharp jawline, almond eyes'],
            ['id' => 'vp04', 'name' => 'Thanh lịch tóc búi', 'ethnicity' => 'Vietnamese female', 'image' => null, 'desc' => 'young Vietnamese woman, 25, elegant low bun hairstyle, minimalist makeup, classic Vietnamese beauty, refined and sophisticated'],
            ['id' => 'vp05', 'name' => 'Năng động tóc đuôi ngựa', 'ethnicity' => 'Vietnamese female', 'image' => null, 'desc' => 'young Vietnamese woman, 22, high ponytail, fresh sporty energetic look, clear glowing skin, bright happy smile, youthful'],
            ['id' => 'vp06', 'name' => 'Ngọt ngào tóc xoăn', 'ethnicity' => 'Vietnamese female', 'image' => null, 'desc' => 'young Vietnamese woman, 21, soft loose curls, sweet innocent face, rosy cheeks, gentle dreamy eyes, cute and charming'],
            ['id' => 'vp07', 'name' => 'Sang trọng mái lệch', 'ethnicity' => 'Vietnamese female', 'image' => null, 'desc' => 'young Vietnamese woman, 26, side-swept bangs, sophisticated editorial makeup, elegant high-fashion look, striking features'],
            ['id' => 'vp08', 'name' => 'Thời trang mắt khói', 'ethnicity' => 'Vietnamese female', 'image' => null, 'desc' => 'young Vietnamese woman, 24, sleek center-parted straight hair, trendy smoky-eye makeup, fashion-forward street style, confident gaze'],
            ['id' => 'vp09', 'name' => 'Dịu dàng tóc đen thẳng', 'ethnicity' => 'Vietnamese female', 'image' => null, 'desc' => 'young Vietnamese woman, 23, long straight jet-black hair, fresh natural no-makeup look, serene calm expression, classic beauty'],
            ['id' => 'vp10', 'name' => 'Hiện đại mái ngố', 'ethnicity' => 'Vietnamese female', 'image' => null, 'desc' => 'young Vietnamese woman, 22, modern curtain bangs, fresh glass-skin makeup, trendy K-pop inspired look, sparkling eyes'],
        ];
    }

    public function poseCatalog(): array
    {
        // 12 pose presets. Only the text skeleton is used (qwen-edit re-renders the pose from text).
        return [
            ['id' => 'pose01', 'name' => 'Đứng thẳng', 'skeleton' => 'standing straight, arms relaxed, full body', 'image' => '/samples/pose-01.png'],
            ['id' => 'pose02', 'name' => 'Tay chống hông', 'skeleton' => 'standing, one hand on hip, one leg crossed', 'image' => '/samples/pose-02.png'],
            ['id' => 'pose03', 'name' => 'Hai tay chống hông', 'skeleton' => 'standing, both hands on hips', 'image' => '/samples/pose-03.png'],
            ['id' => 'pose04', 'name' => 'Chống hông chéo chân', 'skeleton' => 'standing, hands on hips, legs crossed', 'image' => '/samples/pose-04.png'],
            ['id' => 'pose05', 'name' => 'Chống hông (trắng-đen)', 'skeleton' => 'standing, both hands on hips', 'image' => '/samples/pose-05.png'],
            ['id' => 'pose06', 'name' => 'Ngồi ghế', 'skeleton' => 'sitting on a high stool, one leg extended', 'image' => '/samples/pose-06.png'],
            ['id' => 'pose07', 'name' => 'Tay đút túi', 'skeleton' => 'side view, hand in pocket, relaxed', 'image' => '/samples/pose-07.png'],
            ['id' => 'pose08', 'name' => 'Ngồi xổm', 'skeleton' => 'stylish squat pose, knees apart', 'image' => '/samples/pose-08.png'],
            ['id' => 'pose09', 'name' => 'Sải bước', 'skeleton' => 'walking mid-stride catwalk, hand on hip', 'image' => '/samples/pose-09.png'],
            ['id' => 'pose10', 'name' => 'Xoay lưng', 'skeleton' => 'back view, turned away', 'image' => '/samples/pose-10.png'],
            ['id' => 'pose11', 'name' => 'Tựa ghế', 'skeleton' => 'leaning on a stool, hand to head', 'image' => '/samples/pose-11.png'],
            ['id' => 'pose12', 'name' => 'Bước ngang', 'skeleton' => 'walking, side profile, dynamic', 'image' => '/samples/pose-12.png'],
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
                    'image' => $p->image,
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
        if ($id !== '') {
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
        foreach ($this->poseCatalog() as $p) {
            if (($p['id'] ?? '') === $id) {
                return $p;
            }
        }
        return null;
    }

    /**
     * Swap via a SINGLE-pass Qwen image-edit call (the 2-step approach was dropped: its Step-2
     * "swap only the face" is unreliable on the edit model and it lost the reference face).
     * $build (0-10) controls body proportions (height vs slimness): high = tall runway-model build,
     * low = shorter/fuller. $tone adds a color-grade hint (warm/cool/film/neutral/auto) to the prompt.
     */
    public function fallbackEdit(string $designImage, string $modelDesc, string $pose, string $background = '', int $texture = 5, ?string $faceRefUrl = null, int $build = 7, string $tone = 'auto'): ?string
    {
        $swapModel = (string) studio_config('swap_model', 'qwen-image-edit-plus-2025-12-15');
        $this->calls = 1;
        $this->lastModel = null;

        $proportion = $this->buildEnglish($build);
        $bg = ($background && strtolower($background) !== 'keep' && strtolower($background) !== 'original')
            ? 'Replace the ENTIRE background of the scene with: '.$background.'. ' : '';
        $tex = $this->textureEnglish($texture);
        $texS = $tex ? ' Make the outfit fabric '.$tex.'.' : '';
        $toneS = $this->toneInstruction($tone, $background);
        // Common body/garment backbone + strong anti-squash proportion instruction.
        $base = 'Keep the exact garment, outfit and all its details 100% unchanged. '.$bg
            .'Render a full-length, vertically-balanced figure: '.$proportion.', with natural, elongated model proportions (long legs, about 1:7.5 head-to-body) — do NOT make the figure short, squat, compressed or stubby. ';

        if ($faceRefUrl) {
            // Single-pass subject-driven swap: Image 1 = reference face, Image 2 = person to edit.
            $instr = $base
                .'Image 1 is the reference person: use their exact face, facial identity, eye shape, nose, mouth, skin tone and hair. '
                .'Image 2 is the person to edit (wearing the garment). '
                .'Render the reference person from Image 1 in the pose: '.$pose.', wearing the exact garment from Image 2. '
                .'Preserve the reference face precisely and keep natural, anatomically-correct, symmetrical facial proportions — do NOT distort, stretch, deform, warp or reshape the face, eyes, nose, mouth, hair, hands or body. '
                .'Keep the eyes sharp and natural (no double/offset eyes), the mouth symmetric, the skin smooth and lifelike. '
                .$toneS.$texS.' Realistic skin texture, sharp detail, consistent lighting, studio quality.';
        } else {
            $instr = $base.'Replace the person with a full-body '.$modelDesc.' standing '.$pose.'.'.$toneS.$texS.' Photorealistic, full body, high fashion.';
        }

        $imageSvc = app(ImageAIService::class);
        $url = $imageSvc->swapEdit($instr, $designImage, $swapModel, $faceRefUrl);
        if ($url) { $this->lastModel = $imageSvc->lastModel() ?: $swapModel; }
        return $url;
    }

    /**
     * Body-proportion descriptor for the "Tỷ lệ dáng" build slider (0 = short/fuller, 10 = tall/runway).
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

    protected function textureEnglish(int $v): string
    {
        if ($v <= 1) { return 'smooth silk fabric, sleek'; }
        if ($v <= 3) { return 'soft matte fabric'; }
        if ($v <= 5) { return 'natural subtle fabric texture'; }
        if ($v <= 7) { return 'lightly woven textured fabric'; }
        return 'heavy knit, coarse weave, visible thread detail';
    }
}
