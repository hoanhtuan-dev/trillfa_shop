<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * "Thuật sỹ ảo" — an AI fashion stylist that guides the user ONE STEP AT A TIME.
 * Given a garment type + the choices already made, it asks the single most important
 * next question (with concrete options); once it has enough, it produces the final
 * image-generation prompt. It never dumps a big preset list — it interviews, as a stylist.
 */
class StylistService
{
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
     * Ask the stylist for the next step given the accumulated choices.
     * @param string $type   garment type id
     * @param array  $history [['label'=>'Phom dáng','answer'=>'Ôm'], ...]
     * @return array {done:bool, question, options[], prompt, summary}
     */
    public function next(string $type, array $history): array
    {
        $typeName = $this->nameOf($type);
        $historyText = '';
        if ($history) {
            $historyText = implode("\n", array_map(fn ($h) => '- '.($h['label'] ?? 'Bước').': '.($h['answer'] ?? ''), $history));
        }

        $instruction = <<<PROMPT
You are a Vietnamese high-fashion creative director and AI stylist (thuật sỹ). Guide the user ONE STEP AT A TIME to craft a design prompt for: {$typeName}.

Choices so far:
{$historyText}

Rules:
- Until you have enough (aim 3-6 steps), ask the SINGLE most important next question in Vietnamese (short) and give 3-5 concrete, distinct options.
- Once enough, return the FINAL image-generation prompt in ENGLISH (precise: fabric, silhouette, color, fit, details, occasion, model pose, lighting, background) plus a short Vietnamese summary.
- NEVER list all future questions; only the next one.
- Reply ONLY with JSON, no extra text.

{"done":false,"question":"...","options":["a","b","c"]}
or
{"done":true,"prompt":"...","summary":"..."}
PROMPT;

        $json = $this->chat($instruction);
        if ($json === null) {
            return ['done' => false, 'question' => 'Bạn muốn tiếp tục mô tả thiết kế như thế nào?', 'options' => ['Phom dáng', 'Chất liệu', 'Màu sắc', 'Chi tiết / hoạ tiết', 'Xong — tạo prompt'], 'prompt' => '', 'summary' => ''];
        }

        $done = (bool) ($json['done'] ?? false);
        return [
            'done' => $done,
            'question' => (string) ($json['question'] ?? ''),
            'options' => array_values((array) ($json['options'] ?? [])),
            'prompt' => (string) ($json['prompt'] ?? ''),
            'summary' => (string) ($json['summary'] ?? ''),
        ];
    }

    /**
     * Text chat that returns parsed JSON. Tries Gemini (JSON-ready) then Qwen (DashScope).
     */
    protected function chat(string $instruction): ?array
    {
        $geminiKey = studio_api_key('gemini');
        $models = ['gemini-2.0-flash', 'gemini-1.5-flash']; // nhanh hơn cho thuật sỹ
        if ($geminiKey) {
            foreach ($models as $gm) {
                try {
                    $resp = Http::withHeaders(['x-goog-api-key' => $geminiKey])->timeout(60)
                        ->post('https://generativelanguage.googleapis.com/v1beta/models/'.$gm.':generateContent', [
                            'contents' => [['parts' => [['text' => $instruction]]]],
                            'generationConfig' => ['responseMimeType' => 'application/json'],
                        ]);
                    if ($resp->successful()) {
                        $out = trim((string) data_get($resp->json(), 'candidates.0.content.parts.0.text'));
                        $decoded = $this->decodeJson($out);
                        if ($decoded) { return $decoded; }
                    }
                } catch (\Throwable $e) { logger()->warning('Stylist Gemini failed: '.$e->getMessage()); }
            }
        }

        // Qwen fallback (DashScope text-generation).
        $key = studio_api_key('qwen') ?: studio_api_key('dashscope');
        $model = (string) studio_config('prompt_model', 'qwen-turbo'); // model nhanh
        if ($key) {
            try {
                $resp = Http::withToken($key)->timeout(60)
                    ->post(dashscope_base_url($key).'/api/v1/services/aigc/text-generation/generation', [
                        'model' => $model,
                        'input' => ['messages' => [['role' => 'user', 'content' => $instruction]]],
                        'parameters' => ['result_format' => 'message'],
                    ]);
                if ($resp->successful()) {
                    $out = trim((string) data_get($resp->json(), 'output.choices.0.message.content'));
                    $decoded = $this->decodeJson($out);
                    if ($decoded) { return $decoded; }
                }
            } catch (\Throwable $e) { logger()->warning('Stylist Qwen failed: '.$e->getMessage()); }
        }

        return null;
    }

    /** Parse a JSON string, tolerating a markdown-fenced or leading-text wrapper. */
    protected function decodeJson(string $out): ?array
    {
        $out = trim($out);
        $decoded = json_decode($out, true);
        if (is_array($decoded)) { return $decoded; }
        // Some models wrap JSON in fences or prose: extract the first { ... } object.
        $start = strpos($out, '{');
        $end = strrpos($out, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($out, $start, $end - $start + 1), true);
            if (is_array($decoded)) { return $decoded; }
        }
        return null;
    }
}