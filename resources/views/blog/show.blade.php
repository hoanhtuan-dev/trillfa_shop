@extends('layouts.app')

@section('title', $post->title)
@section('meta_description', $post->meta_description ?? $post->excerpt)

@section('content')
<article>
    <div class="container-x py-8">
        <x-breadcrumb :items="[
            ['label' => 'Trang chủ', 'url' => route('home')],
            ['label' => 'Blog', 'url' => route('blog.index')],
            ['label' => $post->title]
        ]" />
    </div>

    <div class="container-x mt-6 max-w-3xl">
        <div class="flex items-center gap-3 text-xs text-ink-500">
            @if($post->category)<a href="{{ route('blog.category', $post->category->slug) }}" class="badge bg-brand-600 text-white">{{ $post->category->name }}</a>@endif
            <span>{{ $post->published_at?->format('d/m/Y') }}</span>
            <span>·</span>
            <span>{{ $post->reading_time }} phút đọc</span>
            <span>·</span>
            <span>{{ number_format($post->views_count) }} lượt xem</span>
        </div>
        <h1 class="mt-4 font-display text-3xl font-semibold leading-tight text-ink-900 sm:text-5xl">{{ $post->title }}</h1>
        <p class="mt-4 text-lg leading-relaxed text-ink-500">{{ $post->excerpt }}</p>

        <div class="mt-8 overflow-hidden rounded-3xl">
            <img src="{{ $post->image_url ?: asset('images/placeholder.svg') }}" class="w-full object-cover" alt="">
        </div>

        <div class="prose-content mt-10">
            {!! $post->body !!}
        </div>

        @if(is_array($post->tags) && count($post->tags))
            <div class="mt-10 flex flex-wrap gap-2">
                @foreach($post->tags as $tag)
                    <a href="{{ route('blog.index', ['q' => $tag]) }}" class="chip !py-1.5 text-xs">#{{ $tag }}</a>
                @endforeach
            </div>
        @endif

        <div class="mt-10 flex items-center justify-between rounded-2xl bg-cream-100 p-6">
            <div class="flex items-center gap-3">
                <span class="grid h-12 w-12 place-items-center rounded-full bg-brand-600 font-display text-lg font-bold text-white">{{ strtoupper(substr($post->author?->name ?? 'T', 0, 1)) }}</span>
                <div>
                    <p class="font-medium text-ink-900">{{ $post->author?->name ?? 'Trillfa Fa' }}</p>
                    <p class="text-xs text-ink-500">Tác giả</p>
                </div>
            </div>
            <a href="{{ route('blog.index') }}" class="btn-outline btn-sm">← Blog</a>
        </div>
    </div>

    @if($related->isNotEmpty())
    <section class="container-x py-16">
        <h2 class="mb-6 section-title">Bài viết liên quan</h2>
        <div class="grid gap-6 sm:grid-cols-3">
            @foreach($related as $rp)
                <a href="{{ route('blog.show', $rp->slug) }}" class="group card card-hover overflow-hidden">
                    <div class="aspect-[16/10] overflow-hidden">
                        <img src="{{ $rp->image_url ?: asset('images/placeholder.svg') }}" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105" alt="">
                    </div>
                    <div class="p-5">
                        <h3 class="font-display text-base font-semibold text-ink-900 group-hover:text-brand-700 line-clamp-2">{{ $rp->title }}</h3>
                        <p class="mt-2 text-xs text-ink-500">{{ $rp->published_at?->format('d/m/Y') }} · {{ $rp->reading_time }} phút</p>
                    </div>
                </a>
            @endforeach
        </div>
    </section>
    @endif
</article>
@endsection