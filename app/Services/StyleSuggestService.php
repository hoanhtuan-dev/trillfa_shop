<?php

namespace App\Services;

use App\Models\Preset;
use Illuminate\Support\Facades\Http;

/**
 * Image → prompt / style suggestion. When a Gemini vision key is available it asks the
 * model to deeply analyse the reference image (style, fabric, silhouette, colours, pose,
 * background) and produce a rich English prompt + matching presets. Otherwise it falls
 * back to a lightweight GD colour analysis so the stub still works offline.
 */
class StyleSuggestService
{
    public function suggest(string $imagePath): array
    {
        $key = studio_api_key('gemini');

        if ($key) {
            try {
                return $this->suggestViaVision($imagePath, $key);
            } catch (\Throwable $e) {
                logger()->error('Vision suggest failed: '.$e->getMessage());
            }
        }

        return $this->suggestViaColor($imagePath);
    }

    protected function suggestViaVision(string $imagePath, string $key): array
    {
        $model = (string) studio_config('vision_model', 'gemini-1.5-flash');
        $mime = function_exists('mime_content_type') ? (mime_content_type($imagePath) ?: 'image/jpeg') : 'image/jpeg';
        $b64 = base64_encode((string) file_get_contents($imagePath));

        $prompt = 'Analyze this fashion model photo and its garment. Return ONLY valid JSON with keys: '
            .'"styles" (1-3 style labels), "background" (one label), "pose" (one label), '
            .'"fabric" (one label), "silhouette" (one label), "camera" (one label), '
            .'"image_prompt_en" (a detailed, ready-to-use English image-generation prompt describing the outfit, fabric, colors, fit and setting).';

        $resp = Http::withHeaders(['x-goog-api-key' => $key])->timeout(90)
            ->post('https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent', [
                'contents' => [['parts' => [
                    ['text' => $prompt],
                    ['inlineData' => ['mimeType' => $mime, 'data' => $b64]],
                ]]],
                'generationConfig' => ['responseMimeType' => 'application/json'],
            ]);

        if (! $resp->successful()) {
            throw new \RuntimeException('Vision ('.$resp->status().'): '.$resp->body());
        }

        $text = trim((string) data_get($resp->json(), 'candidates.0.content.parts.0.text'));
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        $json = ($start !== false && $end !== false) ? json_decode(substr($text, $start, $end - $start + 1), true) : null;
        if (! is_array($json)) {
            throw new \RuntimeException('Không phân tích được JSON từ vision.');
        }

        return [
            'preset_ids' => $this->matchPresets($json),
            'styles' => array_values(array_filter((array) ($json['styles'] ?? []))),
            'background' => (string) ($json['background'] ?? ''),
            'pose' => (string) ($json['pose'] ?? ''),
            'image_prompt_en' => (string) ($json['image_prompt_en'] ?? 'High-fashion editorial photo, premium, ultra detailed, 4k'),
        ];
    }

    protected function matchPresets(array $json): array
    {
        $wants = collect([
            'style' => $json['styles'] ?? [],
            'background' => [$json['background'] ?? null],
            'pose' => [$json['pose'] ?? null],
            'fabric' => [$json['fabric'] ?? null],
            'silhouette' => [$json['silhouette'] ?? null],
            'camera' => [$json['camera'] ?? null],
        ])->filter(fn ($v) => ! empty($v));

        $ids = [];

        foreach ($wants as $category => $labels) {
            foreach ($labels as $label) {
                if (! is_string($label) || $label === '') {
                    continue;
                }
                $found = Preset::category($category)->get()
                    ->first(fn ($p) => str_contains(mb_strtolower($p->ui_label ?? ''), mb_strtolower($label))
                        || str_contains(mb_strtolower($label), mb_strtolower($p->ui_label ?? '')));
                if ($found) {
                    $ids[] = $found->id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    protected function downscaleBase64(string $path): array
    {
        $img = @imagecreatefromstring((string) file_get_contents($path));
        if (! $img) {
            return ['', 'image/jpeg'];
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $max = 1024;
        if ($w > $max || $h > $max) {
            $scale = min($max / $w, $max / $h);
            $nw = max(1, (int) ($w * $scale));
            $nh = max(1, (int) ($h * $scale));
            $tmp = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($tmp, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($img);
            $img = $tmp;
        }
        ob_start();
        imagejpeg($img, null, 85);
        $data = ob_get_clean();
        imagedestroy($img);

        return [base64_encode((string) $data), 'image/jpeg'];
    }

    protected function suggestViaColor(string $imagePath): array
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
