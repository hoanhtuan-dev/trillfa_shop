<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * "Giám đốc sáng tạo" — turns a Vietnamese idea + fashion presets into
 * professional English prompts (image + video) as structured JSON.
 *
 * If GEMINI_API_KEY is configured the real Gemini API is used; otherwise a
 * deterministic stub is returned so the whole flow works without credentials.
 */
class GeminiService
{
    public function generateCreativeDirector(string $idea, array $injections = []): array
    {
        $key = studio_api_key('gemini');

        if ($key) {
            return $this->callGemini($idea, $injections, $key);
        }

        return $this->stub($idea, $injections);
    }

    protected function stub(string $idea, array $injections): array
    {
        $fabric = $injections['fabric'] ?? '';
        $silhouette = $injections['silhouette'] ?? '';
        $style = $injections['style'] ?? '';
        $background = $injections['background'] ?? '';
        $pose = $injections['pose'] ?? '';
        $camera = $injections['camera'] ?? '';

        $subject = trim($idea ?: 'a high-end fashion outfit', ' .,');

        $imagePrompt = $this->clean('High-fashion editorial photo of '.$subject
            .($fabric ? ', crafted from '.$fabric : '')
            .($silhouette ? ', '.$silhouette.' silhouette' : '')
            .($style ? ', '.$style.' aesthetic' : '')
            .($background ? ', '.$background.' background' : '')
            .($pose ? ', '.$pose.' pose' : '')
            .', clean studio background, soft diffused lighting, premium Vogue editorial, ultra detailed, 4k');

        $videoPrompt = $this->clean('Cinematic fashion show. A model walks the runway wearing '.$subject
            .($fabric ? ', made of '.$fabric : '')
            .($silhouette ? ', '.$silhouette.' cut' : '')
            .($style ? ', '.$style.' style' : '')
            .($background ? ', '.$background.' backdrop' : '')
            .($pose ? ', '.$pose.' pose' : '')
            .($camera ? ', camera: '.$camera : ', slow tracking shot')
            .', dramatic runway lighting, slow motion, 4k');

        return [
            'idea' => $idea,
            'image_prompt_en' => $imagePrompt,
            'video_prompt_en' => $videoPrompt,
            'keywords' => array_values(array_filter([$fabric, $silhouette, $style, $camera])),
            'mood' => $style ? $style : 'luxury',
            'color_palette' => ['ivory', 'black', 'gold'],
            'style_notes' => 'High-fashion editorial, minimal, luxury fabric feel.',
            'provider' => 'stub',
        ];
    }

    protected function callGemini(string $idea, array $injections, string $key): array
    {
        $system = 'You are a fashion creative director. Given a Vietnamese idea and optional preset tags, '.
            'return ONLY valid JSON with keys: image_prompt_en, video_prompt_en, keywords (array), mood, '.
            'color_palette (array), style_notes.';

        $prompt = "Idea: {$idea}\nTags: ".json_encode($injections, JSON_UNESCAPED_UNICODE);

        try {
            $model = studio_config('prompt_model', 'gemini-1.5-flash');

            $resp = Http::withHeaders(['x-goog-api-key' => $key])->timeout(60)
                ->post('https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent', [
                    'contents' => [['parts' => [['text' => $system."\n\n".$prompt]]]],
                    'generationConfig' => ['responseMimeType' => 'application/json'],
                ]);

            if ($resp->successful()) {
                $text = (string) data_get($resp->json(), 'candidates.0.content.parts.0.text');
                $json = json_decode(trim($text), true);
                if (is_array($json)) {
                    return array_merge($json, ['idea' => $idea, 'provider' => 'gemini']);
                }
            }
        } catch (\Throwable $e) {
            logger()->error('GeminiService failed: '.$e->getMessage());
        }

        return $this->stub($idea, $injections);
    }

    protected function clean(string $value): string
    {
        return preg_replace('/\s+/', ' ', trim($value));
    }
}
