@extends('layouts.studio')

@section('title', 'Thư viện · Trillfa Studio')

@php
    $items = $generations->map(fn($g) => [
        'id' => $g->id,
        'type' => $g->type,
        'status' => $g->status,
        'media_url' => $g->media_url,
        'model' => $g->model,
        'provider' => $g->provider,
        'prompt' => $g->prompt,
        'credits_cost' => $g->credits_cost,
        'project' => $g->project?->name,
        'created_at' => $g->created_at?->format('d/m/Y H:i'),
        'duration' => $g->duration, 'resolution' => $g->resolution, 'ratio' => $g->ratio,
        'elapsed_ms' => $g->elapsed_ms, 'meta' => $g->meta,
    ])->values();
@endphp

@section('content')
<div x-data="{ sel:null, q:'', items: {{ Js::from($items) }}, total: {{ $generations->total() }}, lbZoom:1, lbPan:{x:0,y:0}, _lbDrag:null, open(i){ this.sel=i; this.lbZoom=1; this.lbPan={x:0,y:0}; document.body.classList.add('overflow-hidden'); }, close(){ this.sel=null; document.body.classList.remove('overflow-hidden'); }, lbZin(){ this.lbZoom=Math.min(8, +(this.lbZoom+0.5).toFixed(2)); }, lbZout(){ this.lbZoom=Math.max(0.6, +(this.lbZoom-0.5).toFixed(2)); }, lbReset(){ this.lbZoom=1; this.lbPan={x:0,y:0}; }, onLbWheel(e){ e.preventDefault(); const d=e.deltaY>0?-0.5:0.5; this.lbZoom=Math.min(8, Math.max(0.6, +(this.lbZoom+d).toFixed(2))); }, lbStartPan(e){ this._lbDrag={x:e.clientX,y:e.clientY,px:this.lbPan.x,py:this.lbPan.y}; }, lbMovePan(e){ if(!this._lbDrag) return; this.lbPan.x=this._lbDrag.px+(e.clientX-this._lbDrag.x); this.lbPan.y=this._lbDrag.py+(e.clientY-this._lbDrag.y); }, lbEndPan(){ this._lbDrag=null; }, fmtElapsed(ms){ ms=Number(ms)||0; if(!ms) return ''; const s=Math.max(0,Math.round(ms/1000)); return s<60? s+'s' : Math.floor(s/60)+'m '+(s%60)+'s'; }, genMeta(i){ const p=[]; if(i.provider)p.push(i.provider); if(i.model)p.push(i.model); if(i.type==='video'&&i.duration)p.push('⏱ '+i.duration+'s'); if(i.ratio)p.push(i.ratio); if(i.resolution)p.push(i.resolution); if(i.elapsed_ms)p.push('tạo '+this.fmtElapsed(i.elapsed_ms)); if(i.created_at)p.push(i.created_at); return p.join(' · '); }, async del(item){ try { const res = await fetch('/studio/generations/' + item.id, { method:'DELETE', headers:{ 'X-CSRF-TOKEN':(document.querySelector('meta[name=csrf-token]')||{}).content||'', Accept:'application/json' } }); const d = await res.json().catch(()=>({})); if(!res.ok) throw new Error(d.message||'Lỗi khi xóa.'); this.items = this.items.filter(x => x.id !== item.id); this.total = Math.max(0, this.total - 1); this.close(); Alpine.store('toast').show(d.message || 'Đã xóa.'); } catch(e){ Alpine.store('toast').show(e.message, 'error'); } } }">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-2xl font-bold text-ink-900">Thư viện</h1>
        <span class="text-sm text-ink-500"><span x-text="total"></span> mục</span>
    </div>

    <!-- Filters -->
    <form method="GET" action="{{ route('studio.library') }}" class="mb-6 card flex flex-wrap items-end gap-3 p-4">
        <div class="w-40">
            <label class="label">Loại</label>
            <select name="type" class="input" @change="this.form.submit()">
                <option value="">Tất cả</option>
                <option value="image" @selected(request('type')==='image')>Ảnh</option>
                <option value="video" @selected(request('type')==='video')>Video</option>
            </select>
        </div>
        <div class="w-52">
            <label class="label">Dự án</label>
            <select name="project_id" class="input" @change="this.form.submit()">
                <option value="">Tất cả dự án</option>
                @foreach($projects as $p)
                    <option value="{{ $p->id }}" @selected((string)request('project_id')===(string)$p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex-1 min-w-[180px]">
            <label class="label">Tìm theo prompt</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Nhập từ khóa…" class="input">
        </div>
        <button type="submit" class="btn-brand">Lọc</button>
        <a href="{{ route('studio.library') }}" class="btn-outline btn-sm">Đặt lại</a>
    </form>

    <!-- Grid -->
    @if($items->isEmpty())
        <div class="card flex min-h-[280px] flex-col items-center justify-center p-10 text-center text-ink-500">
            <p>Chưa có ảnh / video nào. Tạo trong <a class="link" href="{{ route('studio.index') }}">Studio</a>.</p>
        </div>
    @else
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-4">
            <template x-for="i in items" :key="i.id">
                <div class="card overflow-hidden cursor-pointer" @click="open(i)">
                    <div class="relative block w-full">
                        <template x-if="i.status === 'completed' && i.media_url && i.type === 'image'">
                            <img :src="i.media_url" class="aspect-[3/4] w-full object-cover" onerror="this.src='/images/placeholder.svg'">
                        </template>
                        <template x-if="i.status === 'completed' && i.media_url && i.type === 'video'">
                            <video :src="i.media_url" class="aspect-[3/4] w-full object-cover" muted playsinline preload="metadata"></video>
                        </template>
                        <template x-if="i.status !== 'completed'">
                            <div class="grid aspect-[3/4] w-full place-items-center bg-cream-100">
                                <p class="px-3 text-xs text-ink-500" x-text="i.status === 'failed' ? 'Lỗi' : (i.status === 'cancelled' ? 'Đã hủy' : 'Đang xử lý…')"></p>
                            </div>
                        </template>
                        <span class="absolute left-2 top-2 badge" :class="i.status==='completed' ? 'bg-brand-600 text-white' : (i.status==='failed' ? 'bg-red-100 text-red-600' : (i.status==='cancelled' ? 'bg-cream-200 text-ink-500' : 'bg-amber-100 text-amber-700'))" x-text="i.status==='completed'?'Hoàn tất':(i.status==='failed'?'Lỗi':(i.status==='cancelled'?'Đã hủy':'Đang tạo'))"></span>
                        <span x-show="i.type==='video'" class="absolute right-2 top-2 grid h-8 w-8 place-items-center rounded-full bg-ink-900/70 text-white" title="Video">▶</span>
                        <span class="absolute right-2 bottom-2 badge text-[9px] font-bold" :class="i.type==='video' ? 'bg-ink-900/85 text-white' : 'bg-cream-200 text-ink-700'" x-text="i.type==='video' ? (i.duration ? 'VIDEO '+i.duration+'s' : 'VIDEO') : 'ẢNH'"></span>
                    </div>
                    <div class="flex items-center justify-between gap-1 border-t border-cream-200 px-3 py-2 text-xs text-ink-500">
                        <span class="truncate" x-text="i.created_at || ''"></span>
                        <span class="truncate" x-show="i.project" x-text="i.project"></span>
                    </div>
                </div>
            </template>
        </div>
        <div class="mt-8">{{ $generations->links() }}</div>
    @endif

    <!-- Preview modal -->
    <template x-if="sel">
        <div class="fixed inset-0 z-50 flex items-center justify-center overflow-hidden p-4" @wheel.prevent="onLbWheel($event)">
            <div class="absolute inset-0 bg-ink-900/60" @click="close()"></div>
            <div class="relative z-10 w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="relative bg-cream-100">
                    <template x-if="sel.type==='video' && sel.media_url">
                        <video :src="sel.media_url" class="mx-auto max-h-[60vh] w-full object-contain" controls loop muted playsinline></video>
                    </template>
                    <template x-if="sel.type==='image' && sel.media_url">
                        <div class="relative h-[60vh] w-full overflow-hidden bg-cream-100">
                            <img :src="sel.media_url" class="h-full w-full cursor-grab select-none object-contain active:cursor-grabbing"
                                 :style="{ transform: 'translate(' + lbPan.x + 'px, ' + lbPan.y + 'px) scale(' + lbZoom + ')', transformOrigin: 'center' }"
                                 @pointerdown="lbStartPan($event)" @pointermove="lbMovePan($event)" @pointerup="lbEndPan" @pointerleave="lbEndPan"
                                 onerror="this.src='/images/placeholder.svg'">
                            <div class="absolute bottom-3 right-3 z-10 flex items-center gap-1">
                                <button @click="lbZout()" class="grid h-8 w-8 place-items-center rounded-lg border border-cream-200 bg-white/90 text-ink-700 hover:bg-white" title="Thu nhỏ">−</button>
                                <button @click="lbReset()" class="rounded-lg border border-cream-200 bg-white/90 px-2 py-1 text-xs text-ink-700 hover:bg-white" title="Vừa khung">Vừa</button>
                                <button @click="lbZin()" class="grid h-8 w-8 place-items-center rounded-lg border border-cream-200 bg-white/90 text-ink-700 hover:bg-white" title="Phóng to">+</button>
                            </div>
                        </div>
                    </template>
                    <template x-if="sel.status !== 'completed'">
                        <div class="grid min-h-[40vh] place-items-center text-center">
                            <div><p class="text-sm text-ink-500" x-text="sel.status==='failed'?'Lỗi: '+(sel.prompt||''):(sel.status==='cancelled'?'Đã hủy':'Đang xử lý…')"></p></div>
                        </div>
                    </template>
                    <button @click="close()" class="absolute right-2 top-2 grid h-8 w-8 place-items-center rounded-full bg-ink-900/70 text-white">×</button>
                    <span class="absolute left-2 top-2 badge" :class="sel.status==='completed'?'bg-brand-600 text-white':(sel.status==='failed'?'bg-red-100 text-red-600':'bg-amber-100 text-amber-700')" x-text="sel.status==='completed'?'Hoàn tất':(sel.status==='failed'?'Lỗi':'Đang tạo')"></span>
                </div>
                <div class="space-y-3 p-5">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="font-display text-lg font-semibold text-ink-900">Mục #<span x-text="sel.id"></span></h2>
                                <span class="badge text-[10px] font-bold" :class="sel.type==='video' ? 'bg-ink-900 text-white' : 'bg-cream-200 text-ink-700'" x-text="sel.type==='video' ? '▶ VIDEO' : 'ẢNH'"></span>
                            </div>
                            <p class="mt-1 text-xs text-ink-500" x-text="genMeta(sel)"></p>
                        </div>
                        <span class="badge bg-cream-200 text-ink-700"><span x-text="sel.credits_cost || 0"></span> token</span>
                    </div>
                    <div x-show="sel.prompt"><p class="text-xs font-semibold text-ink-700">Prompt:</p><p class="mt-1 rounded-xl bg-cream-100 p-3 text-xs text-ink-500" x-text="sel.prompt"></p></div>
                    <div class="flex flex-wrap gap-2">
                        <a :href="'/studio/generations/' + sel.id + '/download'" class="btn-brand btn-sm" x-show="sel.media_url">Tải xuống</a>
                        <a :href="'/studio?gen=' + sel.id" class="btn-outline btn-sm">Mở trong Studio</a>
                        <button type="button" @click="del(sel)" class="btn-outline btn-sm text-red-600">Xóa</button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
@endsection