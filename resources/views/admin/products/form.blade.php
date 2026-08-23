@extends('layouts.admin')

@section('title', $product->exists ? 'Sửa sản phẩm' : 'Thêm sản phẩm')
@section('page_title', $product->exists ? 'Sửa sản phẩm' : 'Thêm sản phẩm')

@section('content')
@php
    $initialVariants = $product->exists ? $product->variants->map(fn($v) => ['name' => $v->name, 'sku' => $v->sku, 'price' => $v->price, 'compare_price' => $v->compare_price, 'stock' => $v->stock])->values() : [];
    $existingGallery = collect($product->gallery ?? [])->map(fn($p) => [
        'path' => $p,
        'url' => str_starts_with($p, 'http') ? $p : asset('storage/'.$p),
    ])->values()->all();
@endphp

<div x-data="productForm({{ Js::from($initialVariants) }})">
    <form method="POST" action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-3">
        @csrf
        @method($product->exists ? 'PUT' : 'POST')

        <div class="space-y-6 lg:col-span-2">
            <!-- Info -->
            <div class="card p-6">
                <h2 class="mb-4 font-display text-lg font-semibold text-ink-900">Thông tin sản phẩm</h2>
                <div class="space-y-4">
                    <div>
                        <label class="label">Tên sản phẩm *</label>
                        <input type="text" name="name" value="{{ old('name', $product->name) }}" class="input" required>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label">Danh mục</label>
                            <select name="category_id" class="input">
                                <option value="">— Chọn —</option>
                                @foreach($categories as $c)
                                    <option value="{{ $c->id }}" @selected(old('category_id', $product->category_id) == $c->id)>{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label">Thương hiệu</label>
                            <input type="text" name="brand" value="{{ old('brand', $product->brand) }}" class="input">
                        </div>
                        <div>
                            <label class="label">SKU / Mã</label>
                            <input type="text" name="sku" value="{{ old('sku', $product->sku) }}" class="input">
                        </div>
                        <div>
                            <label class="label">Thẻ (phân cách bằng dấu phẩy)</label>
                            <input type="text" name="tags" value="{{ old('tags', is_array($product->tags) ? implode(', ', $product->tags) : '') }}" class="input">
                        </div>
                    </div>
                    <div>
                        <label class="label">Mô tả ngắn</label>
                        <input type="text" name="short_description" value="{{ old('short_description', $product->short_description) }}" class="input">
                    </div>
                    <div>
                        <label class="label">Mô tả chi tiết</label>
                        <div x-data="richEditor" class="overflow-hidden rounded-xl border border-cream-300 bg-white focus-within:border-brand-500 focus-within:ring-4 focus-within:ring-brand-500/10">
                            <div class="flex flex-wrap items-center gap-1 border-b border-cream-200 bg-cream-100 px-2 py-1.5 text-ink-700">
                                <button type="button" @click="exec('bold')" class="grid h-8 w-8 place-items-center rounded-lg hover:bg-white" title="Đậm"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 4h8a4 4 0 01-4 4H6zM6 12h9a4 4 0 01-4 4H6z" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                                <button type="button" @click="exec('italic')" class="grid h-8 w-8 place-items-center rounded-lg hover:bg-white" title="Nghiêng"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5h6M13 19h6M14 5l-4 14" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                                <button type="button" @click="exec('formatBlock', 'h2')" class="px-2 text-sm font-semibold hover:bg-white" title="Tiêu đề 2">H2</button>
                                <button type="button" @click="exec('formatBlock', 'h3')" class="px-2 text-sm font-semibold hover:bg-white" title="Tiêu đề 3">H3</button>
                                <button type="button" @click="exec('insertUnorderedList')" class="grid h-8 w-8 place-items-center rounded-lg hover:bg-white" title="Danh sách">•</button>
                                <button type="button" @click="exec('insertOrderedList')" class="grid h-8 w-8 place-items-center rounded-lg text-xs hover:bg-white" title="Danh sách số">1.</button>
                                <button type="button" @click="exec('formatBlock', 'blockquote')" class="px-2 text-sm hover:bg-white" title="Trích dẫn">❝</button>
                                <button type="button" @click="addLink()" class="grid h-8 w-8 place-items-center rounded-lg hover:bg-white" title="Liên kết"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757" stroke-linecap="round" stroke-linejoin="round"/><path d="M10.81 15.312a4.5 4.5 0 01-1.242-7.244l4.5-4.5a4.5 4.5 0 016.364 6.364l-1.757 1.757" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                                <button type="button" @click="exec('formatBlock', 'p')" class="px-2 text-sm hover:bg-white" title="Đoạn văn">¶</button>
                                <button type="button" @click="exec('undo')" class="grid h-8 w-8 place-items-center rounded-lg hover:bg-white" title="Hoàn tác"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 016 6v3" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                            </div>
                            <div x-ref="editor" contenteditable @input="sync" class="prose-content min-h-[180px] px-4 py-3 text-sm outline-none"></div>
                            <textarea name="description" x-ref="hidden" class="hidden">{{ old('description', $product->description) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pricing / stock -->
            <div class="card p-6">
                <h2 class="mb-4 font-display text-lg font-semibold text-ink-900">Giá & Tồn kho</h2>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="label">Giá bán *</label>
                        <input type="number" name="price" value="{{ old('price', $product->price) }}" class="input" required step="0.01" min="0">
                    </div>
                    <div>
                        <label class="label">Giá so sánh</label>
                        <input type="number" name="compare_price" value="{{ old('compare_price', $product->compare_price) }}" class="input" step="0.01" min="0">
                    </div>
                    <div>
                        <label class="label">Giá vốn</label>
                        <input type="number" name="cost_price" value="{{ old('cost_price', $product->cost_price) }}" class="input" step="0.01" min="0">
                    </div>
                </div>
            </div>

            <!-- Variants -->
            <div class="card p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="font-display text-lg font-semibold text-ink-900">Biến thể (tùy chọn)</h2>
                    <button type="button" @click="addVariant()" class="btn-outline btn-sm">+ Thêm biến thể</button>
                </div>
                <div class="space-y-3">
                    <template x-for="(v, i) in variants" :key="i">
                        <div class="grid grid-cols-2 gap-3 rounded-xl border border-cream-200 p-3 sm:grid-cols-5">
                            <div class="col-span-2">
                                <label class="label">Tên (VD: S / Đen)</label>
                                <input type="text" x-model="v.name" :name="'variants[' + i + '][name]'" class="input !py-2" placeholder="S / Đen">
                            </div>
                            <div><label class="label">SKU</label><input type="text" x-model="v.sku" :name="'variants[' + i + '][sku]'" class="input !py-2"></div>
                            <div><label class="label">Giá</label><input type="number" x-model="v.price" :name="'variants[' + i + '][price]'" class="input !py-2" step="0.01"></div>
                            <div><label class="label">So sánh</label><input type="number" x-model="v.compare_price" :name="'variants[' + i + '][compare_price]'" class="input !py-2" step="0.01"></div>
                            <div class="col-span-2"><label class="label">Tồn kho</label><input type="number" x-model="v.stock" :name="'variants[' + i + '][stock]'" class="input !py-2" min="0"></div>
                            <div class="col-span-2 flex items-end justify-end sm:col-span-5">
                                <button type="button" @click="removeVariant(i)" class="text-sm text-red-500 hover:text-red-700">Xóa biến thể</button>
                            </div>
                        </div>
                    </template>
                    <p x-show="!variants.length" class="text-sm text-ink-500">Chưa có biến thể. Sản phẩm sẽ dùng giá & tồn kho chính.</p>
                </div>
            </div>

            <!-- SEO -->
            <div class="card p-6">
                <h2 class="mb-4 font-display text-lg font-semibold text-ink-900">SEO</h2>
                <div class="space-y-4">
                    <div><label class="label">Meta title</label><input type="text" name="meta_title" value="{{ old('meta_title', $product->meta_title) }}" class="input"></div>
                    <div><label class="label">Meta description</label><textarea name="meta_description" rows="2" class="input">{{ old('meta_description', $product->meta_description) }}</textarea></div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <div class="card p-6 sticky top-24">
                <button type="submit" class="btn-brand w-full">{{ $product->exists ? 'Cập nhật' : 'Tạo sản phẩm' }}</button>
                <a href="{{ route('admin.products.index') }}" class="btn-ghost mt-2 w-full">Hủy</a>
            </div>

            <div class="card p-6" x-data="imageUploader({ existing: @js($product->image_url) })">
                <h2 class="mb-4 font-display text-lg font-semibold text-ink-900">Hình ảnh chính</h2>
                <button type="button" @click="$refs.input.click()" class="flex w-full flex-col items-center justify-center rounded-xl border-2 border-dashed border-cream-300 p-6 text-center transition hover:border-brand-500">
                    <svg class="mb-2 h-8 w-8 text-ink-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A1.5 1.5 0 0021.75 19.5V4.5A1.5 1.5 0 0020.25 3H3.75A1.5 1.5 0 002.25 4.5v15A1.5 1.5 0 003.75 21z"/></svg>
                    <span class="text-sm text-ink-500">Kéo thả hoặc chọn ảnh chính</span>
                    <span x-show="fileName" class="mt-1 text-xs text-brand-700" x-text="fileName"></span>
                </button>
                <input x-ref="input" type="file" name="image" @change="onChange" class="hidden" accept="image/*">
                <template x-if="hasImage">
                    <div class="relative mt-3 overflow-hidden rounded-xl">
                        <img :src="displaySrc" class="h-44 w-full bg-cream-100 object-cover" alt="">
                        <button type="button" @click="remove()" class="absolute right-2 top-2 grid h-8 w-8 place-items-center rounded-full bg-ink-900/70 text-white hover:bg-red-600" title="Xóa ảnh">×</button>
                    </div>
                </template>
            </div>

            <div class="card p-6" x-data="galleryUploader({ existing: {{ Js::from($existingGallery) }} })">
                <h2 class="mb-4 font-display text-lg font-semibold text-ink-900">Galeri (nhiều ảnh)</h2>
                <button type="button" @click="$refs.file.click()" class="flex w-full flex-col items-center justify-center rounded-xl border-2 border-dashed border-cream-300 p-5 text-center transition hover:border-brand-500">
                    <svg class="mb-2 h-7 w-7 text-ink-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A1.5 1.5 0 0021.75 19.5V4.5A1.5 1.5 0 0020.25 3H3.75A1.5 1.5 0 002.25 4.5v15A1.5 1.5 0 003.75 21z"/></svg>
                    <span class="text-sm text-ink-500">Chọn nhiều ảnh cho galeri</span>
                </button>
                <input x-ref="file" type="file" @change="onChange" class="hidden" accept="image/*" multiple>
                <div class="mt-3 grid grid-cols-3 gap-2" x-show="items.length">
                    <template x-for="(it, idx) in items" :key="idx">
                        <div class="group relative overflow-hidden rounded-lg">
                            <img :src="it.url" class="h-20 w-full bg-cream-100 object-cover">
                            <span x-show="!it.isNew" class="absolute left-1 top-1 rounded bg-white/80 px-1 text-[9px] font-semibold text-ink-700">Cũ</span>
                            <button type="button" @click="remove(idx)" class="absolute right-1 top-1 grid h-6 w-6 place-items-center rounded-full bg-ink-900/70 text-xs text-white opacity-0 transition group-hover:opacity-100 hover:bg-red-600">×</button>
                        </div>
                    </template>
                </div>
                <p x-show="!items.length" class="mt-3 text-xs text-ink-500">Chưa có ảnh galeri.</p>
                <div x-ref="newFiles" class="hidden"></div>
                <div x-ref="removed" class="hidden"></div>
            </div>

            <div class="card p-6 space-y-4">
                <h2 class="font-display text-lg font-semibold text-ink-900">Trạng thái</h2>
                <input type="hidden" name="is_active" :value="is_active ? '1' : '0'">
                <input type="hidden" name="featured" :value="featured ? '1' : '0'">
                <label class="flex items-center justify-between">
                    <span class="text-sm text-ink-700">Đang bán</span>
                    <input type="checkbox" x-model="is_active" class="h-5 w-5 accent-brand-600">
                </label>
                <label class="flex items-center justify-between">
                    <span class="text-sm text-ink-700">Sản phẩm nổi bật</span>
                    <input type="checkbox" x-model="featured" class="h-5 w-5 accent-brand-600">
                </label>
                <label class="flex items-center justify-between">
                    <span class="text-sm text-ink-700">Tồn kho chính</span>
                    <input type="number" name="stock" value="{{ old('stock', $product->stock) }}" class="input !w-24 !py-2" min="0">
                </label>
            </div>

            @if($errors->any())
                <div class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ $errors->first() }}</div>
            @endif
        </div>
    </form>
</div>

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('productForm', (initialVariants) => ({
            variants: initialVariants.length ? initialVariants : [],
            is_active: {{ $product->exists ? ($product->is_active ? 'true' : 'false') : 'true' }},
            featured: {{ $product->exists ? ($product->featured ? 'true' : 'false') : 'false' }},
            addVariant() { this.variants.push({ name: '', sku: '', price: '', compare_price: '', stock: 0 }); },
            removeVariant(i) { this.variants.splice(i, 1); }
        }));
    });
</script>
@endpush
@endsection