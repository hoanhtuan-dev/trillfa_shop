@extends('layouts.studio')

@section('title', 'Trillfa Studio')

@php
    $presetJs = $presets->map(fn($group, $cat) => ['category' => $cat, 'items' => $group->map(fn($p) => ['id' => $p->id, 'key' => $p->ui_label, 'label' => $p->ui_label, 'value' => $p->prompt_injection])->values()])->values();
    $gensJs = $latest->map(fn($g) => [
        'id' => $g->id, 'type' => $g->type, 'status' => $g->status,
        'model' => $g->model, 'provider' => $g->provider,
        'media_url' => $g->media_url, 'error' => $g->error, 'credits_cost' => $g->credits_cost,
        'project_id' => $g->project_id, 'created_at' => $g->created_at?->format('d/m H:i'),
    ])->values();
    $projectsJs = $projects->map(fn($p) => ['id' => $p->id, 'name' => $p->name])->values();

    $aiStub = ! studio_api_key('gemini');
    $imgProvider = studio_config('image_provider', 'flux');
    $imgKeySet = match ($imgProvider) {
        'wan' => (bool) (studio_api_key('wan') ?: studio_api_key('dashscope')),
        'qwen' => (bool) (studio_api_key('qwen') ?: studio_api_key('dashscope')),
        'gemini' => (bool) (studio_api_key('gemini')),
        default => (bool) (studio_api_key('fal') ?: studio_api_key('replicate')),
    };
    $catLabels = ['fabric'=>'Chất liệu','silhouette'=>'Phom dáng','style'=>'Phong cách','background'=>'Bối cảnh','pose'=>'Dáng đứng','camera'=>'Góc máy'];
    $imageResolution = studio_config('image_resolution', '2K');
    $videoResolution = studio_config('video_resolution', '720');
    $imageRatio = studio_config('image_ratio', '1:1');
    $videoDuration = studio_config('video_duration', '10');
@endphp

