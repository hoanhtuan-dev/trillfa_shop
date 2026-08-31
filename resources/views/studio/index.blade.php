@extends('layouts.studio')

@section('title', 'Trillfa Studio')

@php
    $presetJs = $presets->map(fn($group, $cat) => ['category' => $cat, 'items' => $group->map(fn($p) => ['id' => $p->id, 'key' => $p->ui_label, 'label' => $p->ui_label, 'value' => $p->prompt_injection, 'note' => $p->note])->values()])->values();
    $gensJs = $latest->map(fn($g) => [
        'id' => $g->id, 'type' => $g->type, 'status' => $g->status,
        'model' => $g->model, 'provider' => $g->provider,
        'media_url' => $g->media_url, 'error' => $g->error, 'credits_cost' => $g->credits_cost,
        'project_id' => $g->project_id, 'prompts_history_id' => $g->prompts_history_id, 'created_at' => $g->created_at?->format('d/m H:i'),
        'resolution' => $g->resolution, 'ratio' => $g->ratio, 'duration' => $g->duration,
        'elapsed_ms' => $g->elapsed_ms, 'meta' => $g->meta, 'prompt' => $g->prompt,
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
    $catLabels = ['fabric'=>'Chất liệu','color'=>'Màu sắc','silhouette'=>'Phom dáng','neckline'=>'Kiểu cổ','sleeve'=>'Dáng tay','fit'=>'Độ vừa','pattern'=>'Họa tiết','style'=>'Phong cách','detail'=>'Chi tiết','occasion'=>'Dịp sử dụng','season'=>'Mùa','background'=>'Bối cảnh','pose'=>'Dáng đứng','camera'=>'Góc máy','lens'=>'Ống kính','video_scene'=>'Kịch bản quay'];
    $imageResolution = studio_config('image_resolution', '2K');
    $videoResolution = studio_config('video_resolution', '720');
    $imageRatio = studio_config('image_ratio', '1:1');
    $videoDuration = studio_config('video_duration', '10');
    $creativeLevel = max(1, min(10, (int) studio_config('creative_level', 6)));
    $creditsUsedToday = (int) auth()->user()->generations()->where('status', 'completed')->whereDate('created_at', today())->sum('credits_cost');
    $quotaResetsAt = (string) setting('studio_provider_quota_resets_at', '');
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
  {{ Js::from($videoDuration) }},
  {{ Js::from($creativeLevel) }}
)">
    <!-- Step indicator (Progressive Disclosure) — always visible -->
    <div class="sticky top-0 z-30 mb-3 flex justify-center">
        <div class="flex items-center gap-1 overflow-x-auto rounded-2xl border border-ink-700 bg-ink-800/95 p-1 text-[11px] font-semibold shadow-lg backdrop-blur">
            <button @click="goStep(1)" class="flex min-w-fit items-center gap-1.5 rounded-xl px-3 py-1.5 transition-colors" :class="step===1?'bg-brand-600 text-white':'text-cream-200 hover:bg-ink-700'"><span class="grid h-5 w-5 place-items-center rounded-full" :class="step===1?'bg-white/25':'bg-white/10'">1</span> Concept</button>
            <span class="text-cream-300/60">›</span>
            <button @click="goStep(2)" class="flex min-w-fit items-center gap-1.5 rounded-xl px-3 py-1.5 transition-colors" :class="step===2?'bg-brand-600 text-white':'text-cream-200 hover:bg-ink-700'"><span class="grid h-5 w-5 place-items-center rounded-full" :class="step===2?'bg-white/25':'bg-white/10'">2</span> Fitting Room</button>
            <span class="text-cream-300/60">›</span>
            <button @click="goStep(3)" class="flex min-w-fit items-center gap-1.5 rounded-xl px-3 py-1.5 transition-colors" :class="step===3?'bg-brand-600 text-white':'text-cream-200 hover:bg-ink-700'"><span class="grid h-5 w-5 place-items-center rounded-full" :class="step===3?'bg-white/25':'bg-white/10'">3</span> Director</button>
        </div>
    </div>

    <div class="grid gap-4 lg:h-[calc(100dvh-6.5rem)] lg:grid-rows-1 lg:grid-cols-[minmax(0,1fr)_400px_150px]">
        <!-- =============================================================== -->
        <style>
            @keyframes stylistIn { from { opacity: 0; transform: translateY(10px) scale(.98); } to { opacity: 1; transform: none; } }
            .stylist-anim { animation: stylistIn .32s cubic-bezier(.22,1,.36,1); }
            @keyframes stylistPulse { 0% { box-shadow: 0 0 0 0 rgba(232,87,125,.45); } 70% { box-shadow: 0 0 0 12px rgba(232,87,125,0); } 100% { box-shadow: 0 0 0 0 rgba(232,87,125,0); } }
            .stylist-pick { animation: stylistPulse .5s ease-out; }
        </style>
        <!-- ===== LEFT: AI Design Inputs ===== -->
        <!-- =============================================================== -->
        <div x-ref="leftPanel" class="scrollbar-hide order-2 space-y-4 lg:h-full lg:min-h-0 lg:overflow-y-auto lg:pr-1" x-show="!isMobile || step===1 || step===2 || step===3">
            <!-- ✨ Thuật sỹ ảo (nổi bật, đầu Bước 1) -->
            <div class="card p-5" x-show="step===1" style="border: 1px solid var(--color-brand-500); background: linear-gradient(135deg, rgba(232,87,125,.16), rgba(74,122,144,.12));">
                <div class="flex items-center justify-between">
                    <h2 class="font-display text-base font-semibold text-brand-300">✨ Thuật sỹ ảo</h2>
                    <button @click="openStylist()" class="btn-brand btn-sm whitespace-nowrap">Bắt đầu</button>
                </div>
                <div class="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-6">
                    <template x-for="t in stylistTypes" :key="t.id">
                        <button @click="openStylist(); startStylist(t.id)" class="group relative aspect-[3/4] overflow-hidden rounded-xl border bg-ink-900 transition-all duration-150 hover:scale-[1.03] hover:border-brand-500 hover:shadow-lg hover:shadow-brand-500/20 active:scale-[0.97]" :class="stylistFlash === t.id ? 'stylist-pick border-brand-500 ring-2 ring-brand-500' : 'border-ink-700'">
                            <img x-show="t.img" :src="t.img" class="h-full w-full object-cover opacity-90 transition-transform group-hover:scale-105" onerror="this.style.display='none'" alt="Trang phục">
                            <span class="absolute inset-0 flex-col items-center justify-center gap-1 bg-ink-900/80 text-center" x-show="!t.img"><span class="text-2xl" x-text="t.emoji"></span><span class="px-1 text-[10px] text-cream-100" x-text="t.name"></span></span>
                            <span class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 to-transparent px-1 pb-1 pt-4 text-center text-[10px] font-medium text-white" x-text="t.name"></span>
                        </button>
                    </template>
                </div>
            </div>

            <!-- Idea -->
            <div class="card p-5" x-show="step===1">
                <div class="mb-2 flex items-center justify-between">
                    <h2 class="font-display text-base font-semibold text-ink-900">🎛 Prompt</h2>
                    <span class="text-[10px] text-cream-300/50">Tạo Prompt để AI viết (ghi đè) · nhập trực tiếp để giữ nguyên.</span>
                </div>
                <textarea x-model="output.image_prompt_en" rows="5" class="input !text-xs" placeholder="Nhập ý tưởng/prompt (tiếng Việt hoặc tiếng Anh) — bấm “Tạo Prompt” để AI viết prompt tiếng Anh, hoặc nhập trực tiếp." @keydown.enter.prevent="ideate()"></textarea>
                <div class="mt-2 flex gap-2">
                    <button @click="ideate()" :disabled="loading || !output.image_prompt_en" class="btn-brand btn-sm flex-1 whitespace-nowrap"><span x-show="!loading">✨ Tạo Prompt</span><span x-show="loading">Đang tạo…</span></button>
                    <button @click="openTranslateVi()" :disabled="translating || !output.image_prompt_en" class="btn-outline btn-sm flex-1 whitespace-nowrap" title="Hiển thị prompt bằng tiếng Việt để chỉnh sửa; bấm Xong sẽ dịch lại và ghi đè prompt tiếng Anh."><span x-show="!translating">🇻🇳 Sửa tiếng Việt</span><span x-show="translating">Đang dịch…</span></button>
                </div>
                <div class="mt-2 flex items-center gap-2 rounded-2xl border border-ink-700 bg-ink-800 px-3 py-2 text-xs" title="Mức độ sáng tạo khi AI tạo prompt. Thấp = bám sát ý tưởng; cao = tự do sáng tạo nhưng vẫn giữ bản sắc trang phục.">
                    <span class="font-medium text-cream-200">Sáng tạo</span>
                    <input type="range" min="1" max="10" x-model="creativeLevel" class="h-2 w-24 cursor-pointer accent-brand-500">
                    <span class="font-semibold text-cream-50" x-text="creativeLevel"></span><span class="text-cream-300/70">/10</span>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <select x-model="imageRatio" class="input !py-2"><option value="1:1">1:1</option><option value="4:3">4:3</option><option value="3:4">3:4</option><option value="9:16">9:16</option><option value="16:9">16:9</option><option value="4:5">4:5</option><option value="21:9">21:9</option><option value="19:6">19:6</option></select>
                    <select x-model="imageRes" class="input !py-2"><option value="1K">1K</option><option value="2K">2K</option></select>
                    <div class="col-span-2">
                        <label class="label">Số biến thể / lần tạo</label>
                        <div class="flex gap-1.5">
                            <template x-for="nv in [1, 2, 4]" :key="nv">
                                <button type="button" @click="variantCount = nv" class="flex-1 rounded-lg border py-1.5 text-xs font-semibold transition-colors" :class="variantCount === nv ? 'border-brand-600 bg-brand-600 text-white' : 'border-ink-700 text-cream-200 hover:border-brand-400'"><span x-text="nv"></span></button>
                            </template>
                        </div>
                    </div>
                    <button @click="generateImage()" :disabled="generating || !output.image_prompt_en" class="btn-brand col-span-2 whitespace-nowrap"><span x-show="!generating">Tạo Ảnh 2D</span><span x-show="generating">Đang gửi…</span></button>
                </div>
            </div>

            <!-- ===== Presets (themed card) ===== -->
            <div class="card mt-4 p-5" x-show="step===1" style="border: 1px solid var(--color-brand-500);">
                <div class="flex items-center justify-between">
                    <h2 class="font-display text-base font-semibold text-brand-300">🎨 Presets <span class="text-xs font-normal text-cream-300/50">(tuỳ chọn)</span></h2>
                    <button @click="presetOpen = true" class="btn-brand btn-sm whitespace-nowrap">🎛 Chọn preset <span x-show="presetIds.length" x-text="'(' + presetIds.length + ')'"></span></button>
                </div>
                <p class="mt-1 text-xs text-cream-200/60">Chọn chất liệu/phom/phong cách/hậu cảnh/góc… để AI tạo prompt chuẩn hơn — chỉ áp dụng preset bạn đã chọn.</p>
                <div class="mt-2" x-show="presetIds.length">
                    <span class="text-[11px] text-brand-200" x-text="selectedPresetSummary()"></span>
                    <button @click="clearPresets()" class="ml-2 btn-outline btn-sm">Đặt lại</button>
                </div>

                <!-- Presets modal -->
                <div x-show="presetOpen" x-cloak @click="presetOpen=false" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm">
                    <div class="flex max-h-[88vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-ink-700 bg-ink-800 shadow-2xl" @click.stop>
                        <div class="flex items-center justify-between border-b border-ink-700 px-5 py-3">
                            <div>
                                <h3 class="font-display text-base font-semibold text-cream-50">🎛 Presets</h3>
                                <p class="text-[11px] text-cream-300/60">Chọn nhiều — chỉ áp dụng preset bạn đã chọn khi tạo.</p>
                            </div>
                            <button @click="presetOpen=false" class="grid h-8 w-8 place-items-center rounded-full bg-ink-700 text-cream-200 hover:bg-ink-600">✕</button>
                        </div>

                        <!-- Siêu-nhóm -->
                        <div class="flex gap-1.5 px-5 pt-3">
                            <template x-for="sec in presetSections()" :key="sec.section">
                                <button @click="presetSection = sec.section" class="rounded-full px-3 py-1.5 text-xs font-semibold transition-colors" :class="presetSection === sec.section ? 'bg-brand-600 text-white' : 'bg-ink-700 text-cream-200 hover:bg-ink-600'"><span x-text="sec.section"></span><span class="ml-1 opacity-70" x-text="'·'+presetSectionCount(sec.section)"></span></button>
                            </template>
                        </div>

                        <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
                            <template x-for="sec in presetSections()" :key="sec.section">
                                <div x-show="presetSection === sec.section">
                                    <template x-for="group in sec.groups" :key="group.category">
                                        <div class="mb-5">
                                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-cream-200/70" x-text="catLabels[group.category] || group.category"></p>
                                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                                <template x-for="item in group.items" :key="item.id">
                                                    <label class="flex cursor-pointer items-start gap-2 rounded-xl border p-2.5 text-[11px] leading-tight transition-all" :class="presetIds.includes(item.id) ? 'border-brand-500 bg-brand-600/25 text-white' : 'border-ink-700 bg-ink-700/40 text-cream-200 hover:border-brand-500/50 hover:bg-ink-700'" :title="item.note">
                                                        <input type="checkbox" class="mt-0.5 h-3.5 w-3.5 shrink-0 accent-brand-500" :checked="presetIds.includes(item.id)" @change="togglePreset(item.id)">
                                                        <span x-text="item.key || item.label"></span>
                                                    </label>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>

                        <div class="flex items-center justify-between gap-2 border-t border-ink-700 px-5 py-3">
                            <span class="text-xs text-cream-300/60" x-show="presetIds.length" x-text="'Đã chọn ' + presetIds.length + ' preset'"></span>
                            <div class="ml-auto flex items-center gap-2">
                                <button @click="clearPresets()" class="btn-outline btn-sm" x-show="presetIds.length">Đặt lại</button>
                                <button @click="presetOpen=false" class="btn-brand btn-sm">Áp dụng & Đóng</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== Reference + Face ===== -->
            <div class="card mt-4 p-5" x-show="step===1">
                <!-- Reference source -->
                <div class="rounded-xl border border-ink-700 bg-ink-800 p-3">
                    <p class="mb-2 text-xs font-semibold text-cream-200">Gợi ý từ ảnh tham khảo</p>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" @click="$refs.refInput.click()" class="btn-outline btn-sm whitespace-nowrap">Tải ảnh</button>
                        <button type="button" @click="openOutputsRef()" class="btn-outline btn-sm whitespace-nowrap" title="Dùng một ảnh kết quả (Output) hoặc trong Thư viện làm nguồn tham khảo.">Từ kết quả</button>
                        <button type="button" @click="openRefPicker()" class="btn-outline btn-sm whitespace-nowrap">Từ sản phẩm</button>
                    </div>
                    <button type="button" @click="suggestStyle()" :disabled="suggesting || (!refFile && !refUrl)" class="btn-brand btn-sm mt-2 w-full whitespace-nowrap"><span x-show="!suggesting">Gợi ý phong cách & prompt</span><span x-show="suggesting">Đang gợi ý…</span></button>
                    <input x-ref="refInput" type="file" accept="image/*" @change="onRefChange" class="hidden">
                    <template x-if="refImage"><div class="relative mt-3 overflow-hidden rounded-xl"><img :src="refImage" class="h-36 w-full bg-ink-900 object-cover" alt="Ảnh tham khảo"><button type="button" @click="clearRef()" class="absolute right-2 top-2 grid h-7 w-7 place-items-center rounded-full bg-ink-900/70 text-white">×</button></div></template>
                    <template x-if="suggestResult.styles.length"><div class="mt-3 rounded-xl bg-brand-900/40 p-3 text-xs text-brand-200"><strong>Gợi ý:</strong> <span x-text="suggestResult.styles.join(', ')"></span><span x-show="suggestResult.background"> · <span x-text="suggestResult.background"></span></span></div></template>

                </div>
            </div>

            <!-- Thay Đổi Người Mẫu (Click-to-Swap) -->
            <div x-show="swapOpen" x-cloak @click="swapOpen=false" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm">
                <div class="w-full max-w-2xl overflow-hidden rounded-2xl border border-ink-700 bg-ink-800 shadow-2xl" @click.stop>
                    <div class="flex items-center justify-between border-b border-ink-700 px-5 py-3">
                        <div>
                            <h3 class="font-display text-base font-semibold text-cream-50">🔄 Thay Đổi Người Mẫu</h3>
                            <p class="text-[11px] text-cream-300/60">Chọn khuôn mặt + dáng → hệ thống tự ghép trang phục (virtual try-on).</p>
                        </div>
                        <button @click="swapOpen=false" class="grid h-8 w-8 place-items-center rounded-full bg-ink-700 text-cream-200 hover:bg-ink-600">✕</button>
                    </div>
                    <div class="max-h-[58vh] min-h-[38vh] overflow-y-auto p-5">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-cream-200/70">👤 Bộ sưu tập Người mẫu</p>
                        <div class="mb-5 grid grid-cols-3 gap-3 sm:grid-cols-6">
                            <template x-for="m in swapModels" :key="m.id">
                                <button @click="toggleSwapModel(m.id)" class="group relative flex flex-col items-center gap-1.5 rounded-2xl border p-3 transition-all" :class="swapModelIds.includes(m.id) ? 'border-brand-500 bg-brand-600/20' : 'border-ink-700 bg-ink-700/40 hover:border-brand-500/50'">
                                    <img :src="m.img" class="h-20 w-20 rounded-2xl bg-ink-900 object-cover ring-2 transition-transform group-hover:scale-105" :class="swapModelIds.includes(m.id) ? 'ring-brand-500' : 'ring-transparent'" onerror="this.style.display='none'; this.nextElementSibling.style.display='grid'" alt="Khuôn mặt">
                                    <span class="hidden h-20 w-20 place-items-center rounded-2xl bg-ink-700 text-xs font-bold text-cream-200" x-text="m.name"></span>
                                    <span class="text-[10px] text-cream-200" x-text="m.name"></span>
                                    <span @click.stop="removeSwapAsset(m)" class="absolute -right-1.5 -top-1.5 grid h-5 w-5 cursor-pointer place-items-center rounded-full bg-red-600 text-[10px] text-white" x-show="m.assetId" title="Xoá khỏi thư viện">×</span>
                                </button>
                            </template>
                        </div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-cream-200/70">🧍 Bộ sưu tập Tư thế</p>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            <template x-for="p in swapPoses" :key="p.id">
                                <button @click="toggleSwapPose(p.id)" class="group relative flex flex-col items-center gap-1.5 rounded-2xl border p-2 transition-all" :class="swapPoseIds.includes(p.id) ? 'border-brand-500 bg-brand-600/20' : 'border-ink-700 bg-ink-700/40 hover:border-brand-500/50'">
                                    <img :src="p.img" class="aspect-[3/4] w-full rounded-xl bg-ink-900 object-cover object-top transition-transform group-hover:scale-[1.02]" onerror="this.style.display='none'; this.nextElementSibling.style.display='block'" alt="Dáng">
                                    <svg viewBox="0 0 24 24" class="hidden h-10 w-10 text-cream-300"><path :d="p.sk" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    <span class="text-[10px] text-cream-200" x-text="p.name"></span>
                                    <span @click.stop="removeSwapAsset(p)" class="absolute -right-1.5 -top-1.5 grid h-5 w-5 cursor-pointer place-items-center rounded-full bg-red-600 text-[10px] text-white" x-show="p.assetId" title="Xoá khỏi thư viện">×</span>
                                </button>
                            </template>
                        </div>
                    </div>
                    <div class="shrink-0 border-t border-ink-700 bg-ink-900/30 px-5 py-3">
                        <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-cream-200/70">🏙 Hậu cảnh</p>
                        <div class="flex flex-wrap gap-1.5">
                            <template x-for="bg in swapBackgrounds" :key="bg.v">
                                <button @click="swapBackground = bg.v" class="rounded-full border px-2 py-1 text-[11px] transition-colors" :class="swapBackground === bg.v ? 'border-brand-500 bg-brand-600 text-white' : 'border-ink-700 bg-ink-700/40 text-cream-200 hover:bg-ink-700'" x-text="bg.l"></button>
                            </template>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 border-t border-ink-700 px-5 py-3">
                        <button class="btn-outline btn-sm mr-auto" @click="$refs.swapModelInput.click()">➕ Thêm khuôn mặt</button>
                        <button class="btn-outline btn-sm" @click="$refs.swapPoseInput.click()">➕ Thêm dáng</button>
                        <input x-ref="swapModelInput" type="file" accept="image/*" @change="addSwapAsset('model', $event)" class="hidden">
                        <input x-ref="swapPoseInput" type="file" accept="image/*" @change="addSwapAsset('pose', $event)" class="hidden">
                        <button @click="swapOpen=false" class="btn-outline btn-sm">Huỷ</button>
                        <button @click="runSwap()" :disabled="swapLoading" class="btn-brand btn-sm"><span x-show="!swapLoading">Áp Dụng</span><span x-show="swapLoading">Đang ghép…</span></button>
                    </div>
                </div>
            </div>

            <!-- ✨ Thuật sỹ ảo -->
            <div x-show="stylistOpen" x-cloak @click="stylistOpen=false" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm">
                <div class="w-full max-w-2xl overflow-hidden rounded-3xl border border-brand-500/20 shadow-2xl shadow-brand-500/10 backdrop-blur-xl" @click.stop style="background: linear-gradient(160deg, #1a1230 0%, #2a1a4a 40%, #1a2340 100%);">
                    <div class="flex items-center justify-between border-b border-white/10 bg-white/5 px-5 py-3.5">
                        <div class="flex items-center gap-2">
                            <span class="grid h-9 w-9 place-items-center rounded-2xl bg-gradient-to-br from-brand-500 to-violet-600 text-lg shadow-lg shadow-brand-500/40">✨</span>
                            <div>
                                <h3 class="font-display text-base font-semibold text-white">Thuật sỹ ảo</h3>
                                <p class="text-[10px] text-white/50">Trực tuyến · tư vấn thiết kế thời trang</p>
                            </div>
                        </div>
                        <button @click="closeStylist()" class="grid h-8 w-8 place-items-center rounded-full bg-white/10 text-white/80 hover:bg-white/20">✕</button>
                    </div>
                    <div class="flex h-[72vh] flex-col">
                        <!-- Bước 1: chọn loại trang phục -->
                        <template x-if="!stylistType">
                            <div class="flex-1 overflow-y-auto p-5">
                                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-brand-200">Chọn loại trang phục</p>
                                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                    <template x-for="t in stylistTypes" :key="t.id">
                                        <button @click="startStylist(t.id)" class="group flex flex-col items-center gap-1.5 rounded-2xl border border-white/10 bg-white/5 p-4 transition-all hover:scale-[1.03] hover:border-brand-400 hover:bg-brand-500/15 active:scale-[0.97]" :class="stylistFlash === t.id ? 'stylist-pick border-brand-400' : ''">
                                            <span class="text-3xl" x-text="t.emoji"></span>
                                            <span class="text-xs font-medium text-white" x-text="t.name"></span>
                                        </button>
                                    </template>
                                </div>
                                <p class="mt-3 text-xs text-white/40" x-show="!stylistTypes.length">Đang nạp các loại trang phục…</p>
                            </div>
                        </template>
                        <!-- Bước 2: cụm câu hỏi xương sườn (một lượt) -->
                        <template x-if="stylistType && !stylistPromptEn">
                            <div class="flex flex-1 flex-col overflow-hidden">
                                <div class="flex-1 overflow-y-auto p-5">
                                    <div class="mb-3 flex justify-start">
                                        <div class="max-w-[85%] rounded-2xl rounded-bl-md border border-white/10 bg-white/8 px-4 py-2.5 text-sm leading-relaxed text-white/90">Chào bạn 👋 Tôi là <b>thuật sỹ thời trang</b>. Cùng thiết kế «<b x-text="(stylistTypes.find(t=>t.id===stylistType)||{}).name"></b>» — bấm chọn hoặc nhập câu trả lời bên dưới (bỏ trống câu không cần):</div>
                                    </div>
                                    <div class="space-y-3" x-show="!stylistLoading">
                                        <template x-for="q in stylistQuestions" :key="q.key">
                                            <div class="stylist-anim rounded-2xl border border-white/10 bg-white/5 p-4">
                                                <p class="mb-2 text-xs font-semibold text-brand-200" x-text="q.q"></p>
                                                <div class="flex flex-wrap gap-1.5">
                                                    <template x-for="(opt,oi) in q.opts" :key="oi">
                                                        <button @click="setStylistAnswer(q.key, opt)" class="rounded-full border px-3 py-1.5 text-xs transition-all duration-150 hover:scale-[1.03] active:scale-[0.97]" :class="stylistAnswers[q.key] === opt ? 'border-brand-400 bg-brand-500/25 text-white shadow-lg shadow-brand-500/20' : 'border-white/15 bg-white/5 text-white/80 hover:border-brand-400'"><span x-text="opt"></span></button>
                                                    </template>
                                                </div>
                                                <input x-model="stylistAnswers[q.key]" @keydown.enter="submitStylist()" placeholder="Hoặc nhập tùy chỉnh…" class="mt-2 w-full rounded-lg border border-white/12 bg-white/8 px-3 py-1.5 text-xs text-white placeholder:text-white/40 focus:border-brand-400 focus:outline-none">
                                            </div>
                                        </template>
                                    </div>
                                    <div class="flex items-center gap-3 py-6" x-show="stylistLoading">
                                        <span class="relative inline-block h-8 w-8 shrink-0 rounded-full bg-ink-700"><span class="absolute inset-0 animate-spin rounded-full border-2 border-brand-500 border-t-transparent"></span></span>
                                        <span class="text-sm text-brand-200">Đang tải cụm câu hỏi…</span>
                                    </div>
                                </div>
                                <div class="border-t border-white/10 bg-white/5 p-4">
                                    <button @click="submitStylist()" :disabled="stylistLoading" class="btn-brand w-full"><span x-show="!stylistLoading">✨ Tạo prompt thiết kế</span><span x-show="stylistLoading">Đang tạo…</span></button>
                                </div>
                            </div>
                        </template>
                        <!-- Kết quả: prompt + summary -->
                        <template x-if="stylistType && stylistPromptEn">
                            <div class="flex flex-1 flex-col overflow-hidden">
                                <div class="flex-1 overflow-y-auto p-5">
                                    <div class="stylist-anim rounded-2xl border border-brand-500/40 p-4" style="background: linear-gradient(150deg, rgba(232,87,125,.15), rgba(124,58,237,.12));">
                                        <div class="mb-3 flex items-center justify-between">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-brand-300">✨ Prompt thiết kế</p>
                                            <div class="flex overflow-hidden rounded-full border border-white/15 text-xs">
                                                <button @click="stylistPromptLang='vi'" class="px-3 py-1 transition-colors" :class="stylistPromptLang==='vi' ? 'bg-brand-500/30 text-white' : 'text-white/60 hover:text-white'">🇻🇳 Tiếng Việt</button>
                                                <button @click="stylistPromptLang='en'" class="px-3 py-1 transition-colors" :class="stylistPromptLang==='en' ? 'bg-brand-500/30 text-white' : 'text-white/60 hover:text-white'">🇬🇧 Tiếng Anh</button>
                                            </div>
                                        </div>
                                        <div class="rounded-xl bg-black/30 p-3">
                                            <p class="text-[10px] font-semibold uppercase tracking-wide text-white/50" x-text="stylistPromptLang==='vi' ? 'Prompt tiếng Việt' : 'Prompt tiếng Anh'"></p>
                                            <p class="mt-1 text-xs leading-relaxed text-white/90" x-text="stylistPromptLang==='vi' ? stylistPromptVi : stylistPromptEn"></p>
                                        </div>
                                        <button @click="applyStylistPrompt()" class="btn-brand mt-3 w-full">Dùng prompt này → Tạo ảnh 2D</button>
                                        <button @click="stylistPromptEn=''; stylistAnswers = {};" class="mt-2 w-full text-xs text-white/60 hover:text-white/80">← Chỉnh lại câu trả lời</button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Chỉnh sửa prompt tiếng Việt -->
            <div x-show="translateViOpen" x-cloak @click="translateViOpen=false" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm">
                <div class="w-full max-w-xl overflow-hidden rounded-2xl border border-ink-700 bg-ink-800 shadow-2xl" @click.stop>
                    <div class="flex items-center justify-between border-b border-ink-700 px-5 py-3">
                        <h3 class="font-display text-base font-semibold text-cream-50">🇻🇳 Chỉnh sửa prompt tiếng Việt</h3>
                        <button @click="translateViOpen=false" class="grid h-8 w-8 place-items-center rounded-full bg-ink-700 text-cream-200 hover:bg-ink-600">✕</button>
                    </div>
                    <div class="p-5">
                        <p class="mb-2 text-xs text-cream-300/60">Sửa prompt tiếng Việt theo ý bạn, bấm <b>Xong</b> để ghi đè vào ô nhập prompt (giữ nguyên bản tiếng Việt) và đóng.</p>
                        <p class="mb-2 text-[10px] text-cream-300/40" x-show="translateMeta && translateMeta.provider">Dịch bằng <b x-text="translateMeta.provider"></b><span x-show="translateMeta.model"> (<span x-text="translateMeta.model"></span>)</span> · lần dịch gần nhất.</p>
                        <textarea x-model="viPrompt" rows="8" class="input !text-sm" placeholder="Prompt tiếng Việt…"></textarea>
                        <div class="mt-3 flex items-center justify-end gap-2">
                            <button @click="translateViOpen=false" class="btn-outline btn-sm">Huỷ</button>
                            <button @click="saveTranslateVi()" :disabled="translating" class="btn-brand btn-sm"><span x-show="!translating">Xong</span><span x-show="translating">Đang dịch…</span></button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bước 2 · Inpaint -->
            <!-- Fitting Room: Thay Đổi Người Mẫu (Click-to-Swap) -->
            <div class="card p-5" x-show="step===2" style="border: 1px solid var(--color-brand-500);">
                <div class="flex items-center justify-between">
                    <h2 class="font-display text-base font-semibold text-brand-300">🪄 Thay Đổi Người Mẫu</h2>
                    <button @click="openSwap()" class="btn-brand btn-sm whitespace-nowrap">Chọn</button>
                </div>
                <template x-if="swapModelIds.length">
                    <div class="mt-3">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center gap-2">
                                <img :src="(swapModels.find(m=>m.id===swapModelIds[0])||{}).img || '/images/placeholder.svg'" class="h-14 w-14 rounded-2xl bg-ink-900 object-cover" alt="Khuôn mặt">
                                <div class="text-xs"><p class="font-semibold text-cream-200" x-text="swapModelIds.length + ' khuôn mặt'"></p></div>
                            </div>
                            <div class="flex items-center gap-2">
                                <img :src="(swapPoses.find(p=>p.id===swapPoseIds[0])||{}).img || '/images/placeholder.svg'" class="h-14 w-14 rounded-2xl bg-ink-900 object-cover" alt="Tư thế">
                                <div class="text-xs"><p class="font-semibold text-cream-200" x-text="swapPoseIds.length + ' dáng'"></p></div>
                            </div>
                            <button @click="clearSwap()" class="grid h-7 w-7 place-items-center rounded-full bg-ink-700 text-cream-300 hover:bg-red-600 hover:text-white" title="Xoá lựa chọn khuôn mặt & dáng">✕</button>
                            <button @click="runSwap()" :disabled="swapLoading" class="btn-brand btn-sm ml-auto whitespace-nowrap"><span x-show="!swapLoading">Áp Dụng · <b x-text="swapModelIds.length * swapPoseIds.length"></b> phiên bản</span><span x-show="swapLoading">Đang ghép…</span></button>
                        </div>
                        <div class="mt-3 grid grid-cols-3 gap-2" x-show="lookbook.length">
                            <template x-for="item in lookbook" :key="item.id">
                                <button @click="previewId = item.id" class="group relative overflow-hidden rounded-xl border border-ink-700 bg-ink-900" :class="previewId === item.id ? 'outline outline-2 outline-brand-500' : 'hover:border-brand-500/40'">
                                    <img :src="item.url || '/images/placeholder.svg'" class="aspect-[3/4] w-full object-cover object-top" loading="lazy" alt="Phiên bản">
                                </button>
                            </template>
                        </div>
                    </div>
                </template>
                <p class="mt-2 text-[10px] text-ink-500" x-show="!swapModelIds.length">Bấm "Chọn" để chọn khuôn mặt + dáng (giữ nguyên 100% trang phục).</p>
            </div>

            <!-- Fitting Room: Ảnh để chỉnh sửa -->
            <div class="card p-5" x-show="step===2">
                <h2 class="mb-2 font-display text-base font-semibold text-ink-900">🖼 Ảnh để chỉnh sửa</h2>
                <div class="flex flex-wrap gap-2">
                    <button class="btn-outline btn-sm" @click="$refs.editImgInput.click()">Tải ảnh</button>
                    <button class="btn-outline btn-sm" @click="pickTarget='edit'; openOutputsRef()">Từ kết quả</button>
                    <button class="btn-outline btn-sm" @click="pickTarget='edit'; openRefPicker()">Từ sản phẩm</button>
                </div>
                <input x-ref="editImgInput" type="file" accept="image/*" @change="onEditImgChange" class="hidden">
                <template x-if="editSource">
                    <div class="mt-3 flex items-center gap-2 rounded-xl border border-ink-700 bg-ink-800 p-2">
                        <img :src="editSourceTmp" class="h-16 w-16 rounded-lg bg-ink-900 object-cover" onerror="this.src='/images/placeholder.svg'">
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-semibold text-cream-200" x-text="editSource.label"></p>
                            <p class="truncate text-[10px] text-cream-200/50">Ảnh này sẽ được chỉnh sửa (phẫu thuật) theo prompt + preset bên dưới.</p>
                        </div>
                        <button class="btn-outline btn-sm" @click="clearEditSource()">Bỏ</button>
                    </div>
                </template>
            </div>

            <!-- Fitting Room: Phẫu thuật Ảnh -->
            <div class="card p-5" x-show="step===2">
                <h2 class="mb-2 font-display text-base font-semibold text-ink-900">✏️ Phẫu thuật Ảnh</h2>
                <textarea x-model="refinePrompt" rows="2" class="input" placeholder="VD: add puff sleeve, change background to a beach…"></textarea>
                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-ink-700">
                    <label class="flex items-center gap-2"><input type="checkbox" x-model="preserveFace" class="h-4 w-4 accent-brand-600"> Giữ nguyên khuôn mặt & dáng</label>
                    <label class="flex items-center gap-2"><input type="checkbox" x-model="preserveBg" class="h-4 w-4 accent-brand-600"> Giữ nguyên nền</label>
                </div>
                <button @click="surgery()" :disabled="editSurging || !(editSource && editSource.url)" class="btn-brand mt-3 w-full"><span x-show="!editSurging">🔧 Phẫu thuật Ảnh</span><span x-show="editSurging">Đang phẫu thuật…</span></button>
            </div>

            <!-- Color Palette (Bước 2) -->
            <div class="card p-5" x-show="step===2">
                <h2 class="mb-2 font-display text-sm font-semibold text-ink-900">🎨 Color Palette</h2>
                <div class="flex items-center gap-1.5" x-show="palette.length">
                    <template x-for="c in palette" :key="c"><button type="button" class="h-7 w-7 rounded-full border border-ink-700" :style="{ background: c }" :title="c" @click="Alpine.store('toast').show('Màu ' + c, 'info')"></button></template>
                </div>
                <p class="text-xs text-cream-300" x-show="!palette.length">Chọn một ảnh ở Outputs để trích màu chủ đạo.</p>
            </div>

            <!-- Texture (Bước 2) -->
            <div class="card p-5" x-show="step===2">
                <h2 class="mb-2 font-display text-sm font-semibold text-ink-900">🧵 Texture</h2>
                <input type="range" min="0" max="10" x-model="texture" class="w-full accent-brand-500">
                <div class="mt-2 flex items-center justify-between text-xs">
                    <span class="text-cream-300">0 · Mịn</span>
                    <span class="font-semibold text-cream-50" x-text="texture"></span>
                    <span class="text-cream-300">10 · Chi tiết bề mặt</span>
                </div>
                <p class="mt-2 text-[10px] text-ink-500" x-text="'Tự thêm vào prompt: ' + (textureHint(texture))"></p>
            </div>

            <!-- Bước 3 · Ghế Đạo Diễn -->
            <div class="card p-5" x-show="step===3">
                <h2 class="mb-3 font-display text-base font-semibold text-ink-900">🎬 Ghế Đạo Diễn · Prompt video</h2>
                <div class="mb-3">
                    <label class="label">Model video</label>
                    <select x-model="videoModel" class="input !py-2">
                        <option value="">— Mặc định (theo thứ tự ưu tiên) —</option>
                        <template x-for="m in videoModels" :key="m.id">
                            <option :value="m.id" x-text="m.label + (m.provider ? ' · ' + m.provider : '')"></option>
                        </template>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="label">Kịch bản quay</label>
                    <div class="flex flex-wrap gap-1.5">
                        <template x-for="scene in videoScenes" :key="scene.id">
                            <button type="button" @click="videoScene = videoScene===scene.value ? '' : scene.value" class="rounded-full border px-3 py-1.5 text-xs transition-colors" :class="videoScene===scene.value ? 'border-brand-600 bg-brand-600 font-semibold text-white' : 'border-ink-700 text-cream-200 hover:border-brand-400 hover:text-brand-200'" :title="scene.note"><span x-text="scene.label.split(' (')[0]"></span></button>
                        </template>
                    </div>
                    <p class="mt-1 text-[10px] text-brand-300" x-show="videoScene" x-text="'Kịch bản sẽ được áp dụng: ' + videoSceneLabel"></p>
                </div>
                <div class="mb-3 grid grid-cols-2 gap-2">
                    <div><label class="label">Thời lượng</label><select x-model="videoDuration" class="input !py-2"><option>5</option><option>8</option><option>10</option><option>15</option><option>20</option></select></div>
                    <div><label class="label">Độ phân giải</label><select x-model="videoRes" class="input !py-2"><option>480</option><option>720</option><option>1080</option></select></div>
                </div>
                <div class="mb-3">
                    <label class="label">Prompt video</label>
                    <textarea x-model="output.video_prompt_en" rows="3" class="input !text-xs" placeholder="(để trống để ghép tự động từ ý tưởng + preset + kịch bản quay)"></textarea>
                    <button type="button" @click="suggestVideoPrompt()" class="btn-outline btn-sm mt-1 w-full" title="Ghép prompt video từ ý tưởng + preset + kịch bản quay (không dùng AI)">✨ Gợi ý prompt video</button>
                </div>
                <button @click="renderVideo()" :disabled="videoBusy || !videoSourceId" class="btn-brand w-full whitespace-nowrap"><span x-show="!videoBusy">🎬 Render Video</span><span x-show="videoBusy">Đang gửi…</span></button>
                <p class="mt-2 text-[10px] text-ink-500" x-text="videoSourceId ? 'Nguồn ảnh #' + videoSourceId : 'Chọn ảnh nguồn (Bước 2) để Render.'"></p>
            </div>
        </div>

        <!-- =============================================================== -->
        <!-- ===== CENTER: Canvas preview ===== -->
        <!-- =============================================================== -->
        <div class="order-1 min-w-0" x-show="!isMobile || step===2 || step===3">
            <div class="card flex flex-col overflow-hidden p-0 lg:h-full lg:min-h-0">
                <!-- Media area -->
                <div class="relative h-[58vh] cursor-grab touch-none overflow-hidden bg-gradient-to-b from-ink-900 to-ink-800 active:cursor-grabbing lg:h-auto lg:min-h-0 lg:flex-1" @contextmenu.prevent @pointerdown="startPan($event)" @pointermove="movePan($event)" @pointerup="endPan" @pointerleave="endPan" @wheel.prevent="onWheel($event)" @touchstart="onTouchPinch($event)" @touchmove.prevent="onTouchPinch($event)">

                    <!-- Clean canvas + cancel -->
                    <div class="absolute left-3 top-3 z-20 flex gap-1.5" @pointerdown.stop @click.stop>
                        <button x-show="preview || selectedImageId || videoSourceId" @click="cleanCanvas()" class="rounded-full bg-ink-900/80 px-3 py-1.5 text-[11px] font-semibold text-cream-200 shadow backdrop-blur transition-colors hover:bg-red-600 hover:text-white" title="Dọn canvas — xoá ảnh/video đang xem và các nguồn (Chỉnh sửa, Video) đang dùng">🗑 Dọn canvas</button>
                        <button x-show="preview && isActive(preview.status)" @click="cancelGeneration(preview)" class="rounded-full bg-ink-900/80 px-3 py-1.5 text-[11px] font-semibold text-red-300 shadow backdrop-blur transition-colors hover:bg-red-600 hover:text-white" title="Dừng tác vụ này và hoàn tiền vào tài khoản">⏹ Dừng · Hoàn tiền</button>
                    </div>

                    <!-- Canvas contextual actions (per-step) -->
                    <div class="absolute left-1/2 top-3 z-20 flex -translate-x-1/2 gap-1.5" @pointerdown.stop @click.stop>
                        <button @click="selectImage(preview)" :disabled="!preview || preview.type !== 'image' || preview.status !== 'completed'" class="btn-brand btn-sm whitespace-nowrap" x-show="step===2 && preview && preview.type==='image' && preview.status==='completed'" title="Chọn ảnh này làm nguồn Chỉnh sửa (Inpaint).">✏️ Sửa ảnh</button>
                        <button @click="selectVideo(preview)" :disabled="!preview || preview.type !== 'image' || preview.status !== 'completed'" class="btn-outline btn-sm whitespace-nowrap" x-show="step>=2 && preview && preview.type==='image' && preview.status==='completed'" title="Chọn ảnh này làm nguồn Render Video.">🎬 Tạo video</button>
                        <button @click="openSwap()" class="btn-outline btn-sm whitespace-nowrap text-brand-200" x-show="step===2 && preview && preview.type==='image' && preview.status==='completed'" title="Thay đổi người mẫu — thử trang phục lên khuôn mặt + dáng bạn chọn.">🔄 Thay Đổi Người Mẫu</button>
                    </div>

                    <!-- Canvas zoom toolbar (vertical, right edge) -->
                    <div class="pointer-events-auto absolute right-2 top-1/2 z-20 flex -translate-y-1/2 flex-col items-center gap-1 rounded-2xl bg-ink-900/80 p-1.5 shadow-lg backdrop-blur" @pointerdown.stop @click.stop>
                        <button @click="zoomIn()" class="grid h-7 w-7 place-items-center rounded-lg text-cream-200 hover:bg-ink-700" title="Phóng to">+</button>
                        <span class="text-[10px] font-semibold text-cream-300" x-text="zoom.toFixed(1)"></span>
                        <button @click="zoomOut()" class="grid h-7 w-7 place-items-center rounded-lg text-cream-200 hover:bg-ink-700" title="Thu nhỏ">−</button>
                        <span class="h-px w-5 bg-white/10"></span>
                        <button @click="resetZoom()" class="grid h-7 w-7 place-items-center rounded-lg text-[10px] text-cream-200 hover:bg-ink-700" title="Vừa khung">⤢</button>
                    </div>
                    <div class="absolute inset-0 grid place-items-center p-4 transition-transform duration-150"
                         :style="{ transform: 'translate(' + pan.x + 'px, ' + pan.y + 'px) scale(' + zoom + ')', transformOrigin: 'center' }">
                        <template x-if="canvasImg || (preview && preview.status === 'completed' && preview.type === 'image' && preview.media_url)">
                            <img :src="canvasImg || preview.media_url" class="max-h-full max-w-full cursor-zoom-in object-contain" onerror="this.src='/images/placeholder.svg'" @click="openLightbox()">
                        </template>
                        <template x-if="preview && preview.status === 'completed' && preview.type === 'video' && preview.media_url">
                            <video :src="preview.media_url" class="max-h-full max-w-full object-contain" controls loop muted playsinline></video>
                        </template>
                        <template x-if="preview && preview.status !== 'completed'">
                            <div class="text-center">
                                <span x-show="isActive(preview.status)" class="inline-block h-9 w-9 animate-spin rounded-full border-2 border-brand-600 border-t-transparent"></span>
                                <p class="mt-3 text-sm text-cream-200" x-text="statusText(preview)"></p>
                                <p class="mt-1 text-xs text-cream-300" x-show="preview.model" x-text="preview.provider + ' · ' + preview.model"></p>
                            </div>
                        </template>
                        <template x-if="!preview && !canvasImg">
                            <div class="text-center">
                                <div x-show="opening" class="mx-auto mb-3 h-16 w-16 animate-spin rounded-full border-4 border-brand-600 border-t-transparent"></div>
                                <div x-show="!opening" class="mx-auto mb-3 grid h-16 w-16 place-items-center rounded-2xl bg-brand-50 text-brand-700">
                                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.25-5.25 4.5 4.5L15.75 9.75l3.75 3.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <p class="text-sm text-cream-300" x-text="opening ? 'Đang mở mục bạn chọn…' : 'Tạo thiết kế ở Bước 1 để bắt đầu — kết quả sẽ hiện ở đây.'"></p>
                            </div>
                        </template>
                    </div>
                    <!-- Variations swiper (Step 2) — floated over the canvas bottom -->
                    <div class="pointer-events-auto absolute inset-x-0 bottom-3 z-20 flex justify-center px-3" x-show="previewVariants.length" @pointerdown.stop @click.stop>
                        <div class="scrollbar-hide flex max-w-full items-center gap-2 overflow-x-auto rounded-2xl bg-ink-900/70 px-2 py-2 shadow-lg backdrop-blur">
                            <template x-for="g in previewVariants" :key="g.id">
                                <button type="button" @click="setPreview(g)" class="relative w-14 shrink-0 overflow-hidden rounded-xl border" :class="previewId===g.id ? 'border-brand-400 ring-2 ring-brand-400/40' : 'border-white/10'">
                                    <template x-if="g.status==='completed' && g.media_url"><img :src="g.media_url" class="aspect-[3/4] w-full object-cover" onerror="this.src='/images/placeholder.svg'"></template>
                                    <template x-if="g.status!=='completed'"><div class="grid aspect-[3/4] w-full place-items-center bg-white/10"><span x-show="isActive(g.status)" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-brand-400 border-t-transparent"></span></div></template>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- =============================================================== -->
        <!-- ===== RIGHT: Generation Parameters ===== -->
        <!-- =============================================================== -->
        <div class="order-3 space-y-4 lg:h-full lg:min-h-0 lg:flex lg:flex-col lg:overflow-hidden lg:pr-1" x-show="!isMobile">
            <!-- Thư viện card (top) -->
            <div class="card flex shrink-0 items-center justify-between gap-2 p-2.5">
                <a href="{{ route('studio.library') }}" class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-600/20 text-brand-300 transition-colors hover:bg-brand-600 hover:text-white" title="Mở thư viện">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.25-5.25 4.5 4.5 3.75-3.75 3 3M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-6.75-3a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"/></svg>
                </a>
                <a href="{{ route('studio.library') }}" class="link text-xs">Thư viện Ảnh</a>
            </div>
            <!-- Outputs grid -->
            <div class="card flex min-h-0 flex-1 flex-col overflow-hidden p-4">
                <div class="mb-3 flex shrink-0 items-center justify-between">
                    <h2 class="font-display text-sm font-semibold text-ink-900">Outputs</h2>
                    <span class="text-xs text-ink-500" x-text="'(' + generations.length + ')'"></span>
                </div>
                <div class="scrollbar-hide min-h-0 flex-1 overflow-y-auto pr-1">
                    <div class="flex flex-col gap-2">
                        <template x-for="g in generations" :key="g.id">
                            <button type="button" @click="openGenView(g)" class="w-full overflow-hidden rounded-xl border text-left" :class="previewId === g.id ? 'border-brand-500 ring-2 ring-brand-500/30' : 'border-cream-200'" :title="gTitle(g)">
                                <div class="relative">
                                    <template x-if="g.status === 'completed' && g.media_url">
                                        <div class="relative aspect-[3/4] w-full bg-cream-100">
                                            <img :src="g.media_url" class="absolute inset-0 h-full w-full object-cover" onerror="this.src='/images/placeholder.svg'">
                                            <template x-if="g.type === 'video'"><div class="absolute inset-0 grid place-items-center"><span class="grid h-10 w-10 place-items-center rounded-full bg-ink-900/70 text-white">▶</span></div></template>
                                        </div>
                                    </template>
                                    <template x-if="g.status !== 'completed'"><div class="grid aspect-[3/4] w-full place-items-center bg-cream-100"><span x-show="isActive(g.status)" class="inline-block h-5 w-5 animate-spin rounded-full border-2 border-brand-600 border-t-transparent"></span></div></template>
                                </div>
                            </button>
                        </template>
                    </div>
                </div>
                <div class="mt-3 shrink-0 text-center text-xs text-ink-500" x-show="!generations.length">Chưa có kết quả.</div>
            </div>


                    </div>
    </div>

    <!-- Reference product picker modal -->
    <template x-if="refOpen">
        <div class="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-4">
            <div class="absolute inset-0 bg-ink-900/60" @click="closeRefPicker()"></div>
            <div class="relative z-10 w-full max-w-2xl overflow-hidden rounded-t-2xl bg-white shadow-2xl sm:rounded-2xl">
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

    <!-- Outputs / Library reference picker ("Gợi ý từ ảnh tham khảo" -> use a generated result) -->
    <template x-if="outputsRefOpen">
        <div class="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-4">
            <div class="absolute inset-0 bg-ink-900/60" @click="outputsRefOpen = false"></div>
            <div class="relative z-10 w-full max-w-2xl overflow-hidden rounded-t-2xl bg-white shadow-2xl sm:rounded-2xl">
                <div class="flex items-center justify-between border-b border-cream-200 px-4 py-3">
                    <h3 class="font-display text-sm font-semibold text-ink-900">Chọn ảnh tham khảo (kết quả gần đây)</h3>
                    <button @click="outputsRefOpen = false" class="grid h-8 w-8 place-items-center rounded-full bg-cream-100 text-ink-500 hover:text-ink-900">×</button>
                </div>
                <div class="max-h-[70vh] overflow-auto p-4">
                    <div class="grid grid-cols-3 gap-3 sm:grid-cols-4">
                        <template x-for="g in generations.filter(x => x.type==='image' && x.status==='completed' && x.media_url)" :key="g.id">
                            <button type="button" @click="useOutputRef(g)" class="overflow-hidden rounded-xl border border-cream-200 text-left hover:border-brand-500">
                                <img :src="g.media_url" class="aspect-[3/4] w-full object-cover" onerror="this.src='/images/placeholder.svg'">
                                <span class="block truncate px-2 py-1 text-[10px] text-ink-500" x-text="'#' + g.id + ' · ' + (g.provider||'') + ' · ' + (g.model||'')"></span>
                            </button>
                        </template>
                    </div>
                    <div class="mt-3 text-center text-xs text-ink-500" x-show="!generations.some(x => x.type==='image' && x.status==='completed' && x.media_url)">Chưa có ảnh kết quả nào. Tạo ảnh trước, hoặc mở Thư viện.</div>
                    <div class="mt-2 text-center"><a href="{{ route('studio.library') }}" class="link text-xs">Mở Thư viện (tất cả kết quả)</a></div>
                </div>
            </div>
        </div>
    </template>

    <!-- Fullscreen lightbox (self-contained zoom/pan; captures wheel & drag, blocks the layer below) -->
    <template x-if="lightbox && (canvasImg || (preview && preview.media_url))">
        <div class="fixed inset-0 z-[80] flex items-center justify-center overflow-hidden bg-ink-900/95 p-4"
             @wheel.prevent="onLbWheel($event)" @click.self="closeLightbox()">
            <button @click="closeLightbox()" class="absolute right-4 top-4 z-20 grid h-10 w-10 place-items-center rounded-full bg-ink-900/80 text-cream-200 hover:text-white">×</button>
            <div class="absolute left-4 top-4 z-20 flex items-center gap-1">
                <button @click="lbZoomOut()" class="grid h-9 w-9 place-items-center rounded-lg border border-cream-200 bg-ink-900/70 text-cream-100 hover:bg-ink-700" title="Thu nhỏ">−</button>
                <button @click="lbReset()" class="rounded-lg border border-cream-200 bg-ink-900/70 px-2.5 py-1.5 text-xs text-cream-100 hover:bg-ink-700" title="Vừa khung">Vừa</button>
                <button @click="lbZoomIn()" class="grid h-9 w-9 place-items-center rounded-lg border border-cream-200 bg-ink-900/70 text-cream-100 hover:bg-ink-700" title="Phóng to">+</button>
            </div>
            <img :src="canvasImg || preview.media_url"
                 class="max-h-[92vh] max-w-[94vw] cursor-grab select-none rounded-xl object-contain shadow-2xl active:cursor-grabbing"
                 :style="{ transform: 'translate(' + lbPan.x + 'px, ' + lbPan.y + 'px) scale(' + lbZoom + ')', transformOrigin: 'center' }"
                 @pointerdown="lbStartPan($event)" @pointermove="lbMovePan($event)" @pointerup="lbEndPan" @pointerleave="lbEndPan"
                 onerror="this.src='/images/placeholder.svg'">
            <div class="pointer-events-none absolute bottom-4 left-1/2 -translate-x-1/2 rounded-full bg-ink-900/80 px-3 py-1 text-xs text-cream-200">Lăn chuột / +− : Thu phóng · Kéo: di chuyển · Zoom <b x-text="lbZoom.toFixed(2)"></b>x</div>
        </div>
    </template>

    <!-- Output viewer popup -->
    <template x-if="viewGen">
        <div class="fixed inset-0 z-[80] flex items-center justify-center p-4 sm:p-8" @click.self="closeGenView()">
            <div class="absolute inset-0 bg-ink-900/90" @click="closeGenView()"></div>
            <div class="relative z-10 flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden rounded-3xl bg-ink-900 shadow-2xl">
                <button @click="closeGenView()" class="absolute right-3 top-3 z-20 grid h-9 w-9 place-items-center rounded-full bg-ink-800 text-cream-200 hover:text-white">×</button>
                <div class="flex min-h-0 flex-1 items-center justify-center overflow-hidden bg-ink-900 p-3">
                    <template x-if="viewGen && viewGen.type==='video' && viewGen.media_url">
                        <video :src="viewGen.media_url" class="max-h-[78vh] w-full rounded-2xl object-contain" controls autoplay loop muted playsinline></video>
                    </template>
                    <template x-if="viewGen && viewGen.type!=='video' && viewGen.media_url">
                        <img :src="viewGen.media_url" class="max-h-[78vh] max-w-full rounded-2xl object-contain" onerror="this.src='/images/placeholder.svg'">
                    </template>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-ink-700 px-4 py-3">
                    <span class="truncate text-xs text-cream-300" x-text="viewGen ? genInfoLine(viewGen) : ''"></span>
                    <span class="flex items-center gap-2">
                        <button @click="openInStudio(viewGen)" class="btn-brand btn-sm whitespace-nowrap" title="Mở trong Studio (canvas)">🖼 Mở trong Studio</button>
                        <a :href="viewGen ? '/studio/generations/' + viewGen.id + '/download' : '#'" class="btn-outline btn-sm" x-show="viewGen && viewGen.media_url">Tải xuống</a>
                    </span>
                </div>
            </div>
        </div>
    </template>
</div>
@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('studioApp', (presets, gens, projects, credits, currentProject, catLabels, imageRes, videoRes, imageRatio, videoDuration, creativeLevel) => ({
        presets, projects, catLabels,
        imageRes: imageRes || '2K', videoRes: videoRes || '720', imageRatio: imageRatio || '1:1', videoDuration: videoDuration || '10', variantCount: 1,
        creativeLevel: Number(creativeLevel) || 6,
        idea: '', presetIds: [],
        loading: false, generating: false, videoBusy: false, refining: false,
        output: { image_prompt_en: '', video_prompt_en: '', history_id: null },
        generations: gens, creditsLeft: Number(credits),
        currentProjectId: currentProject, selectedImageId: null, videoSourceId: null, videoScene: '',
        videoModel: '', videoModels: [
            { label: 'HappyHorse · i2v', model: 'happyhorse-1.1-i2v' },
            { label: 'Wan 2.2 · i2v', model: 'wan2.2-i2v' },
            { label: 'Wan 2.5 · t2v', model: 'wan2.5-t2v' },
            { label: 'Wan 2.1 · i2v turbo', model: 'wan2.1-i2v-turbo' },
        ],
        newProjectName: '', showNewProject: false, refinePrompt: '', preserveBg: true, preserveFace: true,
        refFile: null, refImage: null, refUrl: null, suggesting: false, refOpen: false, refProducts: [], refLoading: false, outputsRefOpen: false,
        suggestResult: { styles: [], background: '', image_prompt_en: '' },
        refBusy: false,
        presetOpen: false, presetSection: 'Trang phục',
        swapOpen: false, swapModelIds: [], swapPoseIds: [], swapLoading: false, lookbook: [], lookbookOpen: false,
        swapBackground: '', _swapBgFallback: [
            { v: '', l: 'Giữ nền gốc' },
            { v: 'a clean white studio background', l: 'Studio trắng' },
            { v: 'an old-town cobblestone street', l: 'Phố cổ' },
            { v: 'a lush green nature landscape', l: 'Thiên nhiên' },
            { v: 'a serene seaside beach at golden hour', l: 'Bờ biển' },
            { v: 'a neon-lit city street at night', l: 'Đường đêm' },
        ],
        swapModels: [
            { id: 'model01', name: 'Mẫu 1', img: '/samples/model-01.png' },
            { id: 'model02', name: 'Mẫu 2', img: '/samples/model-02.png' },
            { id: 'model03', name: 'Mẫu 3', img: '/samples/model-03.png' },
            { id: 'model04', name: 'Mẫu 4', img: '/samples/model-04.png' },
            { id: 'model05', name: 'Mẫu 5', img: '/samples/model-05.png' },
            { id: 'model06', name: 'Mẫu 6', img: '/samples/model-06.png' },
        ],
        swapPoses: [
            { id: 'pose01', name: 'Đứng thẳng', img: '/samples/pose-01.png', sk: 'M12 4a2 2 0 1 1 0 4 2 2 0 0 1 0-4zM12 9l-4 12h2l2-5 2 5h2z' },
            { id: 'pose02', name: 'Tay chống hông', img: '/samples/pose-02.png', sk: 'M12 4a2 2 0 1 1 0 4 2 2 0 0 1 0-4zM12 10l-3 10h2l1-4 1 4h2zM9 13l3-1 3 1' },
            { id: 'pose03', name: 'Hai tay chống hông', img: '/samples/pose-03.png', sk: 'M12 4a2 2 0 1 1 0 4 2 2 0 0 1 0-4zM12 10l-4 10h2l2-5 2 5h2zM8 12l4-1 4 1' },
            { id: 'pose04', name: 'Chống hông chéo chân', img: '/samples/pose-04.png', sk: 'M12 4a2 2 0 1 1 0 4 2 2 0 0 1 0-4zM12 10l-4 10h2l2-5 2 5h2zM8 12l4-1 4 1' },
            { id: 'pose05', name: 'Chống hông', img: '/samples/pose-05.png', sk: 'M12 4a2 2 0 1 1 0 4 2 2 0 0 1 0-4zM12 10l-4 10h2l2-5 2 5h2zM8 12l4-1 4 1' },
            { id: 'pose06', name: 'Ngồi ghế', img: '/samples/pose-06.png', sk: 'M12 5a2 2 0 1 1 0 4 2 2 0 0 1 0-4zM12 10l-5 8h2l2-5 2 5h2zM12 15l-6 2' },
            { id: 'pose07', name: 'Tay đút túi', img: '/samples/pose-07.png', sk: 'M12 4a2 2 0 1 1 0 4 2 2 0 0 1 0-4zM12 10l-4 10h2l1-5 2 5h2zM13 13l4-1' },
            { id: 'pose08', name: 'Ngồi xổm', img: '/samples/pose-08.png', sk: 'M12 5a2 2 0 1 1 0 4 2 2 0 0 1 0-4zM12 10l-4 8h2l2-4 2 4h2z' },
            { id: 'pose09', name: 'Sải bước', img: '/samples/pose-09.png', sk: 'M12 4a2 2 0 1 1 0 4 2 2 0 0 1 0-4zM12 9l-2 11h2l1-4 2 4h2l-3-8zM12 13l-3-2' },
            { id: 'pose10', name: 'Xoay lưng', img: '/samples/pose-10.png', sk: 'M12 4a2 2 0 1 1 0 4 2 2 0 0 1 0-4zM12 9l-3 11h2l1-6 2 6h2l-3-8z' },
            { id: 'pose11', name: 'Tựa ghế', img: '/samples/pose-11.png', sk: 'M12 4a2 2 0 1 1 0 4 2 2 0 0 1 0-4zM12 10l-4 10h2l2-5 2 5h2zM10 13l3-1' },
            { id: 'pose12', name: 'Bước ngang', img: '/samples/pose-12.png', sk: 'M12 4a2 2 0 1 1 0 4 2 2 0 0 1 0-4zM12 9l-3 11h2l1-4 2 4h2l-3-8z' },
        ],
        pickTarget: 'ref',
        editSource: null, editSourceTmp: '', canvasImg: '', editFace: '', editFaceRef: '',
        editPresetOpen: false, editPresetIds: [], editSurging: false,
        translateViOpen: false, viPrompt: '', translating: false, translateMeta: {},
        stylistOpen: false, stylistType: '', stylistTypes: @json($stylistTypes), stylistHistory: [], stylistStep: null, stylistLoading: false, stylistFlash: '', stylistMessages: [], stylistCustom: '', stylistQuestions: [], stylistAnswers: {}, stylistPromptEn: '', stylistPromptVi: '', stylistPromptLang: 'en', stylistSummary: '',

        previewId: null,
        viewGen: null,
        zoom: 1, pan: { x: 0, y: 0 }, palette: [], texture: 5, _drag: null, lightbox: false, opening: false, step: 1,
        lbZoom: 1, lbPan: { x: 0, y: 0 }, _lbDrag: null,
        _timers: {}, now: Date.now(), isMobile: window.innerWidth < 1024,

        async init() {
            // Load registered models (dynamic dropdown: video / image / inference) from the registry.
            try {
                const mres = await fetch('/studio/models', { headers: { Accept: 'application/json' } });
                const md = await mres.json();
                if (md && md.groups) {
                    const vid = md.groups.video || [];
                    if (vid.length) this.videoModels = vid.map(mm => ({ id: mm.id, label: mm.label, provider: mm.provider, model: mm.key }));
                }
            } catch (e) {}
            // "Catwalk Video" nav (/studio#catwalk) opens Step 3 (Director / catwalk) directly.
            const goCatwalk = () => { if (location.hash === '#catwalk') this.step = 3; };
            goCatwalk();
            window.addEventListener('hashchange', goCatwalk);
            const q = new URLSearchParams(location.search).get('gen');
            let target = null;
            if (q) {
                target = this.generations.find(g => g.id === Number(q));
                if (target) {
                    this.openGem(target);
                } else {
                    // Item may be older than the first 12 loaded; fetch the exact one.
                    this.opening = true;
                    try {
                        const res = await fetch('/studio/generations/' + Number(q), { headers: { Accept: 'application/json' } });
                        if (res.ok) {
                            const g = await res.json();
                            target = { id: g.id, type: g.type, status: g.status, model: g.model, provider: g.provider, media_url: g.media_url, error: g.error, credits_cost: g.credits_cost, created_at: '' };
                            if (!this.generations.some(x => x.id === Number(g.id))) this.generations.unshift(target);
                            this.openGem(target);
                        } else {
                            this.openGem(this.generations.find(g => g.status === 'completed') || null);
                        }
                    } catch (e) {
                        this.openGem(this.generations.find(g => g.status === 'completed') || null);
                    } finally { this.opening = false; }
                }
            } else {
                this.openGem(this.generations.find(g => g.status === 'completed') || null);
            }
            this.generations.forEach(g => { if (this.isActive(g.status)) { g._t0 = g._t0 || Date.now(); this.poll(g.id); } });
            // Live clock so the status text shows a running "(Xs)" while a task is generating.
            setInterval(() => { this.now = Date.now(); }, 1000);
            // Mobile wizard: show one screen (step) at a time below lg.
            const onResize = () => { this.isMobile = window.innerWidth < 1024; };
            window.addEventListener('resize', onResize);
            onResize();

            // Âm thầm dịch prompt EN -> VI ngay khi có prompt, để popup "Sửa tiếng Việt" mở tức thì và
            // BẢN TIẾNG VIỆT LUÔN ĐƯỢC LƯU (không mất khi đóng popup).
            let _trDeb = null;
            // Dịch prompt tiếng Việt chỉ chạy khi người dùng bấm nút "Sửa tiếng Việt" (không tự động).
            // Load custom model/pose library assets (Thư viện upload riêng).
            this.loadSwapAssets();
            // Load stylist garment types (✨ Thuật sỹ card).
            this.loadStylistTypes();
        },
        async silentTranslate(en) {
            const v = String(en || '').trim();
            if (!v || this.translating || v === this.viPrompt) return;
            this.translating = true;
            try { const d = await this.api('/studio/translate', { text: v, direction: 'vi' }); if (d.text) this.viPrompt = d.text; this.translateMeta = { provider: d.provider, model: d.model }; }
            catch (e) {}
            finally { this.translating = false; }
        },
        openGem(g) {
            if (!g) { this.previewId = null; this.selectedImageId = null; this.palette = []; return; }
            this.previewId = g.id;
            this.loadPalette(g.id);
            if (g.type === 'image' && g.status === 'completed') { this.selectedImageId = g.id; this.videoSourceId = g.id; Alpine.store('toast').show('Đã mở ảnh #' + g.id); }
            else { this.selectedImageId = null; this.videoSourceId = null; }
        },
        get preview() { return this.generations.find(g => g.id === this.previewId) || null; },
        // Results of the SAME image-generation run (variants share prompts_history_id).
        get previewVariants() {
            const p = this.preview; if (!p) return [];
            if (p.prompts_history_id) { const list = this.generations.filter(g => g.prompts_history_id === p.prompts_history_id); if (list.length) return list; }
            return this.generations.filter(g => g.id === p.id);
        },
        setPreview(g) { if (g) { this.previewId = g.id; this.loadPalette(g.id); } },
        goStep(n) { this.step = n; this.$nextTick(() => { const el = this.$refs.leftPanel; if (el) el.scrollTop = 0; }); },
        _zoomAt(cx, cy, factor) {
            const old = this.zoom;
            const nz = Math.min(4, Math.max(0.6, +(old * factor).toFixed(2)));
            if (nz === old) return;
            const k = nz / old;
            this.pan.x = +((1 - k) * cx + k * this.pan.x).toFixed(2);
            this.pan.y = +((1 - k) * cy + k * this.pan.y).toFixed(2);
            this.zoom = nz;
        },
        zoomIn() { this._zoomAt(0, 0, 1.25); },
        zoomOut() { this._zoomAt(0, 0, 0.8); },
        resetZoom() { this.zoom = 1; this.pan = { x: 0, y: 0 }; },
        panBy(dx, dy) { this.pan.x += dx; this.pan.y += dy; },
        startPan(e) { this._drag = { x: e.clientX, y: e.clientY, px: this.pan.x, py: this.pan.y }; },
        movePan(e) { if (!this._drag) return; this.pan.x = this._drag.px + (e.clientX - this._drag.x); this.pan.y = this._drag.py + (e.clientY - this._drag.y); },
        endPan() { this._drag = null; },
        onWheel(e) {
            const r = e.currentTarget.getBoundingClientRect();
            const cx = e.clientX - (r.left + r.width / 2), cy = e.clientY - (r.top + r.height / 2);
            this._zoomAt(cx, cy, e.deltaY > 0 ? 0.82 : 1.22);
        },
        _touchDist: null,
        onTouchPinch(e) {
            if (e.touches.length === 2) {
                const t0 = e.touches[0], t1 = e.touches[1];
                const d = Math.hypot(t0.clientX - t1.clientX, t0.clientY - t1.clientY);
                if (this._touchDist) {
                    const r = e.currentTarget.getBoundingClientRect();
                    const cx = ((t0.clientX + t1.clientX) / 2) - (r.left + r.width / 2), cy = ((t0.clientY + t1.clientY) / 2) - (r.top + r.height / 2);
                    this._zoomAt(cx, cy, d / this._touchDist);
                }
                this._touchDist = d;
            } else { this._touchDist = null; }
        },
        openGenView(g) { this.viewGen = g; },
        openInStudio(g) { if (g) { this.setPreview(g); } this.viewGen = null; },
        closeGenView() { this.viewGen = null; },
        openLightbox() { this.lbZoom = 1; this.lbPan = { x: 0, y: 0 }; this.lightbox = true; document.body.style.overflow = 'hidden'; },
        closeLightbox() { this.lightbox = false; document.body.style.overflow = ''; },
        _lbZoomAt(cx, cy, factor) {
            const old = this.lbZoom;
            const nz = Math.min(8, Math.max(0.6, +(old * factor).toFixed(2)));
            if (nz === old) return;
            const k = nz / old;
            this.lbPan.x = +((1 - k) * cx + k * this.lbPan.x).toFixed(2);
            this.lbPan.y = +((1 - k) * cy + k * this.lbPan.y).toFixed(2);
            this.lbZoom = nz;
        },
        lbZoomIn() { this._lbZoomAt(0, 0, 1.5); },
        lbZoomOut() { this._lbZoomAt(0, 0, 0.66); },
        lbReset() { this.lbZoom = 1; this.lbPan = { x: 0, y: 0 }; },
        onLbWheel(e) {
            const el = e.currentTarget; const r = el && el.getBoundingClientRect ? el.getBoundingClientRect() : { left: 0, top: 0, width: window.innerWidth, height: window.innerHeight };
            const cx = e.clientX - (r.left + r.width / 2), cy = e.clientY - (r.top + r.height / 2);
            this._lbZoomAt(cx, cy, e.deltaY > 0 ? 0.82 : 1.22);
        },
        lbStartPan(e) { this._lbDrag = { x: e.clientX, y: e.clientY, px: this.lbPan.x, py: this.lbPan.y }; },
        lbMovePan(e) { if (!this._lbDrag) return; this.lbPan.x = this._lbDrag.px + (e.clientX - this._lbDrag.x); this.lbPan.y = this._lbDrag.py + (e.clientY - this._lbDrag.y); },
        lbEndPan() { this._lbDrag = null; },
        get stepLabel() { return ['', 'Bước 1 · Concept', 'Bước 2 · Fitting Room', 'Bước 3 · Director'][this.step] || ''; },
        get fabDisabled() {
            if (this.step === 3) return this.videoBusy || !this.videoSourceId;
            if (this.step === 2) return false;
            return this.generating || (!this.output.image_prompt_en && !this.idea.trim());
        },
        async fabAction() {
            if (this.step === 3) return this.renderVideo();
            if (this.step === 2) { this.step = 3; return; }
            // Step 1: ensure a prompt then generate a 2D design.
            if (!this.output.image_prompt_en && this.idea.trim()) await this.ideate();
            this.generateImage();
        },
        // Hậu cảnh cho swap: dùng chung danh mục 'background' trong Presets (nếu có), fallback danh sách cứng.
        get swapBackgrounds() {
            const grp = this.presets.find(x => x.category === 'background');
            const fromPresets = grp && grp.items ? grp.items.map(i => ({ v: i.value || i.key || '', l: i.label || i.note || i.key })) : [];
            return [...this._swapBgFallback, ...fromPresets.filter(i => i.v && !this._swapBgFallback.some(f => f.v === i.v))];
        },
        get videoScenes() { const g = this.presets.find(x => x.category === 'video_scene'); return g ? g.items : []; },
        get videoSceneLabel() { const s = this.videoScenes.find(x => x.value === this.videoScene); return s ? (s.label + (s.note ? ' · ' + s.note : '')) : ''; },
        get presetGroups() { return this.presets.filter(g => g.category !== 'video_scene'); },
        // Siêu-nhóm preset: chia nhóm theo mục đích dùng để người dùng chọn nhanh & dễ nhìn.
        presetSections() {
            const map = [
                ['Trang phục', ['fabric', 'color', 'silhouette', 'neckline', 'sleeve', 'fit', 'pattern', 'detail']],
                ['Phong cách', ['style', 'occasion', 'season']],
                ['Bối cảnh & Dáng', ['background', 'pose', 'camera', 'lens']],
            ];
            const out = [];
            for (const [section, cats] of map) {
                const groups = this.presetGroups.filter((g) => cats.includes(g.category));
                if (groups.length) out.push({ section, groups, count: groups.reduce((a, g) => a + g.items.length, 0) });
            }
            return out;
        },
        presetSectionCount(sec) { return this.presetSections().find((s) => s.section === sec)?.count || 0; },
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
            this.haptic(12);
        },
        selectedPresetId(cat) {
            const grp = this.presets.find((g) => g.category === cat);
            if (!grp) return '';
            const s = grp.items.find((it) => this.presetIds.includes(it.id));
            return s ? s.id : '';
        },
        setPresetForCategory(cat, id) {
            const grp = this.presets.find((g) => g.category === cat);
            if (grp) {
                const gids = grp.items.map((it) => it.id);
                this.presetIds = this.presetIds.filter((i) => !gids.includes(i));
            }
            if (id) this.presetIds.push(Number(id));
            this.haptic(10);
        },
        selectedPresetText(cat) {
            const grp = this.presets.find((g) => g.category === cat);
            if (!grp) return '';
            return grp.items
                .filter((it) => this.presetIds.includes(it.id))
                .map((it) => it.key || it.label).join(', ');
        },
        selectedPresetSummary() {
            const parts = [];
            this.presetGroups.forEach((g) => {
                const t = this.selectedPresetText(g.category);
                if (t) parts.push((this.catLabels[g.category] || g.category) + ': ' + t);
            });
            return parts.join(' · ');
        },
        // Assemble the video prompt from the Step-1 pieces (idea + garment presets) + motion. No AI inference:
        // the user decides when to press "Gợi ý prompt video". The Kịch bản quay is the camera control and is
        // shown separately (appended at render).
        suggestVideoPrompt() {
            const idea = (this.output.image_prompt_en || this.idea || '').trim();
            const garment = ['fabric', 'silhouette', 'style', 'background', 'pose']
                .map((c) => this.selectedPresetText(c)).filter(Boolean).join(', ');
            let p = 'Cinematic fashion catwalk, a model presenting ' + (idea || 'a high-fashion outfit');
            if (garment) p += ', ' + garment;
            p += ', dynamic fabric motion, professional fashion video.';
            this.output.video_prompt_en = p.trim();
            Alpine.store('toast').show('Đã ghép prompt video từ ý tưởng + preset (chỉnh tay nếu cần).');
        },

        resetForRef() {
            // Reference image changed/removed -> clear the derived prompt & suggestion to avoid overlap.
            this.suggestResult = { styles: [], background: '', image_prompt_en: '' };
            this.output.image_prompt_en = '';
            this.output.video_prompt_en = '';
            this.refinePrompt = '';
        },
        onRefChange(e) {
            const f = e.target.files && e.target.files[0];
            if (!f) return;
            if (this.refImage && String(this.refImage).startsWith('blob:')) URL.revokeObjectURL(this.refImage);
            this.refFile = f; this.refImage = URL.createObjectURL(f); this.refUrl = null;
            this.resetForRef();
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
            if (this.pickTarget === 'edit') { this.chooseEditProduct(item); return; }
            if (this.refImage && String(this.refImage).startsWith('blob:')) URL.revokeObjectURL(this.refImage);
            this.refUrl = item.url; this.refImage = item.url; this.refFile = null; this.refOpen = false;
            this.resetForRef();
        },
        openOutputsRef() { this.outputsRefOpen = true; },
        useOutputRef(g) {
            if (this.pickTarget === 'edit') { this.useEditOutput(g); return; }
            if (this.refImage && String(this.refImage).startsWith('blob:')) URL.revokeObjectURL(this.refImage);
            this.refUrl = g.media_url; this.refImage = g.media_url; this.refFile = null; this.outputsRefOpen = false;
            this.resetForRef();
            Alpine.store('toast').show('Đã chọn ảnh #' + g.id + ' làm ảnh tham khảo.');
        },
        clearRef() {
            if (this.refImage && String(this.refImage).startsWith('blob:')) URL.revokeObjectURL(this.refImage);
            this.refImage = null; this.refFile = null; this.refUrl = null;
            this.resetForRef();
        },
        // ===== Fitting Room: chọn ảnh để chỉnh sửa (3 nguồn) =====
        async onEditImgChange(e) {
            const f = e.target.files && e.target.files[0];
            if (!f) return;
            try {
                const fd = new FormData(); fd.append('image', f);
                const up = await this.upload('/studio/upload-ref', fd);
                this.editSource = { url: up.url, label: 'Ảnh tải lên' };
                this.editSourceTmp = URL.createObjectURL(f);
                this.cleanCanvas(true); this.canvasImg = this.editSourceTmp; this.selectedImageId = null;
                Alpine.store('toast').show('Đã chọn ảnh tải lên để chỉnh sửa.');
            } catch (err) { Alpine.store('toast').show(err.message, 'error'); }
        },
        chooseEditProduct(item) {
            this.editSource = { url: item.url, label: item.name || 'Từ sản phẩm' };
            this.editSourceTmp = item.url; this.cleanCanvas(true); this.canvasImg = item.url; this.selectedImageId = null; this.refOpen = false;
            Alpine.store('toast').show('Đã chọn ảnh sản phẩm để chỉnh sửa.');
        },
        useEditOutput(g) {
            this.editSource = { url: g.media_url, label: 'Kết quả #' + g.id, generationId: g.id };
            this.editSourceTmp = g.media_url; this.cleanCanvas(true); this.canvasImg = g.media_url; this.selectedImageId = g.id; this.outputsRefOpen = false;
        },
        clearEditSource() {
            this.editSource = null; this.editSourceTmp = ''; this.canvasImg = '';
            if (this.selectedImageId) this.selectedImageId = null;
        },
        async onEditFaceChange(e) {
            const f = e.target.files && e.target.files[0];
            if (!f) return;
            try {
                const fd = new FormData(); fd.append('image', f);
                const up = await this.upload('/studio/face', fd);
                this.editFace = up.url || URL.createObjectURL(f); this.editFaceRef = up.url || '';
                Alpine.store('toast').show('Đã đặt khuôn mặt mẫu — phẫu thuật sẽ đồng bộ.');
            } catch (err) { Alpine.store('toast').show(err.message, 'error'); }
        },
        clearEditFace() { this.editFace = ''; this.editFaceRef = ''; },
        toggleEditPreset(id) { const i = this.editPresetIds.indexOf(id); if (i >= 0) this.editPresetIds.splice(i, 1); else this.editPresetIds.push(id); },
        editPresetText(cat) {
            const grp = this.presets.find((g) => g.category === cat);
            if (!grp) return '';
            return grp.items.filter((it) => this.editPresetIds.includes(it.id)).map((it) => it.key || it.label).join(', ');
        },
        // ===== Fitting Room: Phẫu thuật ảnh =====
        textureHint(v) {
            if (v <= 1) return 'smooth silk fabric, sleek';
            if (v <= 3) return 'soft matte fabric';
            if (v <= 5) return 'natural subtle fabric texture';
            if (v <= 7) return 'lightly woven textured fabric';
            return 'heavy knit, coarse weave, visible thread detail';
        },
        async surgery() {
            if (!this.editSource || !this.editSource.url || this.editSurging) { Alpine.store('toast').show('Chọn ảnh để chỉnh sửa trước.', 'error'); return; }
            this.editSurging = true;
            try {
                const bg = this.editPresetText('background');
                const pose = this.editPresetText('pose');
                let prompt = (this.refinePrompt || this.output.image_prompt_en || '').trim();
                if (!prompt) prompt = 'Keep the exact garment, outfit, person, face and camera as in the reference.';
                const infl = [];
                if (bg) infl.push('change the background to ' + bg);
                if (pose) infl.push('change the pose to ' + pose);
                const tex = this.textureHint(this.texture);
                if (tex) infl.push('make the outfit fabric ' + tex);
                if (infl.length) prompt = prompt.replace(/[. ]+$/, '') + '. ' + infl.join(', ') + '.';
                const data = await this.api('/studio/generate', { prompt, base_image: this.editSource.url, edit: '1', history_id: this.output.history_id, project_id: this.currentProjectId || null });
                const items = Array.isArray(data.items) ? data.items : [data];
                items.forEach((it) => this.addGen({ id: it.generation_id, type: 'image', status: it.status, model: it.model, provider: it.provider, media_url: it.media_url, error: it.error, credits_cost: 1, prompts_history_id: it.prompts_history_id, created_at: 'Vừa gửi' }));
                if (items.length) { this.previewId = items[0].generation_id; Alpine.store('toast').show('Đang phẫu thuật ảnh… #' + items[0].generation_id); }
            } catch (e) { Alpine.store('toast').show(e.message || String(e), 'error'); }
            finally { this.editSurging = false; }
        },
        // ===== Thay Đổi Người Mẫu (Click-to-Swap) =====
        openLookbook(item) {
            if (!item || !item.id) return;
            this.cleanCanvas(true); this.canvasImg = '';
            if (!this.generations.some(x => x.id === item.id)) {
                this.generations.unshift({ id: item.id, type: 'image', status: 'completed', model: 'swap', provider: 'swap', media_url: item.url, credits_cost: 1, created_at: 'Phiên bản' });
            }
            this.previewId = item.id;
        },
        async loadStylistTypes() {
            if (this.stylistTypes.length) return;
            try { const d = await (await fetch('/studio/stylist/types', { headers: { Accept: 'application/json' } })).json(); this.stylistTypes = (d && d.types) || []; } catch (e) {}
        },
        openStylist() {
            this.stylistOpen = true; this.stylistType = ''; this.stylistHistory = []; this.stylistStep = null; this.stylistMessages = [];
            if (!this.stylistTypes.length) {
                fetch('/studio/stylist/types', { headers: { Accept: 'application/json' } }).then(r => r.json()).then(d => { this.stylistTypes = (d && d.types) || []; }).catch(() => {});
            }
        },
        closeStylist() { this.stylistOpen = false; this.stylistType = ''; this.stylistHistory = []; this.stylistStep = null; this.stylistMessages = []; },
        async startStylist(id) {
            this.stylistFlash = id; setTimeout(() => { this.stylistFlash = ''; }, 450);
            this.stylistType = id; this.stylistHistory = []; this.stylistStep = null; this.stylistQuestions = []; this.stylistAnswers = {}; this.stylistPromptEn = ''; this.stylistPromptVi = ''; this.stylistPromptLang = 'en'; this.stylistSummary = '';
            this.stylistLoading = true;
            try { const d = await this.api('/studio/stylist/cluster', { type: id }); this.stylistQuestions = (d && d.questions) || []; }
            catch (e) { Alpine.store('toast').show(e.message || 'Lỗi tải cụm câu hỏi.', 'error'); }
            finally { this.stylistLoading = false; }
        },
        setStylistAnswer(key, val) { if (val) { this.stylistAnswers[key] = val; } },
        async submitStylist() {
            const answered = Object.values(this.stylistAnswers).filter(Boolean);
            if (!answered.length) { Alpine.store('toast').show('Chọn ít nhất một câu trả lời.', 'error'); return; }
            this.stylistLoading = true;
            try { const d = await this.api('/studio/stylist/prompt', { type: this.stylistType, answers: this.stylistAnswers }); this.stylistPromptEn = d.prompt_en; this.stylistPromptVi = d.prompt_vi; this.stylistPromptLang = 'en'; this.stylistSummary = ''; }
            catch (e) { Alpine.store('toast').show(e.message || 'Lỗi tạo prompt.', 'error'); }
            finally { this.stylistLoading = false; }
        },
        async stylistNext(history) {
            this.stylistLoading = true;
            try {
                const d = await this.api('/studio/stylist', { type: this.stylistType, history });
                this.stylistStep = d;
                this.$nextTick(() => { const el = this.$refs.stylistChat; if (el) { el.scrollTo({ top: el.scrollHeight, behavior: 'smooth' }); } });
            } catch (e) { Alpine.store('toast').show(e.message || 'Lỗi thuật sỹ.', 'error'); }
            finally { this.stylistLoading = false; }
        },
        async pickStylistOption(answer) {
            if (!answer) return;
            this.stylistFlash = answer; setTimeout(() => { this.stylistFlash = ''; }, 450);
            this.stylistMessages = this.stylistMessages.concat([{ role: 'stylist', text: (this.stylistStep && this.stylistStep.question) || '' }, { role: 'user', text: answer }]);
            this.stylistHistory = this.stylistHistory.concat([{ label: String((this.stylistStep && this.stylistStep.question) || '').slice(0, 90), answer }]);
            await this.stylistNext(this.stylistHistory);
        },
        async skipStylist() {
            const q = (this.stylistStep && this.stylistStep.question) || '';
            this.stylistMessages = this.stylistMessages.concat([{ role: 'stylist', text: q }, { role: 'user', text: 'Bỏ qua ⏭' }]);
            await this.stylistNext(this.stylistHistory);
        },
        submitCustomStylist() {
            const t = (this.stylistCustom || '').trim();
            if (!t) return;
            this.stylistCustom = '';
            this.pickStylistOption(t);
        },
        applyStylistPrompt() {
            const p = this.stylistPromptEn || (this.stylistStep && this.stylistStep.prompt);
            if (!p) { Alpine.store('toast').show('Chưa có prompt hoàn chỉnh.', 'error'); return; }
            this.output.image_prompt_en = p;
            Alpine.store('toast').show('Đã áp dụng prompt từ thuật sỹ vào Bước 1.');
            this.closeStylist();
        },
        clearSwap() { this.swapModelIds = []; this.swapPoseIds = []; this.swapDesign = ''; this.lookbook = []; this.lookbookOpen = false; Alpine.store('toast').show('Đã xoá lựa chọn khuôn mặt & dáng.', 'info'); },
        toggleSwapModel(id) { const i = this.swapModelIds.indexOf(id); if (i >= 0) this.swapModelIds.splice(i, 1); else this.swapModelIds.push(id); },
        toggleSwapPose(id) { const i = this.swapPoseIds.indexOf(id); if (i >= 0) this.swapPoseIds.splice(i, 1); else this.swapPoseIds.push(id); },
        async loadSwapAssets() {
            try {
                const res = await fetch('/studio/assets', { headers: { Accept: 'application/json' } });
                const d = await res.json().catch(() => ({}));
                (d.items || []).forEach((a) => {
                    if (a.type === 'model') this.swapModels.push({ id: 'asset-' + a.id, name: a.name, img: a.path, assetId: a.id });
                    else this.swapPoses.push({ id: 'asset-' + a.id, name: a.name, img: a.path, sk: 'custom pose', assetId: a.id });
                });
            } catch (e) {}
        },
        async addSwapAsset(type, e) {
            const f = e.target.files && e.target.files[0];
            if (!f) return;
            const name = (type === 'model' ? 'Khuôn mặt ' : 'Dáng ') + 'mới';
            try {
                const fd = new FormData(); fd.append('type', type); fd.append('name', name); fd.append('image', f);
                const up = await this.upload('/studio/assets', fd);
                const item = { id: 'asset-' + up.id, name: up.name, img: up.path, assetId: up.id };
                if (type === 'model') this.swapModels.push(item); else this.swapPoses.push({ ...item, sk: 'custom pose' });
                Alpine.store('toast').show('Đã thêm ' + (type === 'model' ? 'khuôn mặt' : 'dáng') + ' mới.');
            } catch (err) { Alpine.store('toast').show(err.message, 'error'); }
            e.target.value = '';
        },
        async removeSwapAsset(item) {
            if (!item || !item.assetId) return;
            try { await fetch('/studio/assets/' + item.assetId, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || '', Accept: 'application/json' } }); } catch (e) {}
            this.swapModels = this.swapModels.filter(x => x.id !== item.id);
            this.swapPoses = this.swapPoses.filter(x => x.id !== item.id);
            this.swapModelIds = this.swapModelIds.filter(x => x !== item.id);
            this.swapPoseIds = this.swapPoseIds.filter(x => x !== item.id);
            Alpine.store('toast').show('Đã xoá khỏi thư viện.', 'info');
        },
        openSwap() {
            const img = (this.editSource && this.editSource.url) || (this.preview && this.preview.media_url);
            if (!img) { Alpine.store('toast').show('Chọn một ảnh 2D (kết quả) trước.', 'error'); return; }
            this.swapDesign = img; if (!this.swapModelIds.length) this.swapModelIds = ['model01']; if (!this.swapPoseIds.length) this.swapPoseIds = ['pose01']; this.swapOpen = true;
        },
        async runSwap() {
            if (!this.swapDesign || this.swapLoading) return;
            if (!this.swapModelIds.length || !this.swapPoseIds.length) { Alpine.store('toast').show('Chọn ít nhất 1 khuôn mặt + 1 dáng.', 'error'); return; }
            this.swapLoading = true; this.swapOpen = false;
            const jobs = [];
            for (const m of this.swapModelIds) for (const p of this.swapPoseIds) jobs.push({ m, p });
            try {
                for (const job of jobs) {
                    const res = await fetch('/studio/swap-model', { method: 'POST', headers: { 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || '', 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ image: this.swapDesign, model_id: job.m, pose_id: job.p, background: this.swapBackground, texture: this.texture }) });
                    await new Promise(r => setTimeout(r, 1500)); // nghỉ 1.5s giữa các lần gọi (tránh rate-limit 429)
                    const d = await res.json().catch(() => ({}));
                    if (!res.ok) { Alpine.store('toast').show(d.message || 'Thay đổi người mẫu thất bại.', 'error'); continue; }
                    if (d.media_url) { // fallback qwen-edit -> hoàn tất ngay
                        this.addGen({ id: d.generation_id, type: 'image', status: 'completed', model: d.provider || 'qwen', provider: d.provider || 'qwen', media_url: d.media_url, credits_cost: 1 });
                        this.lookbook.push({ id: d.generation_id, modelId: job.m, poseId: job.p, url: d.media_url });
                    } else if (d.task_id) {
                        this.addGen({ id: d.generation_id, type: 'image', status: 'pending', model: 'tryon', provider: 'tryon', media_url: null, credits_cost: 1 });
                        this.lookbook.push({ id: d.generation_id, modelId: job.m, poseId: job.p, url: null });
                        this.pollSwap(d.task_id, d.generation_id);
                    }
                }
                const last = this.lookbook[this.lookbook.length - 1];
                if (last && last.id) { this.previewId = last.id; }
                this.lookbookOpen = true;
                Alpine.store('toast').show('Đã tạo ' + jobs.length + ' phiên bản người mẫu.');
            } catch (e) { Alpine.store('toast').show(e.message, 'error'); }
            finally { this.swapLoading = false; }
        },
        pollSwap(taskId, genId) {
            const t = setInterval(async () => {
                try {
                    const res = await fetch('/studio/swap-status/' + encodeURIComponent(taskId), { headers: { Accept: 'application/json' } });
                    const d = await res.json().catch(() => ({}));
                    if (d.status === 'completed' && d.media_url) {
                        clearInterval(t);
                        this.addGen({ id: genId, type: 'image', status: 'completed', model: 'tryon', provider: 'tryon', media_url: d.media_url, credits_cost: 1, created_at: 'Đã thử đồ' });
                        this.previewId = genId;
                        const lb = this.lookbook.find(x => x.id === genId); if (lb) lb.url = d.media_url;
                        Alpine.store('toast').show('Đã thử đồ lên người mẫu.');
                    } else if (d.status === 'failed') {
                        clearInterval(t);
                        this.addGen({ id: genId, status: 'failed', error: d.message || 'Thất bại' });
                        Alpine.store('toast').show(d.message || 'Thay đổi người mẫu thất bại.', 'error');
                    }
                } catch (e) {}
            }, 3000);
        },
        // Mở popup sửa prompt tiếng Việt: dịch prompt EN hiện tại sang VI.
        async openTranslateVi() {
            const text = (this.output.image_prompt_en || '').trim();
            if (!text) { Alpine.store('toast').show('Chưa có prompt để dịch.', 'error'); return; }
            this.translateViOpen = true;
            // Bản tiếng Việt đã được dịch âm thầm & lưu sẵn -> mở tức thì; chỉ dịch bổ sung khi chưa có.
            if (!this.viPrompt) {
                this.translating = true;
                try { const d = await this.api('/studio/translate', { text, direction: 'vi' }); this.viPrompt = d.text || text; this.translateMeta = { provider: d.provider, model: d.model }; }
                catch (e) { this.viPrompt = text; Alpine.store('toast').show(e.message, 'error'); }
                finally { this.translating = false; }
            }
        },
        // Xong: dịch ngược VI -> EN và ghi đè prompt hiện tại.
        async saveTranslateVi() {
            if (!this.viPrompt.trim()) { Alpine.store('toast').show('Prompt tiếng Việt trống.', 'error'); return; }
            // Hành vi đúng: ghi đè bản tiếng Việt đã sửa vào ô nhập prompt rồi thoát — KHÔNG dịch lại.
            this._skipTranslate = true;
            this.output.image_prompt_en = this.viPrompt.trim();
            Alpine.store('toast').show('Đã cập nhật prompt.');
            this.translateViOpen = false;
        },
        cleanCanvas(silent = false) {
            this.previewId = null; this.selectedImageId = null; this.videoSourceId = null; this.viewGen = null;
            this.palette = []; this.pan = { x: 0, y: 0 }; this.zoom = 1;
            if (! silent) { Alpine.store('toast').show('Đã dọn canvas — bỏ ảnh/video đang xem & các nguồn đang dùng.', 'info'); }
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
            if (!this.output.image_prompt_en.trim() || this.loading) return;
            this.loading = true;
            try { const data = await this.api('/studio/ideate', { idea: this.output.image_prompt_en, preset_ids: this.presetIds, creative_level: this.creativeLevel }); this.output.image_prompt_en = data.image_prompt_en; this.output.video_prompt_en = data.video_prompt_en; this.output.history_id = data.history_id; if (data.creative_level) this.creativeLevel = Number(data.creative_level); }
            catch (e) { Alpine.store('toast').show(e.message, 'error'); }
            finally { this.loading = false; }
        },

        async generateImage() {
            if (!this.output.image_prompt_en || this.generating) return;
            this.generating = true;
            const tmpId = 'tmp-' + Date.now();
            this.addGen({ id: tmpId, type: 'image', status: 'processing', model: '', provider: this.imgProvider || '', media_url: null, error: null, credits_cost: 1, created_at: 'Đang tạo' });
            try {
                const data = await this.api('/studio/generate', { prompt: this.output.image_prompt_en, resolution: this.imageRes, ratio: this.imageRatio, history_id: this.output.history_id, project_id: this.currentProjectId || null, variants: Number(this.variantCount) || 1 });
                this.generations = this.generations.filter(g => g.id !== tmpId);
                const items = Array.isArray(data.items) ? data.items : [data];
                items.forEach((it) => {
                    this.addGen({ id: it.generation_id, type: 'image', status: it.status, model: it.model, provider: it.provider, media_url: it.media_url, error: it.error, credits_cost: 1, prompts_history_id: it.prompts_history_id, created_at: 'Vừa gửi' });
                });
                if (items.length) this.previewId = items[0].generation_id;
                this.creditsLeft = data.credits_left != null ? data.credits_left : this.creditsLeft;
                const first = items[0];
                if (first && first.status === 'completed') { Alpine.store('toast').show('Đã tạo xong ' + items.length + ' biến thể.'); if (this.isMobile) this.step = 2; }
                items.forEach(it => this.maybePoll(it.generation_id, it.status));
            } catch (e) {
                this.generations = this.generations.filter(g => g.id !== tmpId);
                Alpine.store('toast').show(e.message || 'Lỗi.', 'error');
            } finally { this.generating = false; }
        },

        async renderVideo() {
            if (!this.videoSourceId || this.videoBusy) return;
            this.videoBusy = true;
            try { const src = this.generations.find(g => g.id === this.videoSourceId); const camera = this.videoScene || 'slow tracking shot'; let prompt = this.output.video_prompt_en || this.output.image_prompt_en || (src && src.prompt) || ''; if (prompt && !this.output.video_prompt_en) { prompt = 'Cinematic fashion catwalk: ' + prompt + ', dynamic fabric motion, professional fashion video.'; } const data = await this.api('/studio/video', { prompt: prompt || 'a fashion model walking on a runway, cinematic fashion catwalk, dynamic fabric motion, professional fashion video', base_image: src ? src.media_url : '', camera, model: (this.videoModels.find(m => Number(m.id) === Number(this.videoModel)) || {}).model || null, model_registry_id: this.videoModel || null, provenance: 'registry', resolution: this.videoRes, duration: this.videoDuration, history_id: this.output.history_id, project_id: this.currentProjectId || null }); this.addGen({ id: data.generation_id, type: 'video', status: data.status, model: data.model, provider: data.provider, media_url: data.media_url, error: data.error, credits_cost: 10, created_at: 'Vừa gửi' }); this.creditsLeft = data.credits_left; this.maybePoll(data.generation_id, data.status); if (data.status === 'completed') { Alpine.store('toast').show('Đã render xong video #' + data.generation_id); this.haptic(40); if (this.isMobile) this.step = 2; } }
            catch (e) { Alpine.store('toast').show(e.message, 'error'); }
            finally { this.videoBusy = false; }
        },

        async refine() {
            if (!this.selectedImageId || !this.refinePrompt.trim() || this.refining) return;
            this.refining = true;
            try { const data = await this.api('/studio/generations/' + this.selectedImageId + '/inpaint', { prompt: this.refinePrompt, preserve_background: this.preserveBg, preserve_face: this.preserveFace }); this.addGen({ id: data.generation_id, type: 'image', status: data.status, model: data.model, provider: data.provider, media_url: data.media_url, error: data.error, credits_cost: 1, created_at: 'Vừa gửi' }); this.creditsLeft = data.credits_left; this.refinePrompt = ''; this.maybePoll(data.generation_id, data.status); }
            catch (e) { Alpine.store('toast').show(e.message, 'error'); }
            finally { this.refining = false; }
        },

        async cancelGeneration(g) {
            if (!this.isActive(g.status)) return;
            try { const data = await this.api('/studio/generations/' + g.id + '/cancel', {}); g.status = 'cancelled'; this.creditsLeft = Number(this.creditsLeft) + Number(g.credits_cost || 0); Alpine.store('toast').show('Đã dừng nhiệm vụ #' + g.id); }
            catch (e) { Alpine.store('toast').show(e.message, 'error'); }
        },

        async removeGeneration(g) {
            try { await this.del('/studio/generations/' + g.id); this.generations = this.generations.filter(x => x.id !== g.id); if (this.selectedImageId === g.id) this.selectedImageId = null; if (this.videoSourceId === g.id) this.videoSourceId = null; if (this.previewId === g.id) this.previewId = null; if (this._timers[g.id]) { clearTimeout(this._timers[g.id]); delete this._timers[g.id]; } Alpine.store('toast').show('Đã xóa nhiệm vụ #' + g.id); }
            catch (e) { Alpine.store('toast').show(e.message, 'error'); }
        },

        async suggestStyle() {
            if ((!this.refFile && !this.refUrl) || this.suggesting) return;
            this.suggesting = true;
            try {
                let data;
                if (this.refFile) {
                    const form = new FormData(); form.append('image', this.refFile); form.append('creative_level', this.creativeLevel);
                    data = await this.upload('/studio/suggest', form);
                } else {
                    data = await this.api('/studio/suggest', { reference_url: this.refUrl, creative_level: this.creativeLevel });
                }
                this.suggestResult = data;
                if (data.image_prompt_en) this.output.image_prompt_en = data.image_prompt_en;
                if (data.video_prompt_en) this.output.video_prompt_en = data.video_prompt_en;
                if (data.creative_level) this.creativeLevel = Number(data.creative_level);
                Alpine.store('toast').show(data.preset_ids && data.preset_ids.length ? 'Đã gợi ý prompt từ ảnh (AI).' : 'Đã gợi ý prompt từ ảnh.');
            } catch (e) { Alpine.store('toast').show(e.message, 'error'); }
            finally { this.suggesting = false; }
        },


        addGen(gen) { const existing = this.generations.find(g => g.id === gen.id); if (existing) Object.assign(existing, gen); else { gen._t0 = Date.now(); this.generations.unshift(gen); } this.previewId = gen.id; if (gen.status === 'completed') { this.loadPalette(gen.id); } else if (this.isActive(gen.status)) { this.poll(gen.id); } this.syncLatest(); },
        async syncLatest() { try { const res = await fetch('/studio/latest', { headers: { Accept: 'application/json' } }); const d = await res.json(); if (d && Array.isArray(d.items)) { const ids = new Set(d.items.map(x => x.id)); const localOnly = this.generations.filter(x => !ids.has(x.id) && x._t0); this.generations = [...localOnly, ...d.items]; } } catch (e) {} },
        selectImage(g) { if (g.type !== 'image' || g.status !== 'completed') return; this.selectedImageId = g.id; this.previewId = g.id; this.editSource = { url: g.media_url, label: 'Ảnh nguồn #' + g.id, generationId: g.id }; this.editSourceTmp = g.media_url; this.canvasImg = ''; Alpine.store('toast').show('Đã chọn ảnh #' + g.id + ' làm nguồn Chỉnh sửa.'); },
        selectVideo(g) { if (g.type !== 'image' || g.status !== 'completed') return; this.videoSourceId = g.id; this.previewId = g.id; Alpine.store('toast').show('Đã chọn ảnh #' + g.id + ' làm nguồn Video.'); },

        statusLabel(s) { return { pending:'Đang chờ', processing:'Đang tạo', completed:'Hoàn tất', failed:'Lỗi', cancelled:'Đã hủy' }[s] || s; },
        isActive(s) { return s === 'pending' || s === 'processing'; },
        badgeClass(s) { return { completed:'bg-brand-600 text-white', failed:'bg-red-100 text-red-600', cancelled:'bg-cream-200 text-ink-500' }[s] || 'bg-amber-100 text-amber-700'; },
        statusText(g) {
            if (g.status === 'failed') return 'Lỗi: ' + (g.error || 'không xác định');
            if (g.status === 'cancelled') return 'Đã hủy';
            const el = (g._t0 && this.isActive(g.status)) ? ' (' + this.fmtElapsed(this.now - g._t0) + ')' : '';
            if (g.type !== 'video') return 'Đang tạo ảnh…' + el;
            const ph = (g.meta && g.meta.video_phase) || '';
            if (ph === 'pending') return 'Đang gửi tác vụ…' + el;
            if (ph === 'running') return 'Trong hàng đợi nhà cung cấp…' + el;
            if (ph === 'succeeded') return 'Hoàn tất';
            if (ph === 'failed') return 'Lỗi tạo video';
            return 'Đang tạo video…' + el;
        },
        haptic(ms = 15) { if (navigator.vibrate) navigator.vibrate(ms); },
        fmtElapsed(ms) {
            ms = Number(ms) || 0;
            if (!ms) return '';
            const s = Math.max(0, Math.round(ms / 1000));
            return s < 60 ? s + 's' : Math.floor(s / 60) + 'm ' + (s % 60) + 's';
        },
        genInfoLine(g) {
            if (!g) return '';
            const parts = [];
            if (g.provider) parts.push(g.provider);
            if (g.model) parts.push(g.model);
            if (g.type === 'video' && g.duration) parts.push('⏱ ' + g.duration + 's');
            if (g.ratio) parts.push(g.ratio);
            if (g.resolution) parts.push(g.resolution);
            if (g.elapsed_ms) parts.push('tạo ' + this.fmtElapsed(g.elapsed_ms));
            if (g.meta && g.meta.creative_level) parts.push('sáng tạo ' + g.meta.creative_level + '/10');
            if (g.created_at) parts.push(g.created_at);
            return parts.join(' · ');
        },
        gTitle(g) {
            if (!g) return '';
            const t = g.type === 'video' ? 'VIDEO' : 'ẢNH';
            const md = this.genInfoLine(g);
            const p = g.prompt ? 'Prompt: ' + g.prompt : '';
            return t + (md ? ' — ' + md : '') + (p ? String.fromCharCode(10) + p : '');
        },

        maybePoll(id, status) { if (['completed','failed','cancelled'].includes(status)) return; this.poll(id); },

        poll(id) {
            if (this._timers[id]) return;
            // Single-flight: never fire overlapping requests (a superseded poll request could abort
            // the in-flight lazy job). The provider status is authoritative; the backend heals stuck
            // jobs (show()) so polling always resolves to a terminal status.
            const tick = async () => {
                try {
                    const res = await fetch('/studio/generations/' + id, { headers: { Accept: 'application/json' } });
                    if (!res.ok) { clearTimeout(this._timers[id]); delete this._timers[id]; return; }
                    const g = await res.json();
                    const item = this.generations.find(x => x.id === Number(g.id));
                    if (item) { item.status = g.status; item.media_url = g.media_url; item.error = g.error; item.model = g.model; item.provider = g.provider; }
                    if (['completed','failed','cancelled'].includes(g.status)) { clearTimeout(this._timers[id]); delete this._timers[id]; return; }
                } catch (e) { clearTimeout(this._timers[id]); delete this._timers[id]; return; }
                this._timers[id] = setTimeout(tick, 500);
            };
            this._timers[id] = setTimeout(tick, 2000);
        },
    }));
});
</script>
@endpush
@endsection