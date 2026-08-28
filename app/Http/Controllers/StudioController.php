<?php

namespace App\Http\Controllers;

use App\Jobs\RenderImageJob;
use App\Jobs\RenderVideoJob;
use App\Models\Generation;
use App\Models\Preset;
use App\Models\Project;
use App\Services\GeminiService;
use App\Services\ImageAIService;
use App\Services\StyleSuggestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class StudioController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $projects = $user->projects()->withCount('generations')->latest()->get();
        $presets = Preset::orderBy('sort_order')->get()->groupBy('category');
        $latest = $user->generations()->with('project')->latest()->limit(12)->get();

        return view('studio.index', compact('projects', 'presets', 'latest'));
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
    public function ideate(Request $request)
    {
        $data = $request->validate([
            'idea' => ['required', 'string', 'max:1000'],
            'preset_ids' => ['nullable', 'array'],
            'preset_ids.*' => ['integer', 'exists:presets,id'],
        ]);

        $injections = $this->resolveInjectedPresets($data['preset_ids'] ?? []);
        $result = app(GeminiService::class)->generateCreativeDirector($data['idea'], $injections);

        $history = auth()->user()->prompts()->create([
            'idea' => $data['idea'],
            'image_prompt_en' => $result['image_prompt_en'],
            'video_prompt_en' => $result['video_prompt_en'],
            'json_response' => $result,
        ]);

        return response()->json([
            'history_id' => $history->id,
            'image_prompt_en' => $result['image_prompt_en'],
            'video_prompt_en' => $result['video_prompt_en'],
            'keywords' => $result['keywords'] ?? [],
        ]);
    }

    /**
     * 2D image generation — async via queue.
     */
    public function generate(Request $request)
    {
        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:4000'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'history_id' => ['nullable', 'integer', 'exists:prompts_history,id'],
        ]);

        $cost = (int) studio_config('image_credits', 1);

        return $this->queueGeneration('image', $data, $cost);
    }

    /**
     * Video catwalk render — async via queue.
     */
    public function renderVideo(Request $request)
    {
        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:4000'],
            'base_image' => ['required', 'string', 'max:2048'],
            'camera' => ['nullable', 'string', 'max:255'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'history_id' => ['nullable', 'integer', 'exists:prompts_history,id'],
        ]);

        $cost = (int) studio_config('video_credits', 10);

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
        ]);

        if ($generation->media_url) {
            $data['base_image'] = $generation->media_url;
        }

        $cost = (int) studio_config('image_credits', 1);

        return $this->queueGeneration('image', $data, $cost, $generation);
    }

    /**
     * Polling endpoint for a single generation.
     */
    public function show(Generation $generation)
    {
        abort_unless($generation->user_id === auth()->id(), 403);

        return response()->json([
            'id' => $generation->id,
            'type' => $generation->type,
            'status' => $generation->status,
            'media_url' => $generation->media_url,
            'error' => $generation->error,
            'credits_cost' => $generation->credits_cost,
        ]);
    }

    protected function queueGeneration(string $type, array $data, int $cost, ?Generation $source = null)
    {
        $user = auth()->user();

        if ($user->credits_balance < $cost) {
            return response()->json(['message' => 'Bạn không đủ tín dụng. Yêu cầu '.$cost.' tín dụng.'], 422);
        }

        $user->decrement('credits_balance', $cost);

        $generation = $user->generations()->create([
            'project_id' => $data['project_id'] ?? null,
            'prompts_history_id' => $data['history_id'] ?? null,
            'type' => $type,
            'status' => 'pending',
            'prompt' => $data['prompt'] ?? null,
            'base_image' => $data['base_image'] ?? $source?->media_url,
            'mask_image' => $data['mask_image'] ?? null,
            'credits_cost' => $cost,
        ]);

        if ($type === 'video') {
            RenderVideoJob::dispatch($generation->id);
        } else {
            RenderImageJob::dispatch($generation->id);
        }

        return response()->json([
            'generation_id' => $generation->id,
            'status' => 'processing',
            'credits_left' => $user->fresh()->credits_balance,
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
        $request->validate([
            'image' => ['required', 'image', 'max:8192'],
        ]);

        $path = $request->file('image')->store('studio/ref', 'public');

        $result = app(StyleSuggestService::class)->suggest(storage_path('app/public/'.$path));

        return response()->json($result);
    }

    public function settings()
    {
        return view('studio.settings', [
            'image_credits' => setting('studio_image_credits', config('studio.image_credits')),
            'video_credits' => setting('studio_video_credits', config('studio.video_credits')),
            'max_generations' => setting('studio_max_generations', 50),
            'image_provider' => setting('studio_image_provider', config('studio.image_provider')),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'image_credits' => ['required', 'integer', 'min:0', 'max:1000'],
            'video_credits' => ['required', 'integer', 'min:0', 'max:1000'],
            'max_generations' => ['nullable', 'integer', 'min:1', 'max:500'],
            'image_provider' => ['required', 'string', 'in:flux,wan,qwen'],
        ]);

        set_setting('studio_image_credits', (string) $data['image_credits']);
        set_setting('studio_video_credits', (string) $data['video_credits']);
        set_setting('studio_max_generations', (string) ($data['max_generations'] ?? 50));
        set_setting('studio_image_provider', $data['image_provider']);

        return back()->with('success', 'Đã lưu cài đặt Studio.');
    }

    public function api()
    {
        $providers = [
            'gemini' => ['label' => 'Gemini — Giám đốc sáng tạo', 'hint' => 'GEMINI_API_KEY', 'configured' => (bool) studio_api_key('gemini')],
            'fal' => ['label' => 'Fal.ai — Flux (ảnh)', 'hint' => 'FAL_KEY', 'configured' => (bool) studio_api_key('fal')],
            'replicate' => ['label' => 'Replicate — Flux (ảnh)', 'hint' => 'REPLICATE_API_TOKEN', 'configured' => (bool) studio_api_key('replicate')],
            'wan' => ['label' => 'Wan AI — video', 'hint' => 'WAN_API_KEY', 'configured' => (bool) studio_api_key('wan')],
            'veo' => ['label' => 'Google Veo — video', 'hint' => 'GOOGLE_VEO_KEY', 'configured' => (bool) studio_api_key('veo')],
            'qwen' => ['label' => 'Qwen AI — ảnh (Alibaba)', 'hint' => 'QWEN_API_KEY', 'configured' => (bool) studio_api_key('qwen')],
        ];

        return view('studio.api', compact('providers'));
    }

    public function updateApi(Request $request)
    {
        $services = ['gemini', 'fal', 'replicate', 'wan', 'veo', 'qwen'];

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

}