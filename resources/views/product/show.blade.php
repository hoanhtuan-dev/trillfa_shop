@extends('layouts.app')

@section('title', $product->name)
@section('meta_description', $product->meta_description ?? $product->short_description)

@section('content')
@php
    $variants = $product->variants->map(fn($v) => [
        'id' => $v->id, 'name' => $v->name, 'price' => (float) $v->price,
        'compare_price' => $v->compare_price ? (float) $v->compare_price : null,
        'stock' => (int) $v->stock, 'image' => $v->image_url, 'options' => $v->options ?? [],
    ])->values();
    $hasVariants = $variants->isNotEmpty();
    $baseImages = array_values(array_filter([
        $product->image_url,
        ...$product->gallery_urls,
    ]));
@endphp

<div class="container-x py-8">
    <x-breadcrumb :items="[
        ['label' => 'Trang chủ', 'url' => route('home')],
        ['label' => $product->category?->name ?? 'Cửa hàng', 'url' => $product->category ? route('shop.category', $product->category->slug) : route('shop.index')],
        ['label' => $product->name]
    ]" />

    <div class="mt-8 grid gap-10 lg:grid-cols-2" x-data="productDetail({ productId: {{ $product->id }}, hasVariants: {{ $hasVariants ? 'true' : 'false' }}, variants: {{ Js::from($variants) }}, minQty: 1, maxQty: {{ max((int) $product->total_stock, 1) }} })">
        <!-- Gallery -->
        <div class="lg:sticky lg:top-24 lg:self-start" x-data="productGallery({ images: {{ Js::from($baseImages) }} })" @variant-image.window="images.unshift($event.detail); active = 0">
            <div class="relative overflow-hidden rounded-3xl bg-cream-100">
                <img :src="images[active]" :alt="'{{ $product->name }}'" class="aspect-[4/5] w-full object-cover" />
                @if($product->discount_percent > 0)
                    <span class="badge absolute left-4 top-4 bg-clay-500 text-white">-{{ $product->discount_percent }}%</span>
                @endif
                <template x-if="images.length > 1">
                    <div class="absolute inset-x-3 top-1/2 flex -translate-y-1/2 justify-between">
                        <button @click="prev()" class="grid h-10 w-10 place-items-center rounded-full bg-white/80 shadow backdrop-blur transition hover:bg-white"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg></button>
                        <button @click="next()" class="grid h-10 w-10 place-items-center rounded-full bg-white/80 shadow backdrop-blur transition hover:bg-white"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg></button>
                    </div>
                </template>
            </div>
            <div class="mt-3 flex gap-3 overflow-x-auto scrollbar-hide" x-show="images.length > 1">
                <template x-for="(img, idx) in images" :key="idx">
                    <button @click="select(idx)" class="h-20 w-20 shrink-0 overflow-hidden rounded-xl border-2 transition" :class="active === idx ? 'border-brand-600' : 'border-transparent'">
                        <img :src="img" class="h-full w-full object-cover" alt="">
                    </button>
                </template>
            </div>
        </div>

        <!-- Info -->
        <div>
            @if($product->brand)<p class="text-xs font-semibold uppercase tracking-[0.2em] text-ink-500">{{ $product->brand }}</p>@endif
            <h1 class="mt-2 font-display text-3xl font-semibold leading-tight text-ink-900 sm:text-4xl">{{ $product->name }}</h1>

            <div class="mt-3 flex items-center gap-3">
                <x-rating :value="$product->rating_avg" :count="$product->rating_count" />
                <a href="#reviews" class="text-sm text-ink-500 hover:text-brand-700">{{ $product->rating_count }} đánh giá</a>
            </div>

            <div class="mt-5 flex items-baseline gap-3">
                <span class="font-display text-3xl font-semibold text-brand-700" x-text="$money(selectedPrice)"></span>
                <template x-if="selectedCompare && selectedCompare > selectedPrice">
                    <span class="text-lg text-ink-500 line-through" x-text="$money(selectedCompare)"></span>
                </template>
            </div>

            @if($product->short_description)
                <p class="mt-5 leading-relaxed text-ink-700">{{ $product->short_description }}</p>
            @endif

            <!-- Variants -->
            @if($hasVariants)
            <div class="mt-6">
                <p class="label">Biến thể <span class="text-ink-500" x-text="selectedVariantName ? '(' + selectedVariantName + ')' : ''"></span></p>
                <div class="flex flex-wrap gap-2">
                    <template x-for="v in variants" :key="v.id">
                        <button
                            @click="selectVariant(v.id)"
                            class="chip"
                            :class="selectedVariantId === v.id ? '!border-brand-600 !bg-brand-50 !text-brand-800' : ''"
                            :disabled="v.stock <= 0"
                        >
                            <span x-text="v.name"></span>
                            <span x-show="v.stock <= 0" class="text-[10px] text-red-500">hết hàng</span>
                        </button>
                    </template>
                </div>
            </div>
            @endif

            <!-- Quantity + add -->
            <form @submit.prevent="addToCart()" class="mt-8">
                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex items-center rounded-full border border-cream-300">
                        <button type="button" @click="dec()" class="px-4 py-3 text-ink-700 hover:text-ink-900">−</button>
                        <input type="number" x-model.number="quantity" min="1" :max="maxQty" class="w-14 bg-transparent text-center text-sm font-medium outline-none" @change="quantity = Math.min(Math.max(quantity, minQty), maxQty)">
                        <button type="button" @click="inc()" class="px-4 py-3 text-ink-700 hover:text-ink-900">+</button>
                    </div>
                    <button type="submit" :disabled="adding || outOfStock" class="btn-brand flex-1 min-w-[220px]">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M5.106 5.272L7.5 14.25m0 0h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84m-1.098 8.978a10.5 10.5 0 11-2.66 4.968"/></svg>
                        <span x-text="outOfStock ? 'Hết hàng' : 'Thêm vào giỏ'"></span>
                    </button>
                    <button type="button" @click.prevent="$store.wishlist.toggle({{ $product->id }})" class="btn-outline !p-3" aria-label="Yêu thích">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/></svg>
                    </button>
                </div>
                <p class="mt-3 text-sm" :class="outOfStock ? 'text-red-600' : 'text-ink-500'" x-text="stockText"></p>
            </form>

            <!-- Delivery/shipping info -->
            <div class="mt-8 space-y-3 rounded-2xl border border-cream-200 bg-white p-5 text-sm">
                <div class="flex items-center gap-3">
                    <svg class="h-5 w-5 shrink-0 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0c-.566.058-.987.538-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/></svg>
                    <span>Miễn phí vận chuyển với đơn hàng từ {{ setting('free_shipping_threshold') ? number_format((float) setting('free_shipping_threshold')) : '500.000' }}đ</span>
                </div>
                <div class="flex items-center gap-3">
                    <svg class="h-5 w-5 shrink-0 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                    <span>Đổi trả dễ dàng trong 7 ngày</span>
                </div>
                <div class="flex items-center gap-3">
                    <svg class="h-5 w-5 shrink-0 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/></svg>
                    <span>Hỗ trợ khách hàng 24/7</span>
                </div>
            </div>

            <!-- Meta -->
            <div class="mt-6 divide-y divide-cream-200 text-sm">
                <div class="flex py-2.5"><span class="w-28 shrink-0 text-ink-500">Danh mục</span><a href="{{ $product->category ? route('shop.category', $product->category->slug) : route('shop.index') }}" class="text-ink-900 hover:text-brand-700">{{ $product->category?->name ?? 'Chung' }}</a></div>
                @if($product->sku)<div class="flex py-2.5"><span class="w-28 shrink-0 text-ink-500">Mã SKU</span><span class="text-ink-900">{{ $product->sku }}</span></div>@endif
                @if($product->tags)<div class="flex py-2.5"><span class="w-28 shrink-0 text-ink-500">Thẻ</span><div class="flex flex-wrap gap-2">@foreach($product->tags as $tag)<a href="{{ route('shop.index', ['q' => $tag]) }}" class="chip !py-1 text-xs">{{ $tag }}</a>@endforeach</div></div>@endif
            </div>
        </div>
    </div>

    <!-- Description / Attributes / Reviews -->
    <div class="mt-16 grid gap-8 lg:grid-cols-3" x-data="{ tab: 'description' }">
        <div class="lg:col-span-2">
            <div class="flex gap-2 border-b border-cream-200">
                <button @click="tab = 'description'" class="pb-3 text-sm font-semibold" :class="tab === 'description' ? 'border-b-2 border-brand-600 text-ink-900' : 'text-ink-500'">Mô tả</button>
                @if($product->attributes)<button @click="tab = 'attributes'" class="pb-3 text-sm font-semibold" :class="tab === 'attributes' ? 'border-b-2 border-brand-600 text-ink-900' : 'text-ink-500'">Thông số</button>@endif
                <button @click="tab = 'reviews'" class="pb-3 text-sm font-semibold" :class="tab === 'reviews' ? 'border-b-2 border-brand-600 text-ink-900' : 'text-ink-500'">Đánh giá ({{ $product->reviews->count() }})</button>
            </div>

            <div x-show="tab === 'description'" class="prose-content mt-6 max-w-none">
                <div>{!! $product->description ?: '<p>Chưa có mô tả cho sản phẩm này.</p>' !!}</div>
            </div>

            @if($product->attributes)
            <div x-show="tab === 'attributes'" class="mt-6">
                <table class="w-full text-sm">
                    <tbody>
                        @foreach($product->attributes as $key => $value)
                            <tr class="border-b border-cream-200">
                                <td class="w-1/3 py-3 font-medium text-ink-500">{{ $key }}</td>
                                <td class="py-3 text-ink-900">{{ is_array($value) ? implode(', ', $value) : $value }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            <!-- Reviews -->
            <div x-show="tab === 'reviews'" id="reviews" class="mt-6">
                <div class="grid gap-6 sm:grid-cols-[auto_1fr]">
                    <div class="card p-6 text-center sm:w-56">
                        <p class="font-display text-5xl font-semibold text-ink-900">{{ number_format((float) $product->rating_avg, 1) }}</p>
                        <div class="mt-2 flex justify-center"><x-rating :value="$product->rating_avg" /></div>
                        <p class="mt-1 text-xs text-ink-500">{{ $product->rating_count }} đánh giá</p>
                        <div class="mt-4 space-y-1.5">
                            @foreach($groups as $star => $count)
                                <div class="flex items-center gap-2 text-xs">
                                    <span class="w-8 text-ink-500">{{ $star }}★</span>
                                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-cream-100">
                                        <div class="h-full rounded-full bg-amber-400" style="width: {{ $product->rating_count ? round($count / $product->rating_count * 100) : 0 }}%"></div>
                                    </div>
                                    <span class="w-6 text-right text-ink-500">{{ $count }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        @auth
                            <form method="POST" action="{{ route('review.store', $product) }}" class="card mb-6 p-6">
                                @csrf
                                <h3 class="mb-4 font-display text-lg font-semibold text-ink-900">Viết đánh giá</h3>
                                <div class="mb-4 flex items-center gap-1" x-data="{ rating: 5 }">
                                    <span class="mr-2 text-sm text-ink-500">Điểm số:</span>
                                    <template x-for="s in [1,2,3,4,5]" :key="s">
                                        <button type="button" @click="rating = s" class="text-2xl transition" :class="s <= rating ? 'text-amber-400' : 'text-cream-300'">★</button>
                                    </template>
                                    <input type="hidden" name="rating" x-model="rating">
                                </div>
                                <input type="text" name="title" placeholder="Tiêu đề (tùy chọn)" class="input mb-3">
                                <textarea name="body" rows="3" required placeholder="Chia sẻ trải nghiệm của bạn về sản phẩm..." class="input mb-4"></textarea>
                                <button type="submit" class="btn-brand">Gửi đánh giá</button>
                            </form>
                            @if($errors->any())
                                <div class="mb-4 rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ $errors->first() }}</div>
                            @endif
                        @else
                            <p class="mb-6 rounded-xl bg-cream-100 p-4 text-sm text-ink-700">Đăng nhập để viết đánh giá. <a href="{{ route('login') }}" class="link">Đăng nhập</a></p>
                        @endauth

                        @forelse($product->reviews as $review)
                            <div class="border-b border-cream-200 py-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <span class="grid h-9 w-9 place-items-center rounded-full bg-brand-100 font-semibold text-brand-700">{{ strtoupper(substr($review->user?->name ?? 'A', 0, 1)) }}</span>
                                        <div>
                                            <p class="text-sm font-medium text-ink-900">{{ $review->user?->name }}</p>
                                            <div class="flex items-center gap-2 text-xs text-ink-500">
                                                <x-rating :value="$review->rating" />
                                                <span>{{ $review->created_at?->format('d/m/Y') }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <form method="POST" action="{{ route('review.helpful', $review) }}">
                                        @csrf
                                        <button type="submit" class="text-xs text-ink-500 hover:text-brand-700">Hữu ích ({{ $review->helpful_count }})</button>
                                    </form>
                                </div>
                                @if($review->title)<p class="mt-3 text-sm font-semibold text-ink-900">{{ $review->title }}</p>@endif
                                <p class="mt-1 text-sm leading-relaxed text-ink-700">{{ $review->body }}</p>
                            </div>
                        @empty
                            <p class="text-sm text-ink-500">Chưa có đánh giá nào. Hãy là người đầu tiên!</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <!-- Side summary -->
        <div>
            <div class="card sticky top-24 p-6">
                <h4 class="mb-4 font-display text-lg font-semibold text-ink-900">Bạn có thể thích</h4>
                <div class="space-y-4">
                    @foreach($related as $rp)
                        <a href="{{ route('product.show', $rp->slug) }}" class="group flex gap-3">
                            <img src="{{ $rp->image_url ?: asset('images/placeholder.svg') }}" class="h-16 w-16 rounded-2xl object-cover" alt="">
                            <div class="flex-1">
                                <p class="text-sm font-medium text-ink-900 group-hover:text-brand-700 line-clamp-2">{{ $rp->name }}</p>
                                <p class="mt-1 text-sm font-semibold text-brand-700">{{ format_price($rp->min_price) }}</p>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('productDetail', (opts) => ({
            productId: opts.productId,
            hasVariants: opts.hasVariants,
            variants: opts.variants || [],
            selectedVariantId: opts.variants && opts.variants.length ? opts.variants[0].id : null,
            quantity: 1,
            minQty: opts.minQty || 1,
            maxQty: opts.maxQty || 999,
            adding: false,

            get selectedVariant() {
                return this.variants.find(v => v.id === this.selectedVariantId) || null;
            },
            get selectedPrice() {
                return this.selectedVariant ? this.selectedVariant.price : {{ (float) $product->price }};
            },
            get selectedCompare() {
                return this.selectedVariant ? this.selectedVariant.compare_price : {{ $product->compare_price ? (float) $product->compare_price : 'null' }};
            },
            get selectedVariantName() {
                return this.selectedVariant ? this.selectedVariant.name : '';
            },
            get outOfStock() {
                if (this.hasVariants && this.selectedVariant) return this.selectedVariant.stock <= 0;
                return {{ (int) $product->total_stock }} <= 0;
            },
            get stockText() {
                const stock = this.hasVariants && this.selectedVariant ? this.selectedVariant.stock : {{ (int) $product->total_stock }};
                if (stock <= 0) return 'Sản phẩm hiện đã hết hàng.';
                if (stock <= 5) return 'Chỉ còn ' + stock + ' sản phẩm trong kho.';
                return 'Còn hàng · Sẵn sàng giao ngay.';
            },
            selectVariant(id) {
                this.selectedVariantId = id;
                const v = this.variants.find(x => x.id === id);
                if (v && v.image) window.dispatchEvent(new CustomEvent('variant-image', { detail: v.image }));
            },
            inc() { if (this.quantity < this.maxQty) this.quantity++; },
            dec() { if (this.quantity > this.minQty) this.quantity--; },
            async addToCart() {
                if (this.outOfStock) return;
                this.adding = true;
                await Alpine.store('cart').add(this.productId, this.hasVariants ? this.selectedVariantId : null, this.quantity);
                this.adding = false;
            }
        }));
    });
</script>
@endpush
@endsection