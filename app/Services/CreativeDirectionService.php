<?php

namespace App\Services;

/**
 * Single source of truth for the "Creative Direction" that drives the whole
 * image -> video pipeline.
 *
 * It standardises the prompt schema (image + video), guarantees the IMAGE and the
 * VIDEO describe the SAME garment (a shared "signature" of fabric / silhouette /
 * style / background), and applies a controllable creativity vs. adherence balance.
 *
 * The model is free to write rich prompts, but the service ensures the shared
 * garment identity appears in both prompts and that the requested creative level
 * is stamped into the instructions.
 */
class CreativeDirectionService
{
    protected const CATEGORIES = ['fabric', 'silhouette', 'style', 'background', 'pose', 'camera'];

    /**
     * Normalise the category => prompt_injection map into a canonical token map.
     */
    public function tokens(array $injections): array
    {
        $out = [];
        foreach (self::CATEGORIES as $cat) {
            $v = $injections[$cat] ?? null;
            if (is_string($v) && trim($v) !== '') {
                $out[$cat] = trim($v);
            } elseif (is_iterable($v)) {
                $out[$cat] = trim(implode(', ', array_filter(array_map('strval', (array) $v))));
            }
        }

        return $out;
    }

    /**
     * The shared "garment signature" — the identical descriptor block embedded in
     * BOTH the image and the video prompt so the video matches the rendered image.
     */
    public function signature(array $tokens): string
    {
        $parts = [];
        foreach (['fabric', 'silhouette', 'style', 'background'] as $cat) {
            $v = trim((string) ($tokens[$cat] ?? ''));
            if ($v === '') {
                continue;
            }
            $v = $this->lowerise($v);
            $parts[] = match ($cat) {
                'fabric' => 'crafted from '.$v,
                'silhouette' => $v.' silhouette',
                'style' => $v.' aesthetic',
                'background' => $v.' background',
                default => $v,
            };
        }

        return trim(implode(', ', $parts));
    }

    /**
     * Instructional directive that steers the model's creativity vs. adherence.
     */
    public function creativityDirective(int $level): string
    {
        $level = $this->clamp($level, 1, 10);

        if ($level <= 3) {
            return 'CREATIVITY: low ('.$level.'/10). Follow the idea and every preset tag verbatim. Do not invent new fabrics, styles, colours, accessories or setting. Reproduce only what is described; keep changes minimal and faithful to the brief.';
        }

        if ($level <= 6) {
            return 'CREATIVITY: medium ('.$level.'/10). Follow the idea and preset tags as the core, then elevate the design tastefully — better fabric handling, a refined silhouette, cohesive styling — while keeping the described outfit clearly recognisable.';
        }

        return 'CREATIVITY: high ('.$level.'/10). Treat the idea and preset tags as a creative springboard. It is fine to recombine, expand and reinterpret boldly for a high-fashion editorial look, staying on-brief only in spirit; keep the result coherent, luxurious and wearable.';
    }

    /**
     * Inverse of creativity: how strictly the result should match the brief.
     */
    public function adherence(int $level): int
    {
        return 11 - $this->clamp($level, 1, 10);
    }

    /**
     * Canonicalise a raw model response into the Creative Direction. This is where
     * the schema is unified and where the image/video prompts are kept in sync.
     */
    public function normalize(array $raw, string $idea, array $injections, int $creativeLevel = 6): array
    {
        $creativeLevel = $this->clamp($creativeLevel, 1, 10);

        // Merge preset injections with any category tokens the model supplied.
        $tokens = $this->tokens($injections);
        $modelTokens = is_array($raw['category'] ?? null) ? $this->tokens($raw['category']) : [];
        $tokens = array_merge($tokens, array_filter($modelTokens));

        $concept = $this->concept($raw, $idea);
        $directive = $this->creativityDirective($creativeLevel);
        $sig = $this->signature($tokens);

        // Build both prompts from the SAME concept + signature (consolidation).
        $rawImage = (string) ($raw['image_prompt_en'] ?? '');
        $rawVideo = (string) ($raw['video_prompt_en'] ?? '');
        $imagePrompt = $this->clean($rawImage !== '' ? $rawImage : $this->buildImagePrompt($concept, $tokens));
        // Video camera action is chosen at render time via the "Kịch bản quay" preset (video_scene),
        // NOT from the image camera angle (category.camera) — the two are decoupled.
        $videoPrompt = $this->clean($rawVideo !== '' ? $rawVideo : $this->buildVideoPrompt($concept, $tokens));

        // Guarantee both prompts carry the same garment identity (no divergence).
        $imagePrompt = $this->ensureSignature($imagePrompt, $sig, $tokens, suppress: true);
        $videoPrompt = $this->ensureSignature($videoPrompt, $sig, $tokens);

        return [
            'idea' => $idea,
            'creative_level' => $creativeLevel,
            'adherence' => $this->adherence($creativeLevel),
            'concept_en' => $concept,
            'category' => $tokens,
            'signature' => $sig,
            'image_prompt_en' => $imagePrompt,
            'video_prompt_en' => $videoPrompt,
            'negative_prompt' => $this->negativePrompt($raw, $creativeLevel),
            'keywords' => array_values(array_filter((array) ($raw['keywords'] ?? $this->keywordsFromTokens($tokens)))),
            'mood' => (string) ($raw['mood'] ?? 'luxury'),
            'color_palette' => array_values((array) ($raw['color_palette'] ?? ['ivory', 'black', 'gold'])),
            'style_notes' => (string) ($raw['style_notes'] ?? 'High-fashion editorial, minimal, luxury fabric feel.'),
            'creativity_directive' => $directive,
        ];
    }

