<?php

namespace App\Http\Controllers;

use App\Jobs\RenderImageJob;
use App\Jobs\RenderVideoJob;
use App\Models\Generation;
use App\Models\Preset;
use App\Models\Product;
use App\Services\GeminiService;
use App\Services\StyleSuggestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class StudioController extends Controller
{
    /**
     * Registry các thao tác theo vùng chọn trên canvas ("Region Tools") — MỞ RỘNG:
     * muốn thêm thao tác mới (vd: recolor, remove-person, replace…) chỉ cần:
     *   1) thêm entry vào đây (+ label),
     *   2) thêm entry mirror trong resources/js/studio/store.js → regionOps,
     *   3) (tuỳ chọn) bổ sung nhánh prompt trong regionPrompt().
     * Luồng chung (mask → AI edit / local fill → generation + poll) tự động áp dụng.
     */
    protected const REGION_OPS = [
        'erase' => ['label' => 'Xóa vùng', 'needs_prompt' => false],
        'replace' => ['label' => 'Thay vùng', 'needs_prompt' => true],
    ];

    /**
     * Vue-style studio (migration preview): mounts the Vue 3 + Pinia studio app.
     */
    public function studioVue()
    {
        return view('studio.vue');
    }

    public function settingsVue() { return view('studio.settings-vue'); }

    public function index()
    {
        // The Vue studio loads its own data; keep this method minimal.
        return view('studio.vue');
    }

    public function storeProject(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'base_concept' => ['nullable', 'string', 'max:1000'],
        ]);

        $project = auth()->user()->projects()->create($data);

        if ($request->wantsJson()) {
            return response()->json(['project_id' => $project->id, 'name' => $project->name]);
        }

        return redirect()->route('studio.index')->with('success', 'Đã tạo dự án.');
    }

    /**
     * Ideation — Gemini turns idea + presets into English image/video prompts.
     */

    /**
     * 2D image generation — async via queue.
     */
    public function generate(Request $request)
    {
        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:4000'],
            'resolution' => ['nullable', 'string', 'in:1K,2K'],
            'ratio' => ['nullable', 'string', 'in:1:1,4:3,3:4,16:9,9:16,4:5,21:9,19:6'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'history_id' => ['nullable', 'integer', 'exists:prompts_history,id'],
            'variants' => ['nullable', 'integer', 'min:1', 'max:4'],
            'base_image' => ['nullable', 'string', 'max:2048'],
            'edit' => ['nullable', 'string', 'in:1,true'],
            'creative_level' => ['nullable', 'integer', 'min:1', 'max:10'],
            'texture' => ['nullable', 'integer', 'min:0', 'max:10'],
            'negative_prompt' => ['nullable', 'string', 'max:2000'],
        ]);

        $userPrompt = (string) $data['prompt'];
        $creativeLevel = (int) ($data['creative_level'] ?? studio_config('creative_level', 6));
        $texture = (int) ($data['texture'] ?? studio_config('texture', 5));
        $customNegative = $data['negative_prompt'] ?? null;
        $shouldEnrich = (bool) studio_config('enrich_prompt', true);

        // Enrich the prompt with CreativeDirectionService
        $direction = app(\App\Services\CreativeDirectionService::class);
        if ($shouldEnrich) {
            $enriched = $direction->enrichGeneratePrompt($userPrompt, $creativeLevel, $texture, $customNegative);
            $finalPrompt = $enriched['prompt'];
            $negativePrompt = $enriched['negative_prompt'];
        } else {
            $finalPrompt = $userPrompt;
            $negativePrompt = $customNegative ?: $direction->negativePrompt([], $creativeLevel);
        }

        // Ensure a shared prompt-history so all variants group as one "generation run".
        if (empty($data['history_id'])) {
            $history = auth()->user()->prompts()->create([
                'idea' => null,
                'image_prompt_en' => $finalPrompt,
                'video_prompt_en' => null,
                'json_response' => [
                    'image_prompt_en' => $finalPrompt,
                    'creative_level' => $creativeLevel,
                    'texture' => $texture,
                    'negative_prompt' => $negativePrompt,
                ],
            ]);
            $data['history_id'] = $history->id;
        }

        $data['prompt'] = $finalPrompt;
        $data['negative_prompt'] = $negativePrompt;

        $cost = (int) studio_config('image_credits', 1);
        $variants = max(1, min(4, (int) ($data['variants'] ?? 1)));

        $items = [];
        for ($i = 0; $i < $variants; $i++) {
            $items[] = $this->queueGeneration('image', $data, $cost)->getData(true);
        }

        return response()->json([
            'items' => $items,
            'credits_left' => auth()->user()->fresh()->credits_balance,
        ]);
    }

    /**
     * Video catwalk render — async via queue.
     */
    public function renderVideo(Request $request)
    {
        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:4000'],
            'base_image' => ['nullable', 'string', 'max:2048'],
            'camera' => ['nullable', 'string', 'max:1000'], // Kịch bản quay (video_scene) injection có thể dài
            'model' => ['nullable', 'string', 'max:120'], // video-model override (multi-model selector)
            'model_registry_id' => ['nullable', 'integer'],
            'provenance' => ['nullable', 'string', 'max:20'],
            'resolution' => ['nullable', 'string', 'in:480,720,1080'],
            'duration' => ['nullable', 'string', 'in:5,8,10,15,20'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'history_id' => ['nullable', 'integer', 'exists:prompts_history,id'],
        ]);

        $cost = (int) studio_config('video_credits', 10);

        // Multi-model selector: resolve the chosen registered model (unique id) so the render uses
        // exactly that provider + model_id (not the highest-priority default). Avoids model_id collisions.
        if (! empty($data['model_registry_id'])) {
            $reg = \App\Models\StudioModel::find($data['model_registry_id']);
            if ($reg) {
                $data['provider'] = $reg->provider;
                $data['model'] = $reg->model_id;
                $data['api_key_ref'] = $reg->api_key_ref;
            }
        } elseif (! empty($data['model'])) {
            set_setting('studio_video_model', $data['model']);
        }

        return $this->queueGeneration('video', $data, $cost);
    }

    /**
     * Inpainting / refinement — reuses the source image as base (stub).
     */
    public function inpaint(Request $request, Generation $generation)
    {
        abort_unless($generation->user_id === auth()->id(), 403);

        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:4000'],
            'preserve_background' => ['nullable', 'boolean'],
            'preserve_face' => ['nullable', 'boolean'],
            // Mask (tích hợp region selection vào Inpaint)
            'mask_mode' => ['nullable', 'string', 'in:rect,brush'],
            'region' => ['nullable', 'array'],
            'region.x' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'region.y' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'region.w' => ['nullable', 'numeric', 'min:0.005', 'max:1'],
            'region.h' => ['nullable', 'numeric', 'min:0.005', 'max:1'],
            'mask_data' => ['nullable', 'string', 'max:2000000'],
            // Ảnh ĐANG HIỂN THỊ trên canvas (upscaleSrc) — mask được vẽ theo ảnh này,
            // nên build mask & base phải dùng ĐÚNG ảnh này (không phải generation.media_url).
            'source_url' => ['nullable', 'string', 'max:2048'],
        ]);

        $preserveBg = ! empty($data['preserve_background']);
        $preserveFace = ! empty($data['preserve_face']);

        $sourceUrl = trim((string) ($data['source_url'] ?? ''));
        if ($sourceUrl === '') { $sourceUrl = (string) $generation->media_url; }
        // Tự downscale về ≤1600px để mask & base CÙNG kích thước model xử lý (kết quả không nhòe)
        $sourceUrl = $this->downscaleSource($sourceUrl, 1600);

        // Nếu có mask → tạo mask image và gửi kèm (dùng ĐÚNG ảnh đang hiển thị)
        $maskUrl = null;
        $maskMode = (string) ($data['mask_mode'] ?? '');
        if ($maskMode !== '' && ! empty($data['region']) && $sourceUrl) {
            $maskUrl = $this->buildMaskImage($sourceUrl, $data['region'], $maskMode, $data['mask_data'] ?? null);
        }

        $promptInstruction = 'Using the provided image as the exact base, edit it surgically. Change ONLY: '.$request->input('prompt')
            .'. Preserve everything else exactly as in the original image — '
            .($preserveFace ? 'the model\'s face and identity, skin tone and hair, ' : '')
            .'pose, body proportions, garment structure and fit, fabric, all colours except the edited element, lighting, shadows, camera angle, composition'
            .($preserveBg ? ', and background' : '')
            .'. Do not restyle, do not add new elements, do not change the setting. '
            .'Output must be clean and sharp: no blur, no noise, no color banding, no posterization, no compression artifacts, no halftone or moiré — keep smooth tonal gradients and crisp clean edges.';

        if ($maskUrl) {
            $promptInstruction .= ' A mask image is provided (same size as the base): its BLACK region is the exact area to edit — change ONLY that black region and keep every pixel outside it identical to the original image.';
        }

        $data['prompt'] = $promptInstruction;

        $data['base_image'] = $sourceUrl;
        if ($maskUrl) {
            $data['mask_image'] = $maskUrl;
        }
        $data['edit'] = true;

        $cost = (int) studio_config('image_credits', 1);

        return $this->queueGeneration('image', $data, $cost, $generation);
    }

    /**
     * Build a mask image (WHITE=keep, BLACK=edit) from normalized region coords.
     * Same size as the source image. Supports rect and brush modes.
     */
    protected function buildMaskImage(string $sourceUrl, array $region, string $maskMode, ?string $brushData): ?string
    {
        $file = null;
        foreach ([public_path(ltrim((string) parse_url($sourceUrl, PHP_URL_PATH), '/')), storage_path('app/public/'.str_replace('storage/', '', ltrim((string) parse_url($sourceUrl, PHP_URL_PATH), '/')))] as $cand) {
            if (is_file($cand)) { $file = $cand; break; }
        }
        if (! $file) return null;
        $src = @imagecreatefromstring((string) file_get_contents($file));
        if (! $src) return null;

        $w = imagesx($src); $h = imagesy($src);
        imagedestroy($src);

        $mask = imagecreatetruecolor($w, $h);
        // Nền TRẮNG = giữ nguyên
        imagefilledrectangle($mask, 0, 0, $w - 1, $h - 1, imagecolorallocate($mask, 255, 255, 255));

        if ($maskMode === 'brush' && ! empty($brushData)) {
            // Brush mode: frontend gửi mask_data (base64 PNG, nền TRẮNG + nét ĐEN)
            $b64 = (string) $brushData;
            if (str_starts_with($b64, 'data:')) {
                $comma = strpos($b64, ',');
                if ($comma !== false) $b64 = substr($b64, $comma + 1);
            }
            $brushRaw = base64_decode($b64, true);
            if ($brushRaw !== false && $brushRaw !== '') {
                $brushImg = @imagecreatefromstring($brushRaw);
                if ($brushImg) {
                    $bw = imagesx($brushImg); $bh = imagesy($brushImg);
                    if ($bw > 0 && $bh > 0) {
                        if ($bw !== $w || $bh !== $h) {
                            $resized = imagecreatetruecolor($w, $h);
                            imagecopyresampled($resized, $brushImg, 0, 0, 0, 0, $w, $h, $bw, $bh);
                            imagedestroy($brushImg);
                            $brushImg = $resized;
                        }
                        imagecopy($mask, $brushImg, 0, 0, 0, 0, $w, $h);
                        imagedestroy($brushImg);
                    }
                }
            }
        } else {
            // Rect mode: vẽ hình chữ nhật ĐEN
            $rx = max(0.0, min(0.99, (float) ($region['x'] ?? 0)));
            $ry = max(0.0, min(0.99, (float) ($region['y'] ?? 0)));
            $rw = max(0.005, min(1 - $rx, (float) ($region['w'] ?? 0.5)));
            $rh = max(0.005, min(1 - $ry, (float) ($region['h'] ?? 0.5)));
            $px = (int) round($rx * $w); $py = (int) round($ry * $h);
            $pw = max(8, min($w - $px, (int) round($rw * $w)));
            $ph = max(8, min($h - $py, (int) round($rh * $h)));
            imagefilledrectangle($mask, $px, $py, $px + $pw - 1, $py + $ph - 1, imagecolorallocate($mask, 0, 0, 0));
        }

        // Feather: mép vùng edit chuyển mềm — model tôn trọng biên, ảnh gộp không seam.
        $this->featherMaskEdges($mask);

        $name = 'studio/mask-'.Str::uuid().'.png';
        \Illuminate\Support\Facades\Storage::disk('public')->put($name, $this->pngBytes($mask));
        imagedestroy($mask);

        return '/storage/'.$name;
    }

    /**
     * Tự downscale ảnh về cạnh dài tối đa $maxSide (mặc định 1600) TRƯỚC khi đưa model edit.
     * Model edit giới hạn ~1600px; nếu gửi ảnh lớn hơn, kết quả bị model thu nhỏ rồi
     * fitToSourceSize phóng lại → nhòe. Downscale 1 lần ở đây giúp mask & base CÙNG kích thước,
     * kết quả luôn sắc nét, không nhòe/sọc.
     */
    protected function downscaleSource(string $url, int $maxSide = 1600): string
    {
        $file = null;
        foreach ([public_path(ltrim((string) parse_url($url, PHP_URL_PATH), '/')), storage_path('app/public/'.str_replace('storage/', '', ltrim((string) parse_url($url, PHP_URL_PATH), '/')))] as $cand) {
            if (is_file($cand)) { $file = $cand; break; }
        }
        if (! $file) { return $url; }
        $img = @imagecreatefromstring((string) file_get_contents($file));
        if (! $img) { return $url; }
        $w = imagesx($img); $h = imagesy($img);
        $long = max($w, $h);
        if ($long <= $maxSide) { imagedestroy($img); return $url; }
        $scale = $maxSide / $long;
        $nw = (int) max(1, round($w * $scale));
        $nh = (int) max(1, round($h * $scale));
        $out = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);
        $name = 'studio/ds-'.Str::uuid().'.png';
        \Illuminate\Support\Facades\Storage::disk('public')->put($name, $this->pngBytes($out));
        imagedestroy($out);
        return '/storage/'.$name;
    }

    /**
     * i2i — Ghép (thay thế) khuôn mặt cho người mẫu.
     * Ảnh gốc (image) + ảnh khuôn mặt tham chiếu (face) → editImage dùng face_ref.
     */
    public function faceSwap(Request $request)
    {
        $data = $request->validate([
            'image' => ['required', 'string', 'max:2048'],
            'face' => ['required', 'string', 'max:2048'],
        ]);

        $finalPrompt = 'Swap the ENTIRE head — face, hairstyle, ears, forehead, jawline and neck — with the reference face photo. '
            .'Match the reference face\'s identity, hairstyle, facial features, ears and head proportions exactly. '
            .'Scale the new head/face to fit the ORIGINAL head size and body proportions naturally — do NOT enlarge, stretch or distort the head/face. '
            .'Blend skin tone, hairline and lighting seamlessly with the original body and background. '
            .'Keep the garment, pose, body, background and composition exactly unchanged. Sharp, realistic, no blur, no artifacts, no distortion.';

        $cost = (int) studio_config('image_credits', 1);

        return $this->queueGeneration('image', [
            'prompt' => $finalPrompt,
            'base_image' => $this->downscaleSource((string) $data['image'], 1600),
            'edit' => true,
            'face_ref' => (string) $data['face'],
        ], $cost);
    }

    /**
     * i2i — Tạo lại ảnh từ ảnh cho trước (Reimagine / Variation).
     * Dùng ảnh gốc làm base (không mask) + prompt → model edit tạo biến thể mới.
     */
    public function reimagine(Request $request)
    {
        $data = $request->validate([
            'image' => ['required', 'string', 'max:2048'],
            'prompt' => ['required', 'string', 'max:4000'],
            'similarity' => ['nullable', 'integer', 'min:0', 'max:100'],
            'variants' => ['nullable', 'integer', 'min:1', 'max:4'],
        ]);

        $sim = (int) ($data['similarity'] ?? 70);
        $userPrompt = trim((string) $data['prompt']);
        $finalPrompt = 'Reimagine this image into a new variation. Keep about '.$sim
            .'% similarity to the original (same subject, identity and key layout), but apply: '.$userPrompt
            .'. Keep high quality, realistic, studio lighting, sharp details.';

        $cost = (int) studio_config('image_credits', 1);
        $variants = max(1, min(4, (int) ($data['variants'] ?? 1)));

        $items = [];
        for ($i = 0; $i < $variants; $i++) {
            $items[] = $this->queueGeneration('image', [
                'prompt' => $finalPrompt,
                'base_image' => $this->downscaleSource((string) $data['image'], 1600),
                'edit' => true,
            ], $cost)->getData(true);
        }

        return response()->json([
            'items' => $items,
            'credits_left' => auth()->user()->fresh()->credits_balance,
        ]);
    }

    /**
     * i2i — Ghép 2–3 ảnh thành 1 (Compose / Blend).
     * Ảnh đầu = base (giữ chủ thể/bố cục); các ảnh sau = ref images để hòa trộn vào cảnh.
     */
    public function compose(Request $request)
    {
        $data = $request->validate([
            'images' => ['required', 'array', 'min:2', 'max:3'],
            'images.*' => ['string', 'max:2048'],
            'prompt' => ['required', 'string', 'max:4000'],
            'layout' => ['nullable', 'string', 'max:100'],
            'variants' => ['nullable', 'integer', 'min:1', 'max:4'],
            'mode' => ['nullable', 'string', 'in:compose,tryon,faceswap'],
        ]);

        $imgs = array_values(array_slice($data['images'], 0, 3));
        $base = $this->downscaleSource((string) $imgs[0], 1600);
        $refs = array_slice($imgs, 1);
        $userPrompt = trim((string) $data['prompt']);
        $isTryon = ($data['mode'] ?? '') === 'tryon';
        $isFaceSwap = ($data['mode'] ?? '') === 'faceswap';

        if ($isTryon) {
            // Thử đồ ảo (chiến lược): @image1 = trang phục, @image2 = pose, @image3 = bối cảnh (tuỳ chọn).
            $finalPrompt = 'Virtual try-on: put the garment in @image1 onto the model pose in @image2, '
                .'keeping the pose, body proportions, skin tone and lighting of @image2. '
                .'Make the garment fit naturally with correct drape, texture and shadows.';
            if (count($refs) > 1) {
                $finalPrompt .= ' Place the result into the background of @image3.';
            }
            $finalPrompt .= ' '.$userPrompt;
        } elseif ($isFaceSwap) {
            // Thay khuôn mặt: @image1 = người mẫu (base), @image2 = khuôn mặt tham chiếu.
            // Prompt kiểm soát tại Settings → Studio → "Prompt thay khuôn mặt".
            $finalPrompt = (string) studio_config('faceswap_prompt', 'Face swap: replace the face of @image1 with the face in @image2, matching identity, hairstyle, ears and proportions. Keep garment, pose, body, background unchanged.').' '.$userPrompt;
        } else {
            $finalPrompt = 'Compose these images into a single cohesive, realistic image. '
                .'The FIRST image is the main base (keep its subject and overall layout). '
                .'Blend the other '.count($refs).' reference image(s) naturally into the scene. '.$userPrompt;
        }

        // Định danh @image1/@image2/@image3 → mô tả chuẩn cho model hiểu đúng từng ảnh.
        $tagMap = [
            '@image1' => 'the FIRST image',
            '@image2' => 'the SECOND image',
            '@image3' => 'the THIRD image',
        ];
        $finalPrompt = strtr($finalPrompt, $tagMap);

        $cost = (int) studio_config('image_credits', 1);
        $variants = max(1, min(4, (int) ($data['variants'] ?? 1)));

        // Thay khuôn mặt: ảnh thứ 2 (refs[0]) là khuôn mặt tham chiếu → face_ref; còn lại là ref_images.
        $faceRef = $isFaceSwap && isset($refs[0]) ? (string) $refs[0] : null;
        $remainingRefs = $isFaceSwap ? array_slice($refs, 1) : $refs;
        logger()->info('Compose mode', ['mode' => $data['mode'] ?? '', 'is_faceswap' => $isFaceSwap, 'face_ref' => (bool) $faceRef, 'images' => count($imgs)]);

        $items = [];
        for ($i = 0; $i < $variants; $i++) {
            $items[] = $this->queueGeneration('image', [
                'prompt' => $finalPrompt,
                'base_image' => $base,
                'edit' => true,
                'ref_images' => $remainingRefs,
                'face_ref' => $faceRef,
            ], $cost)->getData(true);
        }

        return response()->json([
            'items' => $items,
            'credits_left' => auth()->user()->fresh()->credits_balance,
        ]);
    }

    /**
     * Region edit ("xóa theo vùng chọn trên canvas"): nhận vùng chọn (normalized 0..1),
     * dựng mask ảnh (TRẮNG = giữ nguyên, ĐEN = vùng chỉnh sửa, cùng kích thước ảnh gốc) rồi:
     *  - có key AI (Qwen Edit / DashScope / Qwen) → gửi mask + prompt cho model edit (async, poll như inpaint);
     *  - chưa có key → lấp vùng bằng GD cục bộ (vẫn hoạt động chế độ stub), trả completed ngay.
     * Trả về đúng cấu trúc generation để frontend dùng chung pollGeneration().
     */
    public function regionEdit(Request $request, Generation $generation)
    {
        abort_unless($generation->user_id === auth()->id(), 403);

        $data = $request->validate([
            'op' => ['required', 'string', 'in:'.implode(',', array_keys(self::REGION_OPS))],
            'region' => ['required', 'array'],
            'region.x' => ['required', 'numeric', 'min:0', 'max:1'],
            'region.y' => ['required', 'numeric', 'min:0', 'max:1'],
            'region.w' => ['required', 'numeric', 'min:0.005', 'max:1'],
            'region.h' => ['required', 'numeric', 'min:0.005', 'max:1'],
            'prompt' => ['nullable', 'string', 'max:2000'],
            'mask_mode' => ['nullable', 'string', 'in:rect,brush'],
            // Brush mode: frontend vẽ mask tự do → gửi mask_data (base64 PNG).
            'mask_data' => ['nullable', 'string', 'max:2000000'],
            // Ảnh ĐANG HIỂN THỊ trên canvas (upscaleSrc) — backend sửa đúng ảnh này để vùng
            // chọn khớp vị trí tái tạo (tránh lệch do layer/scale/aspect khác preview.media_url).
            'source_url' => ['nullable', 'string', 'max:2048'],
        ]);

        $op = (string) $data['op'];
        if (self::REGION_OPS[$op]['needs_prompt'] && trim((string) ($data['prompt'] ?? '')) === '') {
            return response()->json(['message' => 'Nhập mô tả nội dung thay thế cho vùng chọn.'], 422);
        }

        // Ưu tiên ảnh đang hiển thị (source_url) để vùng chọn khớp vị trí tái tạo; fallback media_url.
        $sourceUrl = trim((string) ($data['source_url'] ?? ''));
        if ($sourceUrl === '') { $sourceUrl = (string) $generation->media_url; }
        if ($sourceUrl === '') {
            return response()->json(['message' => 'Ảnh nguồn chưa có kết quả.'], 422);
        }

        $file = null;
        foreach ([public_path(ltrim((string) parse_url($sourceUrl, PHP_URL_PATH), '/')), storage_path('app/public/'.str_replace('storage/', '', ltrim((string) parse_url($sourceUrl, PHP_URL_PATH), '/')))] as $cand) {
            if (is_file($cand)) { $file = $cand; break; }
        }
        if (! $file) { return response()->json(['message' => 'Không đọc được ảnh nguồn.'], 422); }
        $src = @imagecreatefromstring((string) file_get_contents($file));
        if (! $src) { return response()->json(['message' => 'Ảnh nguồn không hợp lệ.'], 422); }

        $w = imagesx($src); $h = imagesy($src);
        // region normalized -> pixel rect (clamp, tối thiểu 8px)
        $rx = max(0.0, min(0.99, (float) $data['region']['x']));
        $ry = max(0.0, min(0.99, (float) $data['region']['y']));
        $rw = max(0.005, min(1 - $rx, (float) $data['region']['w']));
        $rh = max(0.005, min(1 - $ry, (float) $data['region']['h']));
        $px = (int) round($rx * $w); $py = (int) round($ry * $h);
        $pw = max(8, min($w - $px, (int) round($rw * $w)));
        $ph = max(8, min($h - $py, (int) round($rh * $h)));

        // === DEEP REDESIGN: CROP + INPAINT + PASTE ===
        // Crop vùng chọn + ngữ cảnh quanh thành ảnh nhỏ tập trung → gửi AI sửa TRÊN CROP
        // (đúng vùng + đúng ngữ cảnh → đáng tin cậy hơn gửi cả ảnh / mask toàn ảnh), rồi
        // PASTE lại vào ảnh gốc ĐÚNG TỌA ĐỘ (không lệch vị trí; feather mềm ở composite).
        $pad = (int) max(6, round(min($w, $h) * 0.02));    // giãn vùng ra ngoài (chống cắt mép)
        $ctx = (int) max(72, round(max($pw, $ph) * 0.8)); // NHIỀU ngữ cảnh quanh vùng (vùng hẹp cần context dày)
        $cropX = max(0, $px - $ctx); $cropY = max(0, $py - $ctx);
        $cropX2 = min($w, $px + $pw + $ctx); $cropY2 = min($h, $py + $ph + $ctx);
        $cropW = $cropX2 - $cropX; $cropH = $cropY2 - $cropY;
        $origCropW = $cropW; $origCropH = $cropH; // kích thước gốc (dùng khi paste lại)

        $cropImg = imagecreatetruecolor($cropW, $cropH);
        imagecopy($cropImg, $src, 0, 0, $cropX, $cropY, $cropW, $cropH);

        // Mask (tương đối CROP): TRẮNG = giữ nguyên, ĐEN = vùng chỉnh sửa (đã giãn pad).
        // Brush mode: frontend gửi mask_data (PNG base64, nền TRẮNG + nét ĐEN theo TỈ LỆ ẢNH GỐC)
        // → resize về đúng kích thước ảnh gốc rồi crop theo bbox; nếu không có/không đọc được thì
        // quay về vẽ hình chữ nhật (mãi vẫn an toàn).
        $maskMode = (string) ($data['mask_mode'] ?? 'rect');
        $brushMaskFull = null;
        if ($maskMode === 'brush' && ! empty($data['mask_data'])) {
            $b64 = (string) $data['mask_data'];
            if (str_starts_with($b64, 'data:')) {
                $comma = strpos($b64, ',');
                if ($comma !== false) { $b64 = substr($b64, $comma + 1); }
            }
            $brushRaw = base64_decode($b64, true);
            if ($brushRaw !== false && $brushRaw !== '') {
                $brushImg = @imagecreatefromstring($brushRaw);
                if ($brushImg) {
                    $bw = imagesx($brushImg); $bh = imagesy($brushImg);
                    if ($bw > 0 && $bh > 0 && ($bw !== $w || $bh !== $h)) {
                        $resized = imagecreatetruecolor($w, $h);
                        imagecopyresampled($resized, $brushImg, 0, 0, 0, 0, $w, $h, $bw, $bh);
                        imagedestroy($brushImg);
                        $brushImg = $resized;
                    }
                    $brushMaskFull = $brushImg;
                }
            }
        }

        $cord_x = max(0, $px - $pad - $cropX); $cord_y = max(0, $py - $pad - $cropY);
        $cord_x2 = min($cropW - 1, $px + $pw - 1 + $pad - $cropX);
        $cord_y2 = min($cropH - 1, $py + $ph - 1 + $pad - $cropY);
        $mask = imagecreatetruecolor($cropW, $cropH);
        imagefilledrectangle($mask, 0, 0, $cropW - 1, $cropH - 1, imagecolorallocate($mask, 255, 255, 255));
        if ($brushMaskFull) {
            imagecopy($mask, $brushMaskFull, 0, 0, $cropX, $cropY, $cropW, $cropH);
            imagedestroy($brushMaskFull);
        } else {
            imagefilledrectangle($mask, $cord_x, $cord_y, $cord_x2, $cord_y2, imagecolorallocate($mask, 0, 0, 0));
        }

        // UP-SCALE crop + mask lên tối thiểu ~512px để AI có đủ độ phân giải (vùng hẹp/phức tạp).
        // Paste sẽ cover-crop về kích thước GỐC (origCropW/H) nên vị trí vẫn chính xác.
        $minDim = 512;
        if ($cropW < $minDim || $cropH < $minDim) {
            $scale = max($minDim / $cropW, $minDim / $cropH);
            $cropW2 = (int) round($cropW * $scale); $cropH2 = (int) round($cropH * $scale);
            $tmp = imagecreatetruecolor($cropW2, $cropH2);
            imagecopyresampled($tmp, $cropImg, 0, 0, 0, 0, $cropW2, $cropH2, $cropW, $cropH);
            imagedestroy($cropImg); $cropImg = $tmp;
            $tmp2 = imagecreatetruecolor($cropW2, $cropH2);
            imagecopyresampled($tmp2, $mask, 0, 0, 0, 0, $cropW2, $cropH2, $cropW, $cropH);
            imagedestroy($mask); $mask = $tmp2;
        }

        // Feather mask trước khi lưu — biên vùng edit mềm, composite paste không seam.
        $this->featherMaskEdges($mask);

        $cropName = 'studio/region-crop-'.Str::uuid().'.png';
        $maskName = 'studio/region-mask-'.Str::uuid().'.png';
        \Illuminate\Support\Facades\Storage::disk('public')->put($cropName, $this->pngBytes($cropImg));
        \Illuminate\Support\Facades\Storage::disk('public')->put($maskName, $this->pngBytes($mask));
        imagedestroy($cropImg); imagedestroy($mask);
        $cropUrl = '/storage/'.$cropName; $maskUrl = '/storage/'.$maskName;
        $regionMeta = [
            'region_op' => $op, 'source' => $sourceUrl,
            'crop_x' => $cropX, 'crop_y' => $cropY, 'crop_w' => $origCropW, 'crop_h' => $origCropH,
            'reg_x' => $cord_x, 'reg_y' => $cord_y, 'reg_w' => $cord_x2 - $cord_x + 1, 'reg_h' => $cord_y2 - $cord_y + 1,
        ];

        $cost = (int) studio_config('image_credits', 1);

        // Giới hạn crop tối đa ~2048px để không vượt quá giới hạn model AI.
        $maxDim = 2048;
        if ($cropW > $maxDim || $cropH > $maxDim) {
            $scale = min($maxDim / $cropW, $maxDim / $cropH);
            $cropW2 = (int) round($cropW * $scale); $cropH2 = (int) round($cropH * $scale);
            $tmp = imagecreatetruecolor($cropW2, $cropH2);
            imagecopyresampled($tmp, $cropImg, 0, 0, 0, 0, $cropW2, $cropH2, $cropW, $cropH);
            imagedestroy($cropImg); $cropImg = $tmp;
            $tmp2 = imagecreatetruecolor($cropW2, $cropH2);
            imagecopyresampled($tmp2, $mask, 0, 0, 0, 0, $cropW2, $cropH2, $cropW, $cropH);
            imagedestroy($mask); $mask = $tmp2;
            // Cập nhật crop dimensions để pasteRegionEdit paste đúng
            $origCropW = $cropW2; $origCropH = $cropH2;
            $regionMeta['crop_w'] = $origCropW; $regionMeta['crop_h'] = $origCropH;
            // Scale region_meta coordinates to match
            $regionMeta['reg_x'] = (int) round($regionMeta['reg_x'] * $scale);
            $regionMeta['reg_y'] = (int) round($regionMeta['reg_y'] * $scale);
            $regionMeta['reg_w'] = (int) round($regionMeta['reg_w'] * $scale);
            $regionMeta['reg_h'] = (int) round($regionMeta['reg_h'] * $scale);
        }

        // Cả XÓA lẫn THAY đều dùng AI trên CROP (đáng tin cậy); fallback local khi chưa có key.
        $hasAi = (bool) (studio_api_key('qwen_edit') ?: studio_api_key('dashscope') ?: studio_api_key('qwen') ?: studio_api_key('gemini'));
        if ($hasAi) {
            return $this->queueGeneration('image', [
                'prompt' => $this->regionPrompt($op, (string) ($data['prompt'] ?? '')),
                'base_image' => $cropUrl,
                'mask_image' => $maskUrl,
                'edit' => true,
                'region_meta' => $regionMeta,
            ], $cost, $generation);
        }

        // Chưa có key AI → erase: tái tạo nền cục bộ; replace: trả 422 (tránh P1: thay vùng thành xóa vùng).
        if ($op === 'replace') {
            imagedestroy($src);
            return response()->json(['message' => 'Tính năng Thay vùng cần key AI (Qwen Edit / DashScope). Vui lòng cấu hình API key.'], 422);
        }
        $this->localEraseFill($src, $px, $py, $pw, $ph);
        $name = 'studio/erase-'.Str::uuid().'.png';
        \Illuminate\Support\Facades\Storage::disk('public')->put($name, $this->pngBytes($src));
        imagedestroy($src);
        $gen = auth()->user()->generations()->create([
            'type' => 'image', 'status' => 'completed', 'media_url' => '/storage/'.$name,
            'prompt' => 'Xóa vùng chọn (tái tạo nền)', 'model' => 'erase', 'provider' => 'local', 'credits_cost' => 0,
        ]);
        return response()->json(['generation_id' => $gen->id, 'status' => 'completed', 'media_url' => '/storage/'.$name, 'model' => 'erase', 'provider' => 'local', 'credits_cost' => 0]);
    }

    /**
     * Prompt theo thao tác vùng chọn — MỞ RỘNG: thêm nhánh mới khi thêm op vào REGION_OPS.
     */
    protected function regionPrompt(string $op, string $userPrompt): string
    {
        // Prompt hiệu quả & nhất quán: mask nhị phân (đã giãn pad), composite feather mềm.
        $mask = ' You are given the ORIGINAL photo PLUS a mask image (same size): the BLACK region is the only area you may change; every pixel OUTSIDE the black region must stay EXACTLY identical to the original. The mask already has soft edges.';
        if ($op === 'erase') {
            return 'PROFESSIONAL OBJECT REMOVAL / INPAINTING: erase the object, person or content inside the BLACK region of the mask completely, then reconstruct the background that was hidden behind it.
RULES:
- Fill ONLY with the EXACT surrounding background visible just outside the black region (wall, floor, fabric of the backdrop, table, grass...), extending its color, gradient, texture, lighting and any soft shadows naturally INTO the masked area so it looks continuous.
- Do NOT bring in any fabric, clothing, skin, garment texture, pattern or color from the removed subject — keep the fill purely background.
- Result must look like the object was never there: perfectly seamless, no visible seam, border, halo, blur or leftover artifact at the mask edges.
- Blend softly at the mask edge (feathered), never a hard rectangle line.
'.$mask;
        }
        $p = trim($userPrompt);
        return 'PROFESSIONAL OBJECT INSERTION: create and place the following inside the BLACK region of the mask and blend it naturally into the scene: '.$p.'.
RULES:
- Render it realistically: correct perspective, scale, lighting, shadows and color grading to match the surrounding scene.
- Fill the masked area, extending the object naturally toward the soft mask edges — it may lightly feather into the boundary but must NOT be abruptly cut off by a hard rectangle edge.
- Do NOT change anything outside the black region.
'.$mask;
    }

    /**
     * GD fallback khi chưa cấu hình key AI — "tách nền → xóa vật thể → gộp lại" thuần GD:
     *  1) TÁCH NỀN: suy nền từ 4 cạnh viền ngay sát vùng chọn (cùng hàng/cột);
     *  2) XÓA VẬT THỂ: mỗi pixel trong vùng = nội suy tuyến tính giữa nền TRÁI–PHẢI và TRÊN–DƯỚI
     *     (tái tạo nền studio/backdrop trơn, tốt hơn nhiều so với đổ 1 màu);
     *  3) GỘP LẠI: làm mờ patch (lề feather) rồi dán lại → biên hòa mượt vào ảnh gốc.
     */
    protected function localEraseFill(\GdImage $img, int $px, int $py, int $pw, int $ph): void
    {
        $w = imagesx($img); $h = imagesy($img);
        $x0 = max(0, $px); $x1 = min($w - 1, $px + $pw - 1);
        $y0 = max(0, $py); $y1 = min($h - 1, $py + $ph - 1);
        if ($x0 > $x1 || $y0 > $y1) { return; }
        // Các cạnh nền ngay sát vùng (đã clamp)
        $lx = max(0, $px - 1); $rx = min($w - 1, $px + $pw);
        $ty = max(0, $py - 1); $by = min($h - 1, $py + $ph);

        // Snapshot ảnh gốc để feather (blend mép vùng với ảnh gốc — KHÔNG dùng blurred patch).
        $orig = imagecreatetruecolor($w, $h);
        imagecopy($orig, $img, 0, 0, 0, 0, $w, $h);

        // 1+2) Reconstruction theo khoảng cách nghịch đảo + nhiễu nền (chân thật hơn):
        //      mỗi pixel trong vùng = trộn màu nền 4 cạnh theo trọng số 1/khoảng-cách tới cạnh →
        //      bám gradient/bóng nền từ cạnh GẦN NHẤT (tự nhiên hơn trộn tuyến tính đều), rồi
        //      cộng nhiễu nhẹ khớp độ mịn nền → hết cảm giác "miếng vá phẳng".
        //      Guard đen: nếu một cạnh gần đen (thanh đen/letterbox) thì dùng màu cạnh đối diện.
        $dark = function (int $c): bool { return ((($c >> 16) & 0xFF) + (($c >> 8) & 0xFF) + ($c & 0xFF)) < 72; };
        // Đo độ mịn nền (std per-channel) quanh viền vùng → biên độ nhiễu nhẹ.
        $ring = [];
        $step = max(2, (int) round(max($x1 - $x0, $y1 - $y0) / 24));
        for ($x = $x0; $x <= $x1; $x += $step) {
            $ring[] = imagecolorat($img, $x, $ty);
            $ring[] = imagecolorat($img, $x, $by);
        }
        for ($y = $y0; $y <= $y1; $y += $step) {
            $ring[] = imagecolorat($img, $lx, $y);
            $ring[] = imagecolorat($img, $rx, $y);
        }
        $std = function (array $v): float { $n = max(1, count($v)); $m = array_sum($v) / $n; $s = 0.0; foreach ($v as $x) { $s += ($x - $m) ** 2; } return sqrt($s / $n); };
        $rf = []; $gf = []; $bf = [];
        foreach ($ring as $c) { $rf[] = ($c >> 16) & 0xFF; $gf[] = ($c >> 8) & 0xFF; $bf[] = $c & 0xFF; }
        $noise = (($std($rf) + $std($gf) + $std($bf)) / 3.0) * 0.55;

        for ($y = $y0; $y <= $y1; $y++) {
            $lc = imagecolorat($img, $lx, $y); $rc = imagecolorat($img, $rx, $y);
            if ($dark($lc) && ! $dark($rc)) { $lc = $rc; }
            elseif ($dark($rc) && ! $dark($lc)) { $rc = $lc; }
            for ($x = $x0; $x <= $x1; $x++) {
                $tc = imagecolorat($img, $x, $ty); $bc = imagecolorat($img, $x, $by);
                if ($dark($tc) && ! $dark($bc)) { $tc = $bc; }
                elseif ($dark($bc) && ! $dark($tc)) { $bc = $tc; }
                $wl = 1.0 / (($x - $x0) + 1); $wr = 1.0 / (($x1 - $x) + 1);
                $wt = 1.0 / (($y - $y0) + 1); $wb = 1.0 / (($y1 - $y) + 1);
                $ws = $wl + $wr + $wt + $wb;
                $r = (($lc >> 16) & 0xFF) * $wl + (($rc >> 16) & 0xFF) * $wr + (($tc >> 16) & 0xFF) * $wt + (($bc >> 16) & 0xFF) * $wb;
                $g = (($lc >> 8) & 0xFF) * $wl + (($rc >> 8) & 0xFF) * $wr + (($tc >> 8) & 0xFF) * $wt + (($bc >> 8) & 0xFF) * $wb;
                $b = ($lc & 0xFF) * $wl + ($rc & 0xFF) * $wr + ($tc & 0xFF) * $wt + ($bc & 0xFF) * $wb;
                $n = (int) round((mt_rand(-1000, 1000) / 1000.0) * $noise);
                imagesetpixel($img, $x, $y, imagecolorallocate($img,
                    max(0, min(255, (int) round($r / $ws) + $n)),
                    max(0, min(255, (int) round($g / $ws) + $n)),
                    max(0, min(255, (int) round($b / $ws) + $n))));
            }
        }

        // 3) Feather mềm biên KHÔNG DÙNG BLUR (GD blur pad mép = đen gây vệt đen):
        //    trộn mép vùng với ẢNH GỐC theo khoảng cách tới biên → mép = ảnh gốc (liền mạch),
        //    vào trong dần = màu tái tạo. Không patch, không viền đen.
        $feather = (int) max(2, min(32, round(min($x1 - $x0, $y1 - $y0) * 0.08)));
        for ($y = $y0; $y <= $y1; $y++) {
            for ($x = $x0; $x <= $x1; $x++) {
                $d = min($x - $x0, $x1 - $x, $y - $y0, $y1 - $y);
                if ($d >= $feather) { continue; }
                $a = $d / max(1, $feather); // 0 ở mép (giữ ảnh gốc) → 1 vào trong (tái tạo)
                $fc = imagecolorat($img, $x, $y); $oc = imagecolorat($orig, $x, $y);
                $r = (int) round((($oc >> 16) & 0xFF) * (1 - $a) + (($fc >> 16) & 0xFF) * $a);
                $g2 = (int) round((($oc >> 8) & 0xFF) * (1 - $a) + (($fc >> 8) & 0xFF) * $a);
                $b2 = (int) round(($oc & 0xFF) * (1 - $a) + ($fc & 0xFF) * $a);
                imagesetpixel($img, $x, $y, imagecolorallocate($img, $r, $g2, $b2));
            }
        }
        imagedestroy($orig);
    }

    /**
     * Mark a generation failed and refund its credits (used for stuck / aborted jobs).
     */
    protected function failStuck(Generation $generation, string $message): void
    {
        $generation->update(['status' => 'failed', 'error' => $message]);
        if ($generation->credits_cost > 0) {
            $generation->user?->increment('credits_balance', $generation->credits_cost);
        }
    }

    /**
     * Polling endpoint for a single generation.
     */
    public function show(Generation $generation)
    {
        abort_unless($generation->user_id === auth()->id(), 403);

        // A generation left 'processing' by a killed web request (client disconnect) is healed here
        // so polling resolves instead of spinning "Đang tạo" forever.
        $stuckWindow = $generation->type === 'video' ? 8 : 6; // minutes; job poll deadline is 5m (video) / ~5m (image)
        if ($generation->status === 'processing'
            && $generation->updated_at->lt(now()->subMinutes($stuckWindow))) {
            $this->failStuck($generation, 'Hết thời gian xử lý (có thể request đã bị ngắt). Đã hoàn tiền vào tài khoản. Vui lòng thử lại bằng cách tạo mới, hoặc bấm “Xử lý ngay” ở thanh công cụ nếu còn nhiệm vụ chờ.');
        } elseif ($generation->status === 'pending') {
            // Lazy worker: if this generation is still pending (not picked up by a worker), process it
            // inline now so the polling request returns the completed result. Keep running even if the
            // polling client disconnects (ignore_user_abort) so a slow provider isn't killed mid-run.
            set_time_limit(600);
            ignore_user_abort(true);
            if (($generation->meta['swap'] ?? false) === true) {
                // Swap: CAS claim pending->processing rồi chạy pipeline inline — không phụ thuộc queue worker.
                $claimed = \App\Models\Generation::where('id', $generation->id)->where('status', 'pending')->update(['status' => 'processing']);
                if ($claimed) {
                    try {
                        app(StudioController::class)->executeSwapFromGeneration($generation);
                    } catch (\Throwable $e) {
                        logger()->error('Lazy swap failed for generation #'.$generation->id.': '.$e->getMessage());
                        $generation->update(['status' => 'failed', 'error' => $e->getMessage()]);
                    }
                }
            } else {
                $generation->update(['status' => 'processing']);
                try {
                    if ($generation->type === 'video') {
                        RenderVideoJob::dispatchSync($generation->id);
                    } else {
                        RenderImageJob::dispatchSync($generation->id);
                    }
                } catch (\Throwable $e) {
                    logger()->error('Lazy process failed for generation #'.$generation->id.': '.$e->getMessage());
                    $generation->update(['status' => 'failed', 'error' => $e->getMessage()]);
                }
            }
        }

        $g = $generation->fresh();

        return response()->json([
            'id' => $g->id,
            'type' => $g->type,
            'status' => $g->status,
            'model' => $g->model,
            'provider' => $g->provider,
            'media_url' => $g->media_url,
            'error' => $g->error,
            'credits_cost' => $g->credits_cost,
            'resolution' => $g->resolution,
            'ratio' => $g->ratio,
            'duration' => $g->duration,
            'elapsed_ms' => $g->elapsed_ms,
            'meta' => $g->meta,
        ]);
    }

    /**
     * Resolve the provider + model for a generation type.
     *
     * @return array{0: string, 1: string}
     */
    protected function defaultProviderModel(string $type): array
    {
        // Unified priority: default-settings model first, then registered models of the group by priority.
        // Same list as generation and the settings check, so they never disagree.
        $group = in_array($type, ['video', 'inference', 'text']) ? $type : 'image';
        $list = studio_model_candidates($group);
        if ($list) {
            return [$list[0]['provider'], $list[0]['model']];
        }

        return ['flux', (string) studio_config('image_model', 'flux-1.1-schnell')];
    }

    /**
     * Cancel a pending/processing generation and refund its credits.
     */
    public function cancel(Generation $generation)
    {
        abort_unless($generation->user_id === auth()->id(), 403);

        if (! in_array($generation->status, ['pending', 'processing'])) {
            return response()->json(['message' => 'Nhiệm vụ đã kết thúc.'], 422);
        }

        $generation->update(['status' => Generation::STATUS_CANCELLED]);

        if ($generation->credits_cost > 0) {
            $generation->user?->increment('credits_balance', $generation->credits_cost);
        }

        return response()->json(['status' => 'cancelled']);
    }

    /**
     * Delete a generation (and its stored media).
     */
    public function destroy(Generation $generation)
    {
        abort_unless($generation->user_id === auth()->id(), 403);

        $generation->delete();

        return response()->json(['message' => 'Đã xóa nhiệm vụ.']);
    }

    /**
     * Refund credits held by jobs stuck in 'processing' (e.g. the web request was killed mid-run),
     * so the balance reflects reality and creation is not blocked by phantom usage.
     */
    protected function reconcileStuckCredits($user): void
    {
        $stuck = $user->generations()
            ->where('status', 'processing')
            ->where('updated_at', '<', now()->subMinutes(30))
            ->get();

        foreach ($stuck as $g) {
            $g->update(['status' => 'failed', 'error' => 'Hết thời gian xử lý (job bị ngắt).']);
            if ($g->credits_cost > 0) {
                $g->user?->increment('credits_balance', $g->credits_cost);
            }
        }
    }

    /**
     * JSON: registered models grouped by capability, for the Studio UI (dropdowns)
     * and for dynamic model selection.
     */
    public function models()
    {
        $groups = ['image', 'video', 'inference'];
        $rows = \App\Models\StudioModel::orderBy('priority', 'desc')->orderBy('id')->get();
        $out = [];
        foreach ($groups as $g) {
            $out[$g] = $rows->where('group', $g)->where('enabled', true)->values()->map(fn ($m) => [
                'id' => $m->id, 'key' => $m->model_id, 'label' => $m->name,
                'provider' => $m->provider, 'priority' => $m->priority,
            ])->all();
        }
        return response()->json(['groups' => $out]);
    }

    /**
     * JSON: report how a registered model resolves — provider, model_id, api_key_ref,
     * whether a key exists, the base URL, and a hint if the model_id looks invalid.
     */
    public function testModel(\App\Models\StudioModel $model)
    {
        $knownVideo = ['wan2.5-t2v', 'wan2.2-i2v', 'wan2.5-i2v', 'wan2.1-i2v-turbo', 'happyhorse-1.1-i2v', 'wanx2.1-t2v-turbo', 'wanx2.1-i2v-turbo'];
        $group = $model->group;

        // The checked model itself is the subject — report ITS key (generation uses the same
        // candidate-key resolver). The priority list is shown for context only.
        $candidates = studio_model_candidates($group);
        $names = array_map(fn ($c) => ($c['provider'] ?? '').':'.($c['model'] ?? ''), $candidates);
        $candidateKeys = studio_candidate_key(['provider' => $model->provider, 'model' => $model->model_id], $group);
        $keyVal = $candidateKeys[0] ?? null;
        $keyPrefix = $keyVal ? substr($keyVal, 0, 8).'…' : null;
        $baseUrl = $keyVal ? dashscope_base_url($keyVal) : '';
        $keyOrder = array_map(fn ($k) => substr($k, 0, 8).'…', $candidateKeys);

        $note = '';
        if ($group === 'video' && ! in_array($model->model_id, $knownVideo)) {
            $note .= '⚠️ Model_id này KHÔNG nằm trong nhóm model video phổ biến của DashScope/Wan — dễ gặp lỗi "Model not exist". ';
        }
        if (! $keyVal) {
            $note .= 'Chưa có KEY dùng được cho "'.$model->provider.'" — thêm key Pay-As-You-Go trong API Keys Registry (hoặc env).';
        } elseif (str_starts_with($keyVal, 'sk-sp-')) {
            $note .= '⚠️ Key đang dùng (theo độ ưu tiên) là Token/Coding Plan (sk-sp-…). Host plan KHÔNG phục vụ model '.$model->model_id.' → dễ báo "Model not exist". Đăng ký/ưu tiên key Pay-As-You-Go (sk-… hoặc sk-ws-…).';
        } elseif (str_contains($baseUrl, 'token-plan')) {
            $note .= '⚠️ Base URL đang trỏ tới host Token/Coding Plan — không phục vụ model tạo ảnh. Đặt "DashScope Base" về host Pay-As-You-Go (dashscope-intl.aliyuncs.com).';
        } elseif (count($candidateKeys) > 1) {
            $note .= 'OK — gọi '.$keyPrefix.' trước ('.count($candidateKeys).' key theo độ ưu tiên) cho '.$model->provider.':'.$model->model_id.'.';
        } else {
            $note .= 'OK — gọi key '.$keyPrefix.' cho '.$model->provider.':'.$model->model_id.'.';
        }
        if ($names) {
            $note .= ' | Thứ tự ưu tiên model: '.implode(' → ', $names);
        }

        return response()->json([
            'provider' => $model->provider,
            'model_id' => $model->model_id,
            'model_name' => $model->name,
            'group' => $group,
            'api_key_ref' => $model->provider,
            'key_exists' => (bool) $keyVal,
            'key_prefix' => $keyPrefix,
            'base_url' => $baseUrl,
            'candidates' => $names,
            'keys' => $keyOrder,
            'note' => $note ?: 'OK — provider + key + model_id đã cấu hình hợp lý.',
        ]);
    }

    protected function queueGeneration(string $type, array $data, int $cost, ?Generation $source = null)
    {
        $user = auth()->user();

        // Internal admin tool: never hard-block on credits. Track usage (balance may go negative).
        $this->reconcileStuckCredits($user);
        $user->decrement('credits_balance', $cost);

        if (! empty($data['edit'])) {
            $provider = 'qwen';
            $model = (string) studio_config('qwen_edit_model', 'qwen-image-edit');
        } else {
            // Explicit provider/model from the registry selector wins; else resolve the default.
            [$provider, $model] = (! empty($data['provider']) && ! empty($data['model']))
                ? [(string) $data['provider'], (string) $data['model']]
                : $this->defaultProviderModel($type);
        }

        $generation = $user->generations()->create([
            'project_id' => $data['project_id'] ?? null,
            'prompts_history_id' => $data['history_id'] ?? null,
            'type' => $type,
            'status' => 'pending',
            'prompt' => $data['prompt'] ?? null,
            'provider' => $provider,
            'model' => $model,
            'resolution' => $data['resolution'] ?? null,
            'ratio' => $data['ratio'] ?? null,
            'duration' => $data['duration'] ?? null,
            'base_image' => $data['base_image'] ?? $source?->media_url,
            'mask_image' => $data['mask_image'] ?? null,
            'credits_cost' => $cost,
            'meta' => array_filter([
                'camera' => ($type === 'video' && ! empty($data['camera'])) ? $data['camera'] : null,
                'region_op' => $data['region_meta']['region_op'] ?? null,
                'source' => $data['region_meta']['source'] ?? null,
                'crop_x' => $data['region_meta']['crop_x'] ?? null,
                'crop_y' => $data['region_meta']['crop_y'] ?? null,
                'crop_w' => $data['region_meta']['crop_w'] ?? null,
                'crop_h' => $data['region_meta']['crop_h'] ?? null,
                'reg_x' => $data['region_meta']['reg_x'] ?? null,
                'reg_y' => $data['region_meta']['reg_y'] ?? null,
                'reg_w' => $data['region_meta']['reg_w'] ?? null,
                'reg_h' => $data['region_meta']['reg_h'] ?? null,
                'negative_prompt' => $data['negative_prompt'] ?? null,
                'ref_images' => $data['ref_images'] ?? null,
                'face_ref' => $data['face_ref'] ?? null,
            ], fn ($v) => $v !== null && $v !== ''),
        ]);

        // The job is processed lazily when the client polls this generation (show()), or via the
        // "Xử lý ngay" button / studio:process. The create request returns fast (pending) so the
        // Canvas shows "Đang tạo" immediately.
        $fresh = $generation->fresh();

        return response()->json([
            'generation_id' => $fresh->id,
            'status' => $fresh->status,
            'model' => $fresh->model,
            'provider' => $fresh->provider,
            'media_url' => $fresh->media_url,
            'error' => $fresh->error,
            'credits_cost' => $fresh->credits_cost,
            'credits_left' => $user->fresh()->credits_balance,
            'prompts_history_id' => $fresh->prompts_history_id,
        ]);
    }

    /**
     * Map selected preset ids to a category => prompt_injection map.
     */
    protected function resolveInjectedPresets(array $ids): array
    {
        $presets = Preset::whereIn('id', $ids)->get()->groupBy('category');

        return $presets->map(function ($group) {
            return $group->pluck('prompt_injection')->filter()->implode(', ');
        })->all();
    }

    /**
     * Reverse-prompt: analyse a reference image and suggest style/prompt.
     */
    public function suggest(Request $request)
    {
        $data = $request->validate([
            'image' => ['nullable', 'image', 'max:8192'],
            'reference_url' => ['nullable', 'string', 'max:2048'],
            'creative_level' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $imagePath = null;

        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $path = $request->file('image')->store('studio/ref', 'public');
            $imagePath = storage_path('app/public/'.$path);
        } elseif (! empty($data['reference_url'])) {
            $imagePath = $this->resolveReferencePath($data['reference_url']);
        }

        if (! $imagePath || ! is_file($imagePath)) {
            return response()->json(['message' => 'Không đọc được ảnh nguồn. Vui lòng tải ảnh hoặc chọn ảnh sản phẩm.'], 422);
        }

        // "Gợi ý từ ảnh" dùng mức sáng tạo RIÊNG (studio_suggest_creative_level), không theo cấu hình chung.
        $creativeLevel = (int) ($data['creative_level'] ?? studio_suggest_config('creative_level', 6));
        $result = app(StyleSuggestService::class)->suggest($imagePath, $creativeLevel);

        if (($result['disabled'] ?? false) === true) {
            return response()->json(['message' => 'Tính năng "Gợi ý từ ảnh" đang bị tắt trong cài đặt Studio.'], 422);
        }

        return response()->json($result);
    }

    /**
     * Upload a reference image (from a local blob) and return a public storage URL so it can be
     * used as a base_image for the pixel-preserving edit flow.
     */
    /**
     * List images under public_html/studio/images/assets (for the source-image upload popup).
     */
    public function refImages(): \Illuminate\Http\JsonResponse
    {
        $dir = storage_path('app/public/studio/ref');
        $files = is_dir($dir) ? glob($dir.'/*.{png,jpg,jpeg,webp,gif}', GLOB_BRACE) : [];
        $items = [];
        $current = request()->get('current', '');
        foreach ($files as $f) {
            $name = basename($f);
            $used = \App\Models\Generation::where('media_url', 'like', '%'.$name.'%')->exists();
            $items[] = ['name' => $name, 'url' => '/storage/studio/ref/'.$name, 'used' => $used];
        }
        return response()->json(['items' => $items]);
    }

    /**
     * Delete an uploaded source image if it isn't referenced by any generation (not in use).
     */
    public function refImageDelete(Request $request, string $name): \Illuminate\Http\JsonResponse
    {
        $name = basename($name);
        $used = \App\Models\Generation::where('media_url', 'like', '%'.$name.'%')->exists();
        if ($used) { return response()->json(['message' => 'Ảnh đang được dùng, không thể xóa.'], 422); }
        $file = storage_path('app/public/studio/ref/'.$name);
        if (is_file($file)) { @unlink($file); }
        return response()->json(['ok' => true]);
    }

    public function uploadRef(Request $request)
    {
        $data = $request->validate(['image' => ['required', 'image', 'max:8192']]);
        $name = 'ref-'.Str::uuid()->toString().'.'.$request->file('image')->extension();
        $request->file('image')->storeAs('studio/ref', $name, 'public');
        $url = '/storage/studio/ref/'.$name;
        return response()->json(['url' => $url, 'name' => $name]);
    }

    /**
     * Translate a prompt between Vietnamese and English (used by the "Chỉnh sửa prompt tiếng Việt" popup).
     */
    /**
     * Custom Model/Pose library assets (uploaded by the user).
     */
    public function assetIndex(): \Illuminate\Http\JsonResponse
    {
        try {
            $assets = \App\Models\StudioAsset::orderBy('type')->orderBy('sort')->get(['id', 'type', 'name', 'path']);
            return response()->json(['items' => $assets]);
        } catch (\Throwable $e) {
            logger()->warning('assetIndex failed: '.$e->getMessage());
            return response()->json(['items' => []]);
        }
    }

    public function assetStore(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'in:model,pose'],
            'name' => ['required', 'string', 'max:80'],
            'image' => ['required', 'image', 'max:8192'],
        ]);
        $path = '/storage/'.$request->file('image')->store('studio/assets', 'public');
        $asset = \App\Models\StudioAsset::create([
            'type' => $data['type'], 'name' => $data['name'], 'path' => $path, 'sort' => 0,
        ]);
        return response()->json(['id' => $asset->id, 'type' => $asset->type, 'name' => $asset->name, 'path' => $asset->path]);
    }

    public function assetDestroy(\App\Models\StudioAsset $asset)
    {
        $asset->delete();
        return response()->json(['ok' => true]);
    }

    /**
     * Serve a garment avatar via a Laravel route (Cache-Control: no-store) so the
     * Hostinger hcdn / LiteSpeed static cache never serves a stale clone.
     */
    /**
     * Public garment-avatar endpoint — serves the avatar from a fixed location with a
     * correct immutable cache header (versioned URL => safe to cache). Public, no auth.
     */
    public function studioImage(string $path)
    {
        if (str_contains($path, '..') || ! preg_match('#^[a-zA-Z0-9/_.\-]+$#', $path)) {
            return response()->json(['error' => 'invalid'], 404);
        }
        $file = storage_path('app/public/'.$path);
        if (! is_file($file)) {
            return response()->json(['error' => 'not found'], 404);
        }
        return response()->file($file, ['Cache-Control' => 'public, max-age=31536000, immutable']);
    }

    public function garmentAvatar(string $id)
    {
        if (! preg_match('/^[a-z0-9-]+$/', $id)) {
            return response()->json(['error' => 'invalid'], 404);
        }
        $path = public_path('assets/garments/garment-'.$id.'.png');
        if (! is_file($path)) {
            return response()->json(['error' => 'not found'], 404);
        }
        return response()->file($path, ['Cache-Control' => 'public, max-age=31536000, immutable']);
    }

    /**
     * Ảnh đại diện thu nhỏ (320×320 JPEG) cho lưới chọn loại trang phục — sinh một lần rồi cache
     * ra đĩa để popup Trợ lý thiết kế tải nhanh thay vì kéo cả PNG gốc 1328×1328 (nhiều MB).
     */
    public function garmentThumb(string $id)
    {
        if (! preg_match('/^[a-z0-9-]+$/', $id)) {
            return response()->json(['error' => 'invalid'], 404);
        }
        $src = public_path('assets/garments/garment-'.$id.'.png');
        if (! is_file($src)) {
            return response()->json(['error' => 'not found'], 404);
        }

        $dir = public_path('assets/garments/thumbs');
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $dst = $dir.'/garment-'.$id.'.jpg';

        if (! is_file($dst)) {
            $im = @imagecreatefrompng($src);
            if (! $im) {
                return response()->json(['error' => 'decode failed'], 500);
            }
            $w = imagesx($im);
            $h = imagesy($im);
            $side = 320;
            $thumb = imagecreatetruecolor($side, $side);
            $white = imagecolorallocate($thumb, 255, 255, 255);
            imagefilledrectangle($thumb, 0, 0, $side, $side, $white);
            imagecopyresampled($thumb, $im, 0, 0, 0, 0, $side, $side, $w, $h);
            imagejpeg($thumb, $dst, 82);
            imagedestroy($im);
            imagedestroy($thumb);
        }

        return response()->file($dst, ['Cache-Control' => 'public, max-age=31536000, immutable']);
    }

    /**
     * Tinh chỉnh & Nâng cấp ảnh: AI-edit refine (optional) + GD upscale with studio photo
     * finish, skin detail and light/shadow passes. Fabric-weave/roughness pass was REMOVED —
     * the per-pixel skin heuristic misclassified dark skin (r <= 70) and painted weave onto
     * faces and detail boundaries.
     */
    public function upscale(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'string', 'max:2048'],
            'scale' => ['nullable', 'integer', 'min:1', 'max:4'],
            'refine' => ['nullable', 'integer', 'min:0', 'max:10'],
            'photoreal' => ['nullable', 'integer', 'min:0', 'max:10'],
            'light_shadow' => ['nullable', 'integer', 'min:0', 'max:10'],
            'sharpen' => ['nullable', 'integer', 'min:0', 'max:10'],
            'clarity' => ['nullable', 'integer', 'min:0', 'max:10'],
            'vibrance' => ['nullable', 'integer', 'min:0', 'max:10'],
        ]);
        $scale = max(1, min(4, (int) ($data['scale'] ?? 2)));
        $refine = max(0, min(10, (int) ($data['refine'] ?? 0)));
        $photoreal = max(0, min(10, (int) ($data['photoreal'] ?? 0)));
        $lightShadow = max(0, min(10, (int) ($data['light_shadow'] ?? 0)));
        $sharpen = max(0, min(10, (int) ($data['sharpen'] ?? 0)));
        $clarity = max(0, min(10, (int) ($data['clarity'] ?? 0)));
        $vibrance = max(0, min(10, (int) ($data['vibrance'] ?? 0)));
        $srcUrl = (string) $data['image'];

        // Optional AI-edit refine for photoreal human detail. The prompt never asks for fabric
        // weave (it bleeds onto faces/dark skin) and must keep the frame unchanged.
        if ($refine > 0) {
            try {
                $keep = 'Keep the exact aspect ratio and framing of the input image — do NOT crop or change the frame. Keep the exact garment, model, pose, composition unchanged. Ultra-detailed, 4K.';
                $guard = 'IMPORTANT: Do NOT add fabric weave, texture, grain, noise, checkerboard, halftone, moiré, pixelation, blocky artifacts or any pattern to the skin, face, hair, jewellery or any flat area — keep them smooth, clean and natural. Keep all detail edges (face, hair, garment seams, outlines) crisp, sharp and completely free of aliasing, halos, ringing, moiré, banding or blur.';
                $detail = 'Enhance this fashion photograph at high resolution (hyper-realistic, like a professional fashion editorial): hyper-realistic human skin with natural pores and soft sub-surface tone, individual hair strands with soft highlights, realistic eyelashes and eye catchlight, crisp sharp edges, rich natural color, '.$guard.' '.$keep;
                $studio = 'Render a high-end professional studio photograph of this fashion garment with hyper-realistic human detail (softbox light, subtle film color grading, shallow depth of field): photorealistic skin with pores, individual hair strands, realistic eyelashes and eye catchlight, ultra-sharp micro-detail, premium catalog quality, '.$guard.' '.$keep;
                $prompt = $photoreal > 0 ? $studio : $detail;
                $out = app(\App\Services\ImageAIService::class)->generate($prompt, $srcUrl);
                if ($out) { $srcUrl = $out; }
            } catch (\Throwable $e) { logger()->warning('Upscale refine failed: '.$e->getMessage()); }
        }


        $rel = ltrim((string) parse_url($srcUrl, PHP_URL_PATH), '/');
        $file = null;
        foreach ([public_path($rel), storage_path('app/public/'.str_replace('storage/', '', $rel))] as $cand) {
            if (is_file($cand)) { $file = $cand; break; }
        }
        if (! $file) { return response()->json(['message' => 'Không đọc được ảnh nguồn.'], 422); }

        $src = @imagecreatefromstring((string) file_get_contents($file));
        if (! $src) { return response()->json(['message' => 'Ảnh nguồn không hợp lệ.'], 422); }
        $sw = imagesx($src); $sh = imagesy($src);
        $dst = $this->smartUpscale($src, $scale);
        // One coarse skin mask, shared by every texture pass, so the face AND a dilated band
        // around it are always protected (no weave/grain ever bleeds onto skin or its boundary).
        $skinMask = $this->buildSkinMask($dst);
        if ($photoreal > 0) { $this->studioPhotoFinish($dst, $photoreal, $skinMask); }
        if ($lightShadow > 0) { $this->lightShadowPass($dst, $lightShadow); }
        if ($sharpen > 0) { $this->sharpenPass($dst, $sharpen, $skinMask); }
        if ($clarity > 0) { $this->clarityPass($dst, $clarity, $skinMask); }
        if ($vibrance > 0) { $this->vibrancePass($dst, $vibrance, $skinMask); }
        $name = 'studio/upscale-'.Str::uuid().'.png';
        \Illuminate\Support\Facades\Storage::disk('public')->put($name, $this->pngBytes($dst));
        imagedestroy($src); imagedestroy($dst);

        $gen = auth()->user()->generations()->create([
            'type' => 'image', 'status' => 'completed',
            'media_url' => '/storage/'.$name,
            'prompt' => 'Nâng cấp ảnh ('.$scale.'x'.($refine ? ', refine '.$refine : '').')',
            'model' => 'upscale', 'provider' => 'upscale', 'credits_cost' => 0,
        ]);

        return response()->json(['media_url' => '/storage/'.$name, 'generation_id' => $gen->id]);
    }

    /**
     * Apply a film-look color grade to an image (1-click presets).
     */
    public function look(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'string', 'max:2048'],
            'look' => ['required', 'string', 'in:studio,warm,cool,cinematic,dramatic,retro,mono'],
            'level' => ['nullable', 'integer', 'min:0', 'max:10'],
        ]);
        $level = max(1, min(10, (int) ($data['level'] ?? 5)));
        $srcUrl = (string) $data['image'];
        $rel = ltrim((string) parse_url($srcUrl, PHP_URL_PATH), '/');
        $file = null;
        foreach ([public_path($rel), storage_path('app/public/'.str_replace('storage/', '', $rel))] as $cand) { if (is_file($cand)) { $file = $cand; break; } }
        if (! $file) { return response()->json(['message' => 'Không đọc được ảnh nguồn.'], 422); }
        $img = @imagecreatefromstring((string) file_get_contents($file));
        if (! $img) { return response()->json(['message' => 'Ảnh nguồn không hợp lệ.'], 422); }
        $this->applyLook($img, (string) $data['look'], $level);
        $this->unsharpMask($img, 0.4);
        $name = 'studio/look-'.Str::uuid().'.png';
        \Illuminate\Support\Facades\Storage::disk('public')->put($name, $this->pngBytes($img));
        imagedestroy($img);
        $gen = auth()->user()->generations()->create([
            'type' => 'image', 'status' => 'completed', 'media_url' => '/storage/'.$name,
            'prompt' => 'Film Look · '.$data['look'], 'model' => 'look', 'provider' => 'look', 'credits_cost' => 0,
        ]);
        return response()->json(['media_url' => '/storage/'.$name, 'generation_id' => $gen->id]);
    }

    public function reframe(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'string', 'max:2048'],
            'ratio' => ['nullable', 'string', 'max:12'],
            'x' => ['nullable', 'integer', 'min:0'],
            'y' => ['nullable', 'integer', 'min:0'],
            'w' => ['nullable', 'integer', 'min:1'],
            'h' => ['nullable', 'integer', 'min:1'],
        ]);
        // If an explicit pixel crop rectangle is given (canvas crop), crop exactly that.
        if (! empty($data['w']) && ! empty($data['h'])) {
            return $this->processAndStore($data['image'], function (\GdImage $img) use ($data) {
                $w = imagesx($img); $h = imagesy($img);
                $x = max(0, min($w - 1, (int) ($data['x'] ?? 0)));
                $y = max(0, min($h - 1, (int) ($data['y'] ?? 0)));
                $cw = max(1, min($w - $x, (int) $data['w']));
                $ch = max(1, min($h - $y, (int) $data['h']));
                $out = imagecreatetruecolor($cw, $ch);
                imagecopy($out, $img, 0, 0, $x, $y, $cw, $ch);
                imagedestroy($img);
                return $out;
            }, 'Crop canvas', 'reframe');
        }
        $ratio = in_array($data['ratio'] ?? '', ['1:1', '3:4', '4:5', '9:16', '16:9', '2:3', '3:2', '4:3'], true) ? $data['ratio'] : '3:4';
        return $this->processAndStore($data['image'], function (\GdImage $img) use ($ratio) { return $this->cropReframe($img, $ratio); }, 'Reframe '.$ratio, 'reframe');
    }

    protected function processAndStore(string $srcUrl, callable $cb, string $prompt, string $model): \Illuminate\Http\JsonResponse
    {
        $rel = ltrim((string) parse_url($srcUrl, PHP_URL_PATH), '/');
        $file = null;
        foreach ([public_path($rel), storage_path('app/public/'.str_replace('storage/', '', $rel))] as $cand) { if (is_file($cand)) { $file = $cand; break; } }
        if (! $file) { return response()->json(['message' => 'Không đọc được ảnh nguồn.'], 422); }
        $img = @imagecreatefromstring((string) file_get_contents($file));
        if (! $img) { return response()->json(['message' => 'Ảnh nguồn không hợp lệ.'], 422); }
        $img = $cb($img);
        $name = 'studio/'.Str::slug($model).'-'.Str::uuid().'.png';
        \Illuminate\Support\Facades\Storage::disk('public')->put($name, $this->pngBytes($img));
        imagedestroy($img);
        $gen = auth()->user()->generations()->create([
            'type' => 'image', 'status' => 'completed', 'media_url' => '/storage/'.$name,
            'prompt' => $prompt, 'model' => $model, 'provider' => $model, 'credits_cost' => 0,
        ]);
        return response()->json(['media_url' => '/storage/'.$name, 'generation_id' => $gen->id]);
    }

    /**
     * Professional studio photo finish: contrast, subtle film color grade, film grain, vignette, and a
     * SINGLE gentle unsharp mask. The grain and the final sharpening are skin-aware (they skip the
     * dilated skin band) so the face and its boundary stay clean, and only ONE USM runs in the whole
     * upscale pipeline (no double sharpening -> no halos/ringing on detail edges).
     */
    protected function studioPhotoFinish(\GdImage $img, int $level, ?array $skinMask = null): void
    {
        $k = $level / 10.0; // 0..1
        $w = imagesx($img); $h = imagesy($img);
        if ($skinMask === null) { $skinMask = $this->buildSkinMask($img); }
        $cols = (int) ceil($w / 2);
        // smart tone: soft contrast + subtle warm/cool grade — KHÔNG blur, KHÔNG grain/noise.
        if (function_exists('imagefilter')) {
            @imagefilter($img, IMG_FILTER_CONTRAST, (int) round(7 * $k));
            @imagefilter($img, IMG_FILTER_COLORIZE, (int) round(-3 * $k), (int) round(-1 * $k), (int) round(3 * $k));
        }
        // 3) gentle vignette (darken corners slightly, keeps depth)
        $cx = $w / 2; $cy = $h / 2; $maxd = (float) max($w, $h);
        for ($y = 0; $y < $h; $y += 4) {
            for ($x = 0; $x < $w; $x += 4) {
                $d = sqrt(($x - $cx) ** 2 + ($y - $cy) ** 2) / $maxd;
                $v = 1 - (0.20 * $k * max(0, $d - 0.35));
                $c = imagecolorat($img, $x, $y);
                $r = (int) (($c >> 16) & 0xFF) * $v; $g = (int) (($c >> 8) & 0xFF) * $v; $b = (int) ($c & 0xFF) * $v;
                imagesetpixel($img, $x, $y, imagecolorallocate($img, (int) $r, (int) $g, (int) $b));
            }
        }
        // 4) FINAL UN-SHARP MASK — ONE crisp pass; skipped on skin so pores stay natural and the
        //    weave/grain are never amplified into halos on detail boundaries.
        if (function_exists('imagefilter') && $w * $h <= 20000000) {
            $blur = imagecreatetruecolor($w, $h);
            imagecopy($blur, $img, 0, 0, 0, 0, $w, $h);
            @imagefilter($blur, IMG_FILTER_GAUSSIAN_BLUR);
            $amount = 0.18 + 0.22 * $k; // nhẹ — tăng nét mà không tạo halo/ringing
            for ($y = 0; $y < $h; $y++) {
                for ($x = 0; $x < $w; $x++) {
                    $c = imagecolorat($img, $x, $y); $b = imagecolorat($blur, $x, $y);
                    $cr = ($c >> 16) & 0xFF; $cg = ($c >> 8) & 0xFF; $cb = $c & 0xFF;
                    if ($this->isSkinPixel($cr, $cg, $cb)) { continue; }
                    $br = ($b >> 16) & 0xFF; $bg = ($b >> 8) & 0xFF; $bb = $b & 0xFF;
                    $nr = max(0, min(255, (int) round($cr + $amount * ($cr - $br))));
                    $ng = max(0, min(255, (int) round($cg + $amount * ($cg - $bg))));
                    $nb = max(0, min(255, (int) round($cb + $amount * ($cb - $bb))));
                    imagesetpixel($img, $x, $y, imagecolorallocate($img, $nr, $ng, $nb));
                }
            }
            imagedestroy($blur);
        }
    }


    /**
     * Skin mask: a robust warm-skin detector shared by the skin and grain passes so each operates on the right region (face/body skin vs
     * background) and they don't cross.
     */
    protected function isSkinPixel(int $r, int $g, int $b): bool
    {
        return $r > 70 && $r > $g && $g > $b && ($r - $b) > 12 && $r < 250 && $g > 45 && $g < 235 && $b > 30;
    }

    /**
     * Coarse skin mask (stride 2, one byte per coarse cell, one string per row).
     * Shared by the grain / USM passes so the face AND a dilated band around it are
     * always protected — no texture ever bleeds onto the face or its boundary.
     * @return array<int, string>
     */
    protected function buildSkinMask(\GdImage $img): array
    {
        $w = imagesx($img); $h = imagesy($img);
        $mask = [];
        for ($y = 0; $y < $h; $y += 2) {
            $row = '';
            for ($x = 0; $x < $w; $x += 2) {
                $c = imagecolorat($img, $x, $y);
                $r = ($c >> 16) & 0xFF; $g = ($c >> 8) & 0xFF; $b = $c & 0xFF;
                $row .= $this->isSkinPixel($r, $g, $b) ? "\x01" : "\x00";
            }
            $mask[$y >> 1] = $row;
        }
        return $mask;
    }

    /**
     * Whether the coarse skin-mask cell at (cx, cy) — or any cell within radius $rad — is skin.
     */
    protected function maskNearSkin(array $mask, int $cols, int $cx, int $cy, int $rad): bool
    {
        $r0 = max(0, $cy - $rad);
        $r1 = min(count($mask) - 1, $cy + $rad);
        $c0 = max(0, $cx - $rad);
        $c1 = min($cols - 1, $cx + $rad);
        for ($ry = $r0; $ry <= $r1; $ry++) {
            $row = $mask[$ry] ?? '';
            for ($rx = $c0; $rx <= $c1; $rx++) {
                if (isset($row[$rx]) && $row[$rx] === "\x01") { return true; }
            }
        }
        return false;
    }

    /**
     * Controlled light & shadow: a soft directional light from the upper-left (brightens that
     * side, deepens the opposite) plus a gentle contrast so shadows gain depth.
     */
    protected function lightShadowPass(\GdImage $img, int $level): void
    {
        if ($level <= 0) { return; }
        $k = $level / 10.0; $w = imagesx($img); $h = imagesy($img);
        for ($y = 0; $y < $h; $y += 2) {
            for ($x = 0; $x < $w; $x += 2) {
                $nx = ($w / 2 - $x) / max(1, $w); $ny = ($h / 2 - $y) / max(1, $h);
                $d = ($nx + $ny) / 2.0; // -0.5..0.5; positive = upper-left side
                $lift = (int) round($d * 28 * $k);
                $c = imagecolorat($img, $x, $y);
                $r = max(0, min(255, (($c >> 16) & 0xFF) + $lift));
                $g = max(0, min(255, (($c >> 8) & 0xFF) + $lift));
                $b = max(0, min(255, ($c & 0xFF) + $lift));
                imagesetpixel($img, $x, $y, imagecolorallocate($img, $r, $g, $b));
            }
        }
        if (function_exists('imagefilter')) {
            @imagefilter($img, IMG_FILTER_CONTRAST, (int) round(3 * $k));
        }
    }

    /**
     * Hậu kỳ — Tăng nét chi tiết (unsharp mask), BỎ QUA vùng da để không lộ khuyết điểm.
     * Làm sắc đường may, họa tiết vải, viền — không blur, không noise.
     */
    protected function sharpenPass(\GdImage $img, int $level, array $skinMask): void
    {
        if ($level <= 0) { return; }
        $k = $level / 10.0;
        $w = imagesx($img); $h = imagesy($img);
        $sharp = imagecreatetruecolor($w, $h);
        imagecopy($sharp, $img, 0, 0, 0, 0, $w, $h);
        $a = 0.2 + 0.6 * $k; // 0.2..0.8 — nhẹ, không tạo halo/ringing
        $kernel = [[0, -$a, 0], [-$a, 1 + 4 * $a, -$a], [0, -$a, 0]];
        @imageconvolution($sharp, $kernel, 1, 0);
        $cols = intdiv($w + 1, 2);
        for ($y = 0; $y < $h; $y += 2) {
            for ($x = 0; $x < $w; $x += 2) {
                if ($this->maskNearSkin($skinMask, $cols, $x >> 1, $y >> 1, 1)) { continue; }
                imagesetpixel($img, $x, $y, imagecolorat($sharp, $x, $y));
            }
        }
        imagedestroy($sharp);
    }

    /**
     * Hậu kỳ — Clarity (micro-contrast cục bộ): tăng độ "nổi khối" cho vải/chi tiết, bỏ qua da.
     */
    protected function clarityPass(\GdImage $img, int $level, array $skinMask): void
    {
        if ($level <= 0) { return; }
        $k = $level / 10.0;
        $w = imagesx($img); $h = imagesy($img);
        $tmp = imagecreatetruecolor($w, $h);
        imagecopy($tmp, $img, 0, 0, 0, 0, $w, $h);
        $c = 0.12 + 0.38 * $k; // 0.12..0.5 — rất nhẹ, chỉ tăng khối, không sọc/halo
        $kernel = [[0, -$c, 0], [-$c, 1 + 4 * $c, -$c], [0, -$c, 0]];
        @imageconvolution($tmp, $kernel, 1, 0);
        $cols = intdiv($w + 1, 2);
        for ($y = 0; $y < $h; $y += 2) {
            for ($x = 0; $x < $w; $x += 2) {
                if ($this->maskNearSkin($skinMask, $cols, $x >> 1, $y >> 1, 1)) { continue; }
                imagesetpixel($img, $x, $y, imagecolorat($tmp, $x, $y));
            }
        }
        imagedestroy($tmp);
    }

    /**
     * Hậu kỳ — Vibrance (tăng độ sống động màu): đẩy màu ra xa xám, bảo vệ tone da (tăng rất nhẹ trên da).
     */
    protected function vibrancePass(\GdImage $img, int $level, array $skinMask): void
    {
        if ($level <= 0) { return; }
        $k = $level / 10.0;
        $w = imagesx($img); $h = imagesy($img);
        $cols = intdiv($w + 1, 2);
        for ($y = 0; $y < $h; $y += 2) {
            for ($x = 0; $x < $w; $x += 2) {
                $skin = $this->maskNearSkin($skinMask, $cols, $x >> 1, $y >> 1, 1);
                $c = imagecolorat($img, $x, $y);
                $r = ($c >> 16) & 0xFF; $g = ($c >> 8) & 0xFF; $b = $c & 0xFF;
                $gray = ($r + $g + $b) / 3.0;
                $factor = $skin ? (1 + 0.12 * $k) : (1 + 0.55 * $k);
                $nr = $gray + ($r - $gray) * $factor;
                $ng = $gray + ($g - $gray) * $factor;
                $nb = $gray + ($b - $gray) * $factor;
                imagesetpixel($img, $x, $y, imagecolorallocate($img,
                    (int) max(0, min(255, $nr)), (int) max(0, min(255, $ng)), (int) max(0, min(255, $nb))));
            }
        }
    }

    protected function smartUpscale(\GdImage $src, int $scale): \GdImage
    {
        if ($scale <= 1) { return $src; }
        $img = $src; $isCopy = false;
        $steps = $scale >= 4 ? [2, (int) round($scale / 2)] : [$scale];
        foreach ($steps as $s) {
            $nw = (int) max(1, round(imagesx($img) * $s));
            $nh = (int) max(1, round(imagesy($img) * $s));
            $next = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($next, $img, 0, 0, 0, 0, $nw, $nh, imagesx($img), imagesy($img));
            if ($isCopy) { imagedestroy($img); }
            $img = $next; $isCopy = true;
        }
        // NOTE: no unsharp mask here. A single, skin-aware USM runs once in studioPhotoFinish,
        // so edges are never double-sharpened (no halos / ringing on detail boundaries).
        return $img;
    }

    /**
     * Film-look color grading: per-pixel tone curve + split-tint for a chosen look.
     * Dependency-free (GD). 'level' (1-10) now scales EVERY component - contrast, lift,
     * saturation and tint - so low levels are genuinely subtle. Previously only the tint
     * was scaled while contrast/lift/saturation ran at full strength, so even level 1
     * looked punchy and the slider barely did anything.
     */
    protected function applyLook(\GdImage $img, string $look, int $level): void
    {
        if ($level <= 0) { return; }
        $k = $level / 10.0; $w = imagesx($img); $h = imagesy($img);
        $tintR = 0; $tintG = 0; $tintB = 0; $contrast = 0.0; $lift = 0.0; $sat = 1.0;
        $split = false; // cinematic split-tone: teal shadows + warm highlights
        switch ($look) {
            case 'warm':    $tintR = 8; $tintB = -5; $contrast = 0.10; $lift = 0.015; break;
            case 'cool':    $tintR = -5; $tintB = 8; $contrast = 0.06; $lift = 0.030; break;
            case 'dramatic':$contrast = 0.26; $tintR = -3; $tintB = 3; $lift = -0.020; break;
            // Film/retro: gentle fade + warm paper tint (softened so it never burns out).
            case 'retro':   $contrast = 0.06; $lift = 0.040; $tintR = 7; $tintG = 2; $tintB = -5; break;
            // Cinematic (điện ảnh): teal shadows + warm highlights, gentle contrast, subtle sat lift.
            case 'cinematic': $contrast = 0.16; $lift = 0.010; $sat = 1.05; $split = true; break;
            case 'mono':    $sat = 0.0; $contrast = 0.16; $lift = 0.015; break;
            default:        $contrast = 0.04; $tintR = 3; $tintB = -1; break; // studio neutral
        }
        // Strength interpolation - everything is scaled by k (level / 10).
        $c = $contrast * $k; $l = $lift * $k; $sm = 1.0 + ($sat - 1.0) * $k;
        $tr = $tintR * $k; $tg = $tintG * $k; $tb = $tintB * $k;
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $px = imagecolorat($img, $x, $y);
                $r = ($px >> 16) & 0xFF; $g = ($px >> 8) & 0xFF; $b = $px & 0xFF;
                $lum = $r * 0.299 + $g * 0.587 + $b * 0.114;
                $dr = $tr; $dg = $tg; $db = $tb;
                if ($split) {
                    // Split-tone by luminance: warm orange in highlights, teal in shadows.
                    $t = $lum / 255.0;
                    $dr = ($t > 0.5 ? 9 : -7) * $k;
                    $db = ($t > 0.5 ? -4 : 8) * $k;
                }
                $nr = $lum + ($r - $lum) * $sm + $c * ($r - 128) + $l * 255 + $dr;
                $ng = $lum + ($g - $lum) * $sm + $c * ($g - 128) + $l * 255 + $dg;
                $nb = $lum + ($b - $lum) * $sm + $c * ($b - 128) + $l * 255 + $db;
                imagesetpixel($img, $x, $y, imagecolorallocate($img,
                    max(0, min(255, (int) round($nr))), max(0, min(255, (int) round($ng))), max(0, min(255, (int) round($nb)))));
            }
        }
        if ($look === 'dramatic' && $level > 4) { // deeper vignette for dramatic
            $cx = $w / 2; $cy = $h / 2; $maxd = (float) max($w, $h);
            for ($y = 0; $y < $h; $y += 3) { for ($x = 0; $x < $w; $x += 3) {
                $d = sqrt(($x - $cx) ** 2 + ($y - $cy) ** 2) / $maxd;
                $v = 1 - (0.16 * $k * max(0, $d - 0.4));
                $cc = imagecolorat($img, $x, $y);
                imagesetpixel($img, $x, $y, imagecolorallocate($img,
                    (int) ((($cc >> 16) & 0xFF) * $v), (int) ((($cc >> 8) & 0xFF) * $v), (int) (($cc & 0xFF) * $v)));
            } }
        }
    }

    protected function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) { return [184, 176, 164]; }
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    /**
     * Center-crop / reframe to a target aspect ratio (presets).
     */
    protected function cropReframe(\GdImage $img, string $ratio): \GdImage
    {
        $w = imagesx($img); $h = imagesy($img);
        $map = ['1:1' => [1, 1], '3:4' => [3, 4], '4:5' => [4, 5], '9:16' => [9, 16], '16:9' => [16, 9], '2:3' => [2, 3], '3:2' => [3, 2], '4:3' => [4, 3]];
        [$rw, $rh] = $map[$ratio] ?? [3, 4];
        $target = $rw / $rh; $cur = $w / $h;
        if ($cur > $target) { $nw = (int) round($h * $target); $x0 = (int) (($w - $nw) / 2); $y0 = 0; $nh = $h; }
        else { $nh = (int) round($w / $target); $y0 = (int) (($h - $nh) / 2); $x0 = 0; $nw = $w; }
        $out = imagecreatetruecolor($nw, $nh);
        imagecopy($out, $img, 0, 0, $x0, $y0, $nw, $nh);
        imagedestroy($img);
        return $out;
    }

    /**
     * Background detection + replace (or remove) via border-color similarity.
     */
    protected function bgReplace(\GdImage $img, string $target, int $level): void
    {
        $w = imagesx($img); $h = imagesy($img);
        $samples = [[0, 0], [$w - 1, 0], [0, $h - 1], [$w - 1, $h - 1], [(int) ($w / 2), 0], [(int) ($w / 2), $h - 1], [0, (int) ($h / 2)], [$w - 1, (int) ($h / 2)]];
        $sr = 0; $sg = 0; $sb = 0;
        foreach ($samples as [$sx, $sy]) { $c = imagecolorat($img, $sx, $sy); $sr += ($c >> 16) & 0xFF; $sg += ($c >> 8) & 0xFF; $sb += $c & 0xFF; }
        $sr = (int) ($sr / count($samples)); $sg = (int) ($sg / count($samples)); $sb = (int) ($sb / count($samples));
        $remove = $target === 'transparent' || $target === '';
        [$tr, $tg, $tb] = $remove ? [0, 0, 0] : $this->hexToRgb($target);
        $tol = (int) (42 + 26 * ($level / 10.0));
        if ($remove) { imagealphablending($img, false); imagesavealpha($img, true); }
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorat($img, $x, $y);
                $r = ($c >> 16) & 0xFF; $g = ($c >> 8) & 0xFF; $b = $c & 0xFF;
                $d = sqrt(($r - $sr) ** 2 + ($g - $sg) ** 2 + ($b - $sb) ** 2);
                if ($d < $tol) {
                    if ($remove) {
                        imagesetpixel($img, $x, $y, imagecolorallocatealpha($img, 0, 0, 0, 127));
                    } else {
                        imagesetpixel($img, $x, $y, imagecolorallocate($img, $tr, $tg, $tb));
                    }
                }
            }
        }
    }

    /**
     * Light unsharp mask helper (blur-subtract) for crisping edges without amplifying noise.
     */
    protected function unsharpMask(\GdImage $img, float $amount = 0.6): void
    {
        $w = imagesx($img); $h = imagesy($img);
        if (! function_exists('imagefilter') || $w * $h > 24000000) { return; }
        $blur = imagecreatetruecolor($w, $h);
        imagecopy($blur, $img, 0, 0, 0, 0, $w, $h);
        @imagefilter($blur, IMG_FILTER_GAUSSIAN_BLUR);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorat($img, $x, $y); $b = imagecolorat($blur, $x, $y);
                $cr = ($c >> 16) & 0xFF; $cg = ($c >> 8) & 0xFF; $cb = $c & 0xFF;
                $br = ($b >> 16) & 0xFF; $bg = ($b >> 8) & 0xFF; $bb = $b & 0xFF;
                imagesetpixel($img, $x, $y, imagecolorallocate($img,
                    max(0, min(255, (int) round($cr + $amount * ($cr - $br)))),
                    max(0, min(255, (int) round($cg + $amount * ($cg - $bg)))),
                    max(0, min(255, (int) round($cb + $amount * ($cb - $bb))))));
            }
        }
        imagedestroy($blur);
    }

    /**
     * Feather mask: làm mềm mép vùng đen (edit) → ảnh gộp không lộ seam / viền cứng.
     * Thực hiện trên bản thu nhỏ (~1/5) rồi nội suy về kích thước gốc — mép đen/trắng
     * chuyển dần qua xám vài px tỉ lệ theo ảnh, nhanh kể cả ảnh lớn.
     */
    protected function featherMaskEdges(\GdImage &$mask): void
    {
        $w = imagesx($mask); $h = imagesy($mask);
        if ($w < 32 || $h < 32) return;
        $tw = max(32, (int) round($w / 5));
        $th = max(32, (int) round($h / 5));
        $small = imagecreatetruecolor($tw, $th);
        imagecopyresampled($small, $mask, 0, 0, 0, 0, $tw, $th, $w, $h);
        imagefilter($small, IMG_FILTER_GAUSSIAN_BLUR);
        imagefilter($small, IMG_FILTER_GAUSSIAN_BLUR);
        imagefilter($small, IMG_FILTER_GAUSSIAN_BLUR);
        $tmp = imagecreatetruecolor($w, $h);
        imagecopyresampled($tmp, $small, 0, 0, 0, 0, $w, $h, $tw, $th);
        imagedestroy($mask);
        imagedestroy($small);
        $mask = $tmp;
    }

    protected function pngBytes(\GdImage $img): string
    {
        ob_start(); imagepng($img); return (string) ob_get_clean();
    }

    /**
     * Deterministic color-tone effect for the swap result: picks a grade (auto = by the chosen
     * background) and applies applyLook(). Returns the new /storage URL or null when no grade applies.
     */
    protected function applyToneToStoredImage(string $url, string $tone, string $background = '', int $level = 6): ?string
    {
        $tone = strtolower(trim((string) $tone));
        $look = match ($tone) {
            'warm' => 'warm',
            'cool' => 'cool',
            'film' => 'retro',
            'cinematic' => 'cinematic',
            'dramatic' => 'dramatic',
            'mono' => 'mono',
            'auto' => $this->autoLookForBackground($background),
            default => null,
        };
        if (! $look || $look === 'none' || $level <= 0) {
            return null;
        }
        $rel = ltrim((string) parse_url($url, PHP_URL_PATH), '/');
        $file = null;
        foreach ([public_path($rel), storage_path('app/public/'.str_replace('storage/', '', $rel))] as $cand) {
            if (is_file($cand)) { $file = $cand; break; }
        }
        if (! $file) { return null; }
        $img = @imagecreatefromstring((string) file_get_contents($file));
        if (! $img) { return null; }
        $this->applyLook($img, $look, max(1, min(10, $level)));
        $name = 'studio/swaptone-'.Str::uuid().'.png';
        \Illuminate\Support\Facades\Storage::disk('public')->put($name, $this->pngBytes($img));
        imagedestroy($img);
        return '/storage/'.$name;
    }

    protected function autoLookForBackground(string $background): ?string
    {
        $b = strtolower((string) $background);
        if ($b === '' || in_array($b, ['keep', 'original'], true)) { return null; }
        if (str_contains($b, 'dark') || str_contains($b, 'moody') || str_contains($b, 'tối') || str_contains($b, 'đêm')) { return 'dramatic'; }
        if (str_contains($b, 'street') || str_contains($b, 'urban') || str_contains($b, 'đường') || str_contains($b, 'phố')) { return 'warm'; }
        if (str_contains($b, 'beige') || str_contains($b, 'neutral') || str_contains($b, 'warm') || str_contains($b, 'cream') || str_contains($b, 'seamless')) { return 'warm'; }
        if (str_contains($b, 'white') || str_contains($b, 'trắng')) { return 'cool'; }
        return null;
    }

    /**
     * ✨ Thuật sỹ ảo — guided fashion-stylist wizard.
     */
    public function stylistTypes(): \Illuminate\Http\JsonResponse
    {
        return response()->json(['types' => app(\App\Services\StylistService::class)->garmentTypes()]);
    }

    public function stylist(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'max:40'],
            'history' => ['nullable', 'array'],
            'history.*.label' => ['nullable', 'string', 'max:500'],
            'history.*.answer' => ['nullable', 'string', 'max:300'],
        ]);
        $svc = app(\App\Services\StylistService::class);
        $step = $svc->next((string) $data['type'], $data['history'] ?? []);
        return response()->json($step);
    }

    /**
     * ✨ Thuật sỹ — cluster (xương sườn) + build prompt.
     */
    /**
     * Swap model/pose catalog (with images) for the Vue studio picker.
     */
    /**
     * Background presets for the swap popup (Preset category 'background').
     */
    public function swapBackgrounds(): \Illuminate\Http\JsonResponse
    {
        $items = \App\Models\Preset::category('background')->get()
            ->map(fn ($p) => ['value' => $p->prompt_injection, 'label' => $p->ui_label ?: $p->prompt_injection])
            ->filter(fn ($i) => ! empty($i['value']))->values();
        if ($items->isEmpty()) {
            $items = collect([['value' => 'clean studio, neutral beige seamless backdrop', 'label' => 'Studio be'], ['value' => 'white seamless studio backdrop, soft light', 'label' => 'Trắng'], ['value' => 'dark moody studio, dramatic light', 'label' => 'Tối'], ['value' => 'outdoor urban street, natural light', 'label' => 'Đường phố']]);
        }
        return response()->json(['items' => $items]);
    }

    public function swapCatalog(string $kind): \Illuminate\Http\JsonResponse
    {
        $svc = app(\App\Services\VirtualTryOnService::class);
        $items = $kind === 'poses' ? $svc->poseCatalog() : $svc->modelCatalog();
        return response()->json(['items' => $items]);
    }

    public function stylistCluster(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate(['type' => ['required', 'string', 'max:40']]);
        return response()->json(['questions' => app(\App\Services\StylistService::class)->cluster((string) $data['type'])]);
    }

    public function stylistRefine(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'max:40'],
            'prompt_en' => ['required', 'string', 'max:4000'],
            'answers' => ['nullable', 'array'],
            'answers.*' => ['nullable', 'string', 'max:500'],
        ]);
        return response()->json(app(\App\Services\StylistService::class)->refine((string) $data['type'], (string) $data['prompt_en'], (array) ($data['answers'] ?? [])));
    }

    public function stylistPrompt(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'max:40'],
            'answers' => ['nullable', 'array'],
            'answers.*' => ['nullable', 'string', 'max:500'],
        ]);
        $svc = app(\App\Services\StylistService::class);
        $answers = array_filter((array) ($data['answers'] ?? []), fn ($v) => ! empty($v));
        $type = (string) $data['type'];
        $promptEn = $svc->buildPrompt($type, $answers);
        $promptVi = $svc->buildPromptVi($type, $answers);

        // Tự lưu thành preset (data của Trợ lý thiết kế) để nút Preset trong popup Prompt load được.
        app(\App\Services\StylistCatalog::class)->savePreset($svc->nameOf($type), $promptEn, $type);

        return response()->json(['prompt_en' => $promptEn, 'prompt_vi' => $promptVi]);
    }

    /**
     * "Tách nền + hiệu ứng + gộp" via remove.bg (proper segmentation): call remove.bg to get the
     * person CUTOUT (accurate alpha), blur the composite, then recomposite the sharp original person
     * (from the composite) over the blurred background using the cutout alpha. The subject is NEVER
     * blurred — 100% correct segmentation, no ghosting, no mirroring.
     */
    protected function applySegmentComposite(string $compositeUrl): ?string
    {
        $cutout = $this->removeBgCutout($compositeUrl);
        if (! $cutout) { return null; }

        $load = function (string $url) {
            $rel = ltrim((string) parse_url($url, PHP_URL_PATH), '/');
            $file = null;
            foreach ([public_path($rel), storage_path('app/public/'.str_replace('storage/', '', $rel))] as $c) {
                if (is_file($c)) { $file = $c; break; }
            }
            return $file ? @imagecreatefromstring((string) file_get_contents($file)) : null;
        };
        $comp = $load($compositeUrl); $cut = $load($cutout);
        if (! $comp || ! $cut) { return null; }
        $w = imagesx($comp); $h = imagesy($comp);
        if (imagesx($cut) !== $w || imagesy($cut) !== $h) {
            $r2 = imagecreatetruecolor($w, $h);
            imagecopyresampled($r2, $cut, 0, 0, 0, 0, $w, $h, imagesx($cut), imagesy($cut));
            imagedestroy($cut); $cut = $r2;
        }
        // Blur the whole composite (background will be blurred; person restored sharp below).
        $blur = imagecreatetruecolor($w, $h);
        imagecopy($blur, $comp, 0, 0, 0, 0, $w, $h);
        for ($i = 0; $i < 3; $i++) { @imagefilter($blur, IMG_FILTER_GAUSSIAN_BLUR); }
        // Recomposite: sharp composite * alpha + blurred * (1-alpha). GD alpha: 0=opaque,127=transparent.
        for ($y = 0; $y < $h; $y += 2) {
            for ($x = 0; $x < $w; $x += 2) {
                $ac = imagecolorat($cut, $x, $y);
                $op = 1 - (($ac >> 24) & 0x7F) / 127.0;   // 1 = solid person, 0 = background
                if ($op <= 0.03) { continue; }             // background -> keep the blurred bg
                if ($op >= 0.97) { 
                    $c = imagecolorat($comp, $x, $y);
                    imagesetpixel($blur, $x, $y, $c);
                    continue;
                }
                $c = imagecolorat($comp, $x, $y);
                $b = imagecolorat($blur, $x, $y);
                $r = (int) round((($c>>16)&255)*$op + (($b>>16)&255)*(1-$op));
                $g = (int) round((($c>>8)&255)*$op + (($b>>8)&255)*(1-$op));
                $bb = (int) round(($c&255)*$op + ($b&255)*(1-$op));
                imagesetpixel($blur, $x, $y, imagecolorallocate($blur, $r, $g, $bb));
            }
        }
        imagedestroy($cut); imagedestroy($comp);
        $name = 'studio/seg-'.Str::uuid().'.png';
        \Illuminate\Support\Facades\Storage::disk('public')->put($name, $this->pngBytes($blur));
        imagedestroy($blur);
        return '/storage/'.$name;
    }

    /**
     * Call the remove.bg API to extract the person (transparent-background cutout). Reads the API key
     * from studio.removebg_key. Returns the cutout URL, or null (no key / request failed).
     */
    protected function removeBgCutout(string $url): ?string
    {
        $key = (string) studio_config('removebg_key', '');
        if ($key === '') { return null; }
        $rel = ltrim((string) parse_url($url, PHP_URL_PATH), '/');
        $file = null;
        foreach ([public_path($rel), storage_path('app/public/'.str_replace('storage/', '', $rel))] as $c) {
            if (is_file($c)) { $file = $c; break; }
        }
        if (! $file) { return null; }
        try {
            $resp = \Illuminate\Support\Facades\Http::withHeaders(['X-Api-Key' => $key])->timeout(90)
                ->attach('image_file', fopen($file, 'r'), 'image.png')
                ->post('https://api.remove.bg/v1.0/removebg', ['size' => 'auto', 'format' => 'png']);
            if ($resp->failed()) {
                logger()->warning('remove.bg failed: '.$resp->status().' '.substr((string) $resp->body(), 0, 200));
                return null;
            }
            $name = 'studio/seg-cut-'.Str::uuid().'.png';
            \Illuminate\Support\Facades\Storage::disk('public')->put($name, (string) $resp->body());
            return '/storage/'.$name;
        } catch (Throwable $e) {
            logger()->warning('remove.bg error: '.$e->getMessage());
            return null;
        }
    }

    /**
     * Scale the subject down slightly (~$scale of the frame) to leave more background visible around
     * them. The extended border is a cover-fit stretch of the scene (NOT mirrored) + a soft blur, and
     * the frame edge is crossfaded into it (no hard seam). Subject stays pixel-sharp.
     */
    protected function applyScaleDown(string $url, float $scale = 0.90): ?string
    {
        $rel = ltrim((string) parse_url($url, PHP_URL_PATH), '/');
        $file = null;
        foreach ([public_path($rel), storage_path('app/public/'.str_replace('storage/', '', $rel))] as $cand) {
            if (is_file($cand)) { $file = $cand; break; }
        }
        if (! $file) { return null; }
        $img = @imagecreatefromstring((string) file_get_contents($file));
        if (! $img) { return null; }
        $w = imagesx($img); $h = imagesy($img);
        $cw = (int) round($w / $scale); $ch = (int) round($h / $scale);
        $ox = (int) (($cw - $w) / 2); $oy = (int) (($ch - $h) / 2);

        // Border: cover-fit stretch of the scene (plausible continuation) then soften.
        $back = imagecreatetruecolor($cw, $ch);
        imagecopyresampled($back, $img, 0, 0, 0, 0, $cw, $ch, $w, $h);
        for ($i = 0; $i < 3; $i++) { @imagefilter($back, IMG_FILTER_GAUSSIAN_BLUR); }
        // Sharp original centered.
        imagecopy($back, $img, $ox, $oy, 0, 0, $w, $h);
        // Crossfade the frame edge into the blurred backdrop (no hard seam).
        $blend = (int) (min($w, $h) * 0.035);
        for ($y = 0; $y < $ch; $y++) {
            $sy = $y - $oy;
            for ($x = 0; $x < $cw; $x++) {
                $sx = $x - $ox;
                $inFrame = ($sx >= 0 && $sx < $w && $sy >= 0 && $sy < $h);
                if (! $inFrame) { continue; }
                $edge = min($sx, $w - 1 - $sx, $sy, $h - 1 - $sy);
                if ($edge >= $blend) { continue; }   // interior -> keep sharp
                $wg = max(0.0, min(1.0, $edge / $blend));
                $c = imagecolorat($img, $sx, $sy);
                $b = imagecolorat($back, $x, $y);
                $r = (int) round((($c >> 16) & 255) * $wg + (($b >> 16) & 255) * (1 - $wg));
                $g = (int) round((($c >> 8) & 255) * $wg + (($b >> 8) & 255) * (1 - $wg));
                $bb = (int) round(($c & 255) * $wg + ($b & 255) * (1 - $wg));
                imagesetpixel($back, $x, $y, imagecolorallocate($back, $r, $g, $bb));
            }
        }
        $name = 'studio/scale-'.Str::uuid().'.png';
        \Illuminate\Support\Facades\Storage::disk('public')->put($name, $this->pngBytes($back));
        imagedestroy($back); imagedestroy($img);
        return '/storage/'.$name;
    }

    /**
     * "Tách nền lần 2" (2-pass background separation): ask the edit model to REMOVE the person and
     * fill the background naturally -> a clean scene with no person. We can then apply effects
     * (blur / depth-of-field) to that background SAFELY (there is no person to blur), build an alpha
     * mask from the pixel difference between the composite and the clean background, and recomposite
     * the ORIGINAL (sharp) person on top. Result: sharp subject, softened background, no mirroring.
     */
    protected function applyBackgroundDepth(string $compositeUrl): ?string
    {
        // 1) AI: remove the person, fill the scene -> clean background.
        $bgClean = $this->removePersonBackground($compositeUrl);
        if (! $bgClean) { return null; }

        $load = function (string $url) {
            $rel = ltrim((string) parse_url($url, PHP_URL_PATH), '/');
            $file = null;
            foreach ([public_path($rel), storage_path('app/public/'.str_replace('storage/', '', $rel))] as $c) {
                if (is_file($c)) { $file = $c; break; }
            }
            return $file ? @imagecreatefromstring((string) file_get_contents($file)) : null;
        };
        $comp = $load($compositeUrl); $clean = $load($bgClean);
        if (! $comp || ! $clean) { return null; }
        $w = imagesx($comp); $h = imagesy($comp);
        // Normalise the clean bg to the composite size (it may come back at a different resolution).
        if (imagesx($clean) !== $w || imagesy($clean) !== $h) {
            $r2 = imagecreatetruecolor($w, $h);
            imagecopyresampled($r2, $clean, 0, 0, 0, 0, $w, $h, imagesx($clean), imagesy($clean));
            imagedestroy($clean); $clean = $r2;
        }

        // 2) Alpha mask = pixel difference (composite vs clean bg), thresholded + feathered.
        $alpha = imagecreatetruecolor($w, $h);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $a = imagecolorat($comp, $x, $y); $b = imagecolorat($clean, $x, $y);
                $dr = abs((($a>>16)&255) - (($b>>16)&255));
                $dg = abs((($a>>8)&255) - (($b>>8)&255));
                $db = abs(($a&255) - ($b&255));
                $diff = max($dr, max($dg, $db));
                $v = (int) max(0, min(255, ($diff - 18) * 255 / 70));
                imagesetpixel($alpha, $x, $y, imagecolorallocate($alpha, $v, $v, $v));
            }
        }
        // Feather the mask (soft hair / silhouette edges).
        for ($i = 0; $i < 2; $i++) { @imagefilter($alpha, IMG_FILTER_GAUSSIAN_BLUR); }

        // 3) Effect on the CLEAN background: depth-of-field blur (safe — no person).
        $proc = imagecreatetruecolor($w, $h);
        imagecopy($proc, $clean, 0, 0, 0, 0, $w, $h);
        for ($i = 0; $i < 3; $i++) { @imagefilter($proc, IMG_FILTER_GAUSSIAN_BLUR); }

        // 4) Recomposite: sharp composite * alpha + processed bg * (1 - alpha).
        for ($y = 0; $y < $h; $y += 2) {
            for ($x = 0; $x < $w; $x += 2) {
                $am = imagecolorat($alpha, $x, $y) & 255;
                if ($am <= 4) { continue; }               // bg -> keep the processed bg
                $wg = $am / 255.0;
                $c = imagecolorat($comp, $x, $y);
                $b = imagecolorat($proc, $x, $y);
                $r = (int) round((($c>>16)&255)*$wg + (($b>>16)&255)*(1-$wg));
                $g = (int) round((($c>>8)&255)*$wg + (($b>>8)&255)*(1-$wg));
                $bb = (int) round(($c&255)*$wg + ($b&255)*(1-$wg));
                imagesetpixel($proc, $x, $y, imagecolorallocate($proc, $r, $g, $bb));
            }
        }
        imagedestroy($alpha); imagedestroy($clean); imagedestroy($comp);

        $name = 'studio/bgdepth-'.Str::uuid().'.png';
        \Illuminate\Support\Facades\Storage::disk('public')->put($name, $this->pngBytes($proc));
        imagedestroy($proc);
        return '/storage/'.$name;
    }

    /**
     * Ask the edit model to remove the person and fill the background naturally.
     */
    protected function removePersonBackground(string $url): ?string
    {
        $prompt = 'Remove the person from this photograph and fill the area behind them with the natural continuation of the scene (the architecture, flowering bougainvillea, plants, lanterns and paved ground). '
            .'Keep the whole scene otherwise EXACTLY unchanged — same lighting, same colors, same framing. '
            .'The result must look like the real location with nobody in it. Photorealistic.';
        try {
            return app(\App\Services\ImageAIService::class)->swapEdit(
                $prompt,
                $url,
                studio_swap_model(),
                null,
                null
            );
        } catch (Throwable $e) {
            logger()->warning('removePersonBackground failed: '.$e->getMessage());
            return null;
        }
    }

    /**
     * AI Outpainting for "Chân dung & Chiều sâu": extend the frame to ~1.22x (subject ~82%) and let
     * the Qwen edit model GENERATE a natural continuation of the scene (architecture, flowers, ground)
     * into the border — no mirroring, no copy-flip. The center (person) is pasted sharp and hidden
     * behind the instruction to keep it unchanged, so the subject stays pixel-identical. Returns the
     * outpainted URL, or null on failure (caller falls back to the raw result).
     */
    protected function outpaintBackground(string $url): ?string
    {
        $rel = ltrim((string) parse_url($url, PHP_URL_PATH), '/');
        $file = null;
        foreach ([public_path($rel), storage_path('app/public/'.str_replace('storage/', '', $rel))] as $cand) {
            if (is_file($cand)) { $file = $cand; break; }
        }
        if (! $file) { return null; }
        $img = @imagecreatefromstring((string) file_get_contents($file));
        if (! $img) { return null; }
        $w = imagesx($img); $h = imagesy($img);
        $scale = 0.82;   // subject target ~82% of frame height
        $cw = (int) round($w / $scale); $ch = (int) round($h / $scale);

        // Seed the larger canvas: a stretched cover-fit of the scene fills the border (rough
        // continuation for the model to refine), then the sharp original is pasted centered.
        $canvas = imagecreatetruecolor($cw, $ch);
        imagecopyresampled($canvas, $img, 0, 0, 0, 0, $cw, $ch, $w, $h);
        $ox = (int) (($cw - $w) / 2); $oy = (int) (($ch - $h) / 2);
        imagecopy($canvas, $img, $ox, $oy, 0, 0, $w, $h);
        $tmp = 'studio/outpaint-src-'.Str::uuid().'.png';
        \Illuminate\Support\Facades\Storage::disk('public')->put($tmp, $this->pngBytes($canvas));
        imagedestroy($canvas); imagedestroy($img);

        $prompt = 'This is a photograph. Extend the scene to fill the whole image naturally: continue the architecture, flowering bougainvillea, lanterns, plants and the paved ground seamlessly into the border regions around the central frame. '
            .'The CENTER of the image (containing the person) is FINAL and must remain EXACTLY unchanged — do NOT alter, re-render, re-light or crop the center. '
            .'The extended border must look like the same real, continuous photograph — no mirroring, no repetition, no stretching artifacts. Photorealistic, studio quality.';

        try {
            $out = app(\App\Services\ImageAIService::class)->swapEdit(
                $prompt,
                '/storage/'.$tmp,
                studio_swap_model(),
                null,
                null
            );
        } finally {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($tmp);
        }

        if ($out) { logger()->info('Swap outpainting done'); }
        return $out;
    }

    /**
     * "Chân dung & Chiều sâu" (Portrait & Depth) post-process.
     * CLEAN depth-of-field bokeh on the ORIGINAL frame (no canvas extension, no mirror-pad — those
     * produced a visible "copy-flipped" background). A soft radial mask keeps the centered subject
     * sharp and blurred only the outer background toward the corners; a wide smooth falloff means no
     * banding/seam. This is artifact-free. (To also make the subject smaller needs real background
     * content generation — see outpainting / segmentation, separate from this deterministic pass.)
     */
    protected function applyPortraitDepth(string $url): ?string
    {
        $rel = ltrim((string) parse_url($url, PHP_URL_PATH), '/');
        $file = null;
        foreach ([public_path($rel), storage_path('app/public/'.str_replace('storage/', '', $rel))] as $cand) {
            if (is_file($cand)) { $file = $cand; break; }
        }
        if (! $file) { return null; }
        $img = @imagecreatefromstring((string) file_get_contents($file));
        if (! $img) { return null; }

        $w = imagesx($img); $h = imagesy($img);
        // Soft bokeh: keep a generous central ellipse sharp, blur outward with a WIDE smooth falloff.
        $blur = imagecreatetruecolor($w, $h);
        imagecopy($blur, $img, 0, 0, 0, 0, $w, $h);
        for ($i = 0; $i < 3; $i++) { @imagefilter($blur, IMG_FILTER_GAUSSIAN_BLUR); }
        $cx = $w / 2; $cy = $h / 2;
        $rx = $w * 0.62; $ry = $h * 0.66;   // generous sharp ellipse (subject stays inside)
        for ($y = 0; $y < $h; $y += 2) {
            for ($x = 0; $x < $w; $x += 2) {
                $dx = ($x - $cx) / $rx; $dy = ($y - $cy) / $ry;
                $d = sqrt($dx * $dx + $dy * $dy);
                if ($d <= 0.72) { continue; }          // sharpen core
                $wgt = (1 - $d) / 0.28;                // 1 at d=0.72 -> 0 at d=1.0 (wide smooth ramp)
                $wgt = max(0.0, min(1.0, $wgt));
                $wg = $wgt * $wgt * (3 - 2 * $wgt);
                if ($wg >= 0.999) { continue; }
                $c = imagecolorat($img, $x, $y);
                $b = imagecolorat($blur, $x, $y);
                $r = (int) round((($c >> 16) & 255) * $wg + (($b >> 16) & 255) * (1 - $wg));
                $g = (int) round((($c >> 8) & 255) * $wg + (($b >> 8) & 255) * (1 - $wg));
                $bb = (int) round(($c & 255) * $wg + ($b & 255) * (1 - $wg));
                imagesetpixel($img, $x, $y, imagecolorallocate($img, $r, $g, $bb));
            }
        }
        imagedestroy($blur);

        $name = 'studio/portrait-'.Str::uuid().'.png';
        \Illuminate\Support\Facades\Storage::disk('public')->put($name, $this->pngBytes($img));
        imagedestroy($img);
        return '/storage/'.$name;
    }

    /**
     * Reflect a coordinate into the range [0, len) so the scene edges can be mirrored outward
     * (seamless extension without duplication artifacts).
     */
    protected function mirrorCoord(int $v, int $len): int
    {
        if ($len <= 0) { return 0; }
        $m = 2 * $len;
        $v = (($v % $m) + $m) % $m;
        if ($v >= $len) { $v = $m - 1 - $v; }
        return $v < 0 ? 0 : min($len - 1, $v);
    }

    /**
     * Moderate the final image via DashScope image-moderation (free tier).
     * Returns true if the image passes, false if it should be blocked.
     */
    protected function moderateImage(string $imageUrl): bool
    {
        $key = studio_api_key('dashscope') ?: studio_api_key('qwen') ?: studio_api_key('qwen_edit');
        if (! $key) { return true; } // no key -> skip moderation (don't block the pipeline)

        $base = dashscope_base_url($key).'/api/v1';
        try {
            $resp = \Illuminate\Support\Facades\Http::withToken($key)->timeout(30)
                ->post($base.'/services/aigc/image-moderation/image-moderation', [
                    'model' => 'image-moderation',
                    'input' => ['image' => $imageUrl],
                ]);
            if ($resp->successful()) {
                $suggestion = data_get($resp->json(), 'output.suggestion', 'pass');
                $label = data_get($resp->json(), 'output.label', '');
                if ($suggestion === 'block') {
                    logger()->warning('Image moderation BLOCKED', ['label' => $label, 'url' => $imageUrl]);
                    return false;
                }
                if ($suggestion === 'review') {
                    logger()->info('Image moderation REVIEW', ['label' => $label, 'url' => $imageUrl]);
                    // Allow review items through but log them
                }
                return true;
            }
            logger()->warning('Image moderation API failed', ['status' => $resp->status()]);
        } catch (\Throwable $e) {
            logger()->warning('Image moderation error: '.$e->getMessage());
        }
        return true; // moderation failed -> allow (don't block the pipeline on API errors)
    }

    /**
     * Score a swap result via qwen-vl-max on 4 criteria: garment preservation, face quality,
     * pose accuracy, and overall aesthetic. Returns a 0-10 score array or null on failure.
     */
    protected function scoreSwapResult(string $imageUrl, string $designImage = ''): ?array
    {
        $key = studio_api_key('qwen') ?: studio_api_key('dashscope');
        if (! $key) { return null; }

        $base = dashscope_base_url($key).'/compatible-mode/v1/chat/completions';
        $models = studio_qwen_vision_models();

        $instruction = ($designImage !== '')
            ? 'You are a fashion photography quality evaluator. The FIRST image is the ORIGINAL design (its garment is the product and must be preserved). The SECOND image is the result to rate 1-10 for each criterion:'
            : 'You are a fashion photography quality evaluator. Rate the image 1-10 for each criterion:';
        $instruction .= '\n1. garment_preservation: garment identical to the original design (colors, patterns, silhouette, length)'
            .'\n2. face_quality: face sharp, natural, well-lit, photorealistic'
            .'\n3. pose_accuracy: pose natural and correctly executed'
            .'\n4. overall_aesthetic: overall appeal, lighting, composition'
            .'\nReturn ONLY valid JSON: {"garment_preservation":N,"face_quality":N,"pose_accuracy":N,"overall_aesthetic":N}';

        foreach ($models as $model) {
            try {
                $content = [];
                if ($designImage !== '') {
                    $content[] = ['type' => 'image_url', 'image_url' => ['url' => studio_vision_image_url($designImage)]];
                }
                $content[] = ['type' => 'image_url', 'image_url' => ['url' => studio_vision_image_url($imageUrl)]];
                $content[] = ['type' => 'text', 'text' => $instruction];

                $resp = \Illuminate\Support\Facades\Http::withToken($key)->timeout(45)
                    ->post($base, [
                        'model' => $model,
                        'messages' => [['role' => 'user', 'content' => $content]],
                        'temperature' => 0.1,
                    ]);

                if ($resp->successful()) {
                    $raw = trim((string) data_get($resp->json(), 'choices.0.message.content'));
                    // Extract JSON from response (may be wrapped in markdown code fences)
                    if (preg_match('/\{[^}]+\}/s', $raw, $m)) {
                        $scores = json_decode($m[0], true);
                        if (is_array($scores) && isset($scores['garment_preservation'])) {
                            logger()->info('QA scored swap result', ['model' => $model, 'scores' => $scores]);
                            return $scores;
                        }
                    }
                }
                // 404/429/5xx -> thử model vision kế tiếp (backoff nhẹ khi rate-limit, không bỏ cuộc ngay).
                if ($resp->status() === 404 || str_contains(strtolower((string) $resp->body()), 'not found')
                    || $resp->status() === 429 || $resp->status() >= 500) {
                    if ($resp->status() === 429) { sleep(2); }
                    continue;
                }
                logger()->warning('QA scoring failed', ['model' => $model, 'status' => $resp->status()]);
            } catch (\Throwable $e) {
                logger()->warning('QA scoring error: '.$e->getMessage());
            }
        }
        return null;
    }

    /**
     * Upscale the swap result via DashScope image-super-resolution (2x or 4x).
     * Falls back gracefully when no key is configured or the API call fails.
     */
    protected function applySuperResolution(string $url, int $scale = 2): ?string
    {
        $rel = ltrim((string) parse_url($url, PHP_URL_PATH), '/');
        $file = null;
        foreach ([public_path($rel), storage_path('app/public/'.str_replace('storage/', '', $rel))] as $cand) {
            if (is_file($cand)) { $file = $cand; break; }
        }
        if (! $file) { return null; }

        $key = studio_api_key('dashscope') ?: studio_api_key('qwen') ?: studio_api_key('qwen_edit');
        if (! $key) { return null; }

        $base = dashscope_base_url($key).'/api/v1';
        try {
            $resp = Http::withToken($key)->timeout(120)
                ->post($base.'/services/aigc/image-enhancement/image-super-resolution', [
                    'model' => 'image-super-resolution',
                    'input' => ['image' => 'data:image/png;base64,'.base64_encode((string) file_get_contents($file))],
                    'parameters' => ['scale' => $scale],
                ]);
            if ($resp->successful()) {
                $upscaledUrl = data_get($resp->json(), 'output.results.0.url');
                if ($upscaledUrl) {
                    $imgSvc = app(\App\Services\ImageAIService::class);
                    // storeRemoteImage is protected — use a direct store via file_get_contents
                    $contents = @file_get_contents($upscaledUrl);
                    if ($contents) {
                        $name = 'studio/sr-'.Str::uuid().'.png';
                        \Illuminate\Support\Facades\Storage::disk('public')->put($name, $contents);
                        logger()->info('Super-resolution upscaled '.$scale.'x', ['src' => $url, 'dst' => '/storage/'.$name]);
                        return '/storage/'.$name;
                    }
                }
            }
            logger()->warning('Super-resolution failed', ['status' => $resp->status(), 'body' => substr((string) $resp->body(), 0, 200)]);
        } catch (\Throwable $e) {
            logger()->warning('Super-resolution error: '.$e->getMessage());
        }
        return null;
    }

    /**
     * Enhance the face in the swap result via DashScope face-image-enhance.
     * Fixes blurry/low-res faces that the edit model sometimes produces.
     */
    protected function applyFaceEnhance(string $url): ?string
    {
        $rel = ltrim((string) parse_url($url, PHP_URL_PATH), '/');
        $file = null;
        foreach ([public_path($rel), storage_path('app/public/'.str_replace('storage/', '', $rel))] as $cand) {
            if (is_file($cand)) { $file = $cand; break; }
        }
        if (! $file) { return null; }

        $key = studio_api_key('dashscope') ?: studio_api_key('qwen') ?: studio_api_key('qwen_edit');
        if (! $key) { return null; }

        $base = dashscope_base_url($key).'/api/v1';
        try {
            $resp = Http::withToken($key)->timeout(60)
                ->post($base.'/services/aigc/image-enhancement/face-image-enhance', [
                    'model' => 'face-image-enhance',
                    'input' => ['image' => 'data:image/png;base64,'.base64_encode((string) file_get_contents($file))],
                ]);
            if ($resp->successful()) {
                $enhancedUrl = data_get($resp->json(), 'output.results.0.url');
                if ($enhancedUrl) {
                    $contents = @file_get_contents($enhancedUrl);
                    if ($contents) {
                        $name = 'studio/fe-'.Str::uuid().'.png';
                        \Illuminate\Support\Facades\Storage::disk('public')->put($name, $contents);
                        logger()->info('Face enhanced', ['src' => $url, 'dst' => '/storage/'.$name]);
                        return '/storage/'.$name;
                    }
                }
            }
            logger()->warning('Face-enhance failed', ['status' => $resp->status(), 'body' => substr((string) $resp->body(), 0, 200)]);
        } catch (\Throwable $e) {
            logger()->warning('Face-enhance error: '.$e->getMessage());
        }
        return null;
    }

    /**
     * Safety net: if the edit model darkened the subject against a dark background (silhouette),
     * lift the exposure of the central subject band with a soft falloff. Only applies when the
     * subject region is genuinely dark — an already-lit result (e.g. a good composite) is untouched.
     */
    protected function brightenDarkSubject(string $url): ?string
    {
        $rel = ltrim((string) parse_url($url, PHP_URL_PATH), '/');
        $file = null;
        foreach ([public_path($rel), storage_path('app/public/'.str_replace('storage/', '', $rel))] as $cand) {
            if (is_file($cand)) { $file = $cand; break; }
        }
        if (! $file) { return null; }
        $img = @imagecreatefromstring((string) file_get_contents($file));
        if (! $img) { return null; }
        $w = imagesx($img); $h = imagesy($img);
        $cx = $w / 2;
        $x0 = (int) ($w * 0.26); $x1 = (int) ($w * 0.74);
        $y0 = (int) ($h * 0.04); $y1 = (int) ($h * 0.96);
        $changed = false;
        // Selective shadow-lift: brighten DARK pixels only (the subject), within the central band,
        // leaving bright background (lanterns, lit walls) and an already-lit subject untouched.
        // This works even when the background is bright (night scene with lights) and the box-average
        // heuristic would have failed to trigger.
        for ($y = 0; $y < $h; $y += 2) {
            for ($x = 0; $x < $w; $x += 2) {
                $wx = max(0.0, 1 - abs($x - $cx) / ($w * 0.24));
                $wy = ($y >= $y0 && $y <= $y1) ? 1.0 : max(0.0, 1 - min(abs($y - $y0), abs($y - $y1)) / ($h * 0.05));
                $wgt = $wx * $wy;
                if ($wgt <= 0.03) { continue; }
                $c = imagecolorat($img, $x, $y);
                $r = ($c >> 16) & 255; $g = ($c >> 8) & 255; $b = $c & 255;
                $lum = 0.299 * $r + 0.587 * $g + 0.114 * $b;
                if ($lum >= 74) { continue; }   // already bright (lantern / wall / lit subject) -> skip
                $lift = (74 - $lum) / 74;
                $boost = 1 + 0.35 * $lift * $wgt;   // gentler lift -> balanced, not overexposed
                imagesetpixel($img, $x, $y, imagecolorallocate($img,
                    max(0, min(255, (int) round($r * $boost))),
                    max(0, min(255, (int) round($g * $boost))),
                    max(0, min(255, (int) round($b * $boost)))));
                $changed = true;
            }
        }
        if (! $changed) { imagedestroy($img); return null; }   // nothing was dark -> untouched
        $name = 'studio/swapbright-'.Str::uuid().'.png';
        \Illuminate\Support\Facades\Storage::disk('public')->put($name, $this->pngBytes($img));
        imagedestroy($img);
        return '/storage/'.$name;
    }

    /**
     * "Thay Đổi Người Mẫu" (Click-to-Swap) — virtual try-on with a chosen model + pose.
     */
    public function swapModel(Request $request)
    {
        if (! studio_config('swap_enabled', false)) {
            return response()->json(['message' => 'Tính năng Thay Đổi Người Mẫu đang tạm tắt.'], 403);
        }

        $data = $request->validate([
            'image' => ['required', 'string', 'max:2048'],   // design image URL (generation media_url or /storage)
            'model_id' => ['nullable', 'string', 'max:80'],   // bắt buộc khi change_face=true
            'change_face' => ['nullable', 'boolean'],            // true = đổi khuôn mặt theo người mẫu; false/mặc định = giữ khuôn mặt gốc
            'pose_id' => ['required', 'string', 'max:80'],
            'background' => ['nullable', 'string', 'max:400'],
            'tone' => ['nullable', 'string', 'max:20'],     // Hiệu ứng tông màu (auto/warm/cool/film/cinematic/dramatic/mono/none)
            'pose_ref' => ['nullable', 'string', 'max:2048'], // pose reference image URL (picker thumbnail; not sent to the model)
        ]);

        $svc = app(\App\Services\VirtualTryOnService::class);
        $changeFace = (bool) ($data['change_face'] ?? false);
        $model = $changeFace ? $svc->pickModel((string) $data['model_id']) : null;
        $pose = $svc->pickPose($data['pose_id']);
        if ($changeFace && ! $model) {
            return response()->json(['message' => 'Không tìm thấy người mẫu.'], 422);
        }
        if (! $pose) {
            return response()->json(['message' => 'Không tìm thấy dáng.'], 422);
        }
        if ($model) {
            logger()->info('Swap face resolved', ['model_id' => $data['model_id'], 'name' => $model['name'], 'image' => $model['image'] ?? null]);
        }

        // Pose reference image: prefer the client-sent one, fall back to the pose catalog image
        // (custom asset / DB preset / built-in sample) so the model can actually replicate the pose.
        $poseRefUrl = (string) ($data['pose_ref'] ?? '') ?: (string) ($pose['image'] ?? '');
        $swapModel = studio_swap_model();

        // The long AI pipeline (try-on + optional face-swap, ~1-3 min per pose) runs in the background
        // queue (SwapModelJob) so this request returns immediately — a synchronous 2-pass swap gets
        // cut by the hosting proxy timeout ("chạy lâu không thấy kết quả").
        $gen = auth()->user()->generations()->create([
            'type' => 'image', 'status' => 'pending',
            'prompt' => 'Thay đổi người mẫu · '.($changeFace ? ($model['name'] ?? 'model') : 'giữ nguyên khuôn mặt').' · '.($pose['name'] ?? 'pose'),
            'model' => $swapModel, 'provider' => 'qwen', 'credits_cost' => 1,
            'meta' => [
                'swap' => true,
                'image' => $data['image'],
                'model_id' => $data['model_id'],
                'pose_id' => $data['pose_id'],
                'model_name' => $model['name'] ?? null,
                'pose_name' => $pose['name'] ?? null,
                'change_face' => $changeFace,
                'face_ref' => $changeFace && (bool) ($model['image'] ?? null),
                'pose_ref' => $poseRefUrl,
                'background' => (string) ($data['background'] ?? ''),
                'tone' => (string) ($data['tone'] ?? 'none'),
            ],
        ]);

        \App\Jobs\SwapModelJob::dispatch($gen->id);

        return response()->json(['generation_id' => $gen->id, 'status' => 'pending', 'provider' => 'qwen', 'model' => $swapModel, 'task_id' => null]);
    }

    /**
     * Run the swap AI pipeline for a queued generation (called by SwapModelJob in the background).
     * Validates the references, runs try-on (+ optional face-swap), post-process, tone, then stores
     * the finished result on the generation row.
     */
    public function executeSwapFromGeneration(\App\Models\Generation $gen): void
    {
        $meta = (array) ($gen->meta ?? []);
        $svc = app(\App\Services\VirtualTryOnService::class);
        $changeFace = (bool) ($meta['change_face'] ?? false);
        $model = $changeFace ? $svc->pickModel((string) ($meta['model_id'] ?? '')) : null;
        $pose = $svc->pickPose((string) ($meta['pose_id'] ?? ''));
        if (($changeFace && ! $model) || ! $pose) {
            $gen->update(['status' => 'failed', 'error' => $changeFace ? 'Không tìm thấy người mẫu hoặc dáng.' : 'Không tìm thấy dáng.']);
            return;
        }

        $fallback = $svc->fallbackEdit(
            (string) ($meta['image'] ?? ''),
            $changeFace ? ($model['desc'] ?? ($model['ethnicity'] ?? 'a model')) : '',
            $pose['skeleton'] ?? ($pose['name'] ?? 'standing'),
            (string) ($meta['background'] ?? ''),
            $changeFace ? ($model['image'] ?? null) : null, // face reference only khi bật đổi khuôn mặt
            (string) ($meta['tone'] ?? 'none'),
            (string) ($meta['pose_ref'] ?? ''),
            $changeFace,
        );
        if (! $fallback) {
            $gen->update(['status' => 'failed', 'error' => 'Không thể thay đổi người mẫu. Kiểm tra model “'.studio_swap_model().'” và key Qwen Edit (Pay-As-You-Go).']);
            return;
        }

        // Safety net (TẮT mặc định): kéo sáng chủ thể tối. Có thể làm lệch màu trang phục nên chỉ bật
        // khi cần chống hiện tượng silhouette đen — cấu hình STUDIO_SWAP_BRIGHTEN=true.
        if (studio_config('swap_brighten', false)) {
            $bright = $this->brightenDarkSubject($fallback);
            if ($bright) { $fallback = $bright; }
        }

        // "Tách nền + hiệu ứng + gộp": mode = removebg | bokeh | off (default removebg).
        //  removebg: segment the person with remove.bg (accurate alpha), blur the background, then
        //            recomposite the SHARP person on top — subject never blurred. If no remove.bg key
        //            is configured (studio.removebg_key) the call is skipped and the raw result kept.
        //  bokeh:    deterministic depth-of-field on the original frame.
        //  off:      no background post-processing (the raw swap result).
        $mode = (string) studio_config('swap_portrait_depth', 'removebg');
        if ($mode === 'removebg') {
            $seg = $this->applySegmentComposite($fallback);
            if ($seg) { $fallback = $seg; }
        } elseif ($mode === 'bokeh') {
            $portrait = $this->applyPortraitDepth($fallback);
            if ($portrait) { $fallback = $portrait; }
        }

        // Làm nhỏ nhân vật một chút (mặc định ~10%) — mở rộng nền nhẹ (không mirror), người giữ nét.
        // Config swap_scale: 0.90 = nhỏ hơn 10%, 1 = tắt.
        $scale = (float) studio_config('swap_scale', 0.90);
        if ($scale > 0.05 && $scale < 1.0) {
            $scaled = $this->applyScaleDown($fallback, $scale);
            if ($scaled) { $fallback = $scaled; }
        }

        // Post-process: upscale (model image-super-resolution — KHÔNG có trên host intl, tắt mặc định).
        if (studio_config('swap_superres', false)) {
            $upscaled = $this->applySuperResolution($fallback, (int) studio_config('swap_superres_scale', 2));
            if ($upscaled) { $fallback = $upscaled; }
        }

        // Post-process: face-enhance khi đổi mặt (model face-image-enhance — KHÔNG có trên host intl, tắt mặc định).
        if ($changeFace && studio_config('swap_face_enhance', false)) {
            $enhanced = $this->applyFaceEnhance($fallback);
            if ($enhanced) { $fallback = $enhanced; }
        }

        // Safety: moderate (model image-moderation — KHÔNG có trên host intl, tắt mặc định).
        if (studio_config('swap_moderation', false)) {
            if (! $this->moderateImage($fallback)) {
                logger()->warning('Swap result flagged by moderation, replacing with fallback');
                $gen->update(['status' => 'failed', 'error' => 'Kết quả không đạt kiểm duyệt nội dung. Vui lòng thử lại với ảnh khác.']);
                return;
            }
        }

        // QA: score the final result (qwen3.8-flash / qwen-vl — bật mặc định, fail êm nếu rate-limit).
        $qaScores = studio_config('swap_qa', true) ? $this->scoreSwapResult($fallback, (string) ($meta['image'] ?? '')) : null;

        $swapModel = studio_swap_model();
        $actualModel = $svc->lastModel() ?: $swapModel;
        $credits = max(1, $svc->calls()); // 2-3 (edit: try-on + face-swap + background)

        $gen->update([
            'status' => 'completed', 'media_url' => $fallback,
            'model' => $actualModel, 'credits_cost' => $credits,
            'meta' => array_merge($meta, [
                'type' => 'image', 'provider' => 'qwen', 'model' => $actualModel, 'config_model' => $swapModel,
                'steps' => $credits,
                'qa' => $qaScores,
            ]),
        ]);
    }

    public function translate(Request $request)
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:4000'],
            'direction' => ['required', 'in:en,vi'],
        ]);
        $text = trim((string) $data['text']);
        $target = $data['direction'] === 'vi' ? 'Vietnamese' : 'English';
        $qwenKey = studio_api_key('qwen') ?: studio_api_key('dashscope');
        $geminiKey = studio_api_key('gemini');
        $qwenModel = (string) studio_config('qwen_prompt_model', 'qwen3.8-flash'); // Qwen chat multimodal (fallback)
        $translateModel = (string) studio_config('translate_model', 'gemini-3.6-flash-image'); // Model dịch chuyên dụng
        $instruction = 'You are a professional fashion prompt translator. Translate the following image-generation prompt to '.$target.'. '
            .'Keep all technical descriptors (fabric, silhouette, camera, lighting) precise. Return ONLY the translated prompt, nothing else.';

        // Gemini translation model candidates — try the configured one, then a safe fallback.
        $gemModels = array_values(array_unique(array_filter([
            $translateModel, 'gemini-2.5-flash', 'gemini-2.0-flash',
        ])));
        if ($geminiKey) {
            foreach ($gemModels as $gm) {
                logger()->info('Translate via GEMINI', ['model' => $gm, 'dir' => $data['direction']]);
                try {
                    $resp = Http::withHeaders(['x-goog-api-key' => $geminiKey])->timeout(60)
                        ->post('https://generativelanguage.googleapis.com/v1beta/models/'.$gm.':generateContent', [
                            'contents' => [['parts' => [['text' => $instruction."\n\n".$text]]]],
                            'generationConfig' => ['responseMimeType' => 'text/plain'],
                        ]);
                    if ($resp->successful()) {
                        $out = trim((string) data_get($resp->json(), 'candidates.0.content.parts.0.text'));
                        if ($out !== '') { return response()->json(['text' => $out, 'provider' => 'gemini', 'model' => $gm]); }
                    }
                    // 404 / model-not-found -> try the next Gemini model; other errors -> log & stop.
                    if ($resp->status() === 404 || str_contains(strtolower((string) $resp->body()), 'not found')) {
                        logger()->warning('Translate (gemini) model not found: '.$gm.' - '.substr((string) $resp->body(), 0, 160));
                        continue;
                    }
                    logger()->warning('Translate (gemini) HTTP '.$resp->status().' '.substr((string) $resp->body(), 0, 180));
                } catch (\Throwable $e) {
                    logger()->error('Translate (gemini) failed: '.$e->getMessage());
                }
            }
        }

        // Qwen chat fallback (if no Gemini key / Gemini failed).
        if ($qwenKey) {
            logger()->info('Translate via QWEN', ['model' => $qwenModel, 'dir' => $data['direction']]);
            try {
                $resp = Http::withToken($qwenKey)->timeout(60)
                    ->post(dashscope_base_url($qwenKey).'/compatible-mode/v1/chat/completions', [
                        'model' => $qwenModel, 'messages' => [
                            ['role' => 'system', 'content' => $instruction],
                            ['role' => 'user', 'content' => $text],
                        ],
                    ]);
                if ($resp->successful()) {
                    $out = trim((string) data_get($resp->json(), 'choices.0.message.content'));
                    if ($out !== '') { return response()->json(['text' => $out, 'provider' => 'qwen', 'model' => $qwenModel]); }
                }
                logger()->warning('Translate (qwen) HTTP '.$resp->status().' '.substr((string) $resp->body(), 0, 180));
            } catch (\Throwable $e) {
                logger()->error('Translate (qwen) failed: '.$e->getMessage());
            }
        }

        return response()->json(['text' => $text, 'provider' => 'none', 'model' => null]); // no key / failed -> keep as-is
    }

    /**
     * Upload a face reference (Fitting Room face-sync) — sets the global face so the edit/surgery applies it.
     */
    protected function resolveReferencePath(string $url): ?string
    {
        $path = ltrim((string) parse_url($url, PHP_URL_PATH), '/');

        foreach ([public_path($path), storage_path('app/public/'.$path)] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Test a provider API key with a lightweight, non-generating request.
     */
    public function testApi(string $service)
    {
        $key = studio_api_key($service);

        if (! $key) {
            return response()->json(['ok' => false, 'message' => 'Chưa cấu hình khoá cho '.$service.'.'], 422);
        }

        try {
            $result = match ($service) {
                'gemini' => $this->testGemini($key),
                'replicate' => $this->testReplicate($key),
                'fal' => ['ok' => true, 'message' => 'Fal.ai: khoá đã lưu (không có endpoint ping miễn phí).'],
                'wan', 'qwen', 'dashscope' => $this->testDashscope($key),
                'qwen_edit' => $this->testQwenEdit($key),
                default => ['ok' => false, 'message' => 'Không hỗ trợ test '.$service.'.'],
            };
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }

        return response()->json($result);
    }

    protected function testGemini(string $key): array
    {
        $resp = Http::timeout(20)->get('https://generativelanguage.googleapis.com/v1beta/models?key='.$key);

        return $resp->successful()
            ? ['ok' => true, 'message' => 'Gemini: kết nối OK ('.count($resp->json('models', []) ?: []).' models).']
            : ['ok' => false, 'message' => 'Gemini: HTTP '.$resp->status().' — '.data_get($resp->json(), 'error.message', 'key không hợp lệ')];
    }

    protected function testReplicate(string $key): array
    {
        $resp = Http::withToken($key)->timeout(20)->get('https://api.replicate.com/v1/models');

        return $resp->successful()
            ? ['ok' => true, 'message' => 'Replicate: kết nối OK.']
            : ['ok' => false, 'message' => 'Replicate: HTTP '.$resp->status().' — key không hợp lệ'];
    }

    /**
     * Lightweight eligibility probe for the dedicated Qwen image-edit model (auth/eligibility only).
     */
    protected function testQwenEdit(string $key): array
    {
        $model = (string) studio_config('qwen_edit_model', 'qwen-image-edit');
        $base = dashscope_base_url($key).'/api/v1';
        $onePx = 'data:image/png;base64,'.base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));

        try {
            $resp = Http::withToken($key)->timeout(25)
                ->post($base.'/services/aigc/multimodal-generation/generation', [
                    'model' => $model,
                    'input' => ['messages' => [['role' => 'user', 'content' => [
                        ['image' => $onePx],
                        ['text' => 'no change'],
                    ]]]],
                    'parameters' => ['watermark' => false],
                ]);

            if ($resp->successful()) {
                return ['ok' => true, 'message' => 'Qwen Edit “'.$model.'” khả dụng (kết nối OK).'];
            }

            if ($resp->status() === 403) {
                return ['ok' => false, 'message' => 'Model edit “'.$model.'” CHƯA được mua/kích hoạt (403 AccessDenied.Unpurchased). '
                    .'Bật/mua model Qwen-Image-Edit trong QwenCloud Model Center, hoặc dùng Gemini.'];
            }
            if ($resp->status() === 404) {
                return ['ok' => false, 'message' => 'Model edit “'.$model.'” không tồn tại trên host này. Chọn model edit đúng gói/QwenCloud.'];
            }
            if ($resp->status() === 401) {
                return ['ok' => false, 'message' => 'Khoá không hợp lệ (401 InvalidApiKey). Dùng key Pay-As-You-Go (sk-…/sk-ws-…).'];
            }

            return ['ok' => false, 'message' => 'HTTP '.$resp->status().': '.substr((string) $resp->body(), 0, 180)];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Đã gửi yêu cầu nhưng chưa có phản hồi ('.$e->getMessage().'). Model có thể đang xử lý — thử lại sau.'];
        }
    }

    protected function testDashscope(string $key): array
    {
        // Wan (image & video) + Qwen run on a DashScope-compatible endpoint. Try every
        // candidate host (classic region + QwenCloud Token/Coding Plan) because a
        // QwenCloud key is bound to a specific base URL by key type.
        $configured = dashscope_base_url($key);
        $candidates = array_unique([
            $configured,
            'https://dashscope.aliyuncs.com',
            'https://dashscope-intl.aliyuncs.com',
            'https://token-plan.ap-southeast-1.maas.aliyuncs.com',
            'https://coding-intl.dashscope.aliyuncs.com',
        ]);

        // Use a REAL image model (matching the generation fallback chain) instead of a made-up
        // name: on plan hosts a non-existent model can return 401 and wrongly look like a bad key.
        $models = ['qwen-image-3.0-pro', 'qwen-image-max', 'qwen-image-plus', 'qwen-image', 'wan2.7-image-pro'];

        $last = null;
        foreach ($candidates as $host) {
            $unpurchased = [];
            foreach ($models as $model) {
                try {
                    $resp = Http::withToken($key)->timeout(25)
                        ->post($host.'/api/v1/services/aigc/multimodal-generation/generation', [
                            'model' => $model,
                            'input' => ['messages' => [['role' => 'user', 'content' => [['text' => 'a minimalist premium fashion editorial photo']]]]],
                            'parameters' => ['n' => 1, 'size' => '1328*1328', 'watermark' => false],
                        ]);
                } catch (\Throwable $e) {
                    continue;
                }

                if ($resp->successful()) {
                    return ['ok' => true, 'message' => 'DashScope: khóa hợp lệ tại '.$host.' — model '.$model.' dùng được (đã tạo thử 1 ảnh).'];
                }
                $status = $resp->status();
                $body = strtolower((string) $resp->body());

                if (in_array($status, [400, 422])) {
                    return ['ok' => true, 'message' => 'DashScope: khóa hợp lệ tại '.$host.' — model '.$model.' dùng được.'];
                }
                if ($status === 403 || str_contains($body, 'unpurchased') || str_contains($body, 'eligible')) {
                    $unpurchased[] = $model;

                    continue;
                }
                // 401 / other auth issues on this host — try the next host/model.
                $last = ['status' => $status, 'host' => $host];
            }

            if ($unpurchased) {
                return ['ok' => false, 'message' => 'DashScope: khóa hợp lệ tại '.$host.' — nhưng model ảnh ('.implode(', ', $unpurchased).') CHƯA được mua trên tài khoản (403 Unpurchased). Hãy bật/mua một model Qwen-Image trong QwenCloud Model Center, hoặc dùng Gemini.'];
            }
        }

        // Probe model CHAT/VISION ĐA PHƯƠNG THỨC (qwen3.8-flash/max, qwen-plus…) qua endpoint
        // OpenAI-compatible — để nút Test phản ánh đúng model chat/vision bạn đang cấu hình
        // (qwen3.8-max chẳng hạn), không chỉ model sinh ảnh như trước.
        $chatProbe = array_values(array_unique(array_filter([
            (string) studio_config('qwen_vision_model', ''),
            (string) studio_config('qwen_prompt_model', ''),
            'qwen3.8-flash', 'qwen3.8-max', 'qwen-plus', 'qwen-turbo',
        ])));
        foreach ($chatProbe as $cm) {
            if (! is_qwen_vision_capable($cm)) {
                continue;
            }
            foreach ($candidates as $host) {
                try {
                    $resp = Http::withToken($key)->timeout(20)
                        ->post($host.'/compatible-mode/v1/chat/completions', [
                            'model' => $cm,
                            'messages' => [['role' => 'user', 'content' => 'hi']],
                            'max_tokens' => 5,
                        ]);
                    if ($resp->successful()) {
                        return ['ok' => true, 'message' => 'DashScope: khóa hợp lệ tại '.$host.' — model chat/vision “'.$cm.'” dùng được (đã test thử chat).'];
                    }
                    if ($resp->status() === 401) {
                        break; // key không hợp lệ trên host này -> thử host kế tiếp
                    }
                } catch (Throwable $e) {
                    // không phản hồi -> thử host/model khác
                }
            }
        }

        return ['ok' => false, 'message' => 'DashScope: key chưa được chấp nhận (HTTP '.($last['status'] ?? '…').' tại '.($last['host'] ?? '…').'). Tạo key mới tại https://home.qwencloud.com/api-keys và dán đầy đủ. Gợi ý ổn định: dùng Gemini (Google AI Studio key) để tạo ảnh.'];
    }

    /**
     * Library — browse & manage all generated assets.
     */
    /**
     * Vue library page (Thư viện) — grid of all generations + gallery popup.
     */
    public function libraryVue()
    {
        return view('studio.library-vue');
    }

    public function library(Request $request)
    {
        $query = auth()->user()->generations()->with('project')->latest();

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->input('project_id'));
        }
        if ($request->filled('q')) {
            $query->where('prompt', 'like', '%'.$request->input('q').'%');
        }

        $generations = $query->paginate(24)->withQueryString();
        $projects = auth()->user()->projects()->orderBy('name')->get();

        return view('studio.library', compact('generations', 'projects'));
    }

    /**
     * Download a generated asset.
     */
    public function download(Generation $generation)
    {
        abort_unless($generation->user_id === auth()->id(), 403);

        if (! $generation->media_url) {
            abort(404);
        }

        $path = ltrim((string) parse_url($generation->media_url, PHP_URL_PATH), '/');
        $path = str_replace('storage/', '', $path);
        $abs = storage_path('app/public/'.$path);

        if (! is_file($abs)) {
            abort(404);
        }

        return response()->download($abs, basename($abs));
    }

    /**
     * Rename a generation's custom label (shown in the output library / canvas layer).
     */
    public function renameGeneration(Request $request, Generation $generation)
    {
        abort_unless($generation->user_id === auth()->id(), 403);

        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $meta = is_array($generation->meta) ? $generation->meta : [];
        $meta['name'] = (string) $data['name'];
        $generation->update(['meta' => $meta]);

        return response()->json(['ok' => true, 'name' => $meta['name']]);
    }

    /**
     * Extract dominant colors from a generated image (for the color palette).
     */
    public function palette(Generation $generation)
    {
        abort_unless($generation->user_id === auth()->id(), 403);

        if (! $generation->media_url) {
            return response()->json(['colors' => []]);
        }

        $rel = ltrim((string) parse_url($generation->media_url, PHP_URL_PATH), '/');
        $rel = str_replace('storage/', '', $rel);
        $abs = storage_path('app/public/'.$rel);

        if (! is_file($abs)) {
            return response()->json(['colors' => []]);
        }

        try {
            return response()->json(['colors' => $this->extractPalette($abs, 6)]);
        } catch (\Throwable $e) {
            logger()->warning('palette failed: '.$e->getMessage());
            return response()->json(['colors' => []]);
        }
    }

    protected function extractPalette(string $file, int $count = 6): array
    {
        $src = @imagecreatefromstring((string) file_get_contents($file));
        if (! $src) {
            return [];
        }

        $W = imagesx($src);
        $H = imagesy($src);
        if ($W <= 0 || $H <= 0) {
            return [];
        }

        $w = 64;
        $h = max(1, (int) round($H * ($w / $W)));
        $thumb = imagecreatetruecolor($w, $h);
        imagecopyresampled($thumb, $src, 0, 0, 0, 0, $w, $h, $W, $H);

        $buckets = [];
        for ($y = 0; $y < $h; $y += 2) {
            for ($x = 0; $x < $w; $x += 2) {
                $c = imagecolorat($thumb, $x, $y);
                $r = ($c >> 16) & 0xFF;
                $g = ($c >> 8) & 0xFF;
                $b = $c & 0xFF;
                $key = ((int) ($r / 32)).','.((int) ($g / 32)).','.((int) ($b / 32));
                if (! isset($buckets[$key])) {
                    $buckets[$key] = ['n' => 0, 'r' => 0, 'g' => 0, 'b' => 0];
                }
                $buckets[$key]['n']++;
                $buckets[$key]['r'] += $r;
                $buckets[$key]['g'] += $g;
                $buckets[$key]['b'] += $b;
            }
        }

        imagedestroy($thumb);
        imagedestroy($src);

        uasort($buckets, fn ($a, $b) => $b['n'] <=> $a['n']);

        $colors = [];
        foreach (array_slice($buckets, 0, $count) as $bk) {
            $r = (int) round($bk['r'] / $bk['n']);
            $g = (int) round($bk['g'] / $bk['n']);
            $b = (int) round($bk['b'] / $bk['n']);
            $colors[] = sprintf('#%02X%02X%02X', $r, $g, $b);
        }

        return $colors;
    }

    /**
     * JSON settings data for the Vue Settings page (API keys, models, providers, config).
     */
    /**
     * JSON save for the Vue Settings page (add/update API key + model + config).
     */
    public function settingsSave(Request $request): IlluminateHttpJsonResponse
    {
        $d = $request->all();
        if (! empty($d['key_value'])) {
            $k = new \App\Models\StudioApiKey();
            $k->provider = (string) ($d['key_provider'] ?? '');
            $k->label = (string) ($d['key_label'] ?? $k->provider);
            $k->value = \Illuminate\Support\Facades\Crypt::encryptString((string) $d['key_value']);
            $k->kind = (string) ($d['key_kind'] ?? '');
            $k->scopes = ['*'];
            $k->priority = (int) ($d['key_priority'] ?? 5);
            $k->enabled = true;
            $k->save();
        }
        if (! empty($d['model_name'])) {
            \App\Models\StudioModel::create([
                'group' => (string) ($d['model_group'] ?? 'image'),
                'name' => (string) $d['model_name'],
                'provider' => (string) ($d['model_provider'] ?? ''),
                'model_id' => (string) ($d['model_id'] ?? ''),
                'api_key_ref' => (string) ($d['model_key_ref'] ?? ''),
                'priority' => (int) ($d['model_priority'] ?? 5),
                'enabled' => true,
            ]);
        }
        if (! empty($d['config']) && is_array($d['config'])) {
            foreach ($d['config'] as $ck => $cv) { if (is_string($ck)) setting([$ck => $cv]); }
        }
        return response()->json(['ok' => true]);
    }

    public function settingsData(): IlluminateHttpJsonResponse
    {
        $providers = [
            'gemini' => ['label' => 'Gemini', 'configured' => (bool) studio_api_key('gemini')],
            'fal' => ['label' => 'Fal.ai — Flux', 'configured' => (bool) studio_api_key('fal')],
            'replicate' => ['label' => 'Replicate — Flux', 'configured' => (bool) studio_api_key('replicate')],
            'wan' => ['label' => 'Wan AI — video', 'configured' => (bool) (studio_api_key('wan') ?: studio_api_key('dashscope'))],
            'veo' => ['label' => 'Google Veo — video', 'configured' => (bool) studio_api_key('veo')],
            'qwen' => ['label' => 'Qwen — ảnh', 'configured' => (bool) studio_api_key('qwen')],
            'qwen_edit' => ['label' => 'Qwen Edit — inpaint', 'configured' => (bool) studio_api_key('qwen_edit')],
            'dashscope' => ['label' => 'DashScope', 'configured' => (bool) studio_api_key('dashscope')],
            'deepseek' => ['label' => 'DeepSeek', 'configured' => (bool) studio_api_key('deepseek')],
        ];
        return response()->json([
            'providers' => $providers,
            'api_keys' => \App\Models\StudioApiKey::orderBy('provider')->orderBy('priority','desc')->get(),
            'models' => \App\Models\StudioModel::orderBy('priority','desc')->orderBy('id')->get(),
            'config' => [
                'image_provider' => setting('studio_image_provider', 'flux'),
                'qwen_model' => setting('studio_qwen_model', ''),
                'vision_provider' => setting('studio_vision_provider', 'gemini'),
                'prompt_provider' => setting('studio_prompt_provider', 'gemini'),
            ],
        ]);
    }

    public function settings()
    {
        return view('studio.settings', [
            'image_credits' => setting('studio_image_credits', config('studio.image_credits')),
            'video_credits' => setting('studio_video_credits', config('studio.video_credits')),
            'max_generations' => setting('studio_max_generations', 50),
            'image_provider' => setting('studio_image_provider', config('studio.image_provider')),
            'prompt_provider' => setting('studio_prompt_provider', config('studio.prompt_provider')),
            'vision_provider' => setting('studio_vision_provider', config('studio.vision_provider')),
            'prompt_model' => setting('studio_prompt_model', config('studio.prompt_model')),
            'qwen_prompt_model' => setting('studio_qwen_prompt_model', config('studio.qwen_prompt_model', 'qwen3.8-flash')),
            'qwen_max_model' => setting('studio_qwen_max_model', config('studio.qwen_max_model', 'qwen3.8-max')),
            'qwen_vision_model' => setting('studio_qwen_vision_model', config('studio.qwen_vision_model', 'qwen3.8-flash')),
            'qwen_vision_models' => setting('studio_qwen_vision_models', ''),
            'qwen_text_models' => setting('studio_qwen_text_models', ''),
            'translate_model' => setting('studio_translate_model', config('studio.translate_model')),
            'swap_model' => setting('studio_swap_model', ''), // '' = dùng chung qwen_edit_model
            'stylist_model' => setting('studio_stylist_model', config('studio.stylist_model')),
            'image_model' => setting('studio_image_model', config('studio.image_model')),
            'wan_model' => setting('studio_wan_model', config('studio.wan_model')),
            'qwen_model' => setting('studio_qwen_model', config('studio.qwen_model')),
            'qwen_edit_model' => setting('studio_qwen_edit_model', config('studio.qwen_edit_model')),
            'gemini_image_model' => setting('studio_gemini_image_model', config('studio.gemini_image_model')),
            'video_model' => setting('studio_video_model', config('studio.video_model')),
            'vision_model' => setting('studio_vision_model', config('studio.vision_model')),
            'dashscope_base' => setting('studio_dashscope_base', config('studio.dashscope_base')),
            'dashscope_token_plan_base' => setting('studio_dashscope_token_plan_base', config('studio.dashscope_token_plan_base')),
            'processing' => setting('studio_processing', config('studio.processing')),
            'image_resolution' => setting('studio_image_resolution', config('studio.image_resolution')),
            'video_resolution' => setting('studio_video_resolution', config('studio.video_resolution')),
            'image_ratio' => setting('studio_image_ratio', config('studio.image_ratio')),
            'video_duration' => setting('studio_video_duration', config('studio.video_duration')),
            'creative_level' => setting('studio_creative_level', config('studio.creative_level', 6)),
            'texture' => setting('studio_texture', config('studio.texture', 5)),
            'prompt_prefix' => setting('studio_prompt_prefix', config('studio.prompt_prefix', '')),
            'prompt_suffix' => setting('studio_prompt_suffix', config('studio.prompt_suffix', '')),
            'negative_prompt' => setting('studio_negative_prompt', config('studio.negative_prompt', '')),
            'faceswap_prompt' => setting('studio_faceswap_prompt', config('studio.faceswap_prompt', '')),
            'enrich_prompt' => filter_var(setting('studio_enrich_prompt', config('studio.enrich_prompt', true)), FILTER_VALIDATE_BOOLEAN),
            // Cấu hình RIÊNG cho "💡 Gợi ý từ ảnh" (tách khỏi Vision chung).
            'suggest_enabled' => studio_suggest_enabled(),
            'suggest_provider' => studio_suggest_provider(),
            'suggest_gemini_model' => studio_suggest_gemini_model(),
            'suggest_qwen_model' => (string) studio_suggest_config('qwen_model', 'qwen3.8-flash'),
            'suggest_qwen_models' => (string) studio_suggest_config('qwen_models', ''),
            'suggest_creative_level' => (int) studio_suggest_config('creative_level', 6),
            'suggest_max_styles' => (int) studio_suggest_config('max_styles', 3),
            'suggest_downscale_max' => (int) studio_suggest_config('downscale_max', 1024),
            'suggest_fallback' => studio_suggest_fallback(),
            'suggest_include_video' => studio_suggest_include_video(),
            'suggest_default_lang' => (string) studio_suggest_config('default_lang', 'en'),
            'pending_count' => auth()->user()->generations()->whereIn('status', ['pending', 'processing'])->count(),
            'queue_driver' => config('queue.default'),
            'usage' => studio_usage(auth()->user()),
            'models' => studio_models(), // registry models (grouped by category)
            'api_keys' => \App\Models\StudioApiKey::orderBy('provider')->orderByDesc('priority')->orderBy('id')->get(),
            'face_presets' => \App\Models\FacePreset::orderBy('sort')->orderBy('id')->get(),
            'pose_presets' => \App\Models\PosePreset::orderBy('sort')->orderBy('id')->get(),
            'providers' => $this->providerStatus(),
        ]);
    }

    protected function providerStatus(): array
    {
        return [
            'gemini' => ['label' => 'Gemini — Giám đốc sáng tạo', 'hint' => 'GEMINI_API_KEY', 'configured' => (bool) studio_api_key('gemini')],
            'fal' => ['label' => 'Fal.ai — Flux (ảnh)', 'hint' => 'FAL_KEY', 'configured' => (bool) studio_api_key('fal')],
            'replicate' => ['label' => 'Replicate — Flux (ảnh)', 'hint' => 'REPLICATE_API_TOKEN', 'configured' => (bool) studio_api_key('replicate')],
            'wan' => ['label' => 'Wan AI — video', 'hint' => 'WAN_API_KEY / DASHSCOPE_API_KEY', 'configured' => (bool) (studio_api_key('wan') ?: studio_api_key('dashscope'))],
            'veo' => ['label' => 'Google Veo — video', 'hint' => 'GOOGLE_VEO_KEY', 'configured' => (bool) studio_api_key('veo')],
            'qwen' => ['label' => 'Qwen — ảnh (QwenCloud)', 'hint' => 'QWEN_API_KEY (home.qwencloud.com/api-keys)', 'configured' => (bool) studio_api_key('qwen')],
            'qwen_edit' => ['label' => 'Qwen Edit — chỉnh sửa ảnh / Inpaint', 'hint' => 'QWEN_EDIT_KEY', 'configured' => (bool) studio_api_key('qwen_edit')],
            'dashscope' => ['label' => 'DashScope — Wan/Qwen image & video', 'hint' => 'DASHSCOPE_API_KEY', 'configured' => (bool) studio_api_key('dashscope')],
        ];
    }

    public function storeModel(Request $request)
    {
        $data = $request->validate([
            'group' => ['required', 'string', 'in:image,video,inference,text'],
            'name' => ['required', 'string', 'max:120'],
            'provider' => ['required', 'string', 'max:40'],
            'model_id' => ['required', 'string', 'max:160'],
            'api_key_ref' => ['nullable', 'string', 'max:80'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $data['priority'] = (int) ($data['priority'] ?? 0);
        $data['enabled'] = true;
        \App\Models\StudioModel::create($data);
        if ($request->wantsJson()) return response()->json(['ok' => true]);
        return back()->with('success', 'Đã thêm model.');
    }

    public function updateModel(Request $request, \App\Models\StudioModel $model)
    {
        $data = $request->validate([
            'group' => ['required', 'string', 'in:image,video,inference,text'],
            'name' => ['required', 'string', 'max:120'],
            'provider' => ['required', 'string', 'max:40'],
            'model_id' => ['required', 'string', 'max:160'],
            'api_key_ref' => ['nullable', 'string', 'max:80'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100'],
            'enabled' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $data['priority'] = (int) ($data['priority'] ?? 0);
        $model->update($data);
        if ($request->wantsJson()) return response()->json(['ok' => true]);
        return back()->with('success', 'Đã cập nhật model.');
    }

    public function deleteModel(\App\Models\StudioModel $model)
    {
        $model->delete();
        if (request()->wantsJson()) return response()->json(['ok' => true]);
        return back()->with('success', 'Đã xóa model.');
    }

    /**
     * Face presets (khuôn mặt mẫu) — manageable from Studio Settings.
     */
    public function facePresetStore(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:1200'],
            'ethnicity' => ['nullable', 'string', 'max:80'],
            'image' => ['nullable', 'image', 'max:8192'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $image = null;
        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $image = '/storage/'.$request->file('image')->store('studio/faces', 'public');
        }

        \App\Models\FacePreset::create([
            'name' => $data['name'],
            'description' => $data['description'],
            'ethnicity' => $data['ethnicity'] ?? null,
            'image' => $image,
            'sort' => (int) ($data['sort'] ?? 0),
            'enabled' => true,
        ]);

        return back()->with('success', 'Đã thêm khuôn mặt mẫu.');
    }

    public function facePresetUpdate(Request $request, \App\Models\FacePreset $preset)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:1200'],
            'ethnicity' => ['nullable', 'string', 'max:80'],
            'image' => ['nullable', 'image', 'max:8192'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'enabled' => ['nullable', 'boolean'],
        ]);

        $fill = [
            'name' => $data['name'],
            'description' => $data['description'],
            'ethnicity' => $data['ethnicity'] ?? null,
            'sort' => (int) ($data['sort'] ?? $preset->sort),
            'enabled' => ! empty($data['enabled']),
        ];
        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $fill['image'] = '/storage/'.$request->file('image')->store('studio/faces', 'public');
        }
        $preset->update($fill);

        return back()->with('success', 'Đã cập nhật khuôn mặt mẫu.');
    }

    public function facePresetDestroy(\App\Models\FacePreset $preset)
    {
        $preset->delete();
        return back()->with('success', 'Đã xóa khuôn mặt mẫu.');
    }

    /**
     * Pose presets (dáng mẫu) — manageable from Studio Settings.
     */
    public function posePresetStore(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:1200'],
            'image' => ['nullable', 'image', 'max:8192'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $image = null;
        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $image = '/storage/'.$request->file('image')->store('studio/poses', 'public');
        }

        \App\Models\PosePreset::create([
            'name' => $data['name'],
            'description' => $data['description'],
            'image' => $image,
            'sort' => (int) ($data['sort'] ?? 0),
            'enabled' => true,
        ]);

        return back()->with('success', 'Đã thêm dáng mẫu.');
    }

    public function posePresetUpdate(Request $request, \App\Models\PosePreset $preset)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:1200'],
            'image' => ['nullable', 'image', 'max:8192'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'enabled' => ['nullable', 'boolean'],
        ]);

        $fill = [
            'name' => $data['name'],
            'description' => $data['description'],
            'sort' => (int) ($data['sort'] ?? $preset->sort),
            'enabled' => ! empty($data['enabled']),
        ];
        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $fill['image'] = '/storage/'.$request->file('image')->store('studio/poses', 'public');
        }
        $preset->update($fill);

        return back()->with('success', 'Đã cập nhật dáng mẫu.');
    }

    public function posePresetDestroy(\App\Models\PosePreset $preset)
    {
        $preset->delete();
        return back()->with('success', 'Đã xóa dáng mẫu.');
    }

    public function storeApiKey(Request $request)
    {
        $data = $request->validate([
            'provider' => ['required', 'string', 'max:40'],
            'label' => ['required', 'string', 'max:120'],
            'value' => ['required', 'string', 'max:500'],
            'kind' => ['nullable', 'string', 'max:20'],
            'scopes' => ['nullable', 'string'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $data['priority'] = (int) ($data['priority'] ?? 0);
        $data['enabled'] = true;
        $data['value'] = \Illuminate\Support\Facades\Crypt::encryptString(trim($data['value']));
        $data['scopes'] = ['*']; // key dùng chung (độc lập model)
        \App\Models\StudioApiKey::create($data);
        return redirect()->back()->with('success', 'Đã thêm API key.');
    }

    public function updateApiKey(Request $request, \App\Models\StudioApiKey $key)
    {
        $data = $request->validate([
            'provider' => ['required', 'string', 'max:40'],
            'label' => ['required', 'string', 'max:120'],
            'value' => ['nullable', 'string', 'max:500'],
            'kind' => ['nullable', 'string', 'max:20'],
            'scopes' => ['nullable', 'string'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100'],
            'enabled' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $data['priority'] = (int) ($data['priority'] ?? 0);
        $data['scopes'] = ['*']; // key dùng chung (độc lập model)
        if (! empty($data['value'])) $data['value'] = \Illuminate\Support\Facades\Crypt::encryptString(trim($data['value']));
        else unset($data['value']);
        $key->update($data);
        return redirect()->back()->with('success', 'Đã cập nhật API key.');
    }

    public function deleteApiKey(\App\Models\StudioApiKey $key)
    {
        $key->delete();
        return redirect()->back()->with('success', 'Đã xóa API key.');
    }

    public function updateModelSettings(Request $request)
    {
        $data = $request->validate([
            'swap_model' => ['nullable', 'string', 'max:255'],
            'qwen_edit_model' => ['nullable', 'string', 'max:255'],
            'qwen_vision_model' => ['nullable', 'string', 'max:255'],
            'qwen_vision_models' => ['nullable', 'string', 'max:1000'],
        ]);

        if (isset($data['swap_model'])) set_setting('studio_swap_model', $data['swap_model']);
        if (isset($data['qwen_edit_model'])) set_setting('studio_qwen_edit_model', $data['qwen_edit_model']);
        if (isset($data['qwen_vision_model'])) set_setting('studio_qwen_vision_model', $data['qwen_vision_model']);
        if (isset($data['qwen_vision_models'])) set_setting('studio_qwen_vision_models', $data['qwen_vision_models']);

        return back()->with('success', 'Đã lưu cấu hình model Thay Đổi Người Mẫu.');
    }

    /**
     * Cấu hình RIÊNG cho "💡 Gợi ý từ ảnh" — provider + model + hành vi độc lập,
     * không phụ thuộc cấu hình chung (Vision / Model Registry).
     */
    public function updateSuggestSettings(Request $request)
    {
        $data = $request->validate([
            'suggest_enabled' => ['nullable', 'string', 'in:1'],
            'suggest_provider' => ['required', 'string', 'in:gemini,qwen'],
            'suggest_gemini_model' => ['nullable', 'string', 'max:255'],
            'suggest_qwen_model' => ['nullable', 'string', 'max:255'],
            'suggest_qwen_models' => ['nullable', 'string', 'max:1000'],
            'suggest_creative_level' => ['nullable', 'integer', 'min:1', 'max:10'],
            'suggest_max_styles' => ['nullable', 'integer', 'min:1', 'max:5'],
            'suggest_downscale_max' => ['nullable', 'integer', 'min:64', 'max:4096'],
            'suggest_fallback' => ['nullable', 'string', 'in:1'],
            'suggest_include_video' => ['nullable', 'string', 'in:1'],
            'suggest_default_lang' => ['required', 'string', 'in:en,vi'],
        ]);

        set_setting('studio_suggest_enabled', ! empty($data['suggest_enabled']) ? '1' : '0');
        set_setting('studio_suggest_provider', $data['suggest_provider']);
        set_setting('studio_suggest_gemini_model', $data['suggest_gemini_model'] ?? '');
        set_setting('studio_suggest_qwen_model', $data['suggest_qwen_model'] ?? '');
        set_setting('studio_suggest_qwen_models', $data['suggest_qwen_models'] ?? '');
        if (isset($data['suggest_creative_level'])) set_setting('studio_suggest_creative_level', (string) $data['suggest_creative_level']);
        if (isset($data['suggest_max_styles'])) set_setting('studio_suggest_max_styles', (string) $data['suggest_max_styles']);
        if (isset($data['suggest_downscale_max'])) set_setting('studio_suggest_downscale_max', (string) $data['suggest_downscale_max']);
        set_setting('studio_suggest_fallback', ! empty($data['suggest_fallback']) ? '1' : '0');
        set_setting('studio_suggest_include_video', ! empty($data['suggest_include_video']) ? '1' : '0');
        set_setting('studio_suggest_default_lang', $data['suggest_default_lang']);

        return back()->with('success', 'Đã lưu cấu hình "Gợi ý từ ảnh".');
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'image_credits' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'video_credits' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'max_generations' => ['nullable', 'integer', 'min:1', 'max:500'],
            'image_provider' => ['required', 'string', 'in:flux,wan,qwen,gemini'],
            'prompt_provider' => ['required', 'string', 'in:gemini,qwen,deepseek'],
            'vision_provider' => ['required', 'string', 'in:gemini,qwen'],
            'prompt_model' => ['required', 'string', 'max:255'],
            'qwen_prompt_model' => ['nullable', 'string', 'max:255'],
            'qwen_max_model' => ['nullable', 'string', 'max:255'],
            'qwen_vision_model' => ['nullable', 'string', 'max:255'],
            'qwen_vision_models' => ['nullable', 'string', 'max:1000'],
            'qwen_text_models' => ['nullable', 'string', 'max:1000'],
            'translate_model' => ['nullable', 'string', 'max:255'],
            'swap_model' => ['nullable', 'string', 'max:255'],
            'stylist_model' => ['nullable', 'string', 'max:255'],
            'image_model' => ['nullable', 'string', 'max:255'],
            'wan_model' => ['nullable', 'string', 'max:255'],
            'qwen_model' => ['nullable', 'string', 'max:255'],
            'qwen_edit_model' => ['nullable', 'string', 'max:255'],
            'gemini_image_model' => ['nullable', 'string', 'max:255'],
            'video_model' => ['nullable', 'string', 'max:255'],
            'vision_model' => ['nullable', 'string', 'max:255'],
            'dashscope_base' => ['required', 'string', 'max:255', 'regex:/^https?:\/\/[^\/]+$/'],
            'dashscope_token_plan_base' => ['nullable', 'string', 'max:255', 'regex:/^https?:\/\/[^\/]+$/'],
            'processing' => ['required', 'string', 'in:sync,queue'],
            'image_resolution' => ['required', 'string', 'in:1K,2K'],
            'video_resolution' => ['required', 'string', 'in:480,720,1080'],
            'image_ratio' => ['required', 'string', 'in:1:1,4:3,3:4,16:9,9:16,4:5,21:9,19:6'],
            'video_duration' => ['required', 'string', 'in:5,8,10,15,20'],
            'creative_level' => ['nullable', 'integer', 'min:1', 'max:10'],
            'texture' => ['nullable', 'integer', 'min:0', 'max:10'],
            'prompt_prefix' => ['nullable', 'string', 'max:500'],
            'prompt_suffix' => ['nullable', 'string', 'max:500'],
            'negative_prompt' => ['nullable', 'string', 'max:2000'],
            'faceswap_prompt' => ['nullable', 'string', 'max:2000'],
            'enrich_prompt' => ['nullable', 'string', 'in:1'],
        ]);

        if (isset($data['image_credits'])) set_setting('studio_image_credits', (string) $data['image_credits']);
        if (isset($data['video_credits'])) set_setting('studio_video_credits', (string) $data['video_credits']);
        if (isset($data['max_generations'])) set_setting('studio_max_generations', (string) ($data['max_generations'] ?? 50));
        set_setting('studio_image_provider', $data['image_provider']);
        set_setting('studio_prompt_provider', $data['prompt_provider']);
        set_setting('studio_vision_provider', $data['vision_provider']);
        set_setting('studio_prompt_model', $data['prompt_model']);
        if (isset($data['qwen_prompt_model'])) set_setting('studio_qwen_prompt_model', $data['qwen_prompt_model']);
        if (isset($data['qwen_max_model'])) set_setting('studio_qwen_max_model', $data['qwen_max_model']);
        if (isset($data['qwen_vision_model'])) set_setting('studio_qwen_vision_model', $data['qwen_vision_model']);
        if (isset($data['qwen_vision_models'])) set_setting('studio_qwen_vision_models', $data['qwen_vision_models']);
        if (isset($data['qwen_text_models'])) set_setting('studio_qwen_text_models', $data['qwen_text_models']);
        if (isset($data['translate_model'])) set_setting('studio_translate_model', $data['translate_model']);
        if (isset($data['swap_model'])) set_setting('studio_swap_model', $data['swap_model']);
        if (isset($data['stylist_model'])) set_setting('studio_stylist_model', $data['stylist_model']);
        set_setting('studio_image_model', $data['image_model'] ?? '');
        set_setting('studio_wan_model', $data['wan_model']);
        set_setting('studio_qwen_model', $data['qwen_model']);
        set_setting('studio_qwen_edit_model', $data['qwen_edit_model'] ?? '');
        set_setting('studio_gemini_image_model', $data['gemini_image_model'] ?? '');
        set_setting('studio_video_model', $data['video_model']);
        set_setting('studio_vision_model', $data['vision_model']);
        set_setting('studio_dashscope_base', $data['dashscope_base']);
        set_setting('studio_dashscope_token_plan_base', $data['dashscope_token_plan_base'] ?? config('studio.dashscope_token_plan_base'));
        set_setting('studio_processing', $data['processing']);
        set_setting('studio_image_resolution', $data['image_resolution']);
        set_setting('studio_video_resolution', $data['video_resolution']);
        set_setting('studio_image_ratio', $data['image_ratio']);
        set_setting('studio_video_duration', $data['video_duration']);
        if (isset($data['creative_level'])) set_setting('studio_creative_level', (string) $data['creative_level']);
        if (isset($data['texture'])) set_setting('studio_texture', (string) $data['texture']);
        if (isset($data['prompt_prefix'])) set_setting('studio_prompt_prefix', $data['prompt_prefix']);
        if (isset($data['prompt_suffix'])) set_setting('studio_prompt_suffix', $data['prompt_suffix']);
        if (isset($data['negative_prompt'])) set_setting('studio_negative_prompt', $data['negative_prompt']);
        if (isset($data['faceswap_prompt'])) set_setting('studio_faceswap_prompt', $data['faceswap_prompt']);
        set_setting('studio_enrich_prompt', ! empty($data['enrich_prompt']) ? '1' : '0');

        return back()->with('success', 'Đã lưu cài đặt Studio.');
    }

    public function api()
    {
        $providers = [
            'gemini' => ['label' => 'Gemini — Giám đốc sáng tạo', 'hint' => 'GEMINI_API_KEY', 'configured' => (bool) studio_api_key('gemini')],
            'fal' => ['label' => 'Fal.ai — Flux (ảnh)', 'hint' => 'FAL_KEY', 'configured' => (bool) studio_api_key('fal')],
            'replicate' => ['label' => 'Replicate — Flux (ảnh)', 'hint' => 'REPLICATE_API_TOKEN', 'configured' => (bool) studio_api_key('replicate')],
            'wan' => ['label' => 'Wan AI — video (dùng DASHSCOPE_API_KEY)', 'hint' => 'WAN_API_KEY / DASHSCOPE_API_KEY', 'configured' => (bool) (studio_api_key('wan') ?: studio_api_key('dashscope'))],
            'veo' => ['label' => 'Google Veo — video', 'hint' => 'GOOGLE_VEO_KEY', 'configured' => (bool) studio_api_key('veo')],
            'qwen' => ['label' => 'Qwen — ảnh (QwenCloud, dùng endpoint DashScope)', 'hint' => 'QWEN_API_KEY (home.qwencloud.com/api-keys) · model qwen-image', 'configured' => (bool) studio_api_key('qwen')],
            'qwen_edit' => ['label' => 'Qwen Edit — chỉnh sửa ảnh / Inpaint', 'hint' => 'QWEN_EDIT_KEY · model edit (qwen-image-edit, wanx2.1-imageedit…)', 'configured' => (bool) studio_api_key('qwen_edit')],
            'dashscope' => ['label' => 'DashScope — Wan/Qwen image & video (Alibaba)', 'hint' => 'DASHSCOPE_API_KEY', 'configured' => (bool) studio_api_key('dashscope')],
            'deepseek' => ['label' => 'DeepSeek — ngôn ngữ / suy luận (prompt, chat)', 'hint' => 'DEEPSEEK_API_KEY · model deepseek-chat', 'configured' => (bool) studio_api_key('deepseek')],
        ];

        return view('studio.api', compact('providers'));
    }

    public function updateApi(Request $request)
    {
        $services = ['gemini', 'fal', 'replicate', 'wan', 'veo', 'qwen', 'qwen_edit', 'dashscope'];

        foreach ($services as $service) {
            // Clear if requested, else store a new encrypted key, else keep.
            if ($request->boolean('clear_'.$service)) {
                set_setting('api_'.$service.'_key', '');

                continue;
            }

            $value = trim((string) $request->input('key_'.$service, ''));

            if ($value !== '') {
                set_setting('api_'.$service.'_key', Crypt::encryptString($value));
            }
        }

        return back()->with('success', 'Đã lưu cấu hình API.');
    }

    /**
     * Active products with an image — used as reference-image sources in Studio.
     */
    public function references()
    {
        $items = Product::where('is_active', true)->whereNotNull('image')
            ->latest()->limit(40)->get(['id', 'name', 'image'])
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'url' => $p->image_url])
            ->filter(fn ($i) => ! empty($i['url']))
            ->values();

        return response()->json(['items' => $items]);
    }

    /**
     * Prompt template (preset) manager — admin CRUD.
     */
    public function presets()
    {
        $presets = Preset::orderBy('sort_order')->get()->groupBy('category');
        $categories = ['fabric', 'silhouette', 'style', 'background', 'pose', 'camera', 'lens', 'video_scene'];

        return view('studio.presets', compact('presets', 'categories'));
    }

    public function storePreset(Request $request)
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'max:40'],
            'ui_label' => ['required', 'string', 'max:120'],
            'prompt_injection' => ['required', 'string', 'max:1000'],
            'note' => ['nullable', 'string', 'max:600'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        Preset::create($data + ['sort_order' => $data['sort_order'] ?? 0]);

        return back()->with('success', 'Đã thêm preset.');
    }

    public function updatePreset(Request $request, Preset $preset)
    {
        $data = $request->validate([
            'ui_label' => ['required', 'string', 'max:120'],
            'prompt_injection' => ['required', 'string', 'max:1000'],
            'note' => ['nullable', 'string', 'max:600'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $preset->update($data + ['sort_order' => $data['sort_order'] ?? 0]);

        return back()->with('success', 'Đã cập nhật preset.');
    }

    public function destroyPreset(Preset $preset)
    {
        $preset->delete();

        return back()->with('success', 'Đã xóa preset.');
    }

    /**
     * Pattern Maker — generate a seamless fabric pattern via the configured image provider.
     */
    public function patternPage()
    {
        return view('studio.pattern', [
            'latest' => auth()->user()->generations()->where('type', 'image')->latest()->limit(8)->get(),
        ]);
    }

    public function tryonPage()
    {
        return view('studio.tryon', [
            'latest' => auth()->user()->generations()->where('type', 'image')->latest()->limit(8)->get(),
        ]);
    }

    public function pattern(Request $request)
    {
        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:2000'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'history_id' => ['nullable', 'integer', 'exists:prompts_history,id'],
        ]);
        $data['prompt'] = 'Seamless textile fabric pattern, '.$data['prompt'].', high detail, repeatable tile, premium fashion, 4k';
        $cost = (int) studio_config('image_credits', 1);

        return $this->queueGeneration('image', $data, $cost);
    }

    /**
     * Virtual Try-On — best-effort try-on using the image provider (upload a person photo).
     */
    public function tryon(Request $request)
    {
        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'max:8192'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'history_id' => ['nullable', 'integer', 'exists:prompts_history,id'],
        ]);
        $cost = (int) studio_config('image_credits', 1);

        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $data['base_image'] = '/storage/'.$request->file('image')->store('studio/ref', 'public');
        }

        return $this->queueGeneration('image', $data, $cost);
    }

    /**
     * Latest generations (JSON) — used to re-sync the Studio output grid reliably.
     */
    /**
     * Return studio config defaults so the frontend can initialise its sliders/fields.
     */
    public function defaults(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'creative_level' => (int) studio_config('creative_level', 6),
            'texture' => (int) studio_config('texture', 5),
            'image_resolution' => (string) studio_config('image_resolution', '1K'),
            'image_ratio' => (string) studio_config('image_ratio', '1:1'),
            'video_duration' => (string) studio_config('video_duration', '10'),
            'video_resolution' => (string) studio_config('video_resolution', '720'),
            'enrich_prompt' => (bool) studio_config('enrich_prompt', true),
            'negative_prompt' => (string) studio_config('negative_prompt', ''),
            // Gợi ý từ ảnh — trạng thái + ngôn ngữ mặc định cho SuggestCard.
            'suggest_enabled' => studio_suggest_enabled(),
            'suggest_default_lang' => (string) studio_suggest_config('default_lang', 'en'),
            'image_credits' => (int) studio_config('image_credits', 1),
        ]);
    }

    public function latest()
    {
        $items = auth()->user()->generations()->with('project')->latest()->limit(30)->get()
            ->map(fn ($g) => [
                'id' => $g->id, 'type' => $g->type, 'status' => $g->status,
                'model' => $g->model, 'provider' => $g->provider,
                'media_url' => $g->media_url, 'error' => $g->error,
                'credits_cost' => $g->credits_cost, 'project_id' => $g->project_id,
                'prompts_history_id' => $g->prompts_history_id,
                'created_at' => $g->created_at?->format('d/m H:i'),
                'resolution' => $g->resolution, 'ratio' => $g->ratio, 'duration' => $g->duration,
                'elapsed_ms' => $g->elapsed_ms, 'meta' => $g->meta, 'prompt' => $g->prompt,
            ])->values();

        return response()->json(['items' => $items]);
    }

    /**
     * Process the user's queued generations synchronously (no worker / cron needed).
     * Best for quick jobs (stub / Gemini / short renders); long async jobs (Wan/Qwen)
     * are better handled by the queue worker via cron.
     */
    public function processQueue()
    {
        // Swap generations are handled by SwapModelJob via the queue worker, not by this sync path.
        $pending = auth()->user()->generations()
            ->whereIn('status', ['pending', 'processing'])
            ->orderBy('id')->limit(10)->get()
            ->reject(fn ($g) => ($g->meta['swap'] ?? false) === true)
            ->take(5)->values();

        $n = 0;
        foreach ($pending as $gen) {
            try {
                if ($gen->type === 'video') {
                    RenderVideoJob::dispatchSync($gen->id);
                } else {
                    RenderImageJob::dispatchSync($gen->id);
                }
                $n++;
            } catch (Throwable $e) {
                logger()->error('Process queue failed for generation #'.$gen->id.': '.$e->getMessage());
            }
        }

        return response()->json(['processed' => $n, 'message' => 'Đã xử lý '.$n.' công việc đang chờ.']);
    }

    /**
     * Return the last 5 unique image prompts for the user (prompt history).
     */
    public function promptHistory(): \Illuminate\Http\JsonResponse
    {
        $items = auth()->user()->prompts()
            ->whereNotNull('image_prompt_en')
            ->where('image_prompt_en', '!=', '')
            ->latest()
            ->limit(20)
            ->get(['id', 'image_prompt_en', 'json_response', 'created_at'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'prompt' => $p->image_prompt_en,
                'creative_level' => $p->json_response['creative_level'] ?? null,
                'texture' => $p->json_response['texture'] ?? null,
                'negative_prompt' => $p->json_response['negative_prompt'] ?? null,
                'created_at' => $p->created_at?->format('d/m H:i'),
            ]);

        // Deduplicate by prompt text, keep most recent
        $seen = [];
        $unique = [];
        foreach ($items as $item) {
            $key = md5($item['prompt']);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $item;
            }
        }

        return response()->json(['items' => array_slice($unique, 0, 5)]);
    }

    /**
     * Preview the enriched prompt (prefix + suffix + texture + directive) without generating.
     */
    public function previewEnrich(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:4000'],
            'creative_level' => ['nullable', 'integer', 'min:1', 'max:10'],
            'texture' => ['nullable', 'integer', 'min:0', 'max:10'],
            'negative_prompt' => ['nullable', 'string', 'max:2000'],
        ]);

        $userPrompt = (string) $data['prompt'];
        $creativeLevel = (int) ($data['creative_level'] ?? studio_config('creative_level', 6));
        $texture = (int) ($data['texture'] ?? studio_config('texture', 5));
        $customNegative = $data['negative_prompt'] ?? null;

        $direction = app(\App\Services\CreativeDirectionService::class);
        $enriched = $direction->enrichGeneratePrompt($userPrompt, $creativeLevel, $texture, $customNegative);

        return response()->json([
            'original' => $userPrompt,
            'enriched' => $enriched['prompt'],
            'negative_prompt' => $enriched['negative_prompt'],
            'prefix' => $enriched['prefix'],
            'suffix' => $enriched['suffix'],
            'texture_descriptor' => $enriched['texture_descriptor'],
            'creativity_directive' => $enriched['creativity_directive'],
        ]);
    }
}
