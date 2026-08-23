@extends('layouts.admin')

@section('title', 'Bài viết')
@section('page_title', 'Bài viết')

@section('content')
    <div class="mb-5 flex items-center justify-between">
        <form method="GET" class="flex items-center gap-2">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Tìm bài viết..." class="input !w-72 !py-2.5">
            <button type="submit" class="btn-primary btn-sm">Tìm</button>
        </form>
        <a href="{{ route('admin.posts.create') }}" class="btn-brand">+ Bài viết mới</a>
    </div>

    <div class="card overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-cream-100 text-left text-xs font-semibold uppercase tracking-wide text-ink-500">
                <tr><th class="px-5 py-3">Tiêu đề</th><th class="px-5 py-3">Danh mục</th><th class="px-5 py-3">Tác giả</th><th class="px-5 py-3">Trạng thái</th><th class="px-5 py-3">Ngày</th><th class="px-5 py-3 text-right">Thao tác</th></tr>
            </thead>
            <tbody class="divide-y divide-cream-200">
                @forelse($posts as $post)
                    <tr class="hover:bg-cream-50">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                <img src="{{ $post->image_url ?: asset('images/placeholder.svg') }}" class="h-10 w-10 rounded-lg object-cover" alt="">
                                <span class="font-medium text-ink-900">{{ $post->title }}</span>
                            </div>
                        </td>
                        <td class="px-5 py-3 text-ink-500">{{ $post->category?->name ?? '—' }}</td>
                        <td class="px-5 py-3 text-ink-500">{{ $post->author?->name }}</td>
                        <td class="px-5 py-3"><span class="badge {{ $post->status === 'published' ? 'bg-brand-100 text-brand-700' : 'bg-cream-100 text-ink-500' }}">{{ $post->status }}</span></td>
                        <td class="px-5 py-3 text-xs text-ink-500">{{ $post->published_at?->format('d/m/Y') }}</td>
                        <td class="px-5 py-3 text-right">
                            <div class="flex justify-end gap-1">
                                <a href="{{ route('admin.posts.preview', $post) }}" target="_blank" class="btn-ghost !p-2"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/></svg></a>
                                <a href="{{ route('admin.posts.edit', $post) }}" class="btn-ghost !p-2"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg></a>
                                <form method="POST" action="{{ route('admin.posts.destroy', $post) }}" onsubmit="return confirm('Xóa bài viết?')">@csrf @method('DELETE')<button type="submit" class="btn-ghost !p-2 text-red-500"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9"/></svg></button></form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center text-ink-500">Chưa có bài viết.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="border-t border-cream-200 p-4">{{ $posts->links() }}</div>
    </div>
@endsection