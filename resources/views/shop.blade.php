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

    <div class="grid gap-8 lg:grid-cols-[260px_1fr]">
        <!-- Filters -->
        <aside class="lg:sticky lg:top-24 lg:self-start" x-data="{ open: false }">
            <button @click="open = !open" class="btn-outline w-full lg:hidden">Bộ lọc</button>
            <div class="mt-4 space-y-6 lg:mt-0" :class="open ? 'block' : 'hidden lg:block'">
                <!-- Search -->
                <form method="GET" class="card p-5">
                    @if($category)<input type="hidden" name="category_id" value="{{ $category->id }}">@endif
                    @if(request()->has('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif
                    <label class="label">Tìm kiếm</label>
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Tên sản phẩm..." class="input">
                    <button type="submit" class="btn-brand btn-sm mt-3 w-full">Lọc</button>
                </form>

                <!-- Categories -->
                <div class="card p-5">
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-ink-900">Danh mục</h3>
                    <ul class="space-y-2 text-sm">
                        <li>
                            <a href="{{ route('shop.index') }}" class="flex items-center justify-between text-ink-700 hover:text-brand-700 {{ !$category ? 'font-semibold text-brand-700' : '' }}">Tất cả</a>
                        </li>
                        @foreach($categories as $cat)
                            <li>
                                <a href="{{ route('shop.category', $cat->slug) }}" class="flex items-center justify-between text-ink-700 hover:text-brand-700 {{ $category && $category->id === $cat->id ? 'font-semibold text-brand-700' : '' }}">
                                    <span class="flex items-center gap-2">
                                        <span class="grid h-6 w-6 place-items-center text-brand-600"><x-category-icon :name="$cat->icon" :image="$cat->icon_image_url" size="h-5 w-5" /></span>
                                        {{ $cat->name }}
                                    </span>
                                    <span class="text-xs text-ink-500">{{ $cat->products_count ?? '' }}</span>
                                </a>
                                @if($cat->children->isNotEmpty())
                                    <ul class="mt-1.5 space-y-1.5 pl-4">
                                        @foreach($cat->children as $child)
                                            <li><a href="{{ route('shop.category', $child->slug) }}" class="flex items-center justify-between text-xs text-ink-500 hover:text-brand-700">{{ $child->name }}</a></li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>

                <!-- Price -->
                <form method="GET" class="card p-5">
                    @if($category)<input type="hidden" name="category_id" value="{{ $category->id }}">@endif
                    @if(request()->has('q'))<input type="hidden" name="q" value="{{ request('q') }}">@endif
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-ink-900">Khoảng giá</h3>
                    <div class="flex items-center gap-2">
                        <input type="number" name="min_price" value="{{ request('min_price') }}" placeholder="Từ" class="input !py-2" min="0">
                        <span class="text-ink-500">–</span>
                        <input type="number" name="max_price" value="{{ request('max_price') }}" placeholder="Đến" class="input !py-2" min="0">
                    </div>
                    <button type="submit" class="btn-outline btn-sm mt-3 w-full">Áp dụng</button>
                </form>

                <!-- Brand -->
                @if($brands->isNotEmpty())
                <div class="card p-5">
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-ink-900">Thương hiệu</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach($brands as $brand)
                            <a href="{{ route('shop.index', ['brand' => $brand] + request()->except(['page','brand'])) }}" class="chip {{ request('brand') === $brand ? '!border-brand-600 !text-brand-700' : '' }}">{{ $brand }}</a>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </aside>

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