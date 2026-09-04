<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

/**
 * Deep AI assistance for the product create/edit form.
 *
 * Two-stage flow:
 *  1) analyzeImage() — reads the product cover image (vision, qwen3.8-flash /
 *     configurable) and returns a structured "understanding" (styles, colors,
 *     fabric, subject, keywords). Result is cached by image hash so re-clicking
 *     with the same image does NOT re-analyze.
 *  2) generate() — combines the current form state + the (cached) image
 *     analysis + your hint to produce / ENRICH product content + SEO. Each
 *     call refines based on what you've typed so far.
 *
 * Falls back to a deterministic stub when no API key is configured.
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

    // ------------------------------------------------------------- stage 1: vision

    public function analyzeImage(string $imagePath, bool $force = false): ?array
    {
        if (! is_file($imagePath)) {
            return null;
        }

        $key = sha1_file($imagePath).'|'.$this->model;
        if (! $force) {
            $cached = Cache::get('product_ai_img:'.$key);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $prompt = 'Đây là ảnh sản phẩm thời trang/phong cách sống. Phân tích và chỉ trả JSON hợp lệ: '
            .'{"styles":"phong cách","colors":"màu chủ đạo","fabric":"chất liệu","subject":"chủ thể/đối tượng",'
            .'"garment":"loại trang phục","keywords":["từ khóa","..."],"feeling":"cảm giác/thông điệp"}.'
            .' Viết ngắn gọn, tiếng Việt, dùng cho content & SEO.';

        $result = $this->vision($imagePath, $prompt);

        if (is_array($result)) {
            Cache::put('product_ai_img:'.$key, $result, 3600 * 24 * 30);
        }

        return $result;
    }

    protected function vision(string $imagePath, string $prompt): ?array
    {
        // Qwen (multimodal) — reuse the Studio's working vision setup so the
        // same key + model list (qwen3.8-flash …) that /studio uses is used here.
        [$b64, $mime] = $this->imageBase64($imagePath);
        $models = function_exists('studio_suggest_qwen_models') ? studio_suggest_qwen_models() : [$this->model];
        $keys = function_exists('studio_qwen_credentials') ? studio_qwen_credentials('vision') : [];
        if (empty($keys)) {
            $keys = array_values(array_filter([studio_api_key('qwen'), studio_api_key('dashscope')]));
        }
        foreach (array_values(array_unique($keys)) as $key) {
            foreach ($models as $model) {
                $base = dashscope_base_url($key).'/compatible-mode/v1';
                try {
                    $resp = Http::withToken($key)->timeout(90)->post($base.'/chat/completions', [
                        'model' => $model,
                        'messages' => [['role' => 'user', 'content' => [
                            ['type' => 'text', 'text' => $prompt],
                            ['type' => 'image_url', 'image_url' => ['url' => 'data:'.$mime.';base64,'.$b64]],
                        ]]],
                        'response_format' => ['type' => 'json_object'],
                    ]);
                    if ($resp->ok()) {
                        $json = $this->parseJson((string) data_get($resp->json(), 'choices.0.message.content'));
                        if ($json) {
                            return $json;
                        }
                    }
                } catch (\Throwable $e) {
                    // try next
                }
            }
        }

        // Gemini vision fallback.
        $geminiKey = studio_api_key('gemini');
        if ($geminiKey) {
            $model = studio_config('qwen_vision_model', 'gemini-2.5-flash');
            $b64 = base64_encode(file_get_contents($imagePath));
            try {
                $resp = Http::withHeaders(['x-goog-api-key' => $geminiKey])->timeout(90)
                    ->post('https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent', [
                        'contents' => [['parts' => [['text' => $prompt], ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => $b64]]]]],
                    ]);
                if ($resp->ok()) {
                    $json = $this->parseJson((string) data_get($resp->json(), 'candidates.0.content.parts.0.text'));
                    if ($json) {
                        return $json;
                    }
                }
            } catch (\Throwable $e) {
                // fall through
            }
        }

        return null;
    }

    // ------------------------------------------------------------- stage 2: generate

    /**
     * @param array $input ['name','category','brand','hint','short_description']
     * @param ?array $imageAnalysis cached/fresh vision result
     */
    public function generate(array $input, ?array $imageAnalysis = null): array
    {
        $prompt = $this->buildPrompt($input, $imageAnalysis);

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

        return $this->stub($input, $imageAnalysis);
    }

    protected function buildPrompt(array $input, ?array $imageAnalysis): string
    {
        $name = ($input['name'] ?? '') ?: 'sản phẩm thời trang/phong cách sống';
        $category = $input['category'] ?? '';
        $brand = $input['brand'] ?? '';
        $hint = $input['hint'] ?? '';
        $currentShort = $input['short_description'] ?? '';

        $img = '';
        if ($imageAnalysis) {
            $styles = $imageAnalysis['styles'] ?? '';
            $colors = $imageAnalysis['colors'] ?? '';
            $fabric = $imageAnalysis['fabric'] ?? '';
            $subject = $imageAnalysis['subject'] ?? '';
            $feeling = $imageAnalysis['feeling'] ?? '';
            $keywords = implode(', ', (array) ($imageAnalysis['keywords'] ?? []));
            $img = "\nDựa trên phân tích ảnh sản phẩm: phong cách={$styles}, màu sắc={$colors}, chất liệu={$fabric}, chủ thể={$subject}, cảm giác={$feeling}, từ khóa={$keywords}";
        }

        // Làm giàu: nếu đã có short_description thì yêu cầu cải thiện dựa trên đó.
        $refine = $currentShort ? "\nNội dung đã viết (hãy dùng làm nền, giữ ý chính, cải thiện làm giàu hơn): {$currentShort}" : '';

        return <<<PROMPT
Bạn là chuyên gia content & SEO thương mại điện tử thời trang Việt Nam. Tối giản, tinh tế, hấp dẫn, khác biệt.
{$img}
{$refine}

Thông tin người dùng đã nhập:
- Tên gợi ý: {$name}
- Danh mục: {$category}
- Thương hiệu: {$brand}
- Ý tưởng/điểm nhấn: {$hint}

Làm giàu & tinh chỉnh nội dung sản phẩm dựa trên các thông tin trên. Chỉ TRẢ VỀ JSON hợp lệ (không markdown):
{
  "suggested_name": "tên sản phẩm hấp dẫn (<=80)",
  "brand": "thương hiệu (giữ nguyên nếu có, nếu không gợi ý)",
  "short_description": "mô tả ngắn 1-2 câu (<=160)",
  "description": "<p>mô tả chi tiết <p><ul><li><blockquote>, có <h2>, ~120 từ, nêu chất liệu/màu/phong cách từ phân tích ảnh nếu có</p>",
  "meta_title": "SEO title <=60 ký tự",
  "meta_description": "SEO description 120-160 ký tự",
  "tags": ["tag1","tag2","tag3","tag4"]
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
                'max_tokens' => 900,
            ]);
            if (! $resp->ok()) {
                return null;
            }
            return $this->parseJson((string) data_get($resp->json(), 'choices.0.message.content'));
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
            return $this->parseJson((string) data_get($resp->json(), 'candidates.0.content.parts.0.text'));
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function parseJson(string $text): ?array
    {
        $text = preg_replace('/```(?:json)?/', '', $text);
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $text = substr($text, $start, $end - $start + 1);
        }
        $json = json_decode(trim($text), true);
        return is_array($json) ? $json : null;
    }

    protected function imageBase64(string $path): array
    {
        $mime = 'image/jpeg';
        $contents = file_get_contents($path);
        $data = base64_encode($contents);
        if (is_callable('getimagesize') && ($info = @getimagesize($path))) {
            $mime = $info['mime'] ?? $mime;
        }
        // Downscale if very large to keep the request light.
        if (strlen($data) > 4_000_000 && is_callable('imagecreatefromstring')) {
            try {
                $img = @imagecreatefromstring($contents);
                if ($img) {
                    $w = imagesx($img); $h = imagesy($img);
                    $scale = min(1, 1024 / max($w, $h));
                    if ($scale < 1) {
                        $nw = (int) ($w * $scale); $nh = (int) ($h * $scale);
                        $dst = imagecreatetruecolor($nw, $nh);
                        imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
                        ob_start(); imagejpeg($dst, null, 88); $data = base64_encode(ob_get_clean());
                    }
                }
            } catch (\Throwable $e) {
                // use raw
            }
        }
        return [$data, $mime];
    }

    protected function stub(array $input, ?array $imageAnalysis): array
    {
        $base = ($input['name'] ?? 'Sản phẩm') ?: 'Sản phẩm thời trang';
        $category = $input['category'] ?? '';
        $brand = $input['brand'] ?? 'Trillfa';
        $color = $imageAnalysis['colors'] ?? '';
        $fabric = $imageAnalysis['fabric'] ?? '';

        return [
            'suggested_name' => $base,
            'brand' => $brand,
            'short_description' => 'Sản phẩm '.($category ? $category.' ' : '').'được tuyển chọn kỹ lưỡng, thiết kế tối giản tinh tế, chất liệu cao cấp — dễ dàng phối đồ và bền bỉ theo thời gian.',
            'description' => '<h2>Mô tả sản phẩm</h2><p>'.$base.' '.($category ? 'thuộc bộ sưu tập '.$category.' ' : '').'của Trillfa Fa — thiết kế tối giản, chất liệu cao cấp'.($fabric ? ', '.$fabric : '').', tôn dáng và thoải mái.</p><ul><li>Chất liệu cao cấp, thân thiện môi trường</li><li>Thiết kế tối giản, dễ phối đồ</li><li>Đổi trả trong 7 ngày</li></ul><blockquote>"Tối giản không phải là ít, mà là đủ."</blockquote>',
            'meta_title' => $base.' | Trillfa Fa',
            'meta_description' => 'Khám phá '.$base.' '.($category ? 'trong '.$category.' ' : '').'— chất liệu cao cấp, thiết kế tối giản, giao nhanh, đổi trả dễ dàng.',
            'tags' => array_values(array_filter([$category, $color, $fabric, 'thời trang', 'phong cách', 'trillfa'])),
            'source' => 'stub',
        ];
    }
}
