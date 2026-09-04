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
        // Same model/key strategy as the Studio "Gợi ý từ ảnh": loop ALL
        // studio_suggest_qwen_models() × studio_qwen_credentials('vision'),
        // then Gemini, then a deterministic offline GD color fallback.
        [$b64, $mime] = $this->imageBase64($imagePath);
        $models = function_exists('studio_suggest_qwen_models') ? studio_suggest_qwen_models() : [$this->model];
        $keys = function_exists('studio_qwen_credentials') ? studio_qwen_credentials('vision') : [];
        if (empty($keys)) {
            $keys = array_values(array_filter([studio_api_key('qwen'), studio_api_key('dashscope')]));
        }
        // Bound attempts so the queued job always finishes well within the
        // frontend 180s poll window: 2 models × 3 keys × ~12s timeout max.
        $models = array_slice(array_values($models), 0, 2);
        $keys = array_slice(array_values(array_unique(array_filter($keys))), 0, 3);
        foreach (array_values(array_unique($keys)) as $key) {
            $base = dashscope_base_url($key).'/compatible-mode/v1';
            foreach ($models as $model) {
                try {
                    $resp = Http::withToken($key)->timeout(12)->post($base.'/chat/completions', [
                        'model' => $model,
                        'messages' => [['role' => 'user', 'content' => [
                            ['type' => 'text', 'text' => $prompt],
                            ['type' => 'image_url', 'image_url' => ['url' => 'data:'.$mime.';base64,'.$b64]],
                        ]]],
                        'response_format' => ['type' => 'json_object'],
                    ]);
                    // 429 = rate/quota limit on this key -> skip the rest of this key.
                    if ($resp->status() === 429 || is_qwen_quota_error((string) $resp->body())) {
                        continue 2;
                    }
                    if ($resp->ok()) {
                        $json = $this->parseJson((string) data_get($resp->json(), 'choices.0.message.content'));
                        if ($json) {
                            return $json;
                        }
                    }
                    $body = (string) $resp->body();
                    if (str_contains(strtolower($body), 'model_not_found') || str_contains(strtolower($body), 'model not exist') || $resp->status() === 404) {
                        continue; // try next model
                    }
                    break; // other error on this model -> next key
                } catch (\Throwable $e) {
                    continue;
                }
            }
        }

        // Gemini vision fallback.
        $geminiKey = studio_api_key('gemini');
        if ($geminiKey) {
            $model = function_exists('studio_suggest_gemini_model') ? studio_suggest_gemini_model() : studio_config('qwen_vision_model', 'gemini-2.5-flash');
            $b64 = base64_encode(file_get_contents($imagePath));
            try {
                $resp = Http::withHeaders(['x-goog-api-key' => $geminiKey])->timeout(15)
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

        // Offline GD color analysis — always produces a usable understanding.
        return $this->offlineAnalysis($imagePath);
    }

    /**
     * Deterministic offline analysis (GD): dominant/warmth/brightness -> a small
     * understanding so the AI always has something to reference without a key.
     */
    protected function offlineAnalysis(string $imagePath): array
    {
        try {
            $img = @imagecreatefromstring(@file_get_contents($imagePath));
            if (! $img) {
                return [];
            }
            $w = imagesx($img); $h = imagesy($img);
            $step = max(1, (int) round(max($w, $h) / 48));
            $rs = 0; $gs = 0; $bs = 0; $n = 0; $warm = 0; $bright = 0;
            for ($x = 0; $x < $w; $x += $step) {
                for ($y = 0; $y < $h; $y += $step) {
                    $c = imagecolorat($img, $x, $y);
                    $r = ($c >> 16) & 0xFF; $g = ($c >> 8) & 0xFF; $b = $c & 0xFF;
                    $rs += $r; $gs += $g; $bs += $b; $n++;
                    $warm += ($r > $b ? $r - $b : $b - $r) === ($r > $b ? $r - $b : 0) ? ($r - $b) : 0;
                    $bright += ($r + $g + $b) / 3;
                }
            }
            imagedestroy($img);
            $r = $n ? (int) round($rs / $n) : 128;
            $g = $n ? (int) round($gs / $n) : 128;
            $b = $n ? (int) round($bs / $n) : 128;
            $avg = ($r + $g + $b) / 3;
            $warmth = ($r - $b);
            $style = $warmth > 20 ? 'ấm áp, tự nhiên' : ($warmth < -20 ? 'lạnh, hiện đại thanh lịch' : 'trung tính, tối giản');
            $brightness = $avg > 170 ? 'sáng, tươi' : ($avg < 90 ? 'đậm, sang trọng' : 'trung bình');
            $color = $r > $g + 30 && $r > $b + 30 ? 'tông đỏ/nâu' : ($g > $r && $g > $b ? 'tông xanh' : ($b > $r && $b > $g ? 'tông xanh dương' : 'tông trung tính'));

            return [
                'styles' => $style,
                'colors' => $color.' ('.sprintf('#%02x%02x%02x', $r, $g, $b).')',
                'fabric' => $brightness,
                'subject' => 'hình ảnh sản phẩm',
                'feeling' => $style.', '.$brightness,
                'keywords' => [$color, $style],
            ];
        } catch (\Throwable $e) {
            return [];
        }
    }

    // ------------------------------------------------------------- stage 2: generate

    /**
     * @param array $input ['name','category','brand','hint','short_description']
     * @param ?array $imageAnalysis cached/fresh vision result
     */
    public function generate(array $input, ?array $imageAnalysis = null): array
    {
        $prompt = $this->buildPrompt($input, $imageAnalysis);

        // TEXT generation — mirror the Studio StylistService chat: try Qwen TEXT
        // models (qwen3.8-flash → …) over studio_qwen_credentials('prompt'), then Gemini.
        foreach ($this->qwenTextModels() as $model) {
            foreach ($this->qwenPromptKeys() as $key) {
                $result = $this->callQwen($prompt, $key, $model);
                if ($result) {
                    return $result;
                }
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

    /**
     * ONE multimodal call: see the image + generate the full structured content
     * + the image understanding, in a single request (like "Gợi ý từ ảnh" ~15s).
     * The understanding is cached by image so later clicks re-use it (text-only).
     */
    public function generateFromImage(array $input, string $imagePath, bool $force = false): array
    {
        $key = sha1_file($imagePath).'|'.(string) studio_config('qwen_prompt_model', 'qwen3.8-flash');
        $cached = $force ? null : Cache::get('product_ai_img:'.$key);
        if (is_array($cached)) {
            $out = $this->generate($input, $cached);
            $out['image_analyzed'] = true;
            $out['analysis_cached'] = true;

            return $out;
        }

        $result = $this->vision($imagePath, $this->buildImageContentPrompt($input));

        // LLM returned real content (has name/description) → use it.
        if (is_array($result) && (isset($result['suggested_name']) || isset($result['description']))) {
            $understanding = is_array($result['understanding'] ?? null) ? $result['understanding'] : null;
            if ($understanding) {
                Cache::put('product_ai_img:'.$key, $understanding, 3600 * 24 * 30);
            }
            unset($result['understanding']);
            $result['image_analyzed'] = true;
            $result['analysis_cached'] = false;

            return $result;
        }

        // vision() fell back to offline understanding (styles/colors only).
        $understanding = is_array($result) ? $result : $this->offlineAnalysis($imagePath);
        $out = $this->generate($input, $understanding);
        $out['image_analyzed'] = true;

        return $out;
    }

    protected function buildImageContentPrompt(array $input): string
    {
        $name = ($input['name'] ?? '') ?: 'sản phẩm thời trang/phong cách sống';
        $category = $input['category'] ?? '';
        $brand = $input['brand'] ?? '';
        $hint = $input['hint'] ?? '';
        $currentShort = $input['short_description'] ?? '';
        $refine = $currentShort ? "\nNội dung đã viết (giữ ý chính, làm giàu hơn): {$currentShort}" : '';

        return <<<PROMPT
Bạn là chuyên gia content & SEO thời trang Việt Nam. NHÌN ẢNH sản phẩm và kết hợp thông tin người dùng để viết nội dung có cấu trúc chuẩn ngành.
{$refine}
Thông tin người dùng: Tên={$name}, Danh mục={$category}, Thương hiệu={$brand}, Ý tưởng={$hint}.
Trả VỀ JSON hợp lệ duy nhất (không markdown):
{
  "understanding": {"styles":"phong cách","colors":"màu sắc","fabric":"chất liệu","subject":"chủ thể","feeling":"cảm giác","keywords":["k1","k2"]},
  "suggested_name": "tên hấp dẫn (<=80)",
  "brand": "thương hiệu",
  "short_description": "1-2 câu (<=160)",
  "description": "<h3>Phong cách</h3><p>…</p><h3>Chất liệu & chất lượng</h3><ul><li>…</li></ul><h3>Màu sắc</h3><p>…</p><h3>Thiết kế chi tiết</h3><ul><li>…</li></ul><h3>Phù hợp</h3><p>…</p><h3>Bảo quản</h3><ul><li>…</li></ul>",
  "meta_title": "<=60",
  "meta_description": "120-160 ký tự",
  "tags": ["t1","t2","t3"]
}
PROMPT;
    }

    protected function qwenTextModels(): array
    {
        $models = function_exists('studio_qwen_text_models') ? studio_qwen_text_models() : [$this->model];

        return array_slice(array_values($models), 0, 2);
    }

    protected function qwenPromptKeys(): array
    {
        $keys = function_exists('studio_qwen_credentials') ? studio_qwen_credentials('prompt') : [];
        if (empty($keys)) {
            $keys = array_values(array_filter([studio_api_key('qwen'), studio_api_key('dashscope')]));
        }

        return array_slice(array_values(array_unique(array_filter($keys))), 0, 3);
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
Bạn là chuyên gia content & SEO thương mại điện tử thời trang Việt Nam. Viết mô tả sản phẩm CHUẨN NGÀNH, có cấu trúc rõ ràng, không sáo rỗng, dựa trên phân tích ảnh + thông tin người dùng.
{$img}
{$refine}

Thông tin người dùng đã nhập:
- Tên gợi ý: {$name}
- Danh mục: {$category}
- Thương hiệu: {$brand}
- Ý tưởng/điểm nhấn: {$hint}

QUAN TRỌNG — mô tả chi tiết (key "description") phải là HTML có cấu trúc gồm CÁC MỤC (dùng <h3> mục + <ul><li>/<p>), bao phủ những gì người mua cần biết về một sản phẩm thời trang chuẩn ngành, ví dụ:
- **Phong cách**: phong cách/đặc tính chính (tối giản, thanh lịch, năng động…)
- **Loại trang phục / dáng**: (đầm, áo sơ mi, quần…) + dáng/kiểu dáng, form
- **Chất liệu & chất lượng**: chất liệu chính, cảm giác, độ bền, thoáng mát…
- **Màu sắc / họa tiết**: từ phân tích ảnh
- **Thiết kế chi tiết**: cổ tay, cúc, túi, đường may, chi tiết nổi bật
- **Phù hợp**: dịp/phong cách phối đồ (công sở, dạo phố, dự tiệc, mùa hè…)
- **Bảo quản & lưu ý**: giặt, ủi, bảo quản, hướng dẫn size
- Một câu cảm xúc / câu chuyện thương hiệu ngắn, khác biệt.

Chỉ TRẢ VỀ JSON hợp lệ (không markdown, không giải thích):
{
  "suggested_name": "tên sản phẩm hấp dẫn (<=80 ký tự)",
  "brand": "thương hiệu (giữ nguyên nếu có, nếu không gợi ý)",
  "short_description": "1-2 câu mô tả ngắn, hấp dẫn (<=160 ký tự)",
  "description": "<h3>Phong cách</h3><p>...</p><h3>Chất liệu</h3><ul><li>...</li></ul>... (đầy đủ các mục trên, ~180-250 từ)",
  "meta_title": "SEO title <=60 ký tự",
  "meta_description": "SEO description 120-160 ký tự",
  "tags": ["tag1","tag2","tag3","tag4"]
}
PROMPT;
    }

    protected function callQwen(string $prompt, string $key, ?string $model = null): ?array
    {
        $base = dashscope_base_url($key).'/compatible-mode/v1';
        try {
            $resp = Http::withToken($key)->timeout(12)->post($base.'/chat/completions', [
                'model' => $model ?: $this->model,
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'temperature' => 0.7,
                'max_tokens' => 1200,
                'response_format' => ['type' => 'json_object'],
            ]);
            // 429 = rate/quota limit -> stop trying (shared quota across keys).
            if ($resp->status() === 429 || is_qwen_quota_error((string) $resp->body())) {
                return null;
            }
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
            $resp = Http::withHeaders(['x-goog-api-key' => $key])->timeout(15)
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
        // Downscale aggressively so the vision request stays small/fast.
        if (is_callable('imagecreatefromstring')) {
            try {
                $img = @imagecreatefromstring($contents);
                if ($img) {
                    $w = imagesx($img); $h = imagesy($img);
                    $scale = min(1, 640 / max($w, $h));
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

        $style = $imageAnalysis['styles'] ?? '';
        $fabric = $imageAnalysis['fabric'] ?? '';
        $color = $imageAnalysis['colors'] ?? '';
        $subject = $imageAnalysis['subject'] ?? '';
        $feeling = $imageAnalysis['feeling'] ?? '';

        $descFabric = $fabric ?: 'chất liệu cao cấp, thoáng mát và bền bỉ';
        $descColor = $color ?: 'tông màu trung tính dễ phối đồ';
        $descStyle = $style ?: 'tối giản, tinh tế, hiện đại';
        $descGarment = $category ?: 'sản phẩm thời trang/phong cách sống';

        $description = '<h3>Phong cách</h3>'
            .'<p>'.$base.' mang phong cách '.$descStyle.', tôn dáng và thoải mái — dễ dàng kết hợp trong nhiều hoàn cảnh.</p>'
            .'<h3>Loại trang phục &amp; dáng</h3>'
            .'<p>'.$descGarment.' với đường cắt tối giản, form cân đối, phù hợp vóc dáng người Việt.</p>'
            .'<h3>Chất liệu &amp; chất lượng</h3>'
            .'<ul><li>'.$descFabric.', tạo cảm giác dễ chịu khi mặc</li><li>Đường may chắc chắn, bền bỉ theo thời gian</li></ul>'
            .'<h3>Màu sắc &amp; họa tiết</h3>'
            .'<p>'.$descColor.($subject ? ', phù hợp với ' . $subject : '').'.</p>'
            .'<h3>Thiết kế chi tiết</h3>'
            .'<ul><li>Chi tiết tối giản, tinh tế, dễ phối đồ</li><li>Form dáng tôn dáng, thoải mái khi vận động</li></ul>'
            .'<h3>Phù hợp</h3>'
            .'<p>Dễ phối cho công sở, dạo phố hoặc những buổi gặp gỡ nhẹ nhàng.</p>'
            .'<h3>Bảo quản &amp; lưu ý</h3>'
            .'<ul><li>Giặt nhẹ, tránh nước tẩy mạnh</li><li>Ủi ở nhiệt độ thấp để giữ form</li><li>Đổi trả trong 7 ngày</li></ul>'
            .'<blockquote>"'.($feeling ?: 'Tối giản không phải là ít, mà là đủ.').' — '.$brand.'"</blockquote>';

        return [
            'suggested_name' => $base,
            'brand' => $brand,
            'short_description' => ($category ? $category.' ' : '').$base.' '.($fabric ? $fabric.' ' : '').'— thiết kế '.$descStyle.', '.$descFabric.', dễ phối đồ và bền bỉ.',
            'description' => $description,
            'meta_title' => $base.' | '.$brand,
            'meta_description' => 'Khám phá '.$base.' '.($fabric ? $fabric.' ' : '').'— '.$descStyle.', chất liệu cao cấp, tôn dáng, giao nhanh, đổi trả dễ dàng.',
            'tags' => array_values(array_filter([$category, $style, $color, $fabric, 'thời trang', 'phong cách', 'trillfa'])),
            'source' => 'stub',
        ];
    }
}
