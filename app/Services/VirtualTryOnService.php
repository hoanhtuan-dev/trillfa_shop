<?php

namespace AppServices;

use IlluminateSupportFacadesHttp;
use IlluminateSupportStr;

/**
 * "Thay Đổi Người Mẫu" (Click-to-Swap) — Virtual Try-On.
 *
 * TWO-STEP, fully automatic (no masking/prompting by the user):
 *  1) Auto-garment extraction: sends the design image (person wearing the outfit) so the
 *     try-on API isolates the garment.
 *  2) Virtual Try-On: sends garment_image + model_image (from the model library) to the
 *     DashScope virtual-try-on API. Falls back to a regular qwen-image-edit (pose/background
 *     change) if the try-on model is unavailable — so the feature always produces a result.
 */
class VirtualTryOnService
{
    public function modelCatalog(): array
    {
        // Model Library — faces / ethnicities the user picks. Optional image URL (bundle a real
        // photo or point at a stored asset); desc drives the prompt if there is no image.
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
        // Pose Presets — the pose the user picks.
        return [
            ['id' => 'stand', 'name' => 'Đứng thẳng', 'skeleton' => 'standing straight, arms relaxed, full body'],
            ['id' => 'hip', 'name' => 'Tay chống hông', 'skeleton' => 'standing, one hand on hip, confident contrapposto'],
            ['id' => 'walk', 'name' => 'Đang bước', 'skeleton' => 'walking mid-stride catwalk, dynamic'],
            ['id' => 'twist', 'name' => 'Xoay lưng', 'skeleton' => 'turned away, looking back over the shoulder'],
            ['id' => 'squat', 'name' => 'Ngồi xổm', 'skeleton' => 'stylish squat pose, knees apart'],
        ];
    }

    public function swap(string $designImage, string $modelId, string $poseId): array
    {
        $model = $this->pick($this->modelCatalog(), $modelId);
        $pose = $this->pick($this->poseCatalog(), $poseId);
        if (! $model) {
            return ['url' => null, 'error' => 'Không tìm thấy mẫu người mẫu.'];
        }
        $poseName = $pose['skeleton'] ?? ($pose['name'] ?? 'standing');

        $key = studio_api_key('qwen') ?: studio_api_key('dashscope');
        if ($key) {
            try {
                $url = $this->callTryOn($key, $designImage, $model, $poseName);
                if ($url) {
                    return ['url' => $url, 'provider' => 'tryon'];
                }
            } catch (Throwable $e) {
                logger()->warning('Virtual try-on failed, falling back to edit: '.$e->getMessage());
            }
        }

        // Fallback: qwen-image-edit (change the model/pose while keeping the exact garment).
        try {
            $url = $this->callEditFallback($designImage, $model, $poseName);
            if ($url) {
                return ['url' => $url, 'provider' => 'edit'];
            }
        } catch (Throwable $e) {
            logger()->error('Try-on fallback edit failed: '.$e->getMessage());
        }

        return ['url' => null, 'error' => 'Không thể thay đổi người mẫu (try-on & edit đều lỗi). Kiểm tra key/model.'];
    }

    protected function callTryOn(string $key, string $designImage, array $model, string $pose): ?string
    {
        $base = dashscope_base_url($key).'/api/v1';
        $tryonModel = (string) studio_config('tryon_model', 'wanx-virtual-try-on');
        $modelImage = $model['image'] ?? null;

        $input = [
            'garment_image' => url($designImage), // the design (person wearing the outfit) -> API extracts garment
            'user_prompts' => 'Wear the outfit naturally, '.$pose.', photorealistic fashion photo, full body.',
        ];
        if ($modelImage) {
            $input['model_image'] = url($modelImage);
        }
        $input = array_filter($input);

        $resp = Http::withToken($key)->withHeaders(['X-DashScope-Async' => 'enable'])->timeout(60)
            ->post($base.'/services/aigc/image-generation/generation', [
                'model' => $tryonModel,
                'input' => $input,
                'parameters' => ['n' => 1],
            ]);

        if (! $resp->successful()) {
            logger()->warning('Try-on submit: HTTP '.$resp->status().' '.substr((string) $resp->body(), 0, 200));
            return null;
        }

        $taskId = data_get($resp->json(), 'output.task_id');
        if (! $taskId) {
            return null;
        }

        $deadline = microtime(true) + 90;
        while (microtime(true) < $deadline) {
            sleep(3);
            $q = Http::withToken($key)->timeout(30)->get($base.'/tasks/'.$taskId);
            $status = (string) data_get($q->json(), 'output.task_status');
            if ($status === 'SUCCEEDED') {
                $rurl = data_get($q->json(), 'output.results.0.url');
                return $rurl ? $this->storeRemoteImage($rurl) : null;
            }
            if ($status === 'FAILED') {
                return null;
            }
        }

        return null;
    }

    protected function callEditFallback(string $designImage, array $model, string $pose): ?string
    {
        $service = app(ImageAIService::class);
        return $service->generate(
            'Keep the exact garment, outfit and all its details 100% unchanged. Change the person to a '.$model['ethnicity'].' and set the pose to '.$pose.'. Photorealistic, full body, high fashion.',
            $designImage
        );
    }

    protected function pick(array $items, string $id): ?array
    {
        foreach ($items as $it) {
            if (($it['id'] ?? '') === $id) {
                return $it;
            }
        }
        return $items[0] ?? null;
    }

    protected function storeRemoteImage(string $url): ?string
    {
        $contents = @file_get_contents($url);
        if (! $contents) {
            return null;
        }
        $name = 'studio/'.Str::uuid().'.jpg';
        IlluminateSupportFacadesStorage::disk('public')->put($name, $contents);
        return '/storage/'.$name;
    }
}
