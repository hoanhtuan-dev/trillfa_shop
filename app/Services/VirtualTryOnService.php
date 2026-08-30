<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * "Thay Đổi Người Mẫu" (Click-to-Swap) — Qwen / DashScope Virtual Try-On.
 * Endpoint: POST {base}/api/v1/services/aigc/virtual-try-on/generation (async -> task_id)
 * Input: model_image_url + garment_image_url + parameters.category. Poll /api/v1/tasks/{taskId};
 * on SUCCEEDED the image is in output.image_url.
 */
class VirtualTryOnService
{
    protected string $taskBase = 'https://dashscope.aliyuncs.com/api/v1'; // overridden to dashscope_base_url($key) per key

    public function modelCatalog(): array
    {
        // 6 model faces (headshots) from the uploaded Face library. In VTON (Bước 2) the model_image_url
        // is the POSE image (full-body mannequin), while these faces drive the Step-1 text prompt
        // and identify the model to the user.
        return [
            ['id' => 'model01', 'name' => 'Mẫu 1', 'ethnicity' => 'East Asian female', 'image' => '/samples/model-01.png', 'desc' => 'East Asian female, shoulder-length reddish-brown hair, fair skin'],
            ['id' => 'model02', 'name' => 'Mẫu 2', 'ethnicity' => 'East Asian female', 'image' => '/samples/model-02.png', 'desc' => 'East Asian female, long black wavy hair, white shirt'],
            ['id' => 'model03', 'name' => 'Mẫu 3', 'ethnicity' => 'East Asian female', 'image' => '/samples/model-03.png', 'desc' => 'East Asian female, high bun updo, fair skin'],
            ['id' => 'model04', 'name' => 'Mẫu 4', 'ethnicity' => 'East Asian female', 'image' => '/samples/model-04.png', 'desc' => 'East Asian female, short bob black hair, smiling'],
            ['id' => 'model05', 'name' => 'Mẫu 5', 'ethnicity' => 'East Asian female', 'image' => '/samples/model-05.png', 'desc' => 'East Asian female, high ponytail, long black hair'],
            ['id' => 'model06', 'name' => 'Mẫu 6', 'ethnicity' => 'East Asian female', 'image' => '/samples/model-06.png', 'desc' => 'East Asian female, long wavy black hair, lace top'],
        ];
    }

    public function poseCatalog(): array
    {
        // 12 pose presets — each with its OWN image (a model in that pose) + a text skeleton for
        // Step-1 text-to-image. VTON uses the image as model_image_url.
        return [
            ['id' => 'pose01', 'name' => 'Đứng thẳng', 'skeleton' => 'standing straight, arms relaxed, full body', 'image' => '/samples/pose-01.png'],
            ['id' => 'pose02', 'name' => 'Tay chống hông', 'skeleton' => 'standing, one hand on hip, one leg crossed', 'image' => '/samples/pose-02.png'],
            ['id' => 'pose03', 'name' => 'Hai tay chống hông', 'skeleton' => 'standing, both hands on hips', 'image' => '/samples/pose-03.png'],
            ['id' => 'pose04', 'name' => 'Chống hông chéo chân', 'skeleton' => 'standing, hands on hips, legs crossed', 'image' => '/samples/pose-04.png'],
            ['id' => 'pose05', 'name' => 'Chống hông (trắng-đen)', 'skeleton' => 'standing, both hands on hips', 'image' => '/samples/pose-05.png'],
            ['id' => 'pose06', 'name' => 'Ngồi ghế', 'skeleton' => 'sitting on a high stool, one leg extended', 'image' => '/samples/pose-06.png'],
            ['id' => 'pose07', 'name' => 'Tay đút túi', 'skeleton' => 'side view, hand in pocket, relaxed', 'image' => '/samples/pose-07.png'],
            ['id' => 'pose08', 'name' => 'Ngồi xổm', 'skeleton' => 'stylish squat pose, knees apart', 'image' => '/samples/pose-08.png'],
            ['id' => 'pose09', 'name' => 'Sải bước', 'skeleton' => 'walking mid-stride catwalk, hand on hip', 'image' => '/samples/pose-09.png'],
            ['id' => 'pose10', 'name' => 'Xoay lưng', 'skeleton' => 'back view, turned away', 'image' => '/samples/pose-10.png'],
            ['id' => 'pose11', 'name' => 'Tựa ghế', 'skeleton' => 'leaning on a stool, hand to head', 'image' => '/samples/pose-11.png'],
            ['id' => 'pose12', 'name' => 'Bước ngang', 'skeleton' => 'walking, side profile, dynamic', 'image' => '/samples/pose-12.png'],
        ];
    }

