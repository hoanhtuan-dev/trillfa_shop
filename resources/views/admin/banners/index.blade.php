@extends('layouts.admin')

@section('title', 'Banner')
@section('page_title', 'Banner')

@section('content')
<div class="grid gap-6 lg:grid-cols-2">
    <div class="space-y-4">
        @forelse($banners as $banner)
            <div class="card flex gap-4 p-4">
                <img src="{{ $banner->image_url ?: asset('images/placeholder.svg') }}" class="h-20 w-32 shrink-0 rounded-xl object-cover" alt="">
                <div class="flex-1">
                    <p class="font-medium text-ink-900">{{ $banner->title }}</p>
                    <p class="text-xs text-ink-500">{{ $banner->position }} · Thứ tự {{ $banner->sort_order }}</p>
                    <div class="mt-2 flex items-center gap-2">
                        <form method="POST" action="{{ route('admin.banners.toggle', $banner) }}">@csrf<button type="submit" class="badge {{ $banner->is_active ? 'bg-brand-100 text-brand-700' : 'bg-red-100 text-red-600' }}">{{ $banner->is_active ? 'Bật' : 'Tắt' }}</button></form>
                        <form method="POST" action="{{ route('admin.banners.destroy', $banner) }}" onsubmit="return confirm('Xóa banner?')">@csrf @method('DELETE')<button type="submit" class="text-xs text-red-500">Xóa</button></form>
                    </div>
                </div>
            </div>
        @empty
            <div class="card p-10 text-center text-ink-500">Chưa có banner nào.</div>
        @endforelse
    </div>

    <div class="card p-6">
        <h2 class="mb-4 font-display text-lg font-semibold text-ink-900">Thêm banner</h2>
        <form method="POST" action="{{ route('admin.banners.store') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div><label class="label">Tiêu đề *</label><input type="text" name="title" class="input" required></div>
            <div><label class="label">Phụ đề</label><input type="text" name="subtitle" class="input"></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label">Nút bấm</label><input type="text" name="button_text" class="input"></div>
                <div><label class="label">Liên kết</label><input type="text" name="button_link" class="input"></div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label">Vị trí</label>
                    <select name="position" class="input"><option value="hero">Hero</option><option value="secondary">Secondary</option></select>
                </div>
                <div><label class="label">Thứ tự</label><input type="number" name="sort_order" value="0" class="input"></div>
            </div>
            <div x-data="imageUploader({ existing: null })">
                <label class="label">Ảnh</label>
                <button type="button" @click="$refs.input.click()" class="flex w-full flex-col items-center justify-center rounded-xl border-2 border-dashed border-cream-300 p-5 text-center transition hover:border-brand-500">
                    <svg class="mb-2 h-7 w-7 text-ink-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A1.5 1.5 0 0021.75 19.5V4.5A1.5 1.5 0 0020.25 3H3.75A1.5 1.5 0 002.25 4.5v15A1.5 1.5 0 003.75 21z"/></svg>
                    <span class="text-sm text-ink-500">Chọn ảnh banner</span>
                </button>
                <input x-ref="input" type="file" name="image" @change="onChange" class="hidden" accept="image/*">
                <template x-if="hasImage">
                    <div class="relative mt-3 overflow-hidden rounded-xl">
                        <img :src="displaySrc" class="h-32 w-full bg-cream-100 object-cover" alt="">
                        <button type="button" @click="remove()" class="absolute right-2 top-2 grid h-7 w-7 place-items-center rounded-full bg-ink-900/70 text-white hover:bg-red-600">×</button>
                    </div>
                </template>
            </div>
            <label class="flex items-center gap-2 text-sm text-ink-700"><input type="checkbox" name="is_active" value="1" checked class="h-4 w-4 accent-brand-600"> Kích hoạt</label>
            <button type="submit" class="btn-brand">Lưu</button>
        </form>
    </div>
</div>
@endsection