    /**
     * The canonical image prompt: subject + shared garment signature + editorial finish.
     */
    public function buildImagePrompt(string $concept, array $tokens): string
    {
        $sig = $this->signature($tokens);
        $direction = $this->creativityDirective((int) ($tokens['creative_level'] ?? 6));

        $parts = ['High-fashion editorial photo of '.$concept];
        if ($sig !== '') {
            $parts[] = $sig;
        }
        if (! empty($tokens['pose'])) {
            $parts[] = $this->lowerise((string) $tokens['pose']).' pose';
        }
        $parts[] = 'clean studio background, soft diffused lighting, premium Vogue editorial, ultra detailed, 4k';

        return $this->clean(implode(', ', $parts).'. '.$direction);
    }

    /**
     * The canonical video prompt derived from the SAME concept + signature.
     */
    public function buildVideoPrompt(string $concept, array $tokens, string $camera = ''): string
    {
        $sig = $this->signature($tokens);
        $direction = $this->creativityDirective((int) ($tokens['creative_level'] ?? 6));

        $parts = ['Cinematic fashion show'];
        if ($concept !== '') {
            $parts[] = ', a model walks the runway wearing '.$concept;
        }
        if ($sig !== '') {
            $parts[] = ', made of '.$sig;
        }
        if (! empty($tokens['pose'])) {
            $parts[] = ', '.$this->lowerise((string) $tokens['pose']).' pose';
        }
        $camera = trim($camera) !== '' ? trim($camera) : 'slow tracking shot';
        $parts[] = ', camera: '.$camera;
        $parts[] = ', dramatic runway lighting, slow motion, 4k';

        return $this->clean(implode('', $parts).'. '.$direction);
    }

    /**
     * Append the shared signature to a prompt unless a distinguishing descriptor
     * (fabric / silhouette / style token) already appears.
     */
    protected function ensureSignature(string $prompt, string $sig, array $tokens, bool $suppress = false): string
    {
        if ($sig === '') {
            return $prompt;
        }

        $needles = array_filter([
            $tokens['fabric'] ?? null,
            $tokens['style'] ?? null,
            $tokens['silhouette'] ?? null,
        ]);

        foreach ($needles as $needle) {
            $n = $this->lowerise((string) $needle);
            if ($n !== '' && str_contains($this->lowerise($prompt), $n)) {
                return $this->clean($prompt); // identity already described
            }
        }

        $tail = $this->clean(trim($prompt, " \t\n.,").', '.$sig);
        if ($suppress) {
            return $tail; // image prompt: keep it as a comma phrase.
        }

        return $tail;
    }

    protected function concept(array $raw, string $idea): string
    {
        $concept = trim((string) ($raw['concept_en'] ?? ''));

        if ($concept === '') {
            $concept = trim($idea ?: 'a high-end fashion outfit', ' .,');
        }

        return $this->clean($concept);
    }

    protected function negativePrompt(array $raw, int $level): string
    {
        $default = 'blurry, low quality, distorted proportions, extra limbs, deformed hands, watermark, text, logo, '
            .'oversaturated, overexposed, duplicated outfit, cropped garment, inconsistent face';

        // Higher creativity tolerates a slightly less strict negative prompt.
        if ($level >= 7) {
            $default = str_replace('duplicated outfit, cropped garment, inconsistent face', '', $default);
        }

        return (string) ($raw['negative_prompt'] ?? $default);
    }

    protected function keywordsFromTokens(array $tokens): array
    {
        return array_values(array_filter(array_map(
            fn ($cat) => $this->lowerise((string) ($tokens[$cat] ?? '')),
            ['fabric', 'silhouette', 'style', 'camera']
        )));
    }

    protected function lowerise(string $value): string
    {
        if (function_exists('mb_strtolower')) {
            return mb_strtolower(trim($value));
        }

        return strtolower(trim($value));
    }

    protected function clean(string $value): string
    {
        return preg_replace('/\s+/', ' ', trim($value)) ?? '';
    }

    protected function clamp(int $value, int $min, int $max): int
    {
        return max($min, min($max, $value));
    }
}
