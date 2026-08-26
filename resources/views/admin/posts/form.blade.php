@extends('layouts.admin')

@section('title', $post->exists ? 'Sửa bài viết' : 'Thêm bài viết')
@section('page_title', $post->exists ? 'Sửa bài viết' : 'Thêm bài viết')

@section('content')
    <form method="POST" action="{{ $post->exists ? route('admin.posts.update', $post) : route('admin.posts.store') }}" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-3">
        @csrf
        @method($post->exists ? 'PUT' : 'POST')

        <div class="space-y-6 lg:col-span-2">
            <div class="card p-6">
                <h2 class="mb-4 font-display text-lg font-semibold text-ink-900">Nội dung bài viết</h2>
                <div class="space-y-4">
                    <div>
                        <label class="label">Tiêu đề *</label>
                        <input type="text" name="title" value="{{ old('title', $post->title) }}" class="input" required>
                    </div>
                    <div><label class="label">Mô tả ngắn</label><input type="text" name="excerpt" value="{{ old('excerpt', $post->excerpt) }}" class="input"></div>
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
                                <button type="button" @click="exec('formatBlock', 'p')" class="px-2 text-sm hover:bg-white">¶</button>
                                <button type="button" @click="exec('undo')" class="grid h-8 w-8 place-items-center rounded-lg hover:bg-white"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 016 6v3" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                            </div>
                            <div x-ref="editor" contenteditable @input="sync" class="prose-content min-h-[320px] px-4 py-3 text-sm outline-none"></div>
                            <textarea name="body" x-ref="hidden" class="hidden">{{ old('body', $post->body) }}</textarea>
                        </div>
                        <p class="mt-1 text-xs text-ink-500">Dùng thanh công cụ để định dạng. Nội dung được lưu dạng HTML.</p>
                    </div>
                </div>
            </div>
            <div class="card p-6">
                <h2 class="mb-4 font-display text-lg font-semibold text-ink-900">SEO</h2>
                <div class="space-y-4">
                    <div><label class="label">Meta title</label><input type="text" name="meta_title" value="{{ old('meta_title', $post->meta_title) }}" class="input"></div>
                    <div><label class="label">Meta description</label><textarea name="meta_description" rows="2" class="input">{{ old('meta_description', $post->meta_description) }}</textarea></div>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="card p-6 sticky top-24">
                <button type="submit" class="btn-brand w-full">{{ $post->exists ? 'Cập nhật' : 'Tạo bài viết' }}</button>
                <a href="{{ route('admin.posts.index') }}" class="btn-ghost mt-2 w-full">Hủy</a>
            </div>

            <div class="card p-6 space-y-4" x-data="{ featured: {{ $post->exists ? ($post->is_featured ? 'true' : 'false') : 'false' }} }">
                <h2 class="font-display text-lg font-semibold text-ink-900">Cài đặt</h2>
                <div>
                    <label class="label">Danh mục</label>
                    <select name="blog_category_id" class="input">
                        <option value="">— Không —</option>
                        @foreach($categories as $c)<option value="{{ $c->id }}" @selected(old('blog_category_id', $post->blog_category_id) == $c->id)>{{ $c->name }}</option>@endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">Trạng thái</label>
                        <select name="status" class="input">
                            <option value="draft" @selected(old('status', $post->status) === 'draft')>Bản nháp</option>
                            <option value="published" @selected(old('status', $post->status) === 'published')>Xuất bản</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Ngày xuất bản</label>
                        <input type="date" name="published_at" value="{{ old('published_at', $post->published_at?->format('Y-m-d')) }}" class="input">
                    </div>
                </div>
                <div><label class="label">Thẻ (phân cách phẩy)</label><input type="text" name="tags" value="{{ old('tags', is_array($post->tags) ? implode(', ', $post->tags) : '') }}" class="input"></div>
                <input type="hidden" name="is_featured" :value="featured ? '1' : '0'">
                <label class="flex items-center justify-between text-sm text-ink-700"><span>Bài viết nổi bật</span><input type="checkbox" x-model="featured" class="h-5 w-5 accent-brand-600"></label>
            </div>

            <div class="card p-6" x-data="imageUploader({ existing: @js($post->image_url) })">
                <h2 class="mb-4 font-display text-lg font-semibold text-ink-900">Ảnh đại diện</h2>
                <button type="button" @click="$refs.input.click()" class="flex w-full flex-col items-center justify-center rounded-xl border-2 border-dashed border-cream-300 p-6 text-center transition hover:border-brand-500">
                    <svg class="mb-2 h-8 w-8 text-ink-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A1.5 1.5 0 0021.75 19.5V4.5A1.5 1.5 0 0020.25 3H3.75A1.5 1.5 0 002.25 4.5v15A1.5 1.5 0 003.75 21z"/></svg>
                    <span class="text-sm text-ink-500">Chọn ảnh đại diện</span>
                    <span x-show="fileName" class="mt-1 text-xs text-brand-700" x-text="fileName"></span>
                </button>
                <input x-ref="input" type="file" name="image" @change="onChange" class="hidden" accept="image/*">
                <template x-if="hasImage">
                    <div class="relative mt-3 overflow-hidden rounded-xl">
                        <img :src="displaySrc" class="h-40 w-full bg-cream-100 object-cover" alt="">
                        <button type="button" @click="remove()" class="absolute right-2 top-2 grid h-8 w-8 place-items-center rounded-full bg-ink-900/70 text-white hover:bg-red-600" title="Xóa ảnh">×</button>
                    </div>
                </template>
            </div>

            @if($errors->any())
                <div class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ $errors->first() }}</div>
            @endif
        </div>
    </form>
@endsection