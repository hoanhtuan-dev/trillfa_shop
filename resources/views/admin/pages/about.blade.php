@extends('layouts.admin')

@section('title', 'Trang Giới thiệu')
@section('page_title', 'Nội dung trang Giới thiệu')

@section('content')
@php
    $aboutImage = setting('about_image');
    $aboutImageUrl = $aboutImage ? asset_image($aboutImage) : null;
@endphp

<form method="POST" action="{{ route('admin.pages.about.update') }}" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-3">
    @csrf
    <div class="lg:col-span-2 space-y-6">
        <div class="card p-6 space-y-4">
            <h2 class="font-display text-lg font-semibold text-ink-900">Nội dung chính</h2>
            <div>
                <label class="label">Tiêu đề (H1) *</label>
                <input type="text" name="about_heading" value="{{ old('about_heading', setting('about_heading', 'Thời trang cho người Việt hiện đại')) }}" class="input" required>
            </div>
            <div>
                <label class="label">Mở đầu (giới thiệu ngắn)</label>
                <div x-data="introEditor" class="overflow-hidden rounded-xl border border-cream-300 bg-white focus-within:border-brand-500 focus-within:ring-4 focus-within:ring-brand-500/10">
                    <div class="flex flex-wrap items-center gap-1 border-b border-cream-200 bg-cream-100 px-2 py-1.5 text-ink-700">
                        <button type="button" @click="exec('bold')" class="grid h-8 w-8 place-items-center rounded-lg hover:bg-white" title="Đậm"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 4h8a4 4 0 01-4 4H6zM6 12h9a4 4 0 01-4 4H6z" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                        <button type="button" @click="exec('italic')" class="grid h-8 w-8 place-items-center rounded-lg hover:bg-white" title="Nghiêng"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5h6M13 19h6M14 5l-4 14" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                        <button type="button" @click="exec('formatBlock', 'h2')" class="px-2 text-sm font-semibold hover:bg-white">H2</button>
                        <button type="button" @click="exec('formatBlock', 'h3')" class="px-2 text-sm font-semibold hover:bg-white">H3</button>
                        <button type="button" @click="exec('insertUnorderedList')" class="grid h-8 w-8 place-items-center rounded-lg hover:bg-white">•</button>
                        <button type="button" @click="exec('insertOrderedList')" class="grid h-8 w-8 place-items-center rounded-lg text-xs hover:bg-white">1.</button>
                        <button type="button" @click="exec('formatBlock', 'blockquote')" class="px-2 text-sm hover:bg-white">❝</button>
                        <button type="button" @click="addLink()" class="grid h-8 w-8 place-items-center rounded-lg hover:bg-white" title="Liên kết"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757" stroke-linecap="round" stroke-linejoin="round"/><path d="M10.81 15.312a4.5 4.5 0 01-1.242-7.244l4.5-4.5a4.5 4.5 0 016.364 6.364l-1.757 1.757" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                        <button type="button" @click="exec('formatBlock', 'p')" class="px-2 text-sm hover:bg-white">¶</button>
                        <button type="button" @click="exec('undo')" class="grid h-8 w-8 place-items-center rounded-lg hover:bg-white"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 016 6v3" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                    </div>
                    <div x-ref="editor" contenteditable @input="sync" class="prose-content min-h-[130px] px-4 py-3 text-sm outline-none"></div>
                    <textarea name="about_intro" x-ref="hidden" class="hidden">{{ old('about_intro', setting('about_intro')) }}</textarea>
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <button type="button" @click="syncToBody()" class="inline-flex items-center gap-1.5 rounded-lg border border-brand-200 bg-brand-50 px-3 py-1.5 text-xs font-semibold text-brand-700 transition hover:border-brand-400 hover:bg-brand-100" title="Ghi Mở đầu thành đoạn mở đầu của Nội dung mở rộng">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                        Đồng bộ vào Nội dung mở rộng
                    </button>
                    <button type="button" @click="pullFromBody()" class="inline-flex items-center gap-1.5 rounded-lg border border-cream-300 bg-white px-3 py-1.5 text-xs font-semibold text-ink-700 transition hover:border-ink-900 hover:text-ink-900" title="Lấy đoạn mở đầu của Nội dung mở rộng làm Mở đầu">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16l-4-4m0 0l4-4m-4 4h18"/></svg>
                        Lấy từ Nội dung mở rộng
                    </button>
                    <span x-show="status === 'synced'" class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2.5 py-1 text-[11px] font-semibold text-brand-700 ring-1 ring-brand-200">
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                        Đã đồng bộ với đoạn mở đầu
                    </span>
                    <span x-show="status === 'pending'" class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-700 ring-1 ring-amber-200">
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                        Cần đồng bộ — ấn “Đồng bộ vào Nội dung mở rộng”
                    </span>
                    <span class="text-xs text-ink-400">Gõ trong "Mở đầu" tự cập nhật đoạn mở đầu của "Nội dung mở rộng (HTML)".</span>
                </div>
            </div>
            <div>
                <label class="label">Nội dung mở rộng (HTML)</label>
                <div x-data="richEditor" class="overflow-hidden rounded-xl border border-cream-300 bg-white focus-within:border-brand-500 focus-within:ring-4 focus-within:ring-brand-500/10">
                    <div class="flex flex-wrap items-center gap-1 border-b border-cream-200 bg-cream-100 px-2 py-1.5 text-ink-700">
                        <button type="button" @click="exec('bold')" class="grid h-8 w-8 place-items-center rounded-lg hover:bg-white" title="Đậm"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 4h8a4 4 0 01-4 4H6zM6 12h9a4 4 0 01-4 4H6z" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                        <button type="button" @click="exec('italic')" class="grid h-8 w-8 place-items-center rounded-lg hover:bg-white" title="Nghiêng"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5h6M13 19h6M14 5l-4 14" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                        <button type="button" @click="exec('formatBlock', 'h2')" class="px-2 text-sm font-semibold hover:bg-white">H2</button>
                        <button type="button" @click="exec('formatBlock', 'h3')" class="px-2 text-sm font-semibold hover:bg-white">H3</button>
                        <button type="button" @click="exec('insertUnorderedList')" class="grid h-8 w-8 place-items-center rounded-lg hover:bg-white">•</button>
                        <button type="button" @click="exec('insertOrderedList')" class="grid h-8 w-8 place-items-center rounded-lg text-xs hover:bg-white">1.</button>
                        <button type="button" @click="exec('formatBlock', 'blockquote')" class="px-2 text-sm hover:bg-white">❝</button>
                        <button type="button" @click="addLink()" class="grid h-8 w-8 place-items-center rounded-lg hover:bg-white" title="Liên kết"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757" stroke-linecap="round" stroke-linejoin="round"/><path d="M10.81 15.312a4.5 4.5 0 01-1.242-7.244l4.5-4.5a4.5 4.5 0 016.364 6.364l-1.757 1.757" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                        <button type="button" @click="exec('formatBlock', 'p')" class="px-2 text-sm hover:bg-white">¶</button>
                        <button type="button" @click="exec('undo')" class="grid h-8 w-8 place-items-center rounded-lg hover:bg-white"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 016 6v3" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                    </div>
                    <div id="about-body-editor" x-ref="editor" contenteditable @input="sync" class="prose-content min-h-[160px] px-4 py-3 text-sm outline-none"></div>
                    <textarea id="about-body-hidden" name="about_body" x-ref="hidden" class="hidden">{{ old('about_body', setting('about_body')) }}</textarea>
                </div>
            </div>
        </div>

        <div class="card p-6">
            <h2 class="mb-4 font-display text-lg font-semibold text-ink-900">3 giá trị cốt lõi</h2>
            <div class="space-y-4">
                @for($i = 1; $i <= 3; $i++)
                    <div class="rounded-xl border border-cream-200 p-4">
                        <label class="label">Giá trị {{ $i }} — Tiêu đề</label>
                        <input type="text" name="about_v{{ $i }}_title" value="{{ old('about_v'.$i.'_title', setting('about_v'.$i.'_title')) }}" class="input mb-3" placeholder="Ví dụ: Tinh gọn & Tối giản">
                        <label class="label">Mô tả</label>
                        <textarea name="about_v{{ $i }}_text" rows="3" class="input" placeholder="Mô tả ngắn về giá trị này...">{{ old('about_v'.$i.'_text', setting('about_v'.$i.'_text')) }}</textarea>
                    </div>
                @endfor
            </div>
        </div>
    </div>

    <div class="space-y-6">
        <div class="card p-6 sticky top-24">
            <button type="submit" class="btn-brand w-full">Lưu nội dung</button>
            <a href="{{ route('page.about') }}" target="_blank" class="btn-ghost mt-2 w-full">Xem trang</a>
        </div>

        <div class="card p-6" x-data="imageUploader({ existing: @js($aboutImageUrl) })">
            <h2 class="mb-4 font-display text-lg font-semibold text-ink-900">Ảnh minh họa</h2>
            <input type="hidden" name="about_image_remove" :value="removed ? '1' : '0'">
            <button type="button" @click="$refs.input.click()" class="flex w-full flex-col items-center justify-center rounded-xl border-2 border-dashed border-cream-300 p-6 text-center transition hover:border-brand-500">
                <svg class="mb-2 h-8 w-8 text-ink-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A1.5 1.5 0 0021.75 19.5V4.5A1.5 1.5 0 0020.25 3H3.75A1.5 1.5 0 002.25 4.5v15A1.5 1.5 0 003.75 21z"/></svg>
                <span class="text-sm text-ink-500">Chọn ảnh minh họa</span>
            </button>
            <input x-ref="input" type="file" name="about_image" @change="onChange" class="hidden" accept="image/*">
            <template x-if="hasImage">
                <div class="relative mt-3 overflow-hidden rounded-xl">
                    <img :src="displaySrc" class="h-40 w-full bg-cream-100 object-cover" alt="">
                    <button type="button" @click="remove()" class="absolute right-2 top-2 grid h-7 w-7 place-items-center rounded-full bg-ink-900/70 text-white hover:bg-red-600">×</button>
                </div>
            </template>
            <label class="mt-3 flex items-center justify-between text-sm text-ink-500"><span>Xóa ảnh hiện có</span><input type="checkbox" x-model="removed" class="h-4 w-4 accent-brand-600"></label>
        </div>

        @if($errors->any())<div class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ $errors->first() }}</div>@endif
    </div>
</form>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('imageUploader', ({ existing = null, inputName = 'image' } = {}) => ({
        existing, inputName,
        preview: null, fileName: '', removed: false,
        get displaySrc() { return this.preview || this.existing; },
        get hasImage() { return !!this.displaySrc; },
        onChange(e) {
            const f = e.target.files && e.target.files[0];
            if (!f) { this.clearPreview(); return; }
            if (this.preview) URL.revokeObjectURL(this.preview);
            this.preview = URL.createObjectURL(f);
            this.fileName = f.name;
        },
        clearPreview() {
            if (this.preview) URL.revokeObjectURL(this.preview);
            this.preview = null; this.fileName = '';
            if (this.$refs.input) this.$refs.input.value = '';
        },
        remove() { this.removed = true; this.clearPreview(); },
    }));
});
</script>
@endpush
@endsection
