<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

/**
 * Deep AI assistance for the product create/edit form (admin).
 *
 * Config-driven and provider-ordered: it reads EVERYTHING (provider order, model
 * lists, API keys, timeouts, attempt bounds, image downscale, cache TTL) from
 * product_ai_* settings (DB -> env -> config/studio.php). Qwen is tried FIRST,
 * then Gemini — both over vision (multimodal single call) and text. Every path
 * ends in a deterministic offline fallback so the feature never returns empty.
 *
 * Two modes:
 *  - generateFromImage(): one multimodal call (see image + write full structured
 *    content + SEO) — the "Gợi ý từ ảnh" approach (~15s when a key works). The
 *    image understanding is cached by image hash, so later clicks (same image)
 *    only re-run the cheap TEXT step.
 *  - generate(): text-only — combines form state + (cached) understanding + hint.
 */
class ProductAIService
{
    protected array $providers;
    protected int $timeout;
    protected int $totalBudget;
    protected int $maxModels;
    protected int $maxKeys;
    protected int $downscaleMax;
    protected int $cacheTtl;
    protected float $temperature;
    protected int $maxTokens;

    /** Hard wall-clock deadline for the current attempt (sync request must never 504). */
    private float $deadline = 0.0;

    public function __construct()
    {
        $this->providers = product_ai_providers();
        $this->timeout = product_ai_timeout();
        $this->totalBudget = product_ai_total_budget();
        $this->maxModels = product_ai_max_models();
        $this->maxKeys = product_ai_max_keys();
        $this->downscaleMax = product_ai_downscale_max();
        $this->cacheTtl = product_ai_cache_ttl();
        $this->temperature = product_ai_temperature();
        $this->maxTokens = product_ai_max_tokens();
    }

    private function timedOut(): bool
    {
        return $this->deadline > 0.0 && microtime(true) >= $this->deadline;
    }

    /** Remaining seconds for the next HTTP call, clamped to the per-call timeout. */
    private function remainingTimeout(): int
    {
        $left = (int) floor($this->deadline - microtime(true));

        return max(1, min($this->timeout, $left));
    }

    // ------------------------------------------------------------- stage 1: vision

    public function analyzeImage(string $imagePath, bool $force = false): ?array
    {
        if (! is_file($imagePath)) {
            return null;
        }

        $key = $this->imageCacheKey($imagePath);
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
            Cache::put('product_ai_img:'.$key, $result, $this->cacheTtl);
        }

