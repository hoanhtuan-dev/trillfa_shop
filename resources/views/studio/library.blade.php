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
    ])->values();
@endphp

@section('content')
<div x-data="{ sel:null, q:'', open(i){ this.sel=i; document.body.classList.add('overflow-hidden'); }, close(){ this.sel=null; document.body.classList.remove('overflow-hidden'); } }">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-2xl font-bold text-ink-900">Thư viện</h1>
        <span class="text-sm text-ink-500">{{ $generations->total() }} mục</span>
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
            <template x-for="i in {{ Js::from($items) }}" :key="i.id">
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
                        <span x-show="i.type==='video'" class="absolute right-2 top-2 grid h-6 w-6 place-items-center rounded-full bg-ink-900/70 text-white" title="Video">▶</span>
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
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-ink-900/60" @click="close()"></div>
            <div class="relative z-10 w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="relative bg-cream-100">
                    <template x-if="sel.type==='video' && sel.media_url">
                        <video :src="sel.media_url" class="mx-auto max-h-[60vh] w-full object-contain" controls loop muted playsinline></video>
                    </template>
                    <template x-if="sel.type==='image' && sel.media_url">
                        <img :src="sel.media_url" class="mx-auto max-h-[60vh] w-full object-contain" onerror="this.src='/images/placeholder.svg'">
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
                            <h2 class="font-display text-lg font-semibold text-ink-900">Mục #<span x-text="sel.id"></span></h2>
                            <p class="text-xs text-ink-500" x-text="(sel.provider || '') + ' · ' + (sel.model || '') + ' · ' + (sel.created_at || '')"></p>
                        </div>
                        <span class="badge bg-cream-200 text-ink-700"><span x-text="sel.credits_cost || 0"></span> token</span>
                    </div>
                    <div x-show="sel.prompt"><p class="text-xs font-semibold text-ink-700">Prompt:</p><p class="mt-1 rounded-xl bg-cream-100 p-3 text-xs text-ink-500" x-text="sel.prompt"></p></div>
                    <div class="flex flex-wrap gap-2">
                        <a :href="'/studio/generations/' + sel.id + '/download'" class="btn-brand btn-sm" x-show="sel.media_url">Tải xuống</a>
                        <a href="{{ route('studio.index') }}" class="btn-outline btn-sm">Mở trong Studio</a>
                                                <form method="POST" :action="'/studio/generations/' + sel.id">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-outline btn-sm text-red-600">Xóa</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
@endsection