<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * "Thuật sỹ ảo" — an AI fashion stylist that walks a SKELETON question matrix then
 * gives deep, specific advice per step. Never dumps a preset list; it interviews.
 */
class StylistService
{
    /** Skeleton "backbone" the stylist always walks through (deep layer comes from the LLM). */
    protected $skeleton = [
        ['en' => 'silhouette and fit',            'vi' => 'Phom dáng / sự vừa vặn',   'opts' => ['Ôm / fitted', 'Suông / straight', 'Rộng / oversized', 'Bồng / volume']],
        ['en' => 'fabric and texture',            'vi' => 'Chất liệu / bề mặt',       'opts' => ['Lụa mềm', 'Cotton', 'Dệt kim', 'Da', 'Thô / linen']],
        ['en' => 'color and print',               'vi' => 'Màu sắc / họa tiết',       'opts' => ['Pastel nhẹ', 'Tối / trầm', 'Tươi sáng', 'Đen - trắng', 'Trung tính (be/cream)']],
        ['en' => 'design details and trims',      'vi' => 'Chi tiết thiết kế',         'opts' => ['Không hoạ tiết', 'Kẻ sọc', 'Chấm bi', 'Hoa văn', 'Thêu / logo']],
        ['en' => 'style and mood',                'vi' => 'Phong cách / cảm hứng',     'opts' => ['Sang trọng', 'Tối giản', 'Boho', 'Streetwear', 'Cổ điển']],
        ['en' => 'occasion and setting',          'vi' => 'Dịp / bối cảnh',            'opts' => ['Tiệc tối', 'Công sở', 'Dạo phố', 'Bãi biển', 'Sự kiện']],
    ];

    public function garmentTypes(): array
    {
        return [
            ['id' => 'dress',   'name' => 'Đầm',              'emoji' => '👗', 'color' => '#e8577d', 'img' => '/samples/garment-dress.png'],
            ['id' => 'top',     'name' => 'Áo sơ mi / Blouse','emoji' => '👚', 'color' => '#7aa7e0', 'img' => '/samples/garment-top.png'],
            ['id' => 'pants',   'name' => 'Quần',             'emoji' => '👖', 'color' => '#6a8f6a', 'img' => '/samples/garment-pants.png'],
            ['id' => 'skirt',   'name' => 'Chân váy',         'emoji' => '🩰', 'color' => '#b57bd0', 'img' => '/samples/garment-skirt.png'],
            ['id' => 'shorts',  'name' => 'Quần short',       'emoji' => '🩳', 'color' => '#e0a95a', 'img' => '/samples/garment-shorts.png'],
            ['id' => 'jacket',  'name' => 'Áo khoác',         'emoji' => '🧥', 'color' => '#8a6a4a', 'img' => '/samples/garment-jacket.png'],
            ['id' => 'aodai',   'name' => 'Áo dài',           'emoji' => '👘', 'color' => '#d04a4a', 'img' => '/samples/garment-aodai.png'],
            ['id' => 'set',     'name' => 'Set đồ',           'emoji' => '🧥', 'color' => '#4a7a90', 'img' => '/samples/garment-set.png'],
        ];
    }

    public function nameOf(string $id): string
    {
        foreach ($this->garmentTypes() as $t) { if ($t['id'] === $id) { return $t['name']; } }
        return ucfirst($id);
    }

