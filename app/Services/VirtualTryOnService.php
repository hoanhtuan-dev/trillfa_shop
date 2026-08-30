<?php

namespace AppServices;

use IlluminateSupportFacadesHttp;
use IlluminateSupportStr;

/**
 * "Thay Đổi Người Mẫu" (Click-to-Swap) — Qwen / DashScope Virtual Try-On.
 * Endpoint: POST {base}/api/v1/services/aigc/virtual-try-on/generation (async -> task_id)
 * Input: model_image_url + garment_image_url + parameters.category. Poll /api/v1/tasks/{taskId};
 * on SUCCEEDED the image is in output.image_url.
 */
class VirtualTryOnService
{
    protected string $taskBase = 'https://dashscope.aliyuncs.com/api/v1';

    public function modelCatalog(): array
    {
        return [
            ['id' => 'asian_f', 'name' => 'Nữ Á Đông', 'ethnicity' => 'East Asian female', 'image' => '/samples/model-asian-f.jpg', 'desc' => 'East Asian female, fair skin, long dark hair'],
            ['id' => 'euro_f', 'name' => 'Nữ Châu Âu', 'ethnicity' => 'European female', 'image' => '/samples/model-euro-f.jpg', 'desc' => 'European female, light skin, blonde hair'],
            ['id' => 'asian_m', 'name' => 'Nam Á Đông', 'ethnicity' => 'East Asian male', 'image' => '/samples/model-asian-m.jpg', 'desc' => 'East Asian male, tan skin, short black hair'],
            ['id' => 'euro_m', 'name' => 'Nam Châu Âu', 'ethnicity' => 'European male', 'image' => '/samples/model-euro-m.jpg', 'desc' => 'European male, light skin, brown hair'],
            ['id' => 'african_f', 'name' => 'Nữ Phi', 'ethnicity' => 'Black female', 'image' => '/samples/model-african-f.jpg', 'desc' => 'Black female, deep skin tone, curly hair'],
        ];
    }

    public function poseCatalog(): array
    {
        return [
            ['id' => 'stand', 'name' => 'Đứng thẳng', 'skeleton' => 'standing straight, arms relaxed, full body'],
            ['id' => 'hip', 'name' => 'Tay chống hông', 'skeleton' => 'standing, one hand on hip, confident contrapposto'],
            ['id' => 'walk', 'name' => 'Đang bước', 'skeleton' => 'walking mid-stride catwalk, dynamic'],
            ['id' => 'twist', 'name' => 'Xoay lưng', 'skeleton' => 'turned away, looking back over the shoulder'],
            ['id' => 'squat', 'name' => 'Ngồi xổm', 'skeleton' => 'stylish squat pose, knees apart'],
        ];
    }

    public function submit(string $modelImageUrl, string $garmentImageUrl, string $category = 'dress'): array
    {
        $key = studio_api_key('qwen') ?: studio_api_key('dashscope');
        if (! $key) { return ['error' => 'Chưa có key Qwen/DashScope để gọi virtual try-on.']; }
        $model = (string) studio_config('tryon_model', 'wanx-virtual-try-on');
        $resp = Http::withToken($key)->withHeaders(['X-DashScope-Async' => 'enable'])->timeout(60)
            ->post($this->taskBase.'/services/aigc/virtual-try-on/generation', [
                'model' => $model,
                'input' => [
                    'model_image_url' => $modelImageUrl,
                    'garment_image_url' => $garmentImageUrl,
                ],
                'parameters' => ['category' => $category],
            ]);
        if (! $resp->successful()) {
            logger()->warning('Try-on submit: HTTP '.$resp->status().' '.substr((string) $resp->body(), 0, 220));
            return ['error' => 'Gửi try-on lỗi (HTTP '.$resp->status().'). '.substr((string) $resp->body(), 0, 160)];
        }
        $taskId = data_get($resp->json(), 'output.task_id');
        return $taskId ? ['task_id' => $taskId] : ['error' => 'Không nhận được task_id try-on.'];
    }

    public function status(string $taskId): array
    {
        $key = studio_api_key('qwen') ?: studio_api_key('dashscope');
        if (! $key) { return ['status' => 'failed', 'error' => 'no key']; }
        $q = Http::withToken($key)->timeout(30)->get($this->taskBase.'/tasks/'.$taskId);
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
        IlluminateSupportFacadesStorage::disk('public')->put($name, $contents);
        return '/storage/'.$name;
    }
}
