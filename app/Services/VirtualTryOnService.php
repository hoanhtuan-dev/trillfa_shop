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
    public function modelCatalog(): array
    {
        // 6 model faces (headshots). We pass the selected face as a reference image to qwen-edit so
        // the swap adopts that person's look; the face's desc text is a secondary descriptor.
        return [
            ['id' => 'model01', 'name' => 'Mẫu 1', 'ethnicity' => 'East Asian female', 'image' => '/samples/model-01.png', 'desc' => 'East Asian female, shoulder-length reddish-brown hair, fair skin'],
            ['id' => 'model02', 'name' => 'Mẫu 2', 'ethnicity' => 'East Asian female', 'image' => '/samples/model-02.png', 'desc' => 'East Asian female, long black wavy hair, white shirt'],
            ['id' => 'model03', 'name' => 'Mẫu 3', 'ethnicity' => 'East Asian female', 'image' => '/samples/model-03.png', 'desc' => 'East Asian female, high bun updo, fair skin'],
            ['id' => 'model04', 'name' => 'Mẫu 4', 'ethnicity' => 'East Asian female', 'image' => '/samples/model-04.png', 'desc' => 'East Asian female, short bob black hair, smiling'],
            ['id' => 'model05', 'name' => 'Mẫu 5', 'ethnicity' => 'East Asian female', 'image' => '/samples/model-05.png', 'desc' => 'East Asian female, high ponytail, long black hair'],
            ['id' => 'model06', 'name' => 'Mẫu 6', 'ethnicity' => 'East Asian female', 'image' => '/samples/model-06.png', 'desc' => 'East Asian female, long wavy black hair, lace top'],
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
        if ($id !== '') {
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
        foreach ($this->modelCatalog() as $m) {
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
     * qwen-image-edit swap: re-render the person in the design image to match the chosen model,
     * keeping the garment 100% unchanged. $faceRefUrl (the model's face photo) is passed as an extra
     * reference image so the result adopts that face; if the edit model rejects a second image it
     * retries with just the design image (the prompt still carries the model description).
     */
    public function fallbackEdit(string $designImage, string $modelDesc, string $pose, string $background = '', int $texture = 5, ?string $faceRefUrl = null): ?string
    {
        $swapModel = (string) studio_config('swap_model', 'qwen-image-edit-plus-2025-12-15');

        if ($faceRefUrl) {
            // Face-image driven: the FIRST reference image is the model's face, the SECOND is the
            // design image (person wearing the garment).
            $instr = 'Keep the exact garment, outfit and all its details 100% unchanged. Replace the person wearing it in the SECOND image with the person from the FIRST reference image (matching face, hair, skin tone and body build), standing '.$pose.'.';
        } else {
            $instr = 'Keep the exact garment, outfit and all its details 100% unchanged. Replace the person with a full-body '.$modelDesc.' standing '.$pose.'.';
        }
        // Hậu cảnh: đưa lên đầu + dùng "replace the ENTIRE background" để model thực sự thay nền.
        if ($background && strtolower($background) !== 'keep' && strtolower($background) !== 'original') {
            $instr = 'Replace the ENTIRE background of the scene with: '.$background.'. '.$instr;
        }
        // Texture: 0 mịn -> 10 dệt kim thô.
        $tex = $this->textureEnglish($texture);
        if ($tex) {
            $instr .= ' Make the outfit fabric '.$tex.'.';
        }
        $instr .= ' Photorealistic, full body, high fashion.';

        $svc = app(ImageAIService::class);
        return $svc->swapEdit($instr, $designImage, $swapModel, $faceRefUrl);
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