    /**
     * Next step of the stylist conversation. Walks the skeleton matrix; the LLM adds depth.
     * @param string $type
     * @param array  $history [['label'=>..., 'answer'=>...], ...]
     * @return array {done, question, options[], prompt, summary, category}
     */
    public function next(string $type, array $history): array
    {
        $stepNum = count($history);
        $typeName = $this->nameOf($type);
        $skeleton = $this->skeleton;

        if ($stepNum >= count($skeleton)) {
            // Skeleton done -> finalize a rich prompt.
            return ['done' => true, 'question' => '', 'options' => [], 'prompt' => $this->buildPrompt($type, $history), 'summary' => $this->buildSummary($history), 'category' => ''];
        }

        $cat = $skeleton[$stepNum];
        $historyText = '';
        if ($history) {
            $historyText = implode("
", array_map(fn ($h) => '- '.($h['label'] ?? 'Bước').': '.($h['answer'] ?? ''), $history));
        }

        $instruction = <<<PROMPT
You are a premium Vietnamese high-fashion creative director and AI stylist (thuật sỹ). You are helping design: {$typeName}.

This is step {$stepNum} of 6 — the topic is: {$cat['vi']} ({$cat['en']}).

Choices so far:
{$historyText}

Rules:
- Ask ONE deep, specific, fashion-expert question in Vietnamese about the current topic for this {$typeName}. Make it feel like a stylist advising a client (not a form).
- Give 3-5 concrete, distinct options that are rich fashion descriptors (not generic).
- Do NOT move to other topics; only the current one.
- Reply ONLY with JSON:
{"done":false,"question":"...","options":["a","b","c"]}
PROMPT;

        $json = $this->chat($instruction);
        if ($json === null || ! is_array($json)) {
            return ['done' => false, 'question' => $cat['vi'].' như thế nào cho '.$typeName.'?', 'options' => array_values($cat['opts']), 'prompt' => '', 'summary' => '', 'category' => $cat['en']];
        }

        return [
            'done' => false,
            'question' => (string) ($json['question'] ?? ($cat['vi'].' như thế nào?')),
            'options' => array_values((array) ($json['options'] ?? $cat['opts'])),
            'prompt' => '',
            'summary' => '',
            'category' => $cat['en'],
        ];
    }

    /** Build a rich EN image prompt from the accumulated answers. */
    protected function buildPrompt(string $type, array $history): string
    {
        $typeName = $this->nameOf($type);
        $parts = [];
        foreach ($history as $h) { if (! empty($h['answer'])) { $parts[] = strtolower(trim((string) $h['answer'])); } }
        $desc = $parts ? implode(', ', $parts) : 'elegant contemporary design';
        return 'A high-fashion editorial photo of a '.$typeName.', '.$desc.', premium Vogue editorial, full-body, refined silhouette, soft even studio lighting, clean minimal background, ultra detailed, 4k';
    }

    protected function buildSummary(array $history): string
    {
        if (! $history) { return 'Bạn đã hoàn thành mô tả thiết kế.'; }
        $lines = array_map(fn ($h) => ucfirst((string) ($h['answer'] ?? '')), $history);
        return 'Thiết kế với: '.implode(' · ', $lines).'.';
    }

    /** Text chat that returns parsed JSON. Tries Qwen (qwen3.8-flash) -> Gemini -> others. */
    protected function chat(string $instruction): ?array
    {
        $qwenKey = studio_api_key('qwen') ?: studio_api_key('dashscope');
        $qwenModels = array_values(array_unique(array_filter([
            (string) studio_config('stylist_model', 'qwen3.8-flash'), 'qwen3.8-flash', (string) studio_config('prompt_model', 'qwen-plus'), 'qwen-plus', 'qwen-max',
        ])));
        if ($qwenKey) {
            foreach ($qwenModels as $qm) {
                try {
                    $resp = Http::withToken($qwenKey)->timeout(50)->post(dashscope_base_url($qwenKey).'/api/v1/services/aigc/text-generation/generation', [
                        'model' => $qm,
                        'input' => ['messages' => [['role' => 'user', 'content' => $instruction]]],
                        'parameters' => ['result_format' => 'message'],
                    ]);
                    if ($resp->successful()) {
                        $out = trim((string) data_get($resp->json(), 'output.choices.0.message.content'));
                        $decoded = $this->decodeJson($out);
                        if ($decoded) { return $decoded; }
                    }
                } catch (\Throwable $e) { logger()->warning('Stylist Qwen('.$qm.') failed: '.$e->getMessage()); }
            }
        }

        $geminiKey = studio_api_key('gemini');
        $gemModels = array_values(array_unique(array_filter([
            (string) studio_config('translate_model', 'gemini-3.5-flash'), 'gemini-3.5-flash', 'gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-1.5-flash',
        ])));
        if ($geminiKey) {
            foreach ($gemModels as $gm) {
                try {
                    $resp = Http::withHeaders(['x-goog-api-key' => $geminiKey])->timeout(50)->post('https://generativelanguage.googleapis.com/v1beta/models/'.$gm.':generateContent', [
                        'contents' => [['parts' => [['text' => $instruction]]]],
                        'generationConfig' => ['responseMimeType' => 'application/json'],
                    ]);
                    if ($resp->successful()) {
                        $out = trim((string) data_get($resp->json(), 'candidates.0.content.parts.0.text'));
                        $decoded = $this->decodeJson($out);
                        if ($decoded) { return $decoded; }
                    }
                } catch (\Throwable $e) { logger()->warning('Stylist Gemini('. $gm.') failed: '.$e->getMessage()); }
            }
        }

        return null;
    }

    /** Parse a JSON string, tolerating a markdown-fenced or leading-text wrapper. */
    protected function decodeJson(string $out): ?array
    {
        $out = trim($out);
        $decoded = json_decode($out, true);
        if (is_array($decoded)) { return $decoded; }
        $start = strpos($out, '{');
        $end = strrpos($out, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($out, $start, $end - $start + 1), true);
            if (is_array($decoded)) { return $decoded; }
        }
        return null;
    }
}
