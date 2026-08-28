@extends('layouts.studio')

@section('title', 'Trillfa Studio')

@php
    $presetJs = $presets->map(fn($group, $cat) => ['category' => $cat, 'items' => $group->map(fn($p) => ['id' => $p->id, 'label' => $p->ui_label])->values()])->values();
    $gensJs = $latest->map(fn($g) => [
        'id' => $g->id, 'type' => $g->type, 'status' => $g->status,
        'media_url' => $g->media_url, 'error' => $g->error, 'credits_cost' => $g->credits_cost,
        'project_id' => $g->project_id, 'created_at' => $g->created_at?->format('d/m H:i'),
    ])->values();
    $projectsJs = $projects->map(fn($p) => ['id' => $p->id, 'name' => $p->name])->values();
@endphp

@section('content')
<div x-data="studioApp(
    {{ Js::from($presetJs) }},
    {{ Js::from($gensJs) }},
    {{ Js::from($projectsJs) }},
    {{ auth()->user()->credits_balance }},
    {{ Js::from($projects->isEmpty() ? null : $projects->first()->id) }}
)">
    <!-- Toolbar -->
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <label class="label mb-0">Dự án:</label>
            <div class="w-56"><select x-model="currentProjectId" class="input !py-2">
                <option value="">— Dự án mới (không lưu) —</option>
                <template x-for="p in projects" :key="p.id">
                    <option :value="p.id" x-text="p.name"></option>
                </template>
            </select></div>
            <template x-if="showNewProject">
                <span class="flex items-center gap-2">
                    <div class="w-48"><input type="text" x-model="newProjectName" @keydown.enter.prevent="createProject()" placeholder="Tên dự án…" class="input !py-2"></div>
                    <button @click="createProject()" class="btn-outline btn-sm">Tạo</button>
                </span>
            </template>
            <button @click="newProjectName='', showNewProject=!showNewProject" class="btn-outline btn-sm">+ Dự án</button>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-sm text-ink-500">Tín dụng:</span>
            <span class="font-bold text-brand-700" x-text="creditsLeft"></span>
        </div>
    </div>

    <div class="grid gap-8 lg:grid-cols-[380px_1fr]">
        <!-- ===== Left: controls ===== -->
        <div class="space-y-6">
            <!-- Idea -->
            <div class="card p-6">
                <h2 class="mb-3 font-display text-lg font-semibold text-ink-900">1. Ý tưởng</h2>
                <textarea x-model="idea" rows="3" class="input" placeholder="VD: Váy dạ hội biển xanh" @keydown.enter.prevent="ideate()"></textarea>
            </div>

            <!-- Suggest from image -->
            <div class="card p-6">
                <h2 class="mb-1 font-display text-lg font-semibold text-ink-900">Gợi ý từ ảnh tham khảo</h2>
                <p class="mb-3 text-xs text-ink-500">Tải ảnh lên → hệ thống gợi ý phong cách + prompt phù hợp.</p>
                <div class="flex items-center gap-2">
                    <button type="button" @click="$refs.refInput.click()" class="btn-outline btn-sm flex-1">Chọn ảnh tham khảo</button>
                    <button type="button" @click="suggestStyle()" :disabled="suggesting || !refFile" class="btn-brand btn-sm">
                        <span x-show="!suggesting">Gợi ý</span><span x-show="suggesting">…</span>
                    </button>
                </div>
                <input x-ref="refInput" type="file" accept="image/*" @change="onRefChange" class="hidden">
                <template x-if="refImage">
                    <div class="relative mt-3 overflow-hidden rounded-xl">
                        <img :src="refImage" class="h-40 w-full bg-cream-100 object-cover" alt="Ảnh tham khảo">
                        <button type="button" @click="refFile=null, refImage=null" class="absolute right-2 top-2 grid h-7 w-7 place-items-center rounded-full bg-ink-900/70 text-white">×</button>
                    </div>
                </template>
                <template x-if="suggestResult.styles.length">
                    <div class="mt-3 rounded-xl bg-brand-50 p-3 text-xs text-brand-800">
                        <p><strong>Gợi ý:</strong> <span x-text="suggestResult.styles.join(', ')"></span><span x-show="suggestResult.background"> · <span x-text="suggestResult.background"></span></span></p>
                    </div>
                </template>
            </div>

            <!-- Presets -->
            <div class="card p-6">
                <h2 class="mb-3 font-display text-lg font-semibold text-ink-900">2. Phong cách (Preset)</h2>
                <div class="space-y-4">
                    <template x-for="group in presets" :key="group.category">
                        <div>
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-ink-500" x-text="group.category"></p>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="item in group.items" :key="item.id">
                                    <button type="button" @click="togglePreset(item.id)" class="chip" :class="presetIds.includes(item.id) ? '!border-brand-600 !bg-brand-50 !text-brand-800' : ''">
                                        <span x-text="item.label"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
                <button @click="ideate()" :disabled="loading || !idea" class="btn-brand mt-5 w-full">
                    <span x-show="!loading">Tạo Prompt Chuyên Nghiệp</span><span x-show="loading">Đang tạo…</span>
                </button>
            </div>

            <!-- Prompts -->
            <div class="card p-6" x-show="output.image_prompt_en || output.video_prompt_en" x-transition.opacity>
                <h2 class="mb-3 font-display text-lg font-semibold text-ink-900">3. Prompt tiếng Anh <span class="text-xs font-normal text-ink-500">(sửa tay được)</span></h2>
                <div class="space-y-3">
                    <div><label class="label">Image prompt</label><textarea x-model="output.image_prompt_en" rows="3" class="input !text-xs"></textarea></div>
                    <div><label class="label">Video prompt</label><textarea x-model="output.video_prompt_en" rows="3" class="input !text-xs"></textarea></div>
                </div>
                <button @click="generateImage()" :disabled="generating || !output.image_prompt_en" class="btn-brand mt-4 w-full">
                    <span x-show="!generating">Tạo Ảnh 2D</span><span x-show="generating">Đang gửi…</span>
                </button>
            </div>

            <!-- Video render -->
            <div class="card p-6" x-show="output.video_prompt_en || selectedImageId" x-transition.opacity>
                <h2 class="mb-3 font-display text-lg font-semibold text-ink-900">4. Render Video Catwalk</h2>
                <p class="mb-2 text-xs text-ink-500">Chọn 1 ảnh bên phải làm nguồn, chọn góc máy.</p>
                <select x-model="videoCamera" class="input">
                    <option value="">— Góc máy —</option>
                    <template x-for="g in presets" :key="g.category"><template x-if="g.category === 'camera'"><template x-for="item in g.items" :key="item.id"><option :value="item.label" x-text="item.label"></option></template></template></template>
                </select>
                <button @click="renderVideo()" :disabled="videoBusy || !selectedImageId || !videoCamera" class="btn-brand mt-4 w-full">
                    <span x-show="!videoBusy">Render Video</span><span x-show="videoBusy">Đang gửi…</span>
                </button>
                <p class="mt-2 text-xs text-ink-400" x-text="selectedImageId ? 'Nguồn: ảnh #' + selectedImageId : 'Chưa chọn ảnh nguồn.'"></p>
            </div>

            <!-- Refine (inpaint) -->
            <div class="card p-6" x-show="selectedImageId" x-transition.opacity>
                <h2 class="mb-3 font-display text-lg font-semibold text-ink-900">5. Tinh chỉnh ảnh (Inpaint)</h2>
                <textarea x-model="refinePrompt" rows="2" class="input" placeholder="VD: thêm tay phồng"></textarea>
                <button @click="refine()" :disabled="refining || !refinePrompt" class="btn-outline mt-3 w-full">
                    <span x-show="!refining">Cập nhật Ảnh</span><span x-show="refining">Đang gửi…</span>
                </button>
            </div>
        </div>

        <!-- ===== Right: results ===== -->
        <div>
            <div class="mb-4 flex items-center justify-between">
                <h2 class="font-display text-xl font-semibold text-ink-900">Kết quả</h2>
                <span class="text-xs text-ink-400" x-text="generations.length + ' mục'"></span>
            </div>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-4">
                <template x-for="g in generations" :key="g.id">
                    <div class="card group overflow-hidden" :class="selectedImageId === g.id ? 'ring-2 ring-brand-600' : ''">
                        <button type="button" @click="selectImage(g)" :disabled="g.type !== 'image' || g.status !== 'completed'" class="relative block w-full">
                            <template x-if="g.status === 'completed' && g.media_url">
                                <img :src="g.media_url" :alt="'Ảnh #'+g.id" class="aspect-[3/4] w-full object-cover" onerror="this.src='/images/placeholder.svg'">
                            </template>
                            <template x-if="g.type === 'video' && g.status === 'completed' && g.media_url">
                                <video :src="g.media_url" class="aspect-[3/4] w-full object-cover" controls loop muted playsinline></video>
                            </template>
                            <template x-if="g.status !== 'completed'">
                                <div class="grid aspect-[3/4] w-full place-items-center bg-cream-100">
                                    <div class="text-center">
                                        <span class="inline-block h-6 w-6 animate-spin rounded-full border-2 border-brand-600 border-t-transparent"></span>
                                        <p class="mt-2 text-xs text-ink-500" x-text="g.status === 'failed' ? 'Lỗi' : (g.type === 'video' ? 'Đang render video…' : 'Đang tạo ảnh…')"></p>
                                    </div>
                                </div>
                            </template>
                            <span class="absolute left-2 top-2 badge" :class="g.type === 'video' ? 'bg-ink-900/80 text-white' : 'bg-white/80 text-ink-700'" x-text="g.type === 'video' ? 'Video' : 'Ảnh'"></span>
                        </button>
                        <div class="flex items-center justify-between border-t border-cream-200 px-3 py-2 text-xs text-ink-500">
                            <span x-text="g.created_at + ' · ' + g.credits_cost + 'đ'"></span>
                            <button type="button" @click="selectImage(g)" :disabled="g.type !== 'image' || g.status !== 'completed'" class="font-semibold text-brand-700">Dùng</button>
                        </div>
                    </div>
                </template>
            </div>
            <div class="card flex min-h-[280px] flex-col items-center justify-center p-10 text-center text-ink-400" x-show="!generations.length">
                <p>Nhập ý tưởng → “Tạo Prompt” → “Tạo Ảnh 2D” để bắt đầu.</p>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('studioApp', (presets, gens, projects, credits, currentProject) => ({
        presets, projects,
        idea: '', presetIds: [],
        loading: false, generating: false, videoBusy: false, refining: false,
        output: { image_prompt_en: '', video_prompt_en: '', history_id: null },
        generations: gens, creditsLeft: Number(credits),
        currentProjectId: currentProject, selectedImageId: null, videoCamera: '',
        newProjectName: '', showNewProject: false, refinePrompt: '',
        refFile: null, refImage: null, suggesting: false,
        suggestResult: { styles: [], background: '', image_prompt_en: '' },
        _timers: {},

        async api(url, body) {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || '',
                    'Content-Type': 'application/json', Accept: 'application/json',
                },
                body: JSON.stringify(body),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || 'Có lỗi xảy ra.');
            return data;
        },

        onRefChange(e) {
            const f = e.target.files && e.target.files[0];
            if (!f) return;
            if (this.refImage) URL.revokeObjectURL(this.refImage);
            this.refFile = f;
            this.refImage = URL.createObjectURL(f);
        },

        async upload(url, form) {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || '',
                    Accept: 'application/json',
                },
                body: form,
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || 'Có lỗi xảy ra.');
            return data;
        },

        async suggestStyle() {
            if (!this.refFile || this.suggesting) return;
            this.suggesting = true;
            try {
                const form = new FormData();
                form.append('image', this.refFile);
                const data = await this.upload('/studio/suggest', form);
                this.suggestResult = data;
                (data.preset_ids || []).forEach((id) => this.togglePreset(id));
                if (data.image_prompt_en) this.output.image_prompt_en = data.image_prompt_en;
                Alpine.store('toast').show('Đã gợi ý phong cách & prompt.');
            } catch (e) {
                Alpine.store('toast').show(e.message, 'error');
            } finally {
                this.suggesting = false;
            }
        },

        togglePreset(id) {
            const i = this.presetIds.indexOf(Number(id));
            if (i >= 0) this.presetIds.splice(i, 1); else this.presetIds.push(Number(id));
        },

        async createProject() {
            if (!this.newProjectName.trim() || this.creatingProject) return;
            this.creatingProject = true;
            try {
                const data = await this.api('/studio/projects', { name: this.newProjectName });
                this.projects.unshift({ id: data.project_id, name: data.name });
                this.currentProjectId = data.project_id;
                this.newProjectName = ''; this.showNewProject = false;
            } catch (e) { Alpine.store('toast').show(e.message, 'error'); }
            finally { this.creatingProject = false; }
        },

        async ideate() {
            if (!this.idea.trim() || this.loading) return;
            this.loading = true;
            try {
                const data = await this.api('/studio/ideate', { idea: this.idea, preset_ids: this.presetIds });
                this.output.image_prompt_en = data.image_prompt_en;
                this.output.video_prompt_en = data.video_prompt_en;
                this.output.history_id = data.history_id;
            } catch (e) { Alpine.store('toast').show(e.message, 'error'); }
            finally { this.loading = false; }
        },

        async generateImage() {
            if (!this.output.image_prompt_en || this.generating) return;
            this.generating = true;
            try {
                const data = await this.api('/studio/generate', {
                    prompt: this.output.image_prompt_en,
                    history_id: this.output.history_id,
                    project_id: this.currentProjectId || null,
                });
                this.addGen({ id: data.generation_id, type: 'image', status: 'pending', media_url: null, error: null, credits_cost: 1, project_id: this.currentProjectId, created_at: 'Vừa xong' });
                this.creditsLeft = data.credits_left;
                this.poll(data.generation_id);
            } catch (e) { Alpine.store('toast').show(e.message, 'error'); }
            finally { this.generating = false; }
        },

        async renderVideo() {
            if (!this.selectedImageId || !this.videoCamera || this.videoBusy) return;
            this.videoBusy = true;
            try {
                const src = this.generations.find(g => g.id === this.selectedImageId);
                const data = await this.api('/studio/video', {
                    prompt: this.output.video_prompt_en || '',
                    base_image: src ? src.media_url : '',
                    camera: this.videoCamera,
                    history_id: this.output.history_id,
                    project_id: this.currentProjectId || null,
                });
                this.addGen({ id: data.generation_id, type: 'video', status: 'pending', media_url: null, error: null, credits_cost: 10, project_id: this.currentProjectId, created_at: 'Vừa xong' });
                this.creditsLeft = data.credits_left;
                this.poll(data.generation_id);
            } catch (e) { Alpine.store('toast').show(e.message, 'error'); }
            finally { this.videoBusy = false; }
        },

        async refine() {
            if (!this.selectedImageId || !this.refinePrompt.trim() || this.refining) return;
            this.refining = true;
            try {
                const data = await this.api('/studio/generations/' + this.selectedImageId + '/inpaint', { prompt: this.refinePrompt });
                this.addGen({ id: data.generation_id, type: 'image', status: 'pending', media_url: null, error: null, credits_cost: 1, created_at: 'Vừa xong' });
                this.creditsLeft = data.credits_left;
                this.refinePrompt = '';
                this.poll(data.generation_id);
            } catch (e) { Alpine.store('toast').show(e.message, 'error'); }
            finally { this.refining = false; }
        },

        addGen(gen) {
            const existing = this.generations.find(g => g.id === gen.id);
            if (existing) Object.assign(existing, gen); else this.generations.unshift(gen);
        },

        selectImage(g) {
            if (g.type !== 'image' || g.status !== 'completed') return;
            this.selectedImageId = g.id;
            Alpine.store('toast').show('Đã chọn ảnh #' + g.id + ' làm nguồn.');
        },

        poll(id) {
            if (this._timers[id]) return;
            this._timers[id] = setInterval(async () => {
                try {
                    const res = await fetch('/studio/generations/' + id, { headers: { Accept: 'application/json' } });
                    const g = await res.json();
                    const item = this.generations.find(x => x.id === Number(g.id));
                    if (item) { item.status = g.status; item.media_url = g.media_url; item.error = g.error; }
                    if (g.status === 'completed' || g.status === 'failed') { clearInterval(this._timers[id]); delete this._timers[id]; }
                } catch (e) {}
            }, 3000);
        },
    }));
});
</script>
@endpush
@endsection