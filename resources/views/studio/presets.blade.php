@extends('layouts.studio')

@section('title', 'Prompt Templates · Trillfa Studio')

@php
    $catLabels = ['fabric'=>'Chất liệu','silhouette'=>'Phom dáng','style'=>'Phong cách','background'=>'Bối cảnh','pose'=>'Dáng đứng','camera'=>'Góc máy','lens'=>'Ống kính','video_scene'=>'Kịch bản quay'];
    $catColors = ['fabric'=>'bg-cream-100 text-ink-700','silhouette'=>'bg-cream-100 text-ink-700','style'=>'bg-brand-50 text-brand-800','background'=>'bg-cream-100 text-ink-700','pose'=>'bg-cream-100 text-ink-700','camera'=>'bg-brand-50 text-brand-800','lens'=>'bg-cream-100 text-ink-700','video_scene'=>'bg-brand-50 text-brand-800'];
@endphp

@section('content')
<div class="mx-auto max-w-5xl">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-display text-2xl font-semibold text-ink-900">Prompt Templates</h1>
            <p class="mt-1 text-sm text-ink-500">Quản lý các mẫu prompt (preset) dùng trong Studio. Mỗi preset là cặp <b>key: value</b> — key hiển thị trên dropdown, value (prompt) tự động được đưa vào câu lệnh khi chọn.</p>
        </div>
        <a href="{{ route('studio.index') }}" class="btn-outline btn-sm">← Studio</a>
    </div>

    <!-- Add form -->
    <form method="POST" action="{{ route('studio.presets.store') }}" class="card mt-6 p-5">
        @csrf
        <h2 class="mb-3 font-display text-base font-semibold text-ink-900">Thêm preset</h2>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="label">Danh mục</label>
                <select name="category" class="input !py-2">
                    @foreach($categories as $cat)<option value="{{ $cat }}" @selected(old('category') === $cat)>{{ $catLabels[$cat] ?? $cat }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="label">Key (nhãn hiển thị)</label>
                <input type="text" name="ui_label" value="{{ old('ui_label') }}" placeholder="VD: Old Money / Classic" class="input !py-2">
            </div>
            <div>
                <label class="label">Thứ tự</label>
                <input type="number" name="sort_order" value="{{ old('sort_order', 0) }}" min="0" class="input !py-2">
            </div>
            <div class="sm:col-span-2 lg:col-span-1">
                <label class="label">&nbsp;</label>
                <button type="submit" class="btn-brand w-full">Thêm</button>
            </div>
        </div>
        <div class="mt-3">
            <label class="label">Value (prompt tiếng Anh)</label>
            <textarea name="prompt_injection" rows="2" class="input !text-xs" placeholder="VD: old money aesthetic, timeless elegance, tailored linen and cashmere, neutral tones…"></textarea>
        </div>
        <div class="mt-3">
            <label class="label">Chú giải (tiếng Việt — hiển thị khi hover / xem thông tin template)</label>
            <textarea name="note" rows="1" class="input !text-xs" placeholder="VD: Chụp Lookbook thương mại, hiển thị tỷ lệ trang phục trung thực."></textarea>
        </div>
        @if($errors->any())<div class="mt-3 rounded-xl bg-red-50 p-3 text-sm text-red-600">{{ $errors->first() }}</div>@endif
    </form>

    <!-- Lists grouped by category -->
    @foreach($categories as $cat)
        <div class="mt-8">
            <div class="mb-3 flex items-center gap-2">
                <span class="badge {{ $catColors[$cat] ?? 'bg-cream-100 text-ink-700' }}">{{ $catLabels[$cat] ?? $cat }}</span>
                <span class="text-xs text-ink-500">{{ ($presets[$cat] ?? collect())->count() }} mẫu</span>
            </div>
            @php($list = $presets[$cat] ?? collect())
            @if($list->isEmpty())
                <p class="text-sm text-ink-500">Chưa có preset nào ở danh mục này.</p>
            @else
                <div class="grid gap-3 md:grid-cols-2">
                    @foreach($list as $p)
                        <form method="POST" action="{{ route('studio.presets.update', $p->id) }}" class="card p-4">
                            @csrf
                            @method('PUT')
                            <div class="grid gap-2">
                                <div class="flex items-center gap-2">
                                    <input type="text" name="ui_label" value="{{ $p->ui_label }}" class="input !py-1.5 text-sm">
                                    <input type="number" name="sort_order" value="{{ $p->sort_order }}" min="0" title="Thứ tự" class="w-20 input !py-1.5 text-sm">
                                </div>
                                <textarea name="prompt_injection" rows="2" class="input !text-xs" placeholder="Giá trị prompt…">{{ $p->prompt_injection }}</textarea>
                                <textarea name="note" rows="1" class="input !text-xs" placeholder="Chú giải tiếng Việt…">{{ $p->note }}</textarea>
                                <div class="flex items-center justify-end gap-2">
                                    <span class="text-[10px] text-ink-500">#{{ $p->id }}</span>
                                    <button type="submit" class="btn-outline btn-sm">Lưu</button>
                                    <button type="submit" form="del-{{ $p->id }}" class="btn-outline btn-sm text-red-600">Xóa</button>
                                </div>
                            </div>
                        </form>
                        <form id="del-{{ $p->id }}" method="POST" action="{{ route('studio.presets.destroy', $p->id) }}" class="hidden">
                            @csrf
                            @method('DELETE')
                        </form>
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach
</div>
@endsection