        return $result;
    }

    /**
     * Vision with an ALWAYS-non-empty result: provider attempts (qwen → gemini),
     * then the deterministic offline GD color analysis.
     */
    protected function vision(string $imagePath, string $prompt): ?array
    {
        $result = $this->attempt('vision', $prompt, $imagePath);

        return is_array($result) ? $result : $this->offlineAnalysis($imagePath);
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
            $rs = 0; $gs = 0; $bs = 0; $n = 0;
            for ($x = 0; $x < $w; $x += $step) {
                for ($y = 0; $y < $h; $y += $step) {
                    $c = imagecolorat($img, $x, $y);
                    $r = ($c >> 16) & 0xFF; $g = ($c >> 8) & 0xFF; $b = $c & 0xFF;
                    $rs += $r; $gs += $g; $bs += $b; $n++;
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
        $result = $this->attempt('text', $prompt);

        return is_array($result) ? $result : $this->stub($input, $imageAnalysis);
    }

    /**
     * ONE multimodal call: see the image + generate the full structured content
     * + the image understanding, in a single request (like "Gợi ý từ ảnh" ~15s).
     * The understanding is cached by image hash so later clicks re-use it (text-only).
     */
    public function generateFromImage(array $input, string $imagePath, bool $force = false): array
    {
        $key = $this->imageCacheKey($imagePath);
        $cached = $force ? null : Cache::get('product_ai_img:'.$key);
        if (is_array($cached)) {
            $out = $this->generate($input, $cached);
            $out['image_analyzed'] = true;
            $out['analysis_cached'] = true;

            return $out;
        }

        $result = $this->attempt('vision', $this->buildImageContentPrompt($input), $imagePath);

        // LLM returned real content (has name/description) → use it.
        if (is_array($result) && (isset($result['suggested_name']) || isset($result['description']))) {
            $understanding = is_array($result['understanding'] ?? null) ? $result['understanding'] : null;
            if ($understanding) {
                Cache::put('product_ai_img:'.$key, $understanding, $this->cacheTtl);
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

    // ------------------------------------------------------------- provider attempts

    /**
     * Try each provider in the configured order (qwen first). Returns a parsed
     * JSON result array, or null when no provider succeeded (caller falls back).
     */
    protected function attempt(string $kind, string $prompt, ?string $imagePath = null): ?array
    {
        if (! product_ai_enabled()) {
            return null;
        }

        $this->deadline = microtime(true) + $this->totalBudget;

        foreach ($this->providers as $provider) {
            if ($this->timedOut()) {
                return null;
            }
            $result = $provider === 'qwen'
                ? $this->attemptQwen($kind, $prompt, $imagePath)
                : $this->attemptGemini($kind, $prompt, $imagePath);

            if (is_array($result)) {
                return $result;
            }
        }

        return null;
    }

    protected function attemptQwen(string $kind, string $prompt, ?string $imagePath): ?array
    {
        $models = array_slice(array_values(
            $kind === 'vision' ? product_ai_qwen_vision_models() : product_ai_qwen_text_models()
        ), 0, $this->maxModels);
        if (empty($models)) {
            $models = ['qwen3.8-flash'];
        }

        $keys = array_values(array_unique(array_filter(
            studio_qwen_credentials($kind === 'vision' ? 'vision' : 'prompt')
        )));
        if (empty($keys)) {
            $keys = array_values(array_filter([studio_api_key('qwen'), studio_api_key('dashscope')]));
        }
        $keys = array_slice($keys, 0, $this->maxKeys);

        foreach ($keys as $key) {
            if ($this->timedOut()) {
                return null;
            }
            $base = dashscope_base_url($key).'/compatible-mode/v1';
            foreach ($models as $model) {
                if ($this->timedOut()) {
                    return null;
                }
                $content = $imagePath !== null
                    ? [
                        ['type' => 'text', 'text' => $prompt],
                        ['type' => 'image_url', 'image_url' => ['url' => $this->imageDataUri($imagePath)]],
                    ]
                    : $prompt;

                try {
                    $resp = Http::withToken($key)->timeout($this->remainingTimeout())->post($base.'/chat/completions', [
                        'model' => $model,
                        'messages' => [['role' => 'user', 'content' => $content]],
                        'temperature' => $this->temperature,
                        'max_tokens' => $this->maxTokens,
                        'response_format' => ['type' => 'json_object'],
                    ]);
                } catch (\Throwable $e) {
                    continue 2; // network error on this key -> next key
                }

                $status = $resp->status();
                $body = (string) $resp->body();

                // 429 = rate/quota limit on this key -> skip to the next key fast.
                if ($status === 429 || is_qwen_quota_error($body)) {
                    continue 2;
                }
                // Model not on this host/account -> try the next model.
                if ($status === 404 || str_contains(strtolower($body), 'model_not_found') || str_contains(strtolower($body), 'model not exist')) {
                    continue;
                }
                if ($resp->ok()) {
                    $json = $this->parseJson((string) data_get($resp->json(), 'choices.0.message.content'));

                    return $json ?: null;
                }

                return null; // other error -> stop trying this provider
            }
        }

        return null;
    }

    protected function attemptGemini(string $kind, string $prompt, ?string $imagePath): ?array
    {
        $key = studio_api_key('gemini');
        if (! $key) {
            return null;
        }

        if ($this->timedOut()) {
            return null;
        }

        $model = $kind === 'vision' ? product_ai_gemini_vision_model() : product_ai_gemini_text_model();
        $parts = [['text' => $prompt]];
        if ($imagePath !== null) {
            [$b64, $mime] = $this->imageBase64($imagePath);
            $parts[] = ['inline_data' => ['mime_type' => $mime, 'data' => $b64]];
        }

        try {
            $resp = Http::withHeaders(['x-goog-api-key' => $key])->timeout(min($this->timeout + 3, $this->remainingTimeout()))
                ->post('https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent', [
                    'contents' => [['parts' => $parts]],
                ]);
            if ($resp->ok()) {
                return $this->parseJson((string) data_get($resp->json(), 'candidates.0.content.parts.0.text'));
            }
        } catch (\Throwable $e) {
            // fall through
        }

        return null;
    }

    // ------------------------------------------------------------- prompts

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

    // ------------------------------------------------------------- helpers

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

    protected function imageCacheKey(string $imagePath): string
    {
        $fingerprint = is_file($imagePath) ? sha1_file($imagePath) : md5($imagePath);

        return $fingerprint.'|'.implode(',', $this->providers);
    }

    protected function imageBase64(string $path): array
    {
        $mime = 'image/jpeg';
        $contents = @file_get_contents($path);
        $data = base64_encode((string) $contents);
        if (function_exists('getimagesize') && ($info = @getimagesize($path))) {
            $mime = $info['mime'] ?? $mime;
        }
        // Downscale aggressively so the vision request stays small/fast.
        if (function_exists('imagecreatefromstring')) {
            try {
                $img = @imagecreatefromstring((string) $contents);
                if ($img) {
                    $w = imagesx($img); $h = imagesy($img);
                    $scale = min(1, $this->downscaleMax / max($w, $h));
                    if ($scale < 1) {
                        $nw = (int) ($w * $scale); $nh = (int) ($h * $scale);
                        $dst = imagecreatetruecolor($nw, $nh);
                        imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
                        ob_start(); imagejpeg($dst, null, 88); $data = base64_encode((string) ob_get_clean());
                    }
                }
            } catch (\Throwable $e) {
                // use raw
            }
        }
        return [$data, $mime];
    }

    protected function imageDataUri(string $path): string
    {
        [$b64, $mime] = $this->imageBase64($path);

        return 'data:'.$mime.';base64,'.$b64;
    }

    protected function stub(array $input, ?array $imageAnalysis): array
    {
        $base = ($input['name'] ?? 'Sản phẩm') ?: 'Sản phẩm thời trang';
        $category = $input['category'] ?? '';
        $brand = $input['brand'] ?? 'Trillfa';

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
