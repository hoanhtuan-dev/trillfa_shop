<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Deep AI assistance for the product create/edit form.
 * Generates suggested product content + SEO (name, short/description, meta
 * title/description, tags) from a small set of hints, using a configurable
 * LLM. Defaults to qwen3.8-flash (fallback gemini), and falls back to a
 * deterministic stub when no API key is configured so the UX always works.
 */
class ProductAIService
{
    protected string $provider;
    protected string $model;

    public function __construct()
    {
        $this->provider = strtolower((string) studio_config('prompt_provider', 'qwen'));
        $this->model = (string) studio_config('qwen_prompt_model', 'qwen3.8-flash');
    }

    /**
     * @param array $input ['name','category','brand','hint','short_description']
     */
    public function generate(array $input): array
    {
        $prompt = $this->buildPrompt($input);

        $qwenKey = studio_api_key('qwen') ?: studio_api_key('dashscope');
        if ($this->provider !== 'gemini' && $qwenKey) {
            $result = $this->callQwen($prompt, $qwenKey);
            if ($result) {
                return $result;
            }
        }

        $geminiKey = studio_api_key('gemini');
        if ($geminiKey) {
            $result = $this->callGemini($prompt, $geminiKey);
            if ($result) {
                return $result;
            }
        }

        return $this->stub($input);
    }

    protected function buildPrompt(array $input): string
    {
        $name = ($input['name'] ?? '') ?: 'sản phẩm thời trang/phong cách sống';
        $category = $input['category'] ?? '';
        $brand = $input['brand'] ?? '';
        $hint = $input['hint'] ?? '';

        return <<<PROMPT
Bạn là chuyên gia content & SEO thương mại điện tử thời trang Việt Nam. Hãy viết nội dung sản phẩm bằng tiếng Việt, giọng tối giản, tinh tế, hấp dẫn, khác biệt.

Thông tin đầu vào:
- Tên sản phẩm gợi ý: {$name}
- Danh mục: {$category}
- Thương hiệu: {$brand}
- Ý tưởng/điểm nhấn: {$hint}

Chỉ TRẢ VỀ JSON hợp lệ (không markdown, không giải thích) với cấu trúc:
{
  "suggested_name": "tên sản phẩm hấp dẫn (<=80 ký tự)",
  "brand": "thương hiệu (giữ nguyên nếu có, hoặc gợi ý)",
  "short_description": "mô tả ngắn 1-2 câu (<=160 ký tự)",
  "description": "<p>mô tả chi tiết nhiều đoạn <p>, <ul><li>, <blockquote>, có tiêu đề <h2>, tối đa ~120 từ</p>",
  "meta_title": "SEO title <=60 ký tự",
  "meta_description": "SEO description 120-160 ký tự",
  "tags": ["tag1","tag2","tag3"]
}
PROMPT;
    }

    protected function callQwen(string $prompt, string $key): ?array
    {
        $base = dashscope_base_url($key).'/compatible-mode/v1';
        try {
            $resp = Http::withToken($key)->timeout(90)->post($base.'/chat/completions', [
                'model' => $this->model,
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'temperature' => 0.7,
                'max_tokens' => 800,
            ]);
            if (! $resp->ok()) {
                return null;
            }
            $text = trim((string) data_get($resp->json(), 'choices.0.message.content'));
            return $this->parseJson($text);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function callGemini(string $prompt, string $key): ?array
    {
        $model = studio_config('prompt_model', 'gemini-2.5-flash');
        try {
            $resp = Http::withHeaders(['x-goog-api-key' => $key])->timeout(90)
                ->post('https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent', [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                ]);
            if (! $resp->ok()) {
                return null;
            }
            $text = trim((string) data_get($resp->json(), 'candidates.0.content.parts.0.text'));
            return $this->parseJson($text);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function parseJson(string $text): ?array
    {
        // Strip markdown fences / surrounding text.
        $text = preg_replace('/```(?:json)?/', '', $text);
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $text = substr($text, $start, $end - $start + 1);
        }
        $json = json_decode(trim($text), true);
        if (! is_array($json)) {
            return null;
        }
        return $json;
    }

    protected function stub(array $input): array
    {
        $base = ($input['name'] ?? 'Sản phẩm') ?: 'Sản phẩm thời trang';
        $category = $input['category'] ?? '';
        $brand = $input['brand'] ?? 'Trillfa';

        return [
            'suggested_name' => $base,
            'brand' => $brand,
            'short_description' => 'Sản phẩm '.($category ? $category.' ' : '').'được tuyển chọn kỹ lưỡng, thiết kế tối giản tinh tế, chất liệu cao cấp — dễ dàng phối đồ và bền bỉ theo thời gian.',
            'description' => '<h2>Mô tả sản phẩm</h2><p>'.$base.' '.($category ? 'thuộc bộ sưu tập '.$category.' ' : '').'của Trillfa Fa — thiết kế tối giản, chất liệu cao cấp, tôn dáng và thoải mái.</p><ul><li>Chất liệu cao cấp, thân thiện môi trường</li><li>Thiết kế tối giản, dễ phối đồ</li><li>Đổi trả trong 7 ngày</li></ul><blockquote>"Tối giản không phải là ít, mà là đủ."</blockquote>',
            'meta_title' => $base.' | Trillfa Fa',
            'meta_description' => 'Khám phá '.$base.' '.($category ? 'trong ' . $category . ' ' : '').'— chất liệu cao cấp, thiết kế tối giản, giao nhanh, đổi trả dễ dàng.',
            'tags' => array_values(array_filter([$category, 'thời trang', 'phong cách', 'trillfa'])),
            'source' => 'stub',
        ];
    }
}
