@extends('layouts.app')

@section('title', setting('site_name', 'Trillfa Fa'))

@section('content')
    <!-- Hero -->
    @if($heroBanners->isNotEmpty())
    <section class="relative">
        <div x-data="{ i: 0, timer: null, slides: {{ $heroBanners->count() }}, start() { this.timer = setInterval(() => { this.i = (this.i + 1) % this.slides; }, 6000) } }" x-init="start()">
            <div class="relative overflow-hidden">
                <div class="flex transition-transform duration-700 ease-out" :style="'transform: translateX(-' + (i * 100) + '%)'">
                    @foreach($heroBanners as $banner)
                        <div class="relative w-full shrink-0">
                            <div class="relative h-[65vh] min-h-[440px] w-full sm:h-[72vh]">
                                <img src="{{ $banner->image_url ?: asset('images/placeholder.svg') }}" alt="{{ $banner->title }}" class="absolute inset-0 h-full w-full object-cover" loading="eager">
                                <div class="absolute inset-0 bg-gradient-to-r from-ink-900/70 via-ink-900/30 to-transparent"></div>
                                <div class="container-x relative z-10 flex h-full items-center">
                                    <div class="max-w-xl text-white">
                                        <p class="kicker !text-brand-300 mb-3">{{ $banner->subtitle }}</p>
                                        <h1 class="font-display text-4xl font-semibold leading-tight text-balance sm:text-6xl">{{ $banner->title }}</h1>
                                        @if($banner->button_text)
                                            <a href="{{ $banner->button_link ?: route('shop.index') }}" class="btn-primary mt-8 !bg-white !text-ink-900 hover:!bg-brand-50">{{ $banner->button_text }}</a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                @if($heroBanners->count() > 1)
                    <div class="absolute bottom-6 left-1/2 z-20 flex -translate-x-1/2 gap-2">
                        @foreach($heroBanners as $banner)
                            <button @click="i = {{ $loop->index }}" class="h-2 rounded-full transition-all" :class="i === {{ $loop->index }} ? 'w-8 bg-white' : 'w-2 bg-white/50'"></button>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </section>
    @endif

    <!-- Benefits -->
    <section class="container-x grid grid-cols-2 gap-4 py-10 lg:grid-cols-4">
        @php
            $benefits = [
                ['Miễn phí vận chuyển', 'Cho đơn hàng từ 500.000₫', 'truck'],
                ['Thanh toán an toàn', 'COD, VNPay, MoMo, chuyển khoản', 'shield'],
                ['Đổi trả dễ dàng', 'Trong vòng 7 ngày', 'refresh'],
                ['Hỗ trợ 24/7', 'Luôn sẵn sàng phục vụ bạn', 'chat'],
            ];
        @endphp
        @foreach($benefits as $b)
            <div class="flex items-start gap-3 rounded-2xl border border-cream-200 bg-white p-4">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                        @if($b[2] === 'truck')<path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.25a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
                        @elseif($b[2] === 'shield')<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                        @elseif($b[2] === 'refresh')<path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M21.015 4.356v4.992m0 0h-4.992m4.992 0l-3.181-3.183a8.25 8.25 0 00-13.803 3.7"/>
                        @else<path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/>
                        @endif
                    </svg>
                </span>
                <div>
                    <p class="text-sm font-semibold text-ink-900">{{ $b[0] }}</p>
                    <p class="text-xs text-ink-500">{{ $b[1] }}</p>
                </div>
            </div>
        @endforeach
    </section>

    <!-- Categories -->
    @if($categories->isNotEmpty())
    <section class="container-x py-8">
        <div class="mb-6 flex items-end justify-between">
            <div>
                <p class="kicker mb-2">Danh mục</p>
                <h2 class="section-title">Khám phá theo danh mục</h2>
            </div>
            <a href="{{ route('shop.index') }}" class="link text-sm">Xem tất cả</a>
        </div>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
            @foreach($categories as $cat)
                <a href="{{ route('shop.category', $cat->slug) }}" class="group card card-hover flex flex-col items-center gap-3 p-5 text-center">
                    <span class="grid h-16 w-16 place-items-center rounded-2xl bg-cream-100 text-brand-600 transition group-hover:bg-brand-600 group-hover:text-white">
                        <x-category-icon :name="$cat->icon" :image="$cat->icon_image_url" />
                    </span>
                    <span class="text-sm font-medium text-ink-900">{{ $cat->name }}</span>
                </a>
            @endforeach
        </div>
    </section>
    @endif

    <!-- Featured products -->
    @if($featured->isNotEmpty())
    <section class="container-x py-12">
        <div class="mb-6 flex items-end justify-between">
            <div>
                <p class="kicker mb-2">Tuyển chọn</p>
                <h2 class="section-title">Sản phẩm nổi bật</h2>
            </div>
            <a href="{{ route('shop.index') }}" class="link text-sm">Xem tất cả</a>
        </div>
        <div class="grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 lg:grid-cols-4">
            @foreach($featured as $product)
                <x-product-card :product="$product" />
            @endforeach
        </div>
    </section>
    @endif

    <!-- Secondary banners -->
    @if($secondaryBanners->isNotEmpty())
    <section class="container-x py-8">
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach($secondaryBanners->take(2) as $banner)
                <a href="{{ $banner->link ?: ($banner->button_link ?: route('shop.index')) }}" class="group relative overflow-hidden rounded-3xl">
                    <img src="{{ $banner->image_url ?: asset('images/placeholder.svg') }}" alt="{{ $banner->title }}" class="h-64 w-full object-cover transition-transform duration-500 group-hover:scale-105">
                    <div class="absolute inset-0 bg-gradient-to-t from-ink-900/70 to-transparent"></div>
                    <div class="absolute bottom-0 left-0 p-6 text-white">
                        <p class="text-xs font-medium uppercase tracking-wide text-brand-200">{{ $banner->subtitle }}</p>
                        <h3 class="mt-1 font-display text-2xl font-semibold">{{ $banner->title }}</h3>
                        @if($banner->button_text)
                            <span class="mt-3 inline-block text-sm font-semibold underline underline-offset-4">{{ $banner->button_text }}</span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    </section>
    @endif

    <!-- New arrivals -->
    @if($newArrivals->isNotEmpty())
    <section class="container-x py-12">
        <div class="mb-6 flex items-end justify-between">
            <div>
                <p class="kicker mb-2">Mới về</p>
                <h2 class="section-title">Hàng mới nhất</h2>
            </div>
            <a href="{{ route('shop.index') }}" class="link text-sm">Xem tất cả</a>
        </div>
        <div class="grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 lg:grid-cols-4">
            @foreach($newArrivals as $product)
                <x-product-card :product="$product" />
            @endforeach
        </div>
    </section>
    @endif

    <!-- Sale section -->
    @if($onSale->isNotEmpty())
    <section class="container-x py-12">
        <div class="card relative overflow-hidden !rounded-[2.5rem] bg-ink-900 p-8 text-white sm:p-12">
            <div class="absolute -right-20 -top-20 h-72 w-72 rounded-full bg-brand-600/30 blur-3xl"></div>
            <div class="relative">
                <p class="kicker mb-2 !text-brand-300">Deal hot</p>
                <h2 class="font-display text-3xl font-semibold sm:text-4xl">Ưu đãi đặc biệt &mdash; giảm sâu</h2>
                <div class="mt-8 grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-4">
                    @foreach($onSale->take(4) as $product)
                        <x-product-card :product="$product" />
                    @endforeach
                </div>
            </div>
        </div>
    </section>
    @endif

    <!-- Bestsellers -->
    @if($bestsellers->isNotEmpty())
    <section class="container-x py-12">
        <div class="mb-6 flex items-end justify-between">
            <div>
                <p class="kicker mb-2">Bán chạy</p>
                <h2 class="section-title">Được yêu thích nhất</h2>
            </div>
            <a href="{{ route('shop.index') }}" class="link text-sm">Xem tất cả</a>
        </div>
        <div class="grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 lg:grid-cols-4">
            @foreach($bestsellers as $product)
                <x-product-card :product="$product" />
            @endforeach
        </div>
    </section>
    @endif

    <!-- Blog preview -->
    @if($latestPosts->isNotEmpty())
    <section class="container-x py-12">
        <div class="mb-6 flex items-end justify-between">
            <div>
                <p class="kicker mb-2">Blog</p>
                <h2 class="section-title">Câu chuyện & Phong cách</h2>
            </div>
            <a href="{{ route('blog.index') }}" class="link text-sm">Đọc tất cả</a>
        </div>
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($latestPosts as $post)
                <a href="{{ route('blog.show', $post->slug) }}" class="group card card-hover overflow-hidden">
                    <div class="aspect-[16/10] overflow-hidden">
                        <img src="{{ $post->image_url ?: asset('images/placeholder.svg') }}" alt="{{ $post->title }}" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105" loading="lazy">
                    </div>
                    <div class="p-5">
                        @if($post->category)
                            <span class="badge bg-brand-50 text-brand-700">{{ $post->category->name }}</span>
                        @endif
                        <h3 class="mt-3 font-display text-lg font-semibold text-ink-900 group-hover:text-brand-700">{{ $post->title }}</h3>
                        <p class="mt-2 line-clamp-2 text-sm text-ink-500">{{ $post->excerpt }}</p>
                        <div class="mt-3 flex items-center gap-3 text-xs text-ink-500">
                            <span>{{ $post->published_at?->format('d/m/Y') }}</span>
                            <span>·</span>
                            <span>{{ $post->reading_time }} phút đọc</span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    </section>
    @endif

    <!-- CTA -->
    @if(widget_enabled('cta'))
    <section class="container-x py-12">
        <div class="card flex flex-col items-center gap-4 bg-brand-50 !border-brand-100 p-10 text-center">
            <h2 class="font-display text-2xl font-semibold text-ink-900 sm:text-3xl">Sẵn sàng nâng cấp phong cách của bạn?</h2>
            <p class="max-w-lg text-ink-500">Khám phá bộ sưu tập mới nhất và tận hưởng ưu đãi hấp dẫn dành riêng cho bạn.</p>
            <a href="{{ route('shop.index') }}" class="btn-brand mt-2">Mua sắm ngay</a>
        </div>
    </section>
    @endif
@endsection