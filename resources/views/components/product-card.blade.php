@props(['product'])

@php
    $img = $product->image_url ?? (count($product->gallery_urls) ? $product->gallery_urls[0] : asset('images/placeholder.svg'));
    $defaultVariant = $product->variants->first();
    $variantId = $defaultVariant?->id;
    $price = (float) $product->min_price;
    $compare = $product->compare_price ? (float) $product->compare_price : null;
    $onSale = $compare && $compare > $price;
    $discount = $product->discount_percent;
    $inStock = $product->total_stock > 0;
@endphp

<div x-data="productCard" class="group relative flex flex-col">
    <a href="{{ route('product.show', $product->slug) }}" class="relative block overflow-hidden rounded-3xl bg-cream-100">
        <div class="aspect-[4/5] w-full overflow-hidden">
            <img src="{{ $img }}" alt="{{ $product->name }}" loading="lazy" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105">
        </div>

        @if($onSale)
            <span class="badge absolute left-3 top-3 bg-clay-500 text-white">-{{ $discount }}%</span>
        @endif

        @if(!$inStock)
            <span class="badge absolute right-3 top-3 bg-ink-900/80 text-cream-50">Hết hàng</span>
        @endif

        <!-- Quick actions -->
        <div class="absolute inset-x-3 bottom-3 flex translate-y-3 items-center justify-center gap-2 opacity-0 transition-all duration-300 group-hover:translate-y-0 group-hover:opacity-100">
            @if($inStock)
                <button
                    @click.prevent.stop="$store.cart.add({{ $product->id }}, {{ $variantId ?: 'null' }}, 1)"
                    class="btn-primary btn-sm flex-1 shadow-lg"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M5.106 5.272L7.5 14.25m0 0h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84m-1.098 8.978a10.5 10.5 0 11-2.66 4.968M6 20.25a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm12 0a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/></svg>
                    Thêm nhanh
                </button>
            @endif
            <button
                @click.prevent.stop="$store.wishlist.toggle({{ $product->id }})"
                class="grid h-9 w-9 place-items-center rounded-full bg-white text-ink-900 shadow-lg transition hover:text-clay-500"
                aria-label="Yêu thích"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/></svg>
            </button>
        </div>
    </a>

    <div class="flex flex-1 flex-col px-1 pt-4">
        <a href="{{ route('product.show', $product->slug) }}" class="text-sm font-medium text-ink-900 transition hover:text-brand-700">{{ $product->name }}</a>
        <div class="mt-1 flex items-center gap-2">
            <x-rating :value="$product->rating_avg" :count="$product->rating_count" />
        </div>
        <div class="mt-2 flex items-baseline gap-2">
            <span class="font-semibold text-ink-900">{{ format_price($price) }}</span>
            @if($onSale)
                <span class="text-sm text-ink-500 line-through">{{ format_price($compare) }}</span>
            @endif
        </div>
    </div>
</div>