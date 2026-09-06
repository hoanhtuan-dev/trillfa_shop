<?php

namespace App\Http\Controllers;

use App\Models\Generation;
use App\Models\Project;
use App\Services\ProjectWorkflowService;
use Illuminate\Http\Request;

/**
 * Trillfa Studio — Project Controller.
 *
 * Cung cấp CRUD + luồng công việc (workflow transitions) + gán generations/assets
 * cho bảng "Dự án thiết kế" của Designer. Mọi thao tác scoped theo user hiện tại
 * (Designer chỉ thấy dự án của mình; Super Admin duyệt qua endpoint transition).
 */
class ProjectController extends Controller
{
    public function __construct(protected ProjectWorkflowService $workflow) {}

    /**
     * GET /studio/projects — danh sách dự án của user (kèm metadata workflow).
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $archived = (bool) $request->input('archived', false);

        $projects = $user->projects()
            ->where('archived', $archived)
            ->withCount('generations')
            ->orderBy('sort')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Project $p) => $this->serialize($p, $user));

        return response()->json([
            'items' => $projects,
            'statuses' => $this->workflow->states(),
            'archived' => $archived,
        ]);
    }

    /**
     * GET /studio/projects/{project} — chi tiết 1 dự án + generations.
     */
    public function show(Request $request, Project $project)
    {
        abort_unless($project->user_id === $request->user()->id, 403);
        $project->load(['generations' => fn ($q) => $q->latest()->limit(60), 'assets']);

        return response()->json($this->serialize($project, $request->user(), true));
    }

    /**
     * POST /studio/projects — tạo dự án mới (mặc định status=draft).
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'base_concept' => ['nullable', 'string', 'max:1000'],
            'brief' => ['nullable', 'string', 'max:4000'],
            'deadline' => ['nullable', 'date'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:40'],
            'color' => ['nullable', 'string', 'max:20'],
            'thumbnail_url' => ['nullable', 'string', 'max:2048'],
        ]);

        $project = $request->user()->projects()->create(array_merge($data, [
            'status' => Project::STATUS_DRAFT,
            'tags' => $data['tags'] ?? [],
        ]));

        if ($request->wantsJson()) {
            return response()->json($this->serialize($project->fresh(), $request->user()), 201);
        }

        return redirect()->route('studio.index')->with('success', 'Đã tạo dự án.');
    }

    /**
     * PUT /studio/projects/{project} — cập nhật dự án (không đổi status qua đây).
     */
    public function update(Request $request, Project $project)
    {
        abort_unless($project->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'base_concept' => ['nullable', 'string', 'max:1000'],
            'brief' => ['nullable', 'string', 'max:4000'],
            'deadline' => ['nullable', 'date'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:40'],
            'color' => ['nullable', 'string', 'max:20'],
            'thumbnail_url' => ['nullable', 'string', 'max:2048'],
            'sort' => ['nullable', 'integer', 'min:0'],
        ]);

        if (isset($data['tags'])) {
            $data['tags'] = array_values(array_map('strval', $data['tags']));
        }
        $project->update($data);

        return response()->json($this->serialize($project->fresh(), $request->user()));
    }

    /**
     * DELETE /studio/projects/{project} — xóa dự án (generations detach, không cascade).
     */
    public function destroy(Request $request, Project $project)
    {
        abort_unless($project->user_id === $request->user()->id, 403);

        // Detach generations (set project_id null) để không mất output đã tạo.
        $project->generations()->update(['project_id' => null]);
        $project->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * POST /studio/projects/{project}/transition — chuyển trạng thái workflow.
     * Body: { to: 'review', note?: '...' }
     */
    public function transition(Request $request, Project $project)
    {
        abort_unless($project->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'to' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $project = $this->workflow->transition($project, $data['to'], $request->user(), $data['note'] ?? null);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->serialize($project->fresh(), $request->user()));
    }

    /**
     * POST /studio/projects/{project}/generations — gán một generation vào dự án.
     * Body: { generation_id: 123, action: 'attach'|'detach' }
     */
    public function attachGeneration(Request $request, Project $project)
    {
        abort_unless($project->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'generation_id' => ['required', 'integer', 'exists:generations,id'],
            'action' => ['nullable', 'string', 'in:attach,detach'],
        ]);

        $gen = Generation::where('id', $data['generation_id'])
            ->where('user_id', $request->user()->id)
            ->first();
        abort_unless($gen, 403);

        if (($data['action'] ?? 'attach') === 'detach') {
            $gen->update(['project_id' => null]);
        } else {
            $gen->update(['project_id' => $project->id]);
        }

        return response()->json(['ok' => true, 'generation' => [
            'id' => $gen->id, 'project_id' => $gen->project_id,
        ]]);
    }

    /**
     * Serialize Project + workflow metadata cho frontend.
     */
    protected function serialize(Project $project, $user, bool $withRelations = false): array
    {
        $desc = $this->workflow->describe($project, $user);
        $data = [
            'id' => $project->id,
            'name' => $project->name,
            'base_concept' => $project->base_concept,
            'brief' => $project->brief,
            'deadline' => $project->deadline?->toIso8601String(),
            'thumbnail_url' => $project->thumbnail_url,
            'tags' => $project->tags ?? [],
            'color' => $project->color,
            'sort' => (int) $project->sort,
            'archived' => (bool) $project->archived,
            'started_at' => $project->started_at?->toIso8601String(),
            'completed_at' => $project->completed_at?->toIso8601String(),
            'created_at' => $project->created_at?->toIso8601String(),
            'updated_at' => $project->updated_at?->toIso8601String(),
            'generations_count' => $project->generations_count ?? $project->generations()->count(),
            'thumbnail' => $project->thumbnail,
            // workflow
            ...$desc,
        ];
        if ($withRelations) {
            $data['generations'] = $project->generations()->latest()->limit(60)->get()->map(fn ($g) => [
                'id' => $g->id, 'type' => $g->type, 'status' => $g->status,
                'media_url' => $g->media_url, 'prompt' => $g->prompt,
                'model' => $g->model, 'provider' => $g->provider,
                'created_at' => $g->created_at?->format('d/m H:i'),
            ]);
            $data['assets'] = $project->assets()->orderByPivot('sort')->get(['studio_assets.id', 'type', 'name', 'path']);
        }

        return $data;
    }
}
