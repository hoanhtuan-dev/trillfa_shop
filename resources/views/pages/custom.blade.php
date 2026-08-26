@extends('layouts.app')

@section('title', ($page->meta_title ?: $page->hero_heading ?: $page->title).' | '.setting('site_name'))

@section('content')
    <x-breadcrumb :items="[['label' => 'Trang chủ', 'url' => route('home')], ['label' => $page->title]]" />

    @if($page->template === 'landing' && ($page->hero_image || $page->hero_heading || $page->hero_subtitle))
    <!-- Hero -->
    <section class="relative overflow-hidden">
        <div class="absolute inset-0">
            @if($page->hero_image_url)
                <img src="{{ $page->hero_image_url }}" alt="{{ $page->hero_heading ?: $page->title }}" class="h-full w-full object-cover" loading="eager">
                <div class="absolute inset-0 bg-gradient-to-r from-ink-900/80 via-ink-900/50 to-transparent"></div>
            @else
                <div class="h-full w-full bg-ink-900"></div>
            @endif
        </div>
        <div class="container-x relative z-10 flex min-h-[46vh] items-center py-16 text-white sm:min-h-[54vh]">
            <div class="max-w-2xl">
                @if($page->hero_heading)
                    <p class="kicker mb-3 !text-brand-300">{{ $page->excerpt }}</p>
                    <h1 class="font-display text-4xl font-semibold leading-tight text-balance sm:text-5xl">{{ $page->hero_heading }}</h1>
                @else
                    <h1 class="font-display text-4xl font-semibold leading-tight text-balance sm:text-5xl">{{ $page->title }}</h1>
                @endif
                @if($page->hero_subtitle)<p class="mt-4 max-w-xl text-white/80">{{ $page->hero_subtitle }}</p>@endif
                @if($page->hero_button_text)
                    <a href="{{ $page->hero_button_url }}" class="btn-primary mt-8 !bg-white !text-ink-900 hover:!bg-brand-50">{{ $page->hero_button_text }}</a>
                @endif
            </div>
        </div>
    </section>
    @else
    <section class="container-x py-12">
        <p class="kicker mb-2">{{ $page->excerpt }}</p>
        <h1 class="font-display text-4xl font-semibold text-ink-900">{{ $page->title }}</h1>
    </section>
    @endif

    <!-- Content -->
    @if($page->content)
    <section class="container-x py-12">
        <div class="prose-content mx-auto max-w-3xl">
            <div>{!! $page->content !!}</div>
        </div>
    </section>
    @endif

    <!-- Collection products -->
    @if($products->isNotEmpty())
    <section class="container-x py-12">
        <div class="mb-6 flex items-end justify-between">
            <div>
                <p class="kicker mb-2">Bộ sưu tập</p>
                <h2 class="section-title">Khám phá bộ sưu tập</h2>
            </div>
            <a href="{{ route('shop.index') }}" class="link text-sm">Xem tất cả sản phẩm</a>
        </div>
        <div class="grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 lg:grid-cols-4">
            @foreach($products as $product)
                <x-product-card :product="$product" />
            @endforeach
        </div>
    </section>
    @endif

    @if(! $products->isNotEmpty() && ! $page->content && $page->template === 'landing')
    <section class="container-x flex justify-center py-16">
        <a href="{{ route('shop.index') }}" class="btn-brand">Mua sắm ngay</a>
    </section>
    @endif
@endsection