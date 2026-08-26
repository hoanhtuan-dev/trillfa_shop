@extends('layouts.admin')

@section('title', $page->exists ? 'Sửa trang đích' : 'Tạo trang đích')
@section('page_title', $page->exists ? 'Sửa trang đích' : 'Tạo trang đích')

@section('content')
    <form method="POST" action="{{ $page->exists ? route('admin.pages.update', $page) : route('admin.pages.store') }}" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-3">
        @csrf
        @method($page->exists ? 'PUT' : 'POST')

        <div class="space-y-6 lg:col-span-2">
            <div class="card p-6">
                <h2 class="mb-4 font-display text-lg font-semibold text-ink-900">Nội dung trang</h2>
                <div class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div><label class="label">Tiêu đề *</label><input type="text" name="title" value="{{ old('title', $page->title) }}" class="input" required></div>
                        <div><label class="label">Đường dẫn (slug)</label><input type="text" name="slug" value="{{ old('slug', $page->slug) }}" class="input" placeholder="bo-suu-tap-moi"></div>
                    </div>
                    <div><label class="label">Mô tả ngắn</label><input type="text" name="excerpt" value="{{ old('excerpt', $page->excerpt) }}" class="input"></div>
                    <div>
                        <label class="label">Nội dung *</label>
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
                                <button type="button" @click="pickImage()" class="grid h-8 w-8 place-items-center rounded-lg hover:bg-white" title="Chèn ảnh"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A1.5 1.5 0 0021.75 19.5V4.5A1.5 1.5 0 0020.25 3H3.75A1.5 1.5 0 002.25 4.5v15A1.5 1.5 0 003.75 21z" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                                <button type="button" @click="exec('undo')" class="grid h-8 w-8 place-items-center rounded-lg hover:bg-white"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 016 6v3" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                            </div>
                            <div x-ref="editor" contenteditable @input="sync" class="prose-content min-h-[300px] px-4 py-3 text-sm outline-none"></div>
                            <textarea name="content" x-ref="hidden" class="hidden">{{ old('content', $page->content) }}</textarea>
                        </div>
                        <p class="mt-1 text-xs text-ink-500">Dùng thanh công cụ để định dạng. Nội dung được lưu dạng HTML.</p>
                    </div>
                </div>
            </div>

            <div class="card p-6">
                <h2 class="mb-4 font-display text-lg font-semibold text-ink-900">Hero (phần đầu trang đích)</h2>
                <div class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div><label class="label">Tiêu đề hero</label><input type="text" name="hero_heading" value="{{ old('hero_heading', $page->hero_heading) }}" class="input"></div>
                        <div><label class="label">Nút hero (chữ)</label><input type="text" name="hero_button_text" value="{{ old('hero_button_text', $page->hero_button_text) }}" class="input" placeholder="Mua sắm ngay"></div>
                    </div>
                    <div><label class="label">Mô tả hero</label><textarea name="hero_subtitle" rows="2" class="input">{{ old('hero_subtitle', $page->hero_subtitle) }}</textarea></div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <div x-data="imageUploader({ existing: @js($page->hero_image_url) })">
                                <label class="label">Ảnh nền hero</label>
                                <button type="button" @click="$refs.input.click()" class="flex w-full items-center justify-center gap-2 rounded-xl border-2 border-dashed border-cream-300 p-6 text-center transition hover:border-brand-500">
                                    <svg class="h-6 w-6 text-ink-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A1.5 1.5 0 0021.75 19.5V4.5A1.5 1.5 0 0020.25 3H3.75A1.5 1.5 0 002.25 4.5v15A1.5 1.5 0 003.75 21z"/></svg>
                                    <span class="text-sm text-ink-500">Chọn ảnh nền</span>
                                </button>
                                <input x-ref="input" type="file" name="hero_image" @change="onChange" class="hidden" accept="image/*">
                                <template x-if="hasImage">
                                    <div class="relative mt-3 overflow-hidden rounded-xl">
                                        <img :src="displaySrc" class="h-40 w-full bg-cream-100 object-cover" alt="">
                                        <button type="button" @click="remove()" class="absolute right-2 top-2 grid h-8 w-8 place-items-center rounded-full bg-ink-900/70 text-white hover:bg-red-600" title="Xóa ảnh">×</button>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <div>
                            <label class="label">Nút hero — chọn danh mục</label>
                            <select name="hero_button_category_id" class="input">
                                <option value="">— Không (dùng liên kết tùy chỉnh) —</option>
                                @foreach($categories as $cat)<option value="{{ $cat->id }}" @selected(old('hero_button_category_id', $page->hero_button_category_id) == $cat->id)>{{ $cat->name }}</option>@endforeach
                            </select>
                            <p class="mt-1 text-xs text-ink-500">Ví dụ: với bộ sưu tập thu đông 2026, chọn danh mục “Thu đông 2026”.</p>
                        </div>
                        <div><label class="label">Hoặc liên kết tùy chỉnh</label><input type="text" name="hero_button_link" value="{{ old('hero_button_link', $page->hero_button_link) }}" class="input" placeholder="/shop"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="card p-6 sticky top-24">
                <button type="submit" class="btn-brand w-full">{{ $page->exists ? 'Cập nhật' : 'Tạo trang' }}</button>
                <a href="{{ route('admin.pages.index') }}" class="btn-ghost mt-2 w-full">Hủy</a>
            </div>

            <div class="card p-6 space-y-4">
                <h2 class="font-display text-lg font-semibold text-ink-900">Xuất bản</h2>
                <div>
                    <label class="label">Template</label>
                    <select name="template" class="input">
                        <option value="landing" @selected(old('template', $page->template) === 'landing')>Landing (hero + nội dung + sản phẩm)</option>
                        <option value="basic" @selected(old('template', $page->template) === 'basic')>Cơ bản (chỉ nội dung)</option>
                    </select>
                </div>
                <div><label class="label">Ngày xuất bản</label><input type="date" name="published_at" value="{{ old('published_at', $page->published_at?->format('Y-m-d')) }}" class="input"></div>
                <label class="flex items-center justify-between text-sm text-ink-700">
                    <span>Hiển thị trang</span>
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $page->is_active)) class="h-5 w-5 accent-brand-600">
                </label>
            </div>

            <div class="card p-6 space-y-4">
                <h2 class="font-display text-lg font-semibold text-ink-900">Bộ sưu tập sản phẩm</h2>
                <p class="text-xs text-ink-500">Chọn danh mục để lọc nhanh, rồi tích chọn sản phẩm hiển thị trên trang đích (vd: bộ sưu tập mới).</p>
                @php
                    $productPickerData = $products->map(fn($p) => ['id' => (int) $p->id, 'name' => $p->name, 'category_id' => $p->category_id])->values();
                    $pickerSelected = old('product_ids', $page->product_ids ?? []);
                @endphp
                <div x-data="productPicker({{ Js::from($productPickerData) }}, {{ Js::from($pickerSelected) }}, {{ Js::from($categories->map(fn($c) => ['id' => (int) $c->id, 'name' => $c->name])) }})">
                    <select x-model="filterCat" class="input !w-full sm:!w-80">
                        <option value="">Tất cả danh mục</option>
                        <template x-for="c in categories" :key="c.id">
                            <option :value="c.id" x-text="c.name"></option>
                        </template>
                    </select>
                    <p class="mt-2 text-xs text-ink-500" x-text="'Đang chọn: ' + selected.length + ' sản phẩm (hiển thị ' + shown().length + ' sản phẩm)'"></p>
                    <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <template x-for="p in shown()" :key="p.id">
                            <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-cream-200 px-3 py-2 text-sm transition hover:border-brand-400" :class="selected.includes(p.id) ? 'border-brand-600 bg-brand-50' : ''">
                                <input type="checkbox" name="product_ids[]" :value="p.id" :checked="selected.includes(p.id)" @change="toggle(p.id)" class="h-4 w-4 shrink-0 accent-brand-600">
                                <span x-text="p.name" class="truncate"></span>
                            </label>
                        </template>
                    </div>
                </div>
            </div>

            <div class="card p-6 space-y-4">
                <h2 class="font-display text-lg font-semibold text-ink-900">SEO</h2>
                <div><label class="label">Meta title</label><input type="text" name="meta_title" value="{{ old('meta_title', $page->meta_title) }}" class="input"></div>
                <div><label class="label">Meta description</label><textarea name="meta_description" rows="2" class="input">{{ old('meta_description', $page->meta_description) }}</textarea></div>
            </div>

            @if($errors->any())
                <div class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ $errors->first() }}</div>
            @endif
        </div>
    </form>
@endsection