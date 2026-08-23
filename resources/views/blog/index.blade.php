@extends('layouts.app')

@section('title', 'Blog')

@section('content')
<div class="container-x py-8">
    <x-breadcrumb :items="[['label' => 'Trang chủ', 'url' => route('home')], ['label' => 'Blog']]" />

    <div class="mt-6 text-center">
        <p class="kicker mb-2">Blog Trillfa Fa</p>
        <h1 class="font-display text-3xl font-semibold text-ink-900 sm:text-5xl">Câu chuyện & Phong cách</h1>
        <p class="mx-auto mt-3 max-w-xl text-ink-500">Cảm hứng thời trang, mẹo phong cách và câu chuyện từ cộng đồng Trillfa Fa.</p>
    </div>

    <!-- Category filter + search -->
    <div class="mt-8 flex flex-col items-center justify-between gap-4 sm:flex-row">
        <div class="flex flex-wrap justify-center gap-2">
            <a href="{{ route('blog.index') }}" class="chip {{ !isset($cat) ? '!border-brand-600 !bg-brand-50 !text-brand-800' : '' }}">Tất cả</a>
            @foreach($categories as $c)
                <a href="{{ route('blog.category', $c->slug) }}" class="chip {{ isset($cat) && $cat->id === $c->id ? '!border-brand-600 !bg-brand-50 !text-brand-800' : '' }}">{{ $c->name }}</a>
            @endforeach
        </div>
        <form method="GET" class="flex items-center gap-2">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Tìm bài viết..." class="input !w-56 !rounded-full !py-2">
            <button type="submit" class="btn-primary btn-sm">Tìm</button>
        </form>
    </div>

    <!-- Featured post -->
    @if($featured && !isset($cat))
        <a href="{{ route('blog.show', $featured->slug) }}" class="card card-hover group mt-8 grid overflow-hidden lg:grid-cols-2">
            <div class="aspect-[16/10] overflow-hidden lg:aspect-auto">
                <img src="{{ $featured->image_url ?: asset('images/placeholder.svg') }}" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105" alt="">
            </div>
            <div class="flex flex-col justify-center p-8 lg:p-12">
                <div class="flex items-center gap-3 text-xs text-ink-500">
                    @if($featured->category)<span class="badge bg-brand-600 text-white">{{ $featured->category->name }}</span>@endif
                    <span>{{ $featured->published_at?->format('d/m/Y') }}</span>
                    <span>·</span>
                    <span>{{ $featured->reading_time }} phút đọc</span>
                </div>
                <h2 class="mt-4 font-display text-2xl font-semibold text-ink-900 group-hover:text-brand-700 sm:text-3xl">{{ $featured->title }}</h2>
                <p class="mt-3 leading-relaxed text-ink-500">{{ $featured->excerpt }}</p>
                <span class="link mt-6">Đọc tiếp →</span>
            </div>
        </a>
    @endif

    <!-- Posts grid -->
    @if($posts->isEmpty())
        <div class="card mt-8 flex flex-col items-center justify-center p-16 text-center">
            <p class="font-medium text-ink-900">Chưa có bài viết nào</p>
            <p class="mt-1 text-sm text-ink-500">Hãy quay lại sau nhé.</p>
        </div>
    @else
        <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($posts as $post)
                <a href="{{ route('blog.show', $post->slug) }}" class="group card card-hover overflow-hidden">
                    <div class="aspect-[16/10] overflow-hidden">
                        <img src="{{ $post->image_url ?: asset('images/placeholder.svg') }}" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105" alt="">
                    </div>
                    <div class="p-6">
                        <div class="flex items-center gap-2 text-xs text-ink-500">
                            @if($post->category)<span class="badge bg-brand-50 text-brand-700">{{ $post->category->name }}</span>@endif
                            <span>{{ $post->published_at?->format('d/m/Y') }}</span>
                        </div>
                        <h3 class="mt-3 font-display text-lg font-semibold text-ink-900 group-hover:text-brand-700 line-clamp-2">{{ $post->title }}</h3>
                        <p class="mt-2 line-clamp-3 text-sm text-ink-500">{{ $post->excerpt }}</p>
                        <div class="mt-4 flex items-center gap-2 text-xs text-ink-500">
                            <span class="grid h-6 w-6 place-items-center rounded-full bg-cream-200 text-[10px] font-bold text-ink-700">{{ strtoupper(substr($post->author?->name ?? 'T', 0, 1)) }}</span>
                            <span>{{ $post->author?->name ?? 'Trillfa' }}</span>
                            <span>·</span>
                            <span>{{ $post->reading_time }} phút</span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
        <div class="mt-10">{{ $posts->links() }}</div>
    @endif
</div>
@endsection
