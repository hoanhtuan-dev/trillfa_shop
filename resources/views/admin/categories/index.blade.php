@extends('layouts.admin')

@section('title', 'Danh mục')
@section('page_title', 'Danh mục')

@section('content')
@php
    $catItems = $categories->map(fn($c) => [
        'id' => $c->id, 'name' => $c->name, 'parent_id' => $c->parent_id,
        'description' => $c->description, 'sort_order' => $c->sort_order,
        'is_active' => $c->is_active, 'image' => $c->image_url,
    ])->values();
    $parents = $categories->whereNull('parent_id');
@endphp

<div class="grid gap-6 lg:grid-cols-2" x-data="categoryForm({{ Js::from($catItems) }}, '{{ route('admin.categories.store') }}')">
    <div class="card overflow-hidden">
        <div class="border-b border-cream-200 p-5"><h2 class="font-display text-lg font-semibold text-ink-900">Danh sách danh mục</h2></div>
        <table class="w-full text-sm">
            <thead class="bg-cream-100 text-left text-xs font-semibold uppercase tracking-wide text-ink-500">
                <tr><th class="px-5 py-3">Tên</th><th class="px-5 py-3">Sản phẩm</th><th class="px-5 py-3">Trạng thái</th><th class="px-5 py-3 text-right">Thao tác</th></tr>
            </thead>
            <tbody class="divide-y divide-cream-200">
                @forelse($categories as $category)
                    <tr class="hover:bg-cream-50">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2">
                                @if($category->image_url)<img src="{{ $category->image_url }}" class="h-8 w-8 rounded-lg object-cover">@endif
                                <div>
                                    <p class="font-medium text-ink-900">{{ $category->name }}</p>
                                    @if($category->parent)<p class="text-xs text-ink-500">Cha: {{ $category->parent->name }}</p>@endif
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3 text-ink-500">{{ $category->products_count }}</td>
                        <td class="px-5 py-3"><span class="badge {{ $category->is_active ? 'bg-brand-600 text-white' : 'bg-cream-200 text-ink-500' }}">{{ $category->is_active ? 'Bật' : 'Tắt' }}</span></td>
                        <td class="px-5 py-3 text-right">
                            <div class="flex justify-end gap-1">
                                <button @click="edit({{ $category->id }})" class="btn-ghost !p-2" title="Sửa"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg></button>
                                <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" onsubmit="return confirm('Xóa danh mục?')">@csrf @method('DELETE')<button type="submit" class="btn-ghost !p-2 text-red-500"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166"/></svg></button></form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-10 text-center text-ink-500">Chưa có danh mục.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card p-6 h-fit">
        <h2 class="mb-4 font-display text-lg font-semibold text-ink-900" x-text="editing ? 'Sửa danh mục' : 'Thêm danh mục'"></h2>
        <form :action="formAction" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <input type="hidden" name="_method" :value="formMethod">
            <input type="hidden" name="is_active" value="0">
            <div>
                <label class="label">Tên *</label>
                <input type="text" name="name" x-model="form.name" class="input" required>
            </div>
            <div>
                <label class="label">Danh mục cha</label>
                <select name="parent_id" x-model="form.parent_id" class="input">
                    <option value="">— Không —</option>
                    @foreach($parents as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                </select>
            </div>
            <div><label class="label">Mô tả</label><input type="text" name="description" x-model="form.description" class="input"></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label">Thứ tự</label><input type="number" name="sort_order" x-model="form.sort_order" class="input"></div>
                <div class="flex items-end">
                    <label class="flex items-center gap-2 pb-2 text-sm text-ink-700"><input type="checkbox" name="is_active" value="1" x-model="form.is_active" class="h-4 w-4 accent-brand-600"> Kích hoạt</label>
                </div>
            </div>
            <div>
                <label class="label">Ảnh</label>
                <button type="button" @click="$refs.image.click()" class="flex w-full items-center justify-center rounded-xl border-2 border-dashed border-cream-300 p-5 text-center transition hover:border-brand-500">
                    <span class="text-sm text-ink-500">Chọn ảnh</span>
                </button>
                <input x-ref="image" type="file" name="image" @change="onImg" class="hidden" accept="image/*">
                <template x-if="imagePreview">
                    <div class="relative mt-3 overflow-hidden rounded-xl">
                        <img :src="imagePreview" class="h-28 w-full bg-cream-100 object-cover" alt="">
                        <button type="button" @click="removeImg" class="absolute right-2 top-2 grid h-7 w-7 place-items-center rounded-full bg-ink-900/70 text-white hover:bg-red-600">×</button>
                    </div>
                </template>
            </div>
            <div class="flex items-center gap-2 pt-2">
                <button type="submit" class="btn-brand">Lưu</button>
                <button type="button" @click="reset()" class="btn-ghost" x-show="editing">Hủy sửa</button>
            </div>
        </form>
        @if($errors->any())<div class="mt-3 rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ $errors->first() }}</div>@endif
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('categoryForm', (items, createUrl) => ({
        items, createUrl,
        editing: null,
        form: { id: null, name: '', parent_id: '', description: '', sort_order: 0, is_active: true },
        imagePreview: null,
        fileName: '',
        get formAction() { return this.editing ? '/admin/categories/' + this.editing : this.createUrl; },
        get formMethod() { return this.editing ? 'PUT' : 'POST'; },
        edit(id) {
            const c = this.items.find(x => x.id === id);
            if (!c) return;
            this.editing = c.id;
            this.form = { id: c.id, name: c.name, parent_id: c.parent_id || '', description: c.description || '', sort_order: c.sort_order || 0, is_active: !!c.is_active };
            this.setImage(c.image || null);
        },
        reset() {
            this.editing = null;
            this.form = { id: null, name: '', parent_id: '', description: '', sort_order: 0, is_active: true };
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
