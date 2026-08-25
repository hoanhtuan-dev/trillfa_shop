@extends('layouts.app')

@section('title', $category?->name ?? 'Cửa hàng')
@section('meta_description', $category?->meta_description ?? 'Khám phá bộ sưu tập thời trang và phong cách sống Trillfa Fa.')

@section('content')
<div class="container-x py-8">
    <x-breadcrumb :items="[
        ['label' => 'Trang chủ', 'url' => route('home')],
        ['label' => $category?->name ?? 'Cửa hàng']
    ]" />

    <div class="mb-6 mt-6 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="font-display text-3xl font-semibold text-ink-900 sm:text-4xl">{{ $category?->name ?? 'Tất cả sản phẩm' }}</h1>
            <p class="mt-2 text-sm text-ink-500">{{ $products->total() }} sản phẩm</p>
        </div>
        <form method="GET" class="flex items-center gap-3">
            @if(request()->has('q'))<input type="hidden" name="q" value="{{ request('q') }}">@endif
            @if($category)<input type="hidden" name="category_id" value="{{ $category->id }}">@endif
            <label class="text-sm text-ink-500">Sắp xếp:</label>
            <select name="sort" onchange="this.form.submit()" class="input !w-auto !rounded-full !py-2">
                <option value="newest" @selected($sort === 'newest')>Mới nhất</option>
                <option value="popular" @selected($sort === 'popular')>Bán chạy</option>
                <option value="rating" @selected($sort === 'rating')>Đánh giá cao</option>
                <option value="price_asc" @selected($sort === 'price_asc')>Giá thấp → cao</option>
                <option value="price_desc" @selected($sort === 'price_desc')>Giá cao → thấp</option>
            </select>
        </form>
    </div>

    <div class="grid gap-8 lg:grid-cols-[260px_1fr]" x-data="{ filtersOpen: false }">
        <!-- Filters (desktop) -->
        <aside class="hidden lg:block lg:sticky lg:top-24 lg:self-start">
            @include('partials.shop-filters')
        </aside>

        <!-- Mobile: open bottom-sheet -->
        <button @click="filtersOpen = true" class="btn-outline lg:hidden">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h12M6 12h12M6 18h6"/></svg>
            Bộ lọc
        </button>

        <!-- Mobile: bottom-sheet -->
        <div x-show="filtersOpen" x-cloak class="fixed inset-0 z-[75] lg:hidden">
            <div @click="filtersOpen = false" class="absolute inset-0 bg-ink-900/40 backdrop-blur-sm"></div>
            <div x-show="filtersOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full" class="absolute inset-x-0 bottom-0 max-h-[85vh] overflow-y-auto rounded-t-3xl bg-white p-5 pb-24">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="font-display text-lg font-semibold text-ink-900">Bộ lọc</h2>
                    <button @click="filtersOpen = false" class="btn-ghost !p-2" aria-label="Đóng">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="space-y-6">
                    @include('partials.shop-filters')
                </div>
                <button @click="filtersOpen = false" class="btn-brand mt-4 w-full">Áp dụng</button>
            </div>
        </div>

        <!-- Products -->
        <div>
            @if($products->isEmpty())
                <div class="card flex flex-col items-center justify-center p-16 text-center">
                    <p class="font-medium text-ink-900">Không tìm thấy sản phẩm phù hợp</p>
                    <p class="mt-1 text-sm text-ink-500">Hãy thử thay đổi bộ lọc hoặc tìm kiếm khác.</p>
                    <a href="{{ route('shop.index') }}" class="btn-primary mt-6">Xóa bộ lọc</a>
                </div>
            @else
                <div class="grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($products as $product)
                        <x-product-card :product="$product" />
                    @endforeach
                </div>
                <div class="mt-10">
                    {{ $products->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection