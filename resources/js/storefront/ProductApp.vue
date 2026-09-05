<script setup>
import { ref, computed, onMounted } from 'vue';
import { useStorefrontStore } from './store.js';
import { formatMoney } from './composables/useFormat.js';
import { useRecentlyViewed } from './composables/useRecentlyViewed.js';
import StorefrontLayout from './components/layout/StorefrontLayout.vue';
import ProductCard from './components/home/ProductCard.vue';
import SectionHeading from './components/ui/SectionHeading.vue';
import BaseButton from './components/ui/BaseButton.vue';
import BaseBadge from './components/ui/BaseBadge.vue';
import Icon from './components/ui/Icon.vue';
import ProductGallery from './components/product/ProductGallery.vue';

const store = useStorefrontStore();
store.ensureBoot();

const boot = window.__STORE_BOOT__ || {};
const product = boot.product || {};
const related = boot.related || [];

const images = computed(() => {
    const imgs = [product.image];
    (product.gallery || []).forEach((g) => { if (g && !imgs.includes(g)) imgs.push(g); });
    return imgs;
});

const activeImg = ref(0);
const qty = ref(1);
const selectedVariantId = ref(product.variants?.[0]?.id || null);

const variant = computed(() => (product.variants || []).find((v) => v.id === selectedVariantId.value) || null);
const currentPrice = computed(() => (variant.value ? variant.value.price : product.price) || 0);
const currentCompare = computed(() => product.compare_price || null);
const onSale = computed(() => currentCompare.value && currentCompare.value > currentPrice.value);
const discount = computed(() => (onSale.value ? Math.round((1 - currentPrice.value / currentCompare.value) * 100) : 0));

const activeVariant = computed(() => variant.value || product.variants?.[0]);
const activeVariantImage = computed(() => activeVariant.value?.id ? null : null);

function selectVariant(v) {
    selectedVariantId.value = v.id;
    const idx = images.value.indexOf(v?.img || '');
}

function incQty() { if (qty.value < 99) qty.value += 1; }
function decQty() { if (qty.value > 1) qty.value -= 1; }
function priceForVariant(v) { return variant.value?.id === v.id ? currentPrice.value : v.price; }

function addToCart() {
    store.addToCart(product.id, qty.value);
}

function toggleWishlist() { store.toggleWishlist(product.id); }

const ratingText = computed(() => (product.rating_avg ? product.rating_avg.toFixed(1) : ''));

// Free-shipping progress (based on current cart subtotal).
const freeShipThreshold = computed(() => Number(store.site.free_shipping_threshold || 0));
const freeShipProgress = computed(() => {
    if (!freeShipThreshold.value) return 0;
    return Math.min(100, Math.round((store.cart.subtotal / freeShipThreshold.value) * 100));
});
const remainingForFree = computed(() => {
    if (!freeShipThreshold.value) return 0;
    return Math.max(0, freeShipThreshold.value - store.cart.subtotal);
});

onMounted(() => {
    store.fetchCart();
    useRecentlyViewed().add(product);
});
</script>

