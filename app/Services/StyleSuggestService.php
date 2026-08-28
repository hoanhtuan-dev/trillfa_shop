<?php

namespace App\Services;

use App\Models\Preset;

/**
 * Image → prompt / style suggestion. Analyses a reference image (GD) and
 * suggests matching style / background / pose presets plus an English prompt.
 * If a vision key (Gemini / Qwen) is set it can be upgraded to a real call.
 */
class StyleSuggestService
{
    public function suggest(string $imagePath): array
    {
        $styles = Preset::category('style')->get();
        $backgrounds = Preset::category('background')->get();
        $poses = Preset::category('pose')->get();

        [$warm, $brightness] = $this->analyzeImage($imagePath);

        $style = $styles->first(fn ($p) => $p->ui_label === $this->pickStyle($warm, $brightness, $styles));
        $bg = $backgrounds->first(fn ($p) => $p->ui_label === $this->pickBackground($brightness, $backgrounds));
        $pose = $poses->isEmpty() ? null : $poses->random();

        $presetIds = collect([$style, $bg, $pose])->filter()->map(fn ($p) => $p->id)->values()->all();

        $prompt = 'High-fashion editorial photo'
            .($style && $style->prompt_injection ? ', '.$style->prompt_injection : '')
            .($bg && $bg->prompt_injection ? ', '.$bg->prompt_injection : '')
            .($pose && $pose->prompt_injection ? ', '.$pose->prompt_injection : '')
            .', ultra detailed, 4k';

        return [
            'preset_ids' => $presetIds,
            'styles' => $style ? [$style->ui_label] : [],
            'background' => $bg?->ui_label,
            'pose' => $pose?->ui_label,
            'image_prompt_en' => $prompt,
        ];
    }

    /**
     * @return array{0: float, 1: float} [warmth, brightness(0..1)]
     */
    protected function analyzeImage(string $path): array
    {
        try {
            $img = @imagecreatefromstring(@file_get_contents($path));
            if (! $img) {
                return [0, 0.5];
            }
            $w = imagesx($img);
            $h = imagesy($img);
            $tmp = imagecreatetruecolor(1, 1);
            imagecopyresampled($tmp, $img, 0, 0, 0, 0, 1, 1, $w, $h);
            $rgb = imagecolorsforindex($tmp, imagecolorat($tmp, 0, 0));
            imagedestroy($tmp);
            imagedestroy($img);

            $brightness = ($rgb['red'] + $rgb['green'] + $rgb['blue']) / 3 / 255;
            $warmth = ($rgb['red'] + $rgb['green']) / (2 * max(1, (int) $rgb['blue']));

            return [$warmth, $brightness];
        } catch (\Throwable $e) {
            return [0, 0.5];
        }
    }

    protected function pickStyle(float $warm, float $brightness, $styles): ?string
    {
        $byLabel = fn ($label) => $styles->contains('ui_label', $label) ? $label : ($styles->first()?->ui_label ?? null);

        if ($warm > 1.2 && $brightness > 0.4) {
            return $byLabel('Boho Chic (Modern)');
        }
        if ($brightness > 0.68) {
            return $byLabel('Old Money / Classic');
        }
        if ($brightness < 0.32) {
            return $byLabel('Gorpcore / Techwear');
        }

        return $styles->random()?->ui_label;
    }

    protected function pickBackground(float $brightness, $backgrounds): ?string
    {
        $byLabel = fn ($label) => $backgrounds->contains('ui_label', $label) ? $label : ($backgrounds->first()?->ui_label ?? null);

        if ($brightness > 0.68) {
            return $byLabel('Minimalist Studio');
        }
        if ($brightness < 0.32) {
            return $byLabel('Concrete Brutalism');
        }

        return $byLabel('High-End Boutique (Shop)');
    }
}