    // Try-on needs a DashScope MODEL key (sk-.../sk-ws-...), NOT a QwenCloud Token-Plan (sk-sp-...).
    protected function key(): ?string
    {
        foreach (studio_qwen_credentials('image') as $k) {
            if ($k && ! str_starts_with($k, 'sk-sp-')) { return $k; }
        }
        return studio_api_key('dashscope');
    }

    public function submit(string $modelImageUrl, string $garmentImageUrl, string $category = 'dress'): array
    {
        $key = $this->key();
        if (! $key) { return ['error' => 'Chưa có key DashScope (Pay-As-You-Go) để gọi virtual try-on.']; }
        $models = array_values(array_unique(array_filter([
            (string) studio_config('tryon_model', 'wanx-virtualmodel'),
            'wanx-virtualmodel', 'virtualmodel-v2', 'wanx-virtual-try-on',
        ])));
        foreach ($models as $model) {
            $resp = Http::withToken($key)->withHeaders(['X-DashScope-Async' => 'enable'])->timeout(60)
                ->post(dashscope_base_url($key).'/api/v1/services/aigc/virtual-try-on/generation', [
                    'model' => $model,
                    'input' => [
                        'model_image_url' => $modelImageUrl,
                        'garment_image_url' => $garmentImageUrl,
                    ],
                    'parameters' => ['category' => $category],
                ]);
            if ($resp->successful()) {
                $taskId = data_get($resp->json(), 'output.task_id');
                if ($taskId) { return ['task_id' => $taskId]; }
            }
            $body = (string) $resp->body();
            logger()->warning('Try-on submit ('.$model.'): HTTP '.$resp->status().' '.substr($body, 0, 200));
            // Model not exist / not supported -> try the next try-on model; other errors -> give up.
            if (! (str_contains(strtolower($body), 'model not exist') || $resp->status() === 400 || $resp->status() === 404)) {
                break;
            }
        }
        return ['error' => 'Không model try-on nào khả dụng trên tài khoản ('.($models[0] ?? '?').'). '.substr($body ?? '', 0, 160)];
    }

    // Fallback khi try-on không khả dụng (region/intl hoặc free-trial hết): dùng qwen-image-edit để
    // đổi người mẫu/dáng trên ảnh thiết kế, GIỮ NGUYÊN 100% trang phục.
    public function fallbackEdit(string $designImage, string $modelDesc, string $pose, string $background = ''): ?string
    {
        // Dùng model được quản lý cho "Thay Đổi Người Mẫu" (studio.swap_model) qua swapEdit (có retry 429).
        $swapModel = (string) studio_config('swap_model', 'qwen-image-edit-plus-2025-12-15');
        $instr = 'Keep the exact garment, outfit and all its details 100% unchanged. Change the person to a '.$modelDesc.' and set the pose to '.$pose.'.';
        if ($background && strtolower($background) !== 'keep') {
            $instr .= ' Set the background to '.$background.'.';
        }
        $instr .= ' Photorealistic, full body, high fashion.';
        $svc = app(ImageAIService::class);
        return $svc->swapEdit($instr, $designImage, $swapModel);
    }

    public function status(string $taskId): array
    {
        $key = $this->key();
        if (! $key) { return ['status' => 'failed', 'error' => 'no key']; }
        $q = Http::withToken($key)->timeout(30)->get(dashscope_base_url($key).'/api/v1/tasks/'.$taskId);
        if (! $q->successful()) { return ['status' => 'failed', 'error' => 'HTTP '.$q->status()]; }
        $status = (string) data_get($q->json(), 'output.task_status');
        $url = data_get($q->json(), 'output.image_url') ?: data_get($q->json(), 'output.results.0.url');
        return ['status' => strtolower($status), 'url' => $url, 'error' => (string) data_get($q->json(), 'output.message', '')];
    }

    public function pickModel(string $id): ?array
    {
        foreach ($this->modelCatalog() as $m) { if (($m['id'] ?? '') === $id) { return $m; } }
        return $this->modelCatalog()[0] ?? null;
    }

    public function pickPose(string $id): ?array
    {
        foreach ($this->poseCatalog() as $p) { if (($p['id'] ?? '') === $id) { return $p; } }
        return $this->poseCatalog()[0] ?? null;
    }

    protected function storeRemoteImage(string $url): ?string
    {
        $contents = @file_get_contents($url);
        if (! $contents) { return null; }
        $name = 'studio/'.Str::uuid().'.jpg';
        \Illuminate\Support\Facades\Storage::disk('public')->put($name, $contents);
        return '/storage/'.$name;
    }
}
