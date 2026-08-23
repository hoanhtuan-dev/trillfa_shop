@extends('layouts.admin')

@section('title', 'Banner')
@section('page_title', 'Banner')

@section('content')
@php
    $bannerItems = $banners->map(fn($b) => [
        'id' => $b->id, 'title' => $b->title, 'subtitle' => $b->subtitle,
        'button_text' => $b->button_text, 'button_link' => $b->button_link,
        'position' => $b->position, 'sort_order' => $b->sort_order,
        'is_active' => $b->is_active, 'image' => $b->image_url,
    ])->values();
@endphp

<div class="grid gap-6 lg:grid-cols-2" x-data="bannerForm({{ Js::from($bannerItems) }}, '{{ route('admin.banners.store') }}')">
    <!-- List -->
    <div class="space-y-4">
        @forelse($banners as $banner)
            <div class="card flex gap-4 p-4">
                <img src="{{ $banner->image_url ?: asset('images/placeholder.svg') }}" class="h-20 w-32 shrink-0 rounded-xl object-cover" alt="">
                <div class="flex-1">
                    <p class="font-medium text-ink-900">{{ $banner->title }}</p>
                    <p class="text-xs text-ink-500">{{ $banner->position }} · Thứ tự {{ $banner->sort_order }}</p>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <button @click="edit({{ $banner->id }})" class="badge bg-brand-50 text-brand-700 hover:bg-brand-100">Sửa</button>
                        <form method="POST" action="{{ route('admin.banners.toggle', $banner) }}">@csrf<button type="submit" class="badge {{ $banner->is_active ? 'bg-brand-100 text-brand-700' : 'bg-red-100 text-red-600' }}">{{ $banner->is_active ? 'Bật' : 'Tắt' }}</button></form>
                        <form method="POST" action="{{ route('admin.banners.destroy', $banner) }}" onsubmit="return confirm('Xóa banner?')">@csrf @method('DELETE')<button type="submit" class="badge bg-red-50 text-red-600 hover:bg-red-100">Xóa</button></form>
                    </div>
                </div>
            </div>
        @empty
            <div class="card p-10 text-center text-ink-500">Chưa có banner nào.</div>
        @endforelse
    </div>

    <!-- Form create/edit -->
    <div class="card h-fit p-6">
        <h2 class="mb-4 font-display text-lg font-semibold text-ink-900" x-text="editing ? 'Sửa banner' : 'Thêm banner'"></h2>
        <form :action="formAction" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <input type="hidden" name="_method" :value="formMethod">
            <input type="hidden" name="is_active" :value="form.is_active ? '1' : '0'">
            <div><label class="label">Tiêu đề *</label><input type="text" name="title" x-model="form.title" class="input" required></div>
            <div><label class="label">Phụ đề</label><input type="text" name="subtitle" x-model="form.subtitle" class="input"></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label">Nút bấm</label><input type="text" name="button_text" x-model="form.button_text" class="input"></div>
                <div><label class="label">Liên kết</label><input type="text" name="button_link" x-model="form.button_link" class="input"></div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label">Vị trí</label>
                    <select name="position" x-model="form.position" class="input"><option value="hero">Hero</option><option value="secondary">Secondary</option></select>
                </div>
                <div><label class="label">Thứ tự</label><input type="number" name="sort_order" x-model="form.sort_order" class="input"></div>
            </div>
            <div>
                <label class="label">Ảnh</label>
                <button type="button" @click="$refs.image.click()" class="flex w-full flex-col items-center justify-center rounded-xl border-2 border-dashed border-cream-300 p-5 text-center transition hover:border-brand-500">
                    <svg class="mb-2 h-7 w-7 text-ink-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A1.5 1.5 0 0021.75 19.5V4.5A1.5 1.5 0 0020.25 3H3.75A1.5 1.5 0 002.25 4.5v15A1.5 1.5 0 003.75 21z"/></svg>
                    <span class="text-sm text-ink-500">Chọn ảnh banner</span>
                </button>
                <input x-ref="image" type="file" name="image" @change="onImg" class="hidden" accept="image/*">
                <template x-if="imagePreview">
                    <div class="relative mt-3 overflow-hidden rounded-xl">
                        <img :src="imagePreview" class="h-32 w-full bg-cream-100 object-cover" alt="">
                        <button type="button" @click="removeImg" class="absolute right-2 top-2 grid h-7 w-7 place-items-center rounded-full bg-ink-900/70 text-white hover:bg-red-600">×</button>
                    </div>
                </template>
            </div>
            <label class="flex items-center justify-between text-sm text-ink-700"><span>Kích hoạt</span><input type="checkbox" x-model="form.is_active" class="h-5 w-5 accent-brand-600"></label>
            <div class="flex items-center gap-2 pt-1">
                <button type="submit" class="btn-brand flex-1" x-text="editing ? 'Cập nhật' : 'Thêm banner'"></button>
                <button type="button" @click="resetForm()" class="btn-ghost" x-show="editing">Hủy</button>
            </div>
        </form>
        @if($errors->any())<div class="mt-3 rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ $errors->first() }}</div>@endif
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('bannerForm', (items, createUrl) => ({
        items, createUrl,
        editing: null,
        form: { id: null, title: '', subtitle: '', button_text: '', button_link: '', position: 'hero', sort_order: 0, is_active: true },
        imagePreview: null,
        fileName: '',
        get formAction() { return this.editing ? '/admin/banners/' + this.editing : this.createUrl; },
        get formMethod() { return this.editing ? 'PUT' : 'POST'; },
        edit(id) {
            const b = this.items.find(x => x.id === id);
            if (!b) return;
            this.editing = b.id;
            this.form = { id: b.id, title: b.title, subtitle: b.subtitle || '', button_text: b.button_text || '', button_link: b.button_link || '', position: b.position || 'hero', sort_order: b.sort_order || 0, is_active: !!b.is_active };
            this.setImage(b.image || null);
        },
        resetForm() {
            this.editing = null;
            this.form = { id: null, title: '', subtitle: '', button_text: '', button_link: '', position: 'hero', sort_order: 0, is_active: true };
            this.setImage(null);
        },
        setImage(url) {
            if (this.imagePreview) URL.revokeObjectURL(this.imagePreview);
            this.imagePreview = url;
            this.fileName = '';
        },
        onImg(e) {
            const f = e.target.files && e.target.files[0];
            if (!f) return;
            if (this.imagePreview) URL.revokeObjectURL(this.imagePreview);
            this.imagePreview = URL.createObjectURL(f);
            this.fileName = f.name;
        },
        removeImg() {
            if (this.imagePreview) URL.revokeObjectURL(this.imagePreview);
            this.imagePreview = null;
            this.fileName = '';
            if (this.$refs.image) this.$refs.image.value = '';
        },
    }));
});
</script>
@endpush
@endsection
