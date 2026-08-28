<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Video catwalk render (Wan AI / QwenCloud via DashScope async video-synthesis).
 *
 * When a Wan / DashScope key is configured it submits a task, polls until SUCSEEDED
 * and downloads the video. Otherwise it returns the bundled demo MP4 (stub).
 */
class VideoAIService
{
    public function render(string $prompt, string $imageUrl, string $cameraPreset, ?string $resolution = null, ?string $duration = null): string
    {
        $key = studio_api_key('wan') ?: studio_api_key('dashscope');

        if ($key) {
            return $this->callDashscopeVideo($prompt, $imageUrl, $cameraPreset, $resolution, $duration, $key);
        }

        // No key configured -> stub (bundled demo video).
        return '/samples/studio-catwalk.mp4';
    }

    protected function callDashscopeVideo(string $prompt, string $imageUrl, string $cameraPreset, ?string $resolution, ?string $duration, string $key): string
    {
        $base = rtrim((string) studio_config('dashscope_base', 'https://dashscope-intl.aliyuncs.com'), '/').'/api/v1';
        $model = (string) studio_config('video_model', 'wan2.5-t2v');
        $size = $this->videoSize($resolution);

        $input = ['prompt' => trim(trim($prompt).' '.$cameraPreset)];
        if ($imageUrl && str_starts_with($imageUrl, '/')) {
            $input['img_url'] = url($imageUrl);
        }

        $submit = Http::withToken($key)->withHeaders(['X-DashScope-Async' => 'enable'])->timeout(60)
            ->post($base.'/services/aigc/video-generation/video-synthesis', [
                'model' => $model,
                'input' => $input,
                'parameters' => ['size' => $size, 'duration' => (int) ($duration ?: 10)],
            ]);

        if (! $submit->successful()) {
            throw new \RuntimeException('DashScope video ('.$submit->status().'): '.Str::limit((string) $submit->body(), 240));
        }

        $taskId = data_get($submit->json(), 'output.task_id');
        if (! $taskId) {
            throw new \RuntimeException('DashScope không trả về task_id video.');
        }

        $deadline = microtime(true) + 300;

        while (microtime(true) < $deadline) {
            sleep(5);

            $q = Http::withToken($key)->timeout(30)->get($base.'/tasks/'.$taskId);

            if (! $q->successful()) {
                throw new \RuntimeException('DashScope ('.$q->status().'): '.Str::limit((string) $q->body(), 240));
            }

            $status = data_get($q->json(), 'output.task_status');

            if ($status === 'SUCCEEDED') {
                $url = data_get($q->json(), 'output.video_url') ?: data_get($q->json(), 'output.results.0.url');
                if (! $url) {
                    throw new \RuntimeException('DashScope hoàn tất nhưng không trả video.');
                }

                $contents = @file_get_contents($url);
                if (! $contents) {
                    throw new \RuntimeException('Không tải được video.');
                }

                $name = Str::uuid().'.mp4';
                Storage::disk('public')->put('studio/'.$name, $contents);

                return '/storage/studio/'.$name;
            }

            if ($status === 'FAILED') {
                throw new \RuntimeException('DashScope: '.(string) data_get($q->json(), 'output.message', 'Tạo video thất bại.'));
            }
        }

        throw new \RuntimeException('Hết thời gian chờ tạo video (task '.$taskId.').');
    }

    protected function videoSize(?string $resolution): string
    {
        return match ($resolution) {
            '480' => '960*480',
            '1080' => '1920*1080',
            default => '1280*720',
        };
    }
}