<template>
    <StorefrontLayout>
        <div class="sf-container py-6 sm:py-10">
            <!-- Breadcrumb -->
            <nav class="mb-5 flex items-center gap-1.5 text-xs text-ink-500">
                <a href="/" class="transition hover:text-brand-700">Trang chủ</a>
                <Icon name="chevron-right" :size="12" />
                <a href="/shop" class="transition hover:text-brand-700">Cửa hàng</a>
                <template v-if="product.category">
                    <Icon name="chevron-right" :size="12" />
                    <span class="text-ink-700">{{ product.category }}</span>
                </template>
            </nav>

            <div class="grid gap-8 lg:grid-cols-2 lg:gap-12">
                <!-- Gallery -->
                <div>
                    <div class="card-surface relative overflow-hidden rounded-[2rem]">
                        <ProductGallery :src="images[activeImg] || product.image" :alt="product.name" />
                        <BaseBadge v-if="onSale" variant="clay" class="absolute left-4 top-4">-{{ discount }}%</BaseBadge>
                        <button @click="toggleWishlist" class="glass-strong absolute right-4 top-4 grid h-10 w-10 place-items-center rounded-full text-ink-900 transition hover:scale-110" :class="store.wishlistHas(product.id) ? 'text-clay-500' : ''" aria-label="Yêu thích">
                            <Icon name="heart" :size="18" :fill="true" :stroke-width="0" />
                        </button>
                    </div>
                    <div v-if="images.length > 1" class="mt-3 flex gap-3 overflow-x-auto no-scrollbar">
                        <button
                            v-for="(img, i) in images"
                            :key="i"
                            @click="activeImg = i"
                            class="h-20 w-20 shrink-0 overflow-hidden rounded-2xl border-2 transition"
                            :class="i === activeImg ? 'border-brand-500' : 'border-transparent opacity-70 hover:opacity-100'"
                        >
                            <img :src="img" :alt="product.name" class="h-full w-full object-cover" />
                        </button>
                    </div>
                </div>

                <!-- Info -->
                <div>
                    <p v-if="product.brand" class="text-[11px] font-semibold uppercase tracking-[0.22em] text-brand-600">{{ product.brand }}</p>
                    <h1 class="mt-2 font-display text-3xl font-semibold text-ink-900 sm:text-4xl">{{ product.name }}</h1>

                    <div class="mt-3 flex items-center gap-3">
                        <div class="flex items-center gap-0.5 text-amber-400">
                            <Icon v-for="i in 5" :key="i" name="star" :size="16" :fill="true" :stroke-width="0" :class="i <= Math.round(Number(product.rating_avg || 0)) ? 'text-amber-400' : 'text-cream-300'" />
                        </div>
                        <span v-if="product.rating_count" class="text-sm text-ink-500">{{ ratingText }} ({{ product.rating_count }} đánh giá)</span>
                    </div>

                    <div class="mt-5 flex items-baseline gap-3">
                        <span class="font-display text-3xl font-semibold text-ink-900">{{ formatMoney(currentPrice) }}</span>
                        <span v-if="onSale" class="text-lg text-ink-500 line-through">{{ formatMoney(currentCompare) }}</span>
                    </div>

                    <p v-if="product.short_description" class="mt-5 leading-relaxed text-ink-700">{{ product.short_description }}</p>

                    <!-- Free-shipping progress -->
                    <div v-if="freeShipThreshold" class="mt-5 rounded-2xl border border-cream-200 bg-white/60 p-3">
                        <p class="text-xs text-ink-500">
                            <template v-if="remainingForFree > 0">Mua thêm <span class="font-semibold text-brand-700">{{ formatMoney(remainingForFree) }}</span> để được miễn phí vận chuyển.</template>
                            <template v-else>🎉 Bạn đủ điều kiện miễn phí vận chuyển!</template>
                        </p>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-cream-200">
                            <div class="h-full rounded-full bg-gradient-to-r from-brand-600 to-brand-500 transition-all" :style="{ width: freeShipProgress + '%' }"></div>
                        </div>
                    </div>

                    <!-- Variants -->
                    <div v-if="product.variants && product.variants.length" class="mt-6">
                        <p class="text-sm font-semibold text-ink-900">Lựa chọn</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button
                                v-for="v in product.variants"
                                :key="v.id"
                                @click="selectedVariantId = v.id"
                                class="rounded-full border px-4 py-2 text-sm font-medium transition"
                                :class="selectedVariantId === v.id ? 'border-brand-500 bg-brand-600/10 text-brand-700' : 'border-cream-200 bg-white/60 text-ink-700 hover:border-brand-300'"
                            >{{ v.name }}</button>
                        </div>
                    </div>

                    <!-- Quantity + Add -->
                    <div class="mt-6 flex flex-wrap items-center gap-3">
                        <div class="inline-flex items-center rounded-full border border-cream-200 bg-white">
                            <button @click="decQty" class="grid h-11 w-11 place-items-center rounded-full text-ink-700 transition hover:bg-cream-100" aria-label="Giảm"><Icon name="minus" :size="18" /></button>
                            <span class="min-w-10 text-center text-base font-semibold">{{ qty }}</span>
                            <button @click="incQty" class="grid h-11 w-11 place-items-center rounded-full text-ink-700 transition hover:bg-cream-100" aria-label="Tăng"><Icon name="plus" :size="18" /></button>
                        </div>
                        <BaseButton v-if="product.in_stock" @click="addToCart" variant="primary" size="lg" class="flex-1">
                            <Icon name="cart" :size="18" /> Thêm vào giỏ
                        </BaseButton>
                        <BaseButton v-else variant="dark" size="lg" class="flex-1" disabled>Hết hàng</BaseButton>
                    </div>

                    <!-- Attributes -->
                    <div v-if="product.attributes && Object.keys(product.attributes).length" class="mt-7 grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <div v-for="(val, key) in product.attributes" :key="key" class="rounded-2xl border border-cream-200 bg-white/60 px-3 py-2.5">
                            <p class="text-[11px] uppercase tracking-wide text-ink-500">{{ key }}</p>
                            <p class="mt-0.5 text-sm font-medium text-ink-900">{{ val }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Description -->
            <div class="mt-12 grid gap-10 lg:grid-cols-[1fr_320px]">
                <div class="prose-content max-w-3xl" v-html="product.description"></div>
                <!-- Reviews -->
                <aside>
                    <h2 class="font-display text-xl font-semibold text-ink-900">Đánh giá</h2>
                    <div v-if="product.reviews && product.reviews.length" class="mt-4 space-y-4">
                        <div v-for="(r, i) in product.reviews" :key="i" class="card-surface rounded-2xl p-4">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-semibold text-ink-900">{{ r.name }}</p>
                                <span class="text-xs text-ink-500">{{ r.created_at }}</span>
                            </div>
                            <div class="mt-1 flex items-center gap-0.5 text-amber-400">
                                <Icon v-for="s in r.rating" :key="s" name="star" :size="13" :fill="true" :stroke-width="0" />
                            </div>
                            <p class="mt-2 text-sm font-medium text-ink-900">{{ r.title }}</p>
                            <p class="mt-1 text-sm text-ink-500">{{ r.body }}</p>
                        </div>
                    </div>
                    <p v-else class="mt-3 text-sm text-ink-500">Chưa có đánh giá nào.</p>
                </aside>
            </div>

            <!-- Related -->
            <section v-if="related.length" v-reveal class="mt-14">
                <SectionHeading kicker="Tuyển chọn" title="Sản phẩm liên quan" link-text="Xem tất cả" link-href="/shop" />
                <div class="grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 lg:grid-cols-4">
                    <ProductCard v-for="p in related" :key="p.id" :product="p" />
                </div>
            </section>
        </div>
    </StorefrontLayout>
</template>