@section('content')
<div x-data="studioApp(
    {{ Js::from($presetJs) }},
    {{ Js::from($gensJs) }},
    {{ Js::from($projectsJs) }},
    {{ auth()->user()->credits_balance }},
    {{ Js::from($projects->isEmpty() ? null : $projects->first()->id) }},
    {{ Js::from($catLabels) }},
    {{ Js::from($imageResolution) }},
    {{ Js::from($videoResolution) }},
  {{ Js::from($imageRatio) }},
  {{ Js::from($videoDuration) }}
)">
    <!-- Toolbar -->
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <label class="label mb-0">Dự án:</label>
            <div class="w-56"><select x-model="currentProjectId" class="input !py-2">
                <option value="">— Dự án mới (không lưu) —</option>
                <template x-for="p in projects" :key="p.id"><option :value="p.id" x-text="p.name"></option></template>
            </select></div>
            <template x-if="showNewProject"><span class="flex items-center gap-2">
                <div class="w-48"><input type="text" x-model="newProjectName" @keydown.enter.prevent="createProject()" placeholder="Tên dự án…" class="input !py-2"></div>
                <button @click="createProject()" class="btn-outline btn-sm">Tạo</button>
            </span></template>
            <button @click="newProjectName='', showNewProject=!showNewProject" class="btn-outline btn-sm">+ Dự án</button>
        </div>
        <div class="flex flex-wrap items-center gap-2 text-xs">
            <span class="badge {{ $aiStub ? 'bg-amber-100 text-amber-700' : 'bg-brand-600 text-white' }}">Prompt: {{ $aiStub ? 'Mô phỏng' : 'Gemini' }}</span>
            <span class="badge bg-cream-200 text-ink-700">Ảnh: {{ $imgProvider }}{{ $imgKeySet ? '' : ' (stub)' }}</span>
            <span class="text-ink-500">Tín dụng: <b class="font-semibold text-ink-900" x-text="creditsLeft"></b></span>
            <span class="text-ink-500">Đã dùng: <b class="font-semibold text-ink-900">{{ $creditsUsed }}</b></span>
            <button @click="processNow()" class="btn-outline btn-sm whitespace-nowrap" title="Xử lý các công việc đang chờ trong hàng đợi">Xử lý ngay</button>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-[300px_minmax(0,1fr)_280px]">
        <!-- =============================================================== -->
        <!-- ===== LEFT: AI Design Inputs ===== -->
        <!-- =============================================================== -->
        <div class="space-y-4 lg:max-h-[calc(100vh-13rem)] lg:overflow-y-auto lg:pr-1">
            <!-- Idea -->
            <div class="card p-5">
                <h2 class="mb-3 font-display text-base font-semibold text-ink-900">Text-to-Image · Ý tưởng</h2>
                <textarea x-model="idea" rows="3" class="input" placeholder="VD: A flowing silk evening gown with sequin…" @keydown.enter.prevent="ideate()"></textarea>
                <button @click="ideate()" :disabled="loading || !idea" class="btn-brand mt-3 w-full whitespace-nowrap"><span x-show="!loading">✨ Tạo Prompt</span><span x-show="loading">Đang tạo…</span></button>

                <!-- Reference source -->
                <div class="mt-4 rounded-xl border border-cream-200 bg-cream-50 p-3">
                    <p class="mb-2 text-xs font-semibold text-ink-700">Gợi ý từ ảnh tham khảo</p>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" @click="$refs.refInput.click()" class="btn-outline btn-sm whitespace-nowrap">Tải ảnh</button>
                        <button type="button" @click="openRefPicker()" class="btn-outline btn-sm whitespace-nowrap">Từ sản phẩm</button>
                        <button type="button" @click="suggestStyle()" :disabled="suggesting || (!refFile && !refUrl)" class="btn-brand btn-sm col-span-2 whitespace-nowrap"><span x-show="!suggesting">Gợi ý phong cách & prompt</span><span x-show="suggesting">Đang gợi ý…</span></button>
                    </div>
                    <input x-ref="refInput" type="file" accept="image/*" @change="onRefChange" class="hidden">
                    <template x-if="refImage"><div class="relative mt-3 overflow-hidden rounded-xl"><img :src="refImage" class="h-36 w-full bg-white object-cover" alt="Ảnh tham khảo"><button type="button" @click="clearRef()" class="absolute right-2 top-2 grid h-7 w-7 place-items-center rounded-full bg-ink-900/70 text-white">×</button></div></template>
                    <template x-if="suggestResult.styles.length"><div class="mt-3 rounded-xl bg-brand-50 p-3 text-xs text-brand-800"><strong>Gợi ý:</strong> <span x-text="suggestResult.styles.join(', ')"></span><span x-show="suggestResult.background"> · <span x-text="suggestResult.background"></span></span></div></template>
                </div>
            </div>

            <!-- Presets -->
            <div class="card p-5">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="font-display text-base font-semibold text-ink-900">Presets <span class="text-xs font-normal text-ink-500">(key: value)</span></h2>
                    <button @click="clearPresets()" class="btn-outline btn-sm" x-show="presetIds.length">Đặt lại</button>
                </div>
                <div class="grid gap-3">
                    <template x-for="group in presets" :key="group.category">
                        <div x-data="{ open: false }" class="relative">
                            <label class="label" x-text="catLabels[group.category] || group.category"></label>
                            <button type="button" @click="open = !open" @click.outside="open = false" class="input !py-2 flex w-full items-center justify-between gap-2 text-left">
                                <span class="truncate" x-text="selectedPresetText(group.category) || 'Chọn ' + (catLabels[group.category] || group.category)"></span>
                                <svg class="h-4 w-4 shrink-0 text-ink-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                            </button>
                            <div x-show="open" x-transition.opacity.duration.150ms class="absolute z-20 mt-1 w-full max-h-48 overflow-auto rounded-xl border border-cream-200 bg-white p-1 shadow-xl">
                                <template x-for="item in group.items" :key="item.id">
                                    <label class="flex cursor-pointer items-center justify-between gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-cream-100">
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate font-medium" x-text="item.key || item.label"></span>
                                            <span class="block truncate text-[10px] text-ink-500" x-text="item.value"></span>
                                        </span>
                                        <input type="checkbox" :checked="presetIds.includes(item.id)" @change="togglePreset(item.id)" class="h-4 w-4 accent-brand-600">
                                    </label>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Prompt + generate -->
            <div class="card p-5" x-show="output.image_prompt_en || output.video_prompt_en" x-transition.opacity>
                <h2 class="mb-3 font-display text-base font-semibold text-ink-900">Prompt tiếng Anh <span class="text-xs font-normal text-ink-500">(sửa tay)</span></h2>
                <textarea x-model="output.image_prompt_en" rows="4" class="input !text-xs" placeholder="Image prompt…"></textarea>
                <div class="mt-2"><label class="label">Video prompt</label><textarea x-model="output.video_prompt_en" rows="3" class="input !text-xs"></textarea></div>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <select x-model="imageRatio" class="input !py-2"><option value="1:1">1:1</option><option value="4:3">4:3</option><option value="3:4">3:4</option><option value="9:16">9:16</option><option value="16:9">16:9</option><option value="4:5">4:5</option><option value="21:9">21:9</option><option value="19:6">19:6</option></select>
                    <select x-model="imageRes" class="input !py-2"><option value="1K">1K</option><option value="2K">2K</option></select>
                    <button @click="generateImage()" :disabled="generating || !output.image_prompt_en" class="btn-brand col-span-2 whitespace-nowrap"><span x-show="!generating">Tạo Ảnh 2D</span><span x-show="generating">Đang gửi…</span></button>
                </div>
            </div>

            <!-- Refine -->
            <div class="card p-5" x-show="selectedImageId" x-transition.opacity>
                <h2 class="mb-3 font-display text-base font-semibold text-ink-900">Chỉnh sửa ảnh (Inpaint)</h2>
                <textarea x-model="refinePrompt" rows="2" class="input" placeholder="VD: add puffy sleeve"></textarea>
                <button @click="refine()" :disabled="refining || !refinePrompt" class="btn-outline mt-3 w-full"><span x-show="!refining">Cập nhật Ảnh</span><span x-show="refining">Đang gửi…</span></button>
            </div>
        </div>

        <!-- =============================================================== -->
        <!-- ===== CENTER: Canvas preview ===== -->
        <!-- =============================================================== -->
        <div class="min-w-0">
            <div class="card overflow-hidden p-0 lg:sticky lg:top-20">
                <!-- Canvas toolbar -->
                <div class="flex items-center justify-between gap-2 border-b border-cream-200 px-3 py-2 text-xs text-ink-500">
                    <span class="flex items-center gap-2">
                        <span class="font-display font-semibold text-ink-900">Canvas</span>
                        <span>zoom <b class="text-ink-900" x-text="zoom.toFixed(2)"></b></span>
                    </span>
                    <span class="flex items-center gap-1">
                        <button @click="zoomOut()" class="grid h-7 w-7 place-items-center rounded-lg border border-cream-200 hover:bg-cream-100">−</button>
                        <button @click="resetZoom()" class="rounded-lg border border-cream-200 px-2 hover:bg-cream-100" title="Vừa khung">Vừa</button>
                        <button @click="zoomIn()" class="grid h-7 w-7 place-items-center rounded-lg border border-cream-200 hover:bg-cream-100">+</button>
                        <span class="mx-1 h-4 w-px bg-cream-200"></span>
                        <span class="hidden text-[10px] text-ink-500 sm:inline">Kéo để di chuyển</span>
                    </span>
                </div>

                <!-- Media area -->
                <div class="relative h-[58vh] cursor-grab overflow-hidden bg-cream-100 active:cursor-grabbing" @pointerdown="startPan($event)" @pointermove="movePan($event)" @pointerup="endPan" @pointerleave="endPan" @wheel.prevent="onWheel($event)">
                    <div class="absolute inset-0 grid place-items-center p-4 transition-transform duration-150"
                         :style="{ transform: 'translate(' + pan.x + 'px, ' + pan.y + 'px) scale(' + zoom + ')', transformOrigin: 'center' }">
                        <template x-if="preview && preview.status === 'completed' && preview.type === 'image' && preview.media_url">
                            <img :src="preview.media_url" class="max-h-full max-w-full object-contain" onerror="this.src='/images/placeholder.svg'">
                        </template>
                        <template x-if="preview && preview.status === 'completed' && preview.type === 'video' && preview.media_url">
                            <video :src="preview.media_url" class="max-h-full max-w-full object-contain" controls loop muted playsinline></video>
                        </template>
                        <template x-if="preview && preview.status !== 'completed'">
                            <div class="text-center">
                                <span x-show="isActive(preview.status)" class="inline-block h-9 w-9 animate-spin rounded-full border-2 border-brand-600 border-t-transparent"></span>
                                <p class="mt-3 text-sm text-ink-500" x-text="statusText(preview)"></p>
                                <p class="mt-1 text-xs text-ink-500" x-show="preview.model" x-text="preview.provider + ' · ' + preview.model"></p>
                            </div>
                        </template>
                        <template x-if="!preview">
                            <div class="text-center">
                                <div class="mx-auto mb-3 grid h-16 w-16 place-items-center rounded-2xl bg-brand-50 text-brand-700">
                                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.25-5.25 4.5 4.5L15.75 9.75l3.75 3.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <p class="text-sm text-ink-500">Chọn một mục ở bảng Outputs để xem chi tiết.</p>
                            </div>
                        </template>
                    </div>
                    <span class="absolute left-3 top-3 badge" :class="badgeClass(preview && preview.status)" x-show="preview" x-text="preview ? statusLabel(preview.status) : ''"></span>
                    <span class="absolute right-3 top-3 badge bg-ink-900/70 text-white" x-show="preview && preview.type === 'video'">Video</span>
                </div>

                <!-- Action bar -->
                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-cream-200 px-4 py-3 text-xs" x-show="preview">
                    <span class="truncate text-ink-500" x-text="preview ? (preview.created_at || '') + ' · ' + preview.credits_cost + ' token' : ''"></span>
                    <span class="flex flex-wrap items-center gap-2">
                        <button @click="selectImage(preview)" :disabled="!preview || preview.type !== 'image' || preview.status !== 'completed'" class="btn-brand btn-sm whitespace-nowrap" x-show="preview && preview.type === 'image'">Sửa · Video</button>
                        <a :href="'/studio/generations/' + preview.id + '/download'" class="btn-outline btn-sm" x-show="preview && preview.media_url">Tải xuống</a>
                        <button @click="cancelGeneration(preview)" :disabled="!preview || !isActive(preview.status)" class="btn-outline btn-sm text-red-600" x-show="preview && isActive(preview.status)">Dừng</button>
                        <button @click="removeGeneration(preview)" class="btn-outline btn-sm text-red-600">Xóa</button>
                    </span>
                </div>
                <div class="px-4 py-2 text-[10px] text-brand-700" x-show="preview && selectedImageId === preview.id">✓ Đã chọn làm nguồn cho Chỉnh sửa / Video</div>

                <!-- ===== Video Rendering Timeline ===== -->
                <div class="border-t border-cream-200 px-4 py-3" x-show="output.video_prompt_en || selectedImageId">
                    <div class="mb-2 flex items-center justify-between">
                        <h3 class="font-display text-sm font-semibold text-ink-900">Video Rendering Timeline</h3>
                        <span class="text-[10px] text-ink-500">Camera keyframes</span>
                    </div>
                    <!-- time ruler -->
                    <div class="relative mb-1 h-4 rounded bg-cream-100 px-1 text-[9px] leading-4 text-ink-500">
                        <span class="absolute left-0">0s</span><span class="absolute left-1/4">15s</span><span class="absolute left-1/2">30s</span><span class="absolute left-3/4">45s</span><span class="absolute right-0">60s</span>
                    </div>
                    <!-- camera dropdown -->
                    <div>
                        <label class="label">Camera (video)</label>
                        <select x-model="videoCamera" class="input !py-2">
                            <option value="">— Chọn góc máy —</option>
                            <template x-for="cam in cameraOptions" :key="cam"><option :value="cam" x-text="cam"></option></template>
                        </select>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <select x-model="videoDuration" class="input !py-2"><option value="5">5s</option><option value="8">8s</option><option value="10">10s</option><option value="15">15s</option><option value="20">20s</option></select>
                        <select x-model="videoRes" class="input !py-2"><option value="480">480p</option><option value="720">720p</option><option value="1080">1080p</option></select>
                        <button @click="renderVideo()" :disabled="videoBusy || !selectedImageId" class="btn-brand col-span-2 whitespace-nowrap"><span x-show="!videoBusy">Render Video</span><span x-show="videoBusy">Đang gửi…</span></button>
                    </div>
                    <p class="mt-2 text-[10px] text-ink-500" x-text="selectedImageId ? 'Nguồn ảnh #' + selectedImageId : 'Chọn ảnh nguồn trước khi Render.'"></p>
                </div>
            </div>
        </div>

        <!-- =============================================================== -->
        <!-- ===== RIGHT: Generation Parameters ===== -->
        <!-- =============================================================== -->
        <div class="space-y-4">
            <!-- Outputs grid -->
            <div class="card p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="font-display text-sm font-semibold text-ink-900">Outputs</h2>
                    <a href="{{ route('studio.library') }}" class="link text-xs">Thư viện</a>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <template x-for="g in generations.slice(0, 6)" :key="g.id">
                        <button type="button" @click="setPreview(g)" class="overflow-hidden rounded-xl border text-left" :class="previewId === g.id ? 'border-brand-500 ring-2 ring-brand-500/30' : 'border-cream-200'">
                            <div class="relative">
                                <template x-if="g.status === 'completed' && g.media_url"><img :src="g.media_url" class="aspect-[3/4] w-full object-cover" onerror="this.src='/images/placeholder.svg'"></template>
                                <template x-if="g.status !== 'completed'"><div class="grid aspect-[3/4] w-full place-items-center bg-cream-100"><span x-show="isActive(g.status)" class="inline-block h-5 w-5 animate-spin rounded-full border-2 border-brand-600 border-t-transparent"></span></div></template>
                                <span class="absolute left-1 top-1 badge text-[9px]" :class="badgeClass(g.status)" x-text="statusLabel(g.status)"></span>
                            </div>
                        </button>
                    </template>
                </div>
                <div class="mt-3 text-center text-xs text-ink-500" x-show="!generations.length">Chưa có kết quả.</div>
            </div>

            <!-- Texture -->
            <div class="card p-4" x-data="{ texture: 5 }">
                <h2 class="mb-2 font-display text-sm font-semibold text-ink-900">Texture</h2>
                <input type="range" min="0" max="10" x-model="texture" class="w-full accent-brand-600">
                <div class="mt-1 flex justify-between text-[10px] text-ink-500"><span>0 · Mịn</span><span class="font-semibold text-ink-900" x-text="texture"></span><span>10 · Chi tiết bề mặt</span></div>
                <p class="mt-2 text-[10px] text-ink-500">Áp dụng khi render với model AI thật; ở chế độ mô phỏng (stub) chỉ là giao diện.</p>
            </div>

            <!-- Color palette -->
            <div class="card p-4">
                <h2 class="mb-2 font-display text-sm font-semibold text-ink-900">Color Palette</h2>
                <div class="flex items-center gap-1.5" x-show="palette.length">
                    <template x-for="c in palette" :key="c"><button type="button" class="h-7 w-7 rounded-full border border-cream-200" :style="{ background: c }" :title="c" @click="Alpine.store('toast').show('Màu ' + c, 'info')"></button></template>
                </div>
                <p class="text-[10px] text-ink-500" x-show="!palette.length">Chọn một ảnh ở Outputs để trích màu chủ đạo.</p>
            </div>

                    </div>
    </div>

    <!-- Reference product picker modal -->
    <template x-if="refOpen">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-ink-900/60" @click="closeRefPicker()"></div>
            <div class="relative z-10 w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-cream-200 px-4 py-3">
                    <h3 class="font-display text-sm font-semibold text-ink-900">Chọn ảnh sản phẩm làm nguồn</h3>
                    <button @click="closeRefPicker()" class="grid h-8 w-8 place-items-center rounded-full bg-cream-100 text-ink-500 hover:text-ink-900">×</button>
                </div>
                <div class="max-h-[70vh] overflow-auto p-4">
                    <p class="mb-3 text-xs text-ink-500" x-show="refLoading">Đang tải…</p>
                    <div class="grid grid-cols-3 gap-3 sm:grid-cols-4" x-show="!refLoading">
                        <template x-for="item in refProducts" :key="item.id">
                            <button type="button" @click="chooseProduct(item)" class="overflow-hidden rounded-xl border border-cream-200 text-left hover:border-brand-500">
                                <img :src="item.url" class="aspect-[3/4] w-full object-cover" onerror="this.src='/images/placeholder.svg'">
                                <span class="block truncate px-2 py-1 text-[10px] text-ink-500" x-text="item.name"></span>
                            </button>
                        </template>
                    </div>
                    <div class="text-center text-xs text-ink-500" x-show="!refLoading && !refProducts.length">Không có sản phẩm nào có ảnh.</div>
                </div>
            </div>
        </div>
    </template>
