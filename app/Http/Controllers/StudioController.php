<?php

namespace App\Http\Controllers;

use App\Jobs\RenderImageJob;
use App\Jobs\RenderVideoJob;
use App\Models\Generation;
use App\Models\Preset;
use App\Models\Project;
use App\Services\GeminiService;
use App\Services\ImageAIService;
use Illuminate\Http\Request;

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

        auth()->user()->projects()->create($data);

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

        $cost = (int) config('studio.image_credits', 1);

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

        $cost = (int) config('studio.video_credits', 10);

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

        $cost = (int) config('studio.image_credits', 1);

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
}
