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
            'base_image' => ['nullable', 'string', 'max:2048'], // edit path: change bg/pose keeping exact pixels
            'edit' => ['nullable', 'string', 'in:1,true'],
        ]);

        // Ensure a shared prompt-history so all variants group as one "generation run".
        if (empty($data['history_id'])) {
            $history = auth()->user()->prompts()->create([
                'idea' => null,
                'image_prompt_en' => $data['prompt'],
                'video_prompt_en' => null,
                'json_response' => ['image_prompt_en' => $data['prompt']],
            ]);
            $data['history_id'] = $history->id;
        }

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
        ]);

        $preserveBg = ! empty($data['preserve_background']);
        $preserveFace = ! empty($data['preserve_face']);

        $data['prompt'] = 'Using the provided image as the exact base, edit it surgically. Change ONLY: '.$request->input('prompt')
            .'. Preserve everything else exactly as in the original image — '
            .($preserveFace ? 'the model\'s face and identity, skin tone and hair, ' : '')
            .'pose, body proportions, garment structure and fit, fabric, all colours except the edited element, lighting, shadows, camera angle, composition'
            .($preserveBg ? ', and background' : '')
            .'. Do not restyle, do not add new elements, do not change the setting.';

        if ($generation->media_url) {
            $data['base_image'] = $generation->media_url;
        }
        $data['edit'] = true;

        $cost = (int) studio_config('image_credits', 1);

        return $this->queueGeneration('image', $data, $cost, $generation);
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
        ]);

        $op = (string) $data['op'];
        if (self::REGION_OPS[$op]['needs_prompt'] && trim((string) ($data['prompt'] ?? '')) === '') {
            return response()->json(['message' => 'Nhập mô tả nội dung thay thế cho vùng chọn.'], 422);
        }

        if (! $generation->media_url) {
            return response()->json(['message' => 'Ảnh nguồn chưa có kết quả.'], 422);
        }

        $file = null;
        foreach ([public_path(ltrim((string) parse_url($generation->media_url, PHP_URL_PATH), '/')), storage_path('app/public/'.str_replace('storage/', '', ltrim((string) parse_url($generation->media_url, PHP_URL_PATH), '/')))] as $cand) {
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

        // Mask: TRẮNG = giữ nguyên, ĐEN = vùng chỉnh sửa (cùng kích thước ảnh gốc)
        $mask = imagecreatetruecolor($w, $h);
        imagefilledrectangle($mask, 0, 0, $w - 1, $h - 1, imagecolorallocate($mask, 255, 255, 255));
        imagefilledrectangle($mask, $px, $py, $px + $pw - 1, $py + $ph - 1, imagecolorallocate($mask, 0, 0, 0));
        $maskName = 'studio/mask-'.Str::uuid().'.png';
        \Illuminate\Support\Facades\Storage::disk('public')->put($maskName, $this->pngBytes($mask));
        imagedestroy($mask);
        $maskUrl = '/storage/'.$maskName;

        $cost = (int) studio_config('image_credits', 1);
        $hasAi = (bool) (studio_api_key('qwen_edit') ?: studio_api_key('dashscope') ?: studio_api_key('qwen'));

        if ($hasAi) {
            return $this->queueGeneration('image', [
                'prompt' => $this->regionPrompt($op, (string) ($data['prompt'] ?? '')),
                'base_image' => $generation->media_url,
                'mask_image' => $maskUrl,
                'edit' => true,
            ], $cost, $generation);
        }

        // Không có key AI → lấp cục bộ bằng GD (chế độ stub / offline vẫn dùng được)
        $this->localEraseFill($src, $px, $py, $pw, $ph);
        $name = 'studio/erase-'.Str::uuid().'.png';
        \Illuminate\Support\Facades\Storage::disk('public')->put($name, $this->pngBytes($src));
        imagedestroy($src);
        $gen = auth()->user()->generations()->create([
            'type' => 'image', 'status' => 'completed', 'media_url' => '/storage/'.$name,
            'prompt' => 'Xóa vùng chọn (local)', 'model' => 'erase', 'provider' => 'local', 'credits_cost' => 0,
        ]);
        return response()->json(['generation_id' => $gen->id, 'status' => 'completed', 'media_url' => '/storage/'.$name, 'model' => 'erase', 'provider' => 'local', 'credits_cost' => 0]);
    }

    /**
     * Prompt theo thao tác vùng chọn — MỞ RỘNG: thêm nhánh mới khi thêm op vào REGION_OPS.
     */
    protected function regionPrompt(string $op, string $userPrompt): string
    {
        $mask = ' A mask image is provided (same size as the base image): its BLACK region is the exact area to edit — change ONLY that black region and keep every pixel outside it identical to the original image.';
        if ($op === 'erase') {
            return 'Remove the object, person or content inside the BLACK region of the mask and fill the area naturally with the surrounding background, fabric or pattern so the result looks clean, seamless and untouched. Do not leave any trace, edge artifact or blur of the removed content.'.$mask;
        }
        $p = trim($userPrompt);
        return $p.'. Apply this change ONLY inside the BLACK region of the mask; keep everything outside it identical to the original image.'.$mask;
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

        // 1+2) Reconstruction tuyến tính: trộn nền ngang (trái↔phải) và dọc (trên↔dưới) theo vị trí.
        //      Guard đen: nếu một cạnh gần đen (thanh đen/letterbox) thì dùng màu cạnh đối diện.
        $dark = function (int $c): bool { return ((($c >> 16) & 0xFF) + (($c >> 8) & 0xFF) + ($c & 0xFF)) < 72; };
        $spanX = max(1, $x1 - $x0); $spanY = max(1, $y1 - $y0);
        for ($y = $y0; $y <= $y1; $y++) {
            $lc = imagecolorat($img, $lx, $y); $rc = imagecolorat($img, $rx, $y);
            if ($dark($lc) && ! $dark($rc)) { $lc = $rc; }
            elseif ($dark($rc) && ! $dark($lc)) { $rc = $lc; }
            $lr = ($lc >> 16) & 0xFF; $lg = ($lc >> 8) & 0xFF; $lb = $lc & 0xFF;
            $rr = ($rc >> 16) & 0xFF; $rg = ($rc >> 8) & 0xFF; $rb = $rc & 0xFF;
            for ($x = $x0; $x <= $x1; $x++) {
                $tc = imagecolorat($img, $x, $ty); $bc = imagecolorat($img, $x, $by);
                if ($dark($tc) && ! $dark($bc)) { $tc = $bc; }
                elseif ($dark($bc) && ! $dark($tc)) { $bc = $tc; }
                $fy = ($y - $y0) / $spanY;
                $tr = ($tc >> 16) & 0xFF; $tg = ($tc >> 8) & 0xFF; $tb = $tc & 0xFF;
                $br = ($bc >> 16) & 0xFF; $bg = ($bc >> 8) & 0xFF; $bb = $bc & 0xFF;
                $fx = ($x - $x0) / $spanX;
                // nền ngang tại x
                $hr = $lr + ($rr - $lr) * $fx; $hg = $lg + ($rg - $lg) * $fx; $hb = $lb + ($rb - $lb) * $fx;
                // nền dọc tại y
                $vr = $tr + ($br - $tr) * $fy; $vg = $tg + ($bg - $tg) * $fy; $vb = $tb + ($bb - $tb) * $fy;
                imagesetpixel($img, $x, $y, imagecolorallocate($img,
                    (int) round(($hr + $vr) / 2),
                    (int) round(($hg + $vg) / 2),
                    (int) round(($hb + $vb) / 2)));
            }
        }

        // 3) Làm mờ patch (có lề feather) rồi dán lại cho hòa biên.
        if (function_exists('imagefilter') && $w * $h <= 8000000) {
            $feather = (int) max(3, min(24, round(min($pw, $ph) * 0.1)));
            $pw2 = $pw + $feather * 2; $ph2 = $ph + $feather * 2;
            $patch = imagecreatetruecolor($pw2, $ph2);
            imagecopy($patch, $img, $feather, $feather, $px, $py, $pw, $ph);
            @imagefilter($patch, IMG_FILTER_GAUSSIAN_BLUR);
            @imagefilter($patch, IMG_FILTER_GAUSSIAN_BLUR);
            imagecopy($img, $patch, max(0, $px - $feather), max(0, $py - $feather), 0, 0, $pw2, $ph2);
            imagedestroy($patch);
        }
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
            'meta' => ($type === 'video' && ! empty($data['camera'])) ? ['camera' => $data['camera']] : null,
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

        $creativeLevel = (int) ($data['creative_level'] ?? studio_config('creative_level', 6));
        $result = app(StyleSuggestService::class)->suggest($imagePath, $creativeLevel);

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
            'skin_detail' => ['nullable', 'integer', 'min:0', 'max:10'],
            'light_shadow' => ['nullable', 'integer', 'min:0', 'max:10'],
        ]);
        $scale = max(1, min(4, (int) ($data['scale'] ?? 2)));
        $refine = max(0, min(10, (int) ($data['refine'] ?? 0)));
        $photoreal = max(0, min(10, (int) ($data['photoreal'] ?? 0)));
        $skinDetail = max(0, min(10, (int) ($data['skin_detail'] ?? 0)));
        $lightShadow = max(0, min(10, (int) ($data['light_shadow'] ?? 0)));
        $srcUrl = (string) $data['image'];

        // Optional AI-edit refine for photoreal human detail. The prompt never asks for fabric
        // weave (it bleeds onto faces/dark skin) and must keep the frame unchanged.
        if ($refine > 0) {
            try {
                $keep = 'Keep the exact aspect ratio and framing of the input image — do NOT crop or change the frame. Keep the exact garment, model, pose, composition unchanged. Ultra-detailed, 4K.';
                $guard = 'IMPORTANT: Do NOT add fabric weave, texture, grain or any pattern to the skin, face, hair or jewellery — keep them smooth, clean and natural. Keep all detail edges (face, hair, garment seams, outlines) crisp, sharp and completely free of aliasing, halos, moiré or blur.';
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
        if ($skinDetail > 0) { $this->skinTexturePass($dst, $skinDetail); }
        if ($lightShadow > 0) { $this->lightShadowPass($dst, $lightShadow); }
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
        // 1) SOFT LIGHT BLEND — blur + lift a copy and blend it in gently for soft, natural ambient light.
        if (function_exists('imagefilter')) {
            $soft = imagecreatetruecolor($w, $h);
            imagecopy($soft, $img, 0, 0, 0, 0, $w, $h);
            @imagefilter($soft, IMG_FILTER_GAUSSIAN_BLUR);
            @imagefilter($soft, IMG_FILTER_GAUSSIAN_BLUR);
            @imagefilter($soft, IMG_FILTER_BRIGHTNESS, (int) round(9 * $k));
            $alpha = 0.04 + 0.08 * $k;
            for ($y = 0; $y < $h; $y++) {
                for ($x = 0; $x < $w; $x++) {
                    $c = imagecolorat($img, $x, $y); $s = imagecolorat($soft, $x, $y);
                    $cr = ($c >> 16) & 0xFF; $cg = ($c >> 8) & 0xFF; $cb = $c & 0xFF;
                    $sr = ($s >> 16) & 0xFF; $sg = ($s >> 8) & 0xFF; $sb = $s & 0xFF;
                    $nr = (int) round($cr * (1 - $alpha) + $sr * $alpha);
                    $ng = (int) round($cg * (1 - $alpha) + $sg * $alpha);
                    $nb = (int) round($cb * (1 - $alpha) + $sb * $alpha);
                    imagesetpixel($img, $x, $y, imagecolorallocate($img, $nr, $ng, $nb));
                }
            }
            imagedestroy($soft);
            // smart tone: soft contrast + subtle warm/cool grade + gentle denoise
            @imagefilter($img, IMG_FILTER_CONTRAST, (int) round(7 * $k));
            @imagefilter($img, IMG_FILTER_COLORIZE, (int) round(-3 * $k), (int) round(-1 * $k), (int) round(3 * $k));
        }
        // 2) SUBTLE FILM GRAIN — on shadows/midtones, but SKIPS the dilated skin band (the face
        //    plus a small rim around it) so the face and its boundary never get a grainy edge.
        for ($y = 0; $y < $h; $y += 3) {
            for ($x = 0; $x < $w; $x += 3) {
                if ($this->maskNearSkin($skinMask, $cols, $x >> 1, $y >> 1, 1)) { continue; }
                $c = imagecolorat($img, $x, $y);
                $r3 = ($c >> 16) & 0xFF; $g3 = ($c >> 8) & 0xFF; $b3 = $c & 0xFF;
                $lum = $r3 * 0.3 + $g3 * 0.6 + $b3 * 0.1;
                if ($lum < 215) {
                    $n = (int) ((mt_rand(-450, 450) / 1000.0) * 6 * $k);
                    $r = max(0, min(255, (($c >> 16) & 0xFF) + $n));
                    $g = max(0, min(255, (($c >> 8) & 0xFF) + $n));
                    $b = max(0, min(255, ($c & 0xFF) + $n));
                    imagesetpixel($img, $x, $y, imagecolorallocate($img, $r, $g, $b));
                }
            }
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
            $amount = 0.30 + 0.35 * $k;
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
     * Skin (face/body) detail: natural pores + subtle freckles/nam, ONLY on skin mask pixels.
     */
    protected function skinTexturePass(\GdImage $img, int $level): void
    {
        if ($level <= 0) { return; }
        $k = $level / 10.0; $w = imagesx($img); $h = imagesy($img);
        // Random soft pores that are DENSER but blended: fewer on bright skin (so highlights stay clean),
        // each pore is a soft 2x2 radial dot (center stronger, edge fades) so it melts into the skin.
        for ($y = 0; $y < $h; $y += 2) {
            for ($x = 0; $x < $w; $x += 2) {
                $c = imagecolorat($img, $x, $y);
                $r = ($c >> 16) & 0xFF; $g = ($c >> 8) & 0xFF; $b = $c & 0xFF;
                if ($this->isSkinPixel($r, $g, $b)) {
                    $bright = $r > 185 ? 0.09 : ($r > 165 ? 0.19 : ($r > 148 ? 0.33 : 1.0));   // face (bright) 50% fewer again
                    if (mt_rand(0, 1000) < (14 + 70 * $k) * $bright) {
                        $amp = (int) ((mt_rand(-120, 120) / 1000.0) * 2.2 * $k * (0.4 + 0.6 * $bright));
                        for ($dy = 0; $dy < 2; $dy++) {
                            for ($dx = 0; $dx < 2; $dx++) {
                                $px = $x + $dx; $py = $y + $dy;
                                if ($px >= $w || $py >= $h) { continue; }
                                $cc = imagecolorat($img, $px, $py);
                                $rr = ($cc >> 16) & 0xFF; $gg = ($cc >> 8) & 0xFF; $bb = $cc & 0xFF;
                                $fade = ($dx + $dy === 0) ? 1.0 : 0.65;
                                imagesetpixel($img, $px, $py, imagecolorallocate($img,
                                    max(0, min(255, $rr + (int) ($amp * $fade))),
                                    max(0, min(255, $gg + (int) ($amp * $fade))),
                                    max(0, min(255, $bb + (int) ($amp * $fade * 0.8)))));
                            }
                        }
                    }
                }
            }
        }
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
            @imagefilter($img, IMG_FILTER_CONTRAST, (int) round(6 * $k));
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
        return response()->json(['prompt_en' => $promptEn, 'prompt_vi' => $promptVi]);
    }

    /**
     * "Chân dung & Chiều sâu" (Portrait & Depth) post-process. Deterministic, preserves the subject:
     *  1) frame is extended (canvas *~1.22) so the person occupies ~82% of the height (smaller,
     *     background visible around them);
     *  2) a blurred, stretched copy of the scene fills the extended frame as a soft backdrop;
     *  3) the original image is composited centered with a depth-of-field mask — the subject stays
     *     sharp and pixel-identical, the surrounding background is softly blurred (bokeh / depth).
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

        $scale = 0.82;          // person target = ~82% of frame height
        $w = imagesx($img); $h = imagesy($img);
        $cw = (int) round($w / $scale); $ch = (int) round($h / $scale);

        // 1) blurred extended backdrop (cover-fit the scene, then soften).
        $back = imagecreatetruecolor($cw, $ch);
        imagecopyresampled($back, $img, 0, 0, 0, 0, $cw, $ch, $w, $h);
        for ($i = 0; $i < 3; $i++) { @imagefilter($back, IMG_FILTER_GAUSSIAN_BLUR); }

        // 2) depth-of-field person layer: original with the BACKGROUND blurred, subject sharp.
        // ELLIPTICAL sharp mask centered on the subject — guarantees the person is never blurred
        // (the subject fits inside a generous central ellipse); only the outer background/corners
        // fall off into the bokeh blur, so the effect lands on the background, not the model.
        $blur2 = imagecreatetruecolor($w, $h);
        imagecopy($blur2, $img, 0, 0, 0, 0, $w, $h);
        for ($i = 0; $i < 3; $i++) { @imagefilter($blur2, IMG_FILTER_GAUSSIAN_BLUR); }
        $cx = $w / 2; $cy = $h / 2;
        $rx = $w * 0.46; $ry = $h * 0.50;   // sharp ellipse (generous: subject + margin stays sharp)
        for ($y = 0; $y < $h; $y += 2) {
            for ($x = 0; $x < $w; $x += 2) {
                $dx = ($x - $cx) / $rx; $dy = ($y - $cy) / $ry;
                $d = sqrt($dx * $dx + $dy * $dy);
                if ($d <= 0.72) { continue; }            // fully sharp inside the ellipse
                $wgt = (1 - $d) / 0.28;                  // 1 at d=0.72 -> 0 at d=1.0
                $wgt = max(0.0, min(1.0, $wgt));
                $wg = $wgt * $wgt * (3 - 2 * $wgt);      // smoothstep -> soft bokeh transition
                $c = imagecolorat($img, $x, $y);
                $b = imagecolorat($blur2, $x, $y);
                $r = (int) round((($c >> 16) & 255) * $wg + (($b >> 16) & 255) * (1 - $wg));
                $g = (int) round((($c >> 8) & 255) * $wg + (($b >> 8) & 255) * (1 - $wg));
                $bb = (int) round(($c & 255) * $wg + ($b & 255) * (1 - $wg));
                imagesetpixel($blur2, $x, $y, imagecolorallocate($blur2, $r, $g, $bb));
            }
        }

        // 3) composite the (depth-of-field) original centered on the extended blurred backdrop.
        $ox = (int) (($cw - $w) / 2); $oy = (int) (($ch - $h) / 2);
        imagecopy($back, $blur2, $ox, $oy, 0, 0, $w, $h);

        $name = 'studio/portrait-'.Str::uuid().'.png';
        \Illuminate\Support\Facades\Storage::disk('public')->put($name, $this->pngBytes($back));
        imagedestroy($back); imagedestroy($blur2); imagedestroy($img);
        return '/storage/'.$name;
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
        $x0 = (int) ($w * 0.30); $x1 = (int) ($w * 0.70);
        $y0 = (int) ($h * 0.05); $y1 = (int) ($h * 0.95);
        $sum = 0; $n = 0;
        for ($y = $y0; $y < $y1; $y += 4) for ($x = $x0; $x < $x1; $x += 4) {
            $c = imagecolorat($img, $x, $y);
            $sum += 0.299 * (($c >> 16) & 255) + 0.587 * (($c >> 8) & 255) + 0.114 * ($c & 255);
            $n++;
        }
        $avg = $n ? $sum / $n : 0;
        if ($avg >= 45) { imagedestroy($img); return null; }   // subject already lit -> keep as-is

        $boost = min(2.2, 1.15 * (55 / max(22, $avg)));
        $cx = ($x0 + $x1) / 2;
        for ($y = 0; $y < $h; $y += 2) {
            for ($x = 0; $x < $w; $x += 2) {
                $wx = max(0.0, 1 - abs($x - $cx) / ($w * 0.22));
                $wy = ($y >= $y0 && $y <= $y1) ? 1.0 : max(0.0, 1 - min(abs($y - $y0), abs($y - $y1)) / ($h * 0.06));
                $wgt = $wx * $wy;
                if ($wgt <= 0.03) { continue; }
                $c = imagecolorat($img, $x, $y);
                $r = ($c >> 16) & 255; $g = ($c >> 8) & 255; $b = $c & 255;
                $f = 1 + ($boost - 1) * $wgt;
                imagesetpixel($img, $x, $y, imagecolorallocate($img,
                    max(0, min(255, (int) round($r * $f))),
                    max(0, min(255, (int) round($g * $f))),
                    max(0, min(255, (int) round($b * $f)))));
            }
        }
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
        $data = $request->validate([
            'image' => ['required', 'string', 'max:2048'],   // design image URL (generation media_url or /storage)
            'model_id' => ['required', 'string', 'max:80'],
            'pose_id' => ['required', 'string', 'max:80'],
            'background' => ['nullable', 'string', 'max:400'],
            'tone' => ['nullable', 'string', 'max:20'],     // Hiệu ứng tông màu (auto/warm/cool/film/cinematic/dramatic/mono/none)
            'pose_ref' => ['nullable', 'string', 'max:2048'], // pose reference image URL (picker thumbnail; not sent to the model)
        ]);

        $svc = app(\App\Services\VirtualTryOnService::class);
        $model = $svc->pickModel($data['model_id']);
        $pose = $svc->pickPose($data['pose_id']);
        if (! $model) {
            return response()->json(['message' => 'Không tìm thấy người mẫu.'], 422);
        }
        if (! $pose) {
            return response()->json(['message' => 'Không tìm thấy dáng.'], 422);
        }

        // Pose reference image: prefer the client-sent one, fall back to the pose catalog image
        // (custom asset / DB preset / built-in sample) so the model can actually replicate the pose.
        $poseRefUrl = (string) ($data['pose_ref'] ?? '') ?: (string) ($pose['image'] ?? '');
        $swapModel = (string) studio_config('swap_model', 'qwen-image-edit-plus-2025-12-15');

        // The long AI pipeline (try-on + optional face-swap, ~1-3 min per pose) runs in the background
        // queue (SwapModelJob) so this request returns immediately — a synchronous 2-pass swap gets
        // cut by the hosting proxy timeout ("chạy lâu không thấy kết quả").
        $gen = auth()->user()->generations()->create([
            'type' => 'image', 'status' => 'pending',
            'prompt' => 'Thay đổi người mẫu · '.($model['name'] ?? 'model').' · '.($pose['name'] ?? 'pose'),
            'model' => $swapModel, 'provider' => 'qwen', 'credits_cost' => 1,
            'meta' => [
                'swap' => true,
                'image' => $data['image'],
                'model_id' => $data['model_id'],
                'pose_id' => $data['pose_id'],
                'model_name' => $model['name'] ?? null,
                'pose_name' => $pose['name'] ?? null,
                'face_ref' => (bool) ($model['image'] ?? null),
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
        $model = $svc->pickModel((string) ($meta['model_id'] ?? ''));
        $pose = $svc->pickPose((string) ($meta['pose_id'] ?? ''));
        if (! $model || ! $pose) {
            $gen->update(['status' => 'failed', 'error' => 'Không tìm thấy người mẫu hoặc dáng.']);
            return;
        }

        $fallback = $svc->fallbackEdit(
            (string) ($meta['image'] ?? ''),
            $model['desc'] ?? ($model['ethnicity'] ?? 'a model'),
            $pose['skeleton'] ?? ($pose['name'] ?? 'standing'),
            (string) ($meta['background'] ?? ''),
            $model['image'] ?? null, // face reference image (custom/catalog)
            (string) ($meta['tone'] ?? 'none'),
            (string) ($meta['pose_ref'] ?? ''),
        );
        if (! $fallback) {
            $gen->update(['status' => 'failed', 'error' => 'Không thể thay đổi người mẫu. Kiểm tra model “'.(string) studio_config('swap_model', 'qwen-image-edit-plus-2025-12-15').'” và key Qwen Edit (Pay-As-You-Go).']);
            return;
        }

        // Safety net: never let the subject become a black silhouette against a dark background.
        // The edit model is non-deterministic — a "keep bright" prompt usually works but not always,
        // so this lifts a genuinely-dark subject band (an already-lit result is left untouched).
        $bright = $this->brightenDarkSubject($fallback);
        if ($bright) { $fallback = $bright; }

        // "Chân dung & Chiều sâu": scale the subject to ~82% of the frame and soften the background
        // (depth of field) so the scene looks natural and dimensional. Deterministic, keeps the
        // subject pixels identical. Opt-out via studio.swap_portrait_depth = false.
        if ((bool) studio_config('swap_portrait_depth', true)) {
            $portrait = $this->applyPortraitDepth($fallback);
            if ($portrait) { $fallback = $portrait; }
        }

        $swapModel = (string) studio_config('swap_model', 'qwen-image-edit-plus-2025-12-15');
        $actualModel = $svc->lastModel() ?: $swapModel;
        $credits = max(1, $svc->calls()); // 2 for the try-on + face-swap passes, 1 otherwise

        $gen->update([
            'status' => 'completed', 'media_url' => $fallback,
            'model' => $actualModel, 'credits_cost' => $credits,
            'meta' => array_merge($meta, [
                'type' => 'image', 'provider' => 'qwen', 'model' => $actualModel, 'config_model' => $swapModel,
                'steps' => $credits,
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
        $qwenModel = (string) studio_config('prompt_model', 'qwen3.8-flash'); // Qwen chat (fallback)
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
            $k->value = IlluminateSupportFacadesCrypt::encryptString((string) $d['key_value']);
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
            'translate_model' => setting('studio_translate_model', config('studio.translate_model')),
            'swap_model' => setting('studio_swap_model', config('studio.swap_model')),
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

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'image_credits' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'video_credits' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'max_generations' => ['nullable', 'integer', 'min:1', 'max:500'],
            'image_provider' => ['required', 'string', 'in:flux,wan,qwen,gemini'],
            'prompt_provider' => ['required', 'string', 'in:gemini,qwen'],
            'vision_provider' => ['required', 'string', 'in:gemini,qwen'],
            'prompt_model' => ['required', 'string', 'max:255'],
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
        ]);

        if (isset($data['image_credits'])) set_setting('studio_image_credits', (string) $data['image_credits']);
        if (isset($data['video_credits'])) set_setting('studio_video_credits', (string) $data['video_credits']);
        if (isset($data['max_generations'])) set_setting('studio_max_generations', (string) ($data['max_generations'] ?? 50));
        set_setting('studio_image_provider', $data['image_provider']);
        set_setting('studio_prompt_provider', $data['prompt_provider']);
        set_setting('studio_vision_provider', $data['vision_provider']);
        set_setting('studio_prompt_model', $data['prompt_model']);
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
}