</div>
@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('studioApp', (presets, gens, projects, credits, currentProject, catLabels, imageRes, videoRes, imageRatio, videoDuration) => ({
        presets, projects, catLabels,
        imageRes: imageRes || '2K', videoRes: videoRes || '720', imageRatio: imageRatio || '1:1', videoDuration: videoDuration || '10',
        idea: '', presetIds: [],
        loading: false, generating: false, videoBusy: false, refining: false,
        output: { image_prompt_en: '', video_prompt_en: '', history_id: null },
        generations: gens, creditsLeft: Number(credits),
        currentProjectId: currentProject, selectedImageId: null, videoCamera: '',
        newProjectName: '', showNewProject: false, refinePrompt: '',
        refFile: null, refImage: null, refUrl: null, suggesting: false, refOpen: false, refProducts: [], refLoading: false,
        suggestResult: { styles: [], background: '', image_prompt_en: '' },
        previewId: null,
        zoom: 1, pan: { x: 0, y: 0 }, palette: [], _drag: null,
        _timers: {},

        init() { const f = this.generations.find(g => g.status === 'completed'); if (f) { this.previewId = f.id; this.loadPalette(f.id); } },
        get preview() { return this.generations.find(g => g.id === this.previewId) || null; },
        setPreview(g) { if (g) { this.previewId = g.id; this.loadPalette(g.id); } },
        zoomIn() { this.zoom = Math.min(4, +(this.zoom + 0.25).toFixed(2)); },
        zoomOut() { this.zoom = Math.max(0.6, +(this.zoom - 0.25).toFixed(2)); },
        resetZoom() { this.zoom = 1; this.pan = { x: 0, y: 0 }; },
        panBy(dx, dy) { this.pan.x += dx; this.pan.y += dy; },
        startPan(e) { this._drag = { x: e.clientX, y: e.clientY, px: this.pan.x, py: this.pan.y }; },
        movePan(e) { if (!this._drag) return; this.pan.x = this._drag.px + (e.clientX - this._drag.x); this.pan.y = this._drag.py + (e.clientY - this._drag.y); },
        endPan() { this._drag = null; },
        onWheel(e) { const delta = e.deltaY > 0 ? -0.25 : 0.25; this.zoom = Math.min(4, Math.max(0.6, +(this.zoom + delta).toFixed(2))); },
        get cameraOptions() { const g = this.presets.find(x => x.category === 'camera'); return g ? g.items.map(i => i.label) : []; },
        async loadPalette(id) {
            if (!id) { this.palette = []; return; }
            try { const res = await fetch('/studio/generations/' + id + '/palette', { headers: { Accept: 'application/json' } }); const d = await res.json(); this.palette = d.colors || []; }
            catch (e) { this.palette = []; }
        },
        get workflowSteps() {
            let cur = 1;
            if (this.output.image_prompt_en) cur = 2;
            if (this.preview) { if (this.preview.type === 'video') cur = 4; else if (this.preview.status === 'completed') cur = 3; }
            const labels = ['Ý tưởng', 'Tạo ảnh', 'Chỉnh sửa', 'Video'];
            return labels.map((label, i) => ({ id: i + 1, label, done: (i + 1) < cur, current: (i + 1) === cur, future: (i + 1) > cur }));
        },

        async api(url, body) {
            const res = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || '', 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(body) });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || 'Có lỗi xảy ra.');
            return data;
        },
        async upload(url, form) {
            const res = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || '', Accept: 'application/json' }, body: form });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || 'Có lỗi xảy ra.');
            return data;
        },
        async del(url) {
            const res = await fetch(url, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || '', Accept: 'application/json' } });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || 'Có lỗi xảy ra.');
            return data;
        },

        togglePreset(id) {
            id = Number(id);
            const i = this.presetIds.indexOf(id);
            if (i >= 0) this.presetIds.splice(i, 1);
            else this.presetIds.push(id);
        },
        selectedPresetText(cat) {
            const grp = this.presets.find((g) => g.category === cat);
            if (!grp) return '';
            return grp.items
                .filter((it) => this.presetIds.includes(it.id))
                .map((it) => it.key || it.label).join(', ');
        },

        onRefChange(e) {
            const f = e.target.files && e.target.files[0];
            if (!f) return;
            if (this.refImage && String(this.refImage).startsWith('blob:')) URL.revokeObjectURL(this.refImage);
            this.refFile = f; this.refImage = URL.createObjectURL(f); this.refUrl = null;
        },
        clearPresets() { this.presetIds = []; },
        async openRefPicker() {
            this.refOpen = true; this.refLoading = true;
            try { const res = await fetch('/studio/references', { headers: { Accept: 'application/json' } }); const d = await res.json(); this.refProducts = d.items || []; }
            catch (e) { this.refProducts = []; }
            finally { this.refLoading = false; }
        },
        closeRefPicker() { this.refOpen = false; },
        chooseProduct(item) {
            if (this.refImage && String(this.refImage).startsWith('blob:')) URL.revokeObjectURL(this.refImage);
            this.refUrl = item.url; this.refImage = item.url; this.refFile = null; this.refOpen = false;
        },
        clearRef() {
            if (this.refImage && String(this.refImage).startsWith('blob:')) URL.revokeObjectURL(this.refImage);
            this.refImage = null; this.refFile = null; this.refUrl = null;
        },

        async processNow() {
            try {
                const res = await fetch('/studio/process', { method: 'POST', headers: { 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || '', Accept: 'application/json' } });
                const d = await res.json();
                Alpine.store('toast').show(d.message || 'Đã xử lý.');
                if (d.processed && d.processed > 0) location.reload();
            } catch (e) { Alpine.store('toast').show('Lỗi: ' + e.message, 'error'); }
        },

        async createProject() {
            if (!this.newProjectName.trim() || this.creatingProject) return;
            this.creatingProject = true;
            try { const data = await this.api('/studio/projects', { name: this.newProjectName }); this.projects.unshift({ id: data.project_id, name: data.name }); this.currentProjectId = data.project_id; this.newProjectName = ''; this.showNewProject = false; }
            catch (e) { Alpine.store('toast').show(e.message, 'error'); }
            finally { this.creatingProject = false; }
        },

        async ideate() {
            if (!this.idea.trim() || this.loading) return;
            this.loading = true;
            try { const data = await this.api('/studio/ideate', { idea: this.idea, preset_ids: this.presetIds }); this.output.image_prompt_en = data.image_prompt_en; this.output.video_prompt_en = data.video_prompt_en; this.output.history_id = data.history_id; }
            catch (e) { Alpine.store('toast').show(e.message, 'error'); }
            finally { this.loading = false; }
        },

        async generateImage() {
            if (!this.output.image_prompt_en || this.generating) return;
            this.generating = true;
            try { const data = await this.api('/studio/generate', { prompt: this.output.image_prompt_en, resolution: this.imageRes, ratio: this.imageRatio, history_id: this.output.history_id, project_id: this.currentProjectId || null }); this.addGen({ id: data.generation_id, type: 'image', status: 'pending', model: data.model, provider: data.provider, media_url: null, error: null, credits_cost: 1, created_at: 'Vừa gửi' }); this.creditsLeft = data.credits_left; this.poll(data.generation_id); }
            catch (e) { Alpine.store('toast').show(e.message, 'error'); }
            finally { this.generating = false; }
        },

        async renderVideo() {
            if (!this.selectedImageId || this.videoBusy) return;
            this.videoBusy = true;
            try { const src = this.generations.find(g => g.id === this.selectedImageId); const camera = this.videoCamera || 'slow tracking shot'; const data = await this.api('/studio/video', { prompt: this.output.video_prompt_en || '', base_image: src ? src.media_url : '', camera, resolution: this.videoRes, duration: this.videoDuration, history_id: this.output.history_id, project_id: this.currentProjectId || null }); this.addGen({ id: data.generation_id, type: 'video', status: data.status, model: data.model, provider: data.provider, media_url: data.media_url, error: data.error, credits_cost: 10, created_at: 'Vừa gửi' }); this.creditsLeft = data.credits_left; this.maybePoll(data.generation_id, data.status); if (data.status === 'completed') Alpine.store('toast').show('Đã render xong video #' + data.generation_id); }
            catch (e) { Alpine.store('toast').show(e.message, 'error'); }
            finally { this.videoBusy = false; }
        },

        async refine() {
            if (!this.selectedImageId || !this.refinePrompt.trim() || this.refining) return;
            this.refining = true;
            try { const data = await this.api('/studio/generations/' + this.selectedImageId + '/inpaint', { prompt: this.refinePrompt }); this.addGen({ id: data.generation_id, type: 'image', status: data.status, model: data.model, provider: data.provider, media_url: data.media_url, error: data.error, credits_cost: 1, created_at: 'Vừa gửi' }); this.creditsLeft = data.credits_left; this.refinePrompt = ''; this.maybePoll(data.generation_id, data.status); }
            catch (e) { Alpine.store('toast').show(e.message, 'error'); }
            finally { this.refining = false; }
        },

        async cancelGeneration(g) {
            if (!this.isActive(g.status)) return;
            try { const data = await this.api('/studio/generations/' + g.id + '/cancel', {}); g.status = 'cancelled'; this.creditsLeft = Number(this.creditsLeft) + Number(g.credits_cost || 0); Alpine.store('toast').show('Đã dừng nhiệm vụ #' + g.id); }
            catch (e) { Alpine.store('toast').show(e.message, 'error'); }
        },

        async removeGeneration(g) {
            try { await this.del('/studio/generations/' + g.id); this.generations = this.generations.filter(x => x.id !== g.id); if (this.selectedImageId === g.id) this.selectedImageId = null; if (this.previewId === g.id) this.previewId = null; Alpine.store('toast').show('Đã xóa nhiệm vụ #' + g.id); }
            catch (e) { Alpine.store('toast').show(e.message, 'error'); }
        },

        async suggestStyle() {
            if ((!this.refFile && !this.refUrl) || this.suggesting) return;
            this.suggesting = true;
            try {
                let data;
                if (this.refFile) {
                    const form = new FormData(); form.append('image', this.refFile);
                    data = await this.upload('/studio/suggest', form);
                } else {
                    data = await this.api('/studio/suggest', { reference_url: this.refUrl });
                }
                this.suggestResult = data;
                this.presetIds = [];
                (data.preset_ids || []).forEach((id) => { if (!this.presetIds.includes(Number(id))) this.presetIds.push(Number(id)); });
                if (data.image_prompt_en) this.output.image_prompt_en = data.image_prompt_en;
                Alpine.store('toast').show('Đã gợi ý phong cách & prompt.');
            } catch (e) { Alpine.store('toast').show(e.message, 'error'); }
            finally { this.suggesting = false; }
        },

        addGen(gen) { const existing = this.generations.find(g => g.id === gen.id); if (existing) Object.assign(existing, gen); else this.generations.unshift(gen); this.previewId = gen.id; if (gen.status === 'completed') this.loadPalette(gen.id); },
        selectImage(g) { if (g.type !== 'image' || g.status !== 'completed') return; this.selectedImageId = g.id; this.previewId = g.id; Alpine.store('toast').show('Đã chọn ảnh #' + g.id + ' làm nguồn.'); },

        statusLabel(s) { return { pending:'Đang chờ', processing:'Đang tạo', completed:'Hoàn tất', failed:'Lỗi', cancelled:'Đã hủy' }[s] || s; },
        isActive(s) { return s === 'pending' || s === 'processing'; },
        badgeClass(s) { return { completed:'bg-brand-600 text-white', failed:'bg-red-100 text-red-600', cancelled:'bg-cream-200 text-ink-500' }[s] || 'bg-amber-100 text-amber-700'; },
        statusText(g) {
            if (g.status === 'failed') return 'Lỗi: ' + (g.error || 'không xác định');
            if (g.status === 'cancelled') return 'Đã hủy';
            return g.type === 'video' ? 'Đang render video…' : 'Đang tạo ảnh…';
        },

        maybePoll(id, status) { if (['completed','failed','cancelled'].includes(status)) return; this.poll(id); },

        poll(id) {
            if (this._timers[id]) return;
            this._timers[id] = setInterval(async () => {
                try {
                    const res = await fetch('/studio/generations/' + id, { headers: { Accept: 'application/json' } });
                    const g = await res.json();
                    const item = this.generations.find(x => x.id === Number(g.id));
                    if (item) { item.status = g.status; item.media_url = g.media_url; item.error = g.error; item.model = g.model; item.provider = g.provider; }
                    if (['completed','failed','cancelled'].includes(g.status)) { clearInterval(this._timers[id]); delete this._timers[id]; }
                } catch (e) {}
            }, 3000);
        },
    }));
});
</script>
@endpush
@endsection