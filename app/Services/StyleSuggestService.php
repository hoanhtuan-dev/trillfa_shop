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
    public function suggest(string $imagePath, int $creativeLevel = 6): array
    {
        if ((string) studio_config('vision_provider', 'gemini') === 'qwen') {
            $qwenKey = studio_api_key('qwen') ?: studio_api_key('dashscope');
            if ($qwenKey) {
                try {
                    return $this->suggestViaQwenVision($imagePath, $creativeLevel);
                } catch (\Throwable $e) {
                    logger()->error('Qwen vision suggest failed: '.$e->getMessage());
                }
            }
        }

        $key = studio_api_key('gemini');

        if ($key) {
            try {
                return $this->suggestViaVision($imagePath, $creativeLevel, $key);
            } catch (\Throwable $e) {
                logger()->error('Vision suggest failed: '.$e->getMessage());
            }
        }

        return $this->suggestViaColor($imagePath, $creativeLevel);
    }

    protected function suggestViaQwenVision(string $imagePath, int $creativeLevel): array
    {
        $model = studio_vision_model('qwen');
        [$b64, $mime] = $this->downscaleBase64($imagePath);
        $direction = app(CreativeDirectionService::class);
        $prompt = 'Analyze this fashion model photo and its garment. '.$direction->creativityDirective($creativeLevel).' '
            .'Return ONLY valid JSON with keys: "styles", "background", "pose", "fabric", "silhouette", "camera", '
            .'"image_prompt_en" (a detailed ready-to-use English image prompt), "video_prompt_en" (a matching '
            .'English video-catwalk prompt for the SAME garment), "keywords" (array).';

        $last = null;
        foreach (studio_qwen_credentials('vision') as $key) {
            $base = dashscope_base_url($key).'/compatible-mode/v1';
            try {
                $resp = Http::withToken($key)->timeout(90)
                    ->post($base.'/chat/completions', [
                        'model' => $model,
                        'messages' => [['role' => 'user', 'content' => [
                            ['type' => 'text', 'text' => $prompt],
                            ['type' => 'image_url', 'image_url' => ['url' => 'data:'.$mime.';base64,'.$b64]],
                        ]]],
                        'response_format' => ['type' => 'json_object'],
                    ]);

                if ($resp->successful()) {
                    $text = (string) data_get($resp->json(), 'choices.0.message.content');
                    $json = json_decode(trim($text), true);
                    if (is_array($json)) {
                        return $this->finalize($json, $creativeLevel);
                    }
                    $last = 'Không phân tích được JSON từ Qwen vision.';
                } elseif (is_qwen_quota_error((string) $resp->body())) {
                    $last = 'HTTP '.$resp->status().': '.substr((string) $resp->body(), 0, 180);
                    continue; // Token Plan quota -> try Pay-As-You-Go next
                } else {
                    $last = 'HTTP '.$resp->status().': '.substr((string) $resp->body(), 0, 180);
                    break;
                }
            } catch (\Throwable $e) {
                $last = $e->getMessage();
                break;
            }
        }

        throw new \RuntimeException('Qwen vision: '.($last ?: 'không xác định'));
    }

    protected function suggestViaVision(string $imagePath, int $creativeLevel, string $key): array
    {
        $model = studio_vision_model();
        $mime = function_exists('mime_content_type') ? (mime_content_type($imagePath) ?: 'image/jpeg') : 'image/jpeg';
        $b64 = base64_encode((string) file_get_contents($imagePath));

        $direction = app(CreativeDirectionService::class);
        $prompt = 'Analyze this fashion model photo and its garment. '.$direction->creativityDirective($creativeLevel).' '
            .'Return ONLY valid JSON with keys: "styles" (1-3 style labels), "background" (one label), "pose" (one label), '
            .'"fabric" (one label), "silhouette" (one label), "camera" (one label), '
            .'"image_prompt_en" (a detailed, ready-to-use English image-generation prompt describing the outfit, fabric, colors, fit and setting), '
            .'"video_prompt_en" (a matching English video-catwalk prompt for the SAME garment), "keywords" (array).';

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

        return $this->finalize($json, $creativeLevel);
    }

    /**
     * Canonicalise a vision suggestion into the unified Creative Direction schema,
     * guaranteeing image & video prompts describe the SAME garment.
     */
    protected function str($v): string
    {
        if (is_array($v)) {
            return trim(implode(', ', array_filter(array_map('strval', $v))));
        }
        return trim((string) $v);
    }

    protected function finalize(array $json, int $creativeLevel): array
    {
        $styles = array_values(array_filter((array) ($json['styles'] ?? [])));
        $background = $this->str($json['background'] ?? '');
        $pose = $this->str($json['pose'] ?? '');
        $fabric = $this->str($json['fabric'] ?? '');
        $silhouette = $this->str($json['silhouette'] ?? '');
        $camera = $this->str($json['camera'] ?? '');

        $injections = array_filter([
            'fabric' => $fabric,
            'silhouette' => $silhouette,
            'style' => implode(', ', $styles),
            'background' => $background,
            'pose' => $pose,
            'camera' => $camera,
        ]);

        $raw = [
            'image_prompt_en' => (string) ($json['image_prompt_en'] ?? ''),
            'video_prompt_en' => (string) ($json['video_prompt_en'] ?? ''),
            'keywords' => $json['keywords'] ?? [],
            'category' => $injections,
            'mood' => $json['mood'] ?? ($styles[0] ?? 'luxury'),
            'color_palette' => $json['color_palette'] ?? ['ivory', 'black', 'gold'],
            'style_notes' => $json['style_notes'] ?? 'High-fashion editorial, minimal, luxury fabric feel.',
        ];

        $dir = app(CreativeDirectionService::class);
        $c = $dir->normalize($raw, '', $injections, $creativeLevel);

        return [
            'preset_ids' => $this->matchPresets($json),
            'styles' => $styles,
            'background' => $background,
            'pose' => $pose,
            'fabric' => $fabric,
            'silhouette' => $silhouette,
            'camera' => $camera,
            'image_prompt_en' => $c['image_prompt_en'],
            'video_prompt_en' => $c['video_prompt_en'],
            'creative_level' => $c['creative_level'],
            'adherence' => $c['adherence'],
            'negative_prompt' => $c['negative_prompt'],
            'keywords' => $c['keywords'],
            'category' => $c['category'],
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

    protected function suggestViaColor(string $imagePath, int $creativeLevel = 6): array
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

        return $this->finalize([
            'image_prompt_en' => $prompt,
            'styles' => $style ? [$style->ui_label] : [],
            'background' => $bg?->ui_label,
            'pose' => $pose?->ui_label,
        ], $creativeLevel);
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
