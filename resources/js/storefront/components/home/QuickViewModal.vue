<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useStorefrontStore } from '../../store.js';
import { apiFetch } from '../../composables/useApi.js';
import { formatMoney } from '../../composables/useFormat.js';
import Icon from '../ui/Icon.vue';

const props = defineProps({
    product: { type: Object, default: null },
});
const emit = defineEmits(['close']);

const store = useStorefrontStore();

const product = ref(props.product || {});
const loading = ref(false);
const qty = ref(1);
const selectedVariantId = ref(null);

// Zoom-on-hover magnifier state.
const zooming = ref(false);
const origin = ref({ x: 50, y: 50 });

const images = computed(() => {
    const arr = [];
    if (product.value?.image) arr.push(product.value.image);
    (product.value?.gallery || []).forEach((g) => { if (g && !arr.includes(g)) arr.push(g); });
    return arr;
});
const activeImg = ref(0);

const variant = computed(() => (product.value?.variants || []).find((v) => v.id === selectedVariantId.value) || null);
const price = computed(() => (variant.value ? variant.value.price : product.value?.price) || 0);
const compare = computed(() => product.value?.compare_price || null);
const onSale = computed(() => compare.value && compare.value > price.value);
const stockText = computed(() => {
    const s = (variant.value?.stock ?? product.value?.total_stock) || 0;
    if (!product.value?.in_stock && s <= 0) return 'Hết hàng';
    if (s > 0 && s <= 5) return `Sắp hết (${s})`;
    return 'Còn hàng';
});
const attrs = computed(() => product.value?.attributes || {});
const discountPct = computed(() => {
    if (onSale.value && compare.value) return Math.round((1 - price.value / compare.value) * 100);
    return product.value?.discount_percent || 0;
});

async function load() {
    if (!props.product?.id) return;
    loading.value = true;
    try {
        const data = await apiFetch('/api/storefront/product/' + props.product.id);
        product.value = { ...props.product, ...data };
        selectedVariantId.value = data.variants?.[0]?.id || null;
    } catch (e) {
        product.value = { ...props.product };
        selectedVariantId.value = props.product.variants?.[0]?.id || null;
    } finally {
        loading.value = false;
    }
}
onMounted(load);
watch(() => props.product?.id, () => { activeImg.value = 0; qty.value = 1; load(); });

function onZoomMove(e) {
    const el = e.currentTarget;
    const rect = el.getBoundingClientRect();
    const x = ((e.clientX - rect.left) / rect.width) * 100;
    const y = ((e.clientY - rect.top) / rect.height) * 100;
    origin.value = { x: Math.min(100, Math.max(0, x)), y: Math.min(100, Math.max(0, y)) };
}
function addToCart() {
    store.addToCart(product.value.id, qty.value, product.value);
    emit('close');
}
function toggleWishlist() { store.toggleWishlist(product.value.id); }
</script>

<template>
    <Teleport to="body">
        <Transition name="quickview">
            <div v-if="product" class="fixed inset-0 z-[92] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-ink-900/55 backdrop-blur-sm" @click="$emit('close')"></div>
                <div class="relative w-full max-w-4xl overflow-hidden rounded-[2rem] bg-white shadow-2xl">
                    <button @click="$emit('close')" class="glass-strong absolute right-3 top-3 z-20 grid h-9 w-9 place-items-center rounded-full text-ink-700" aria-label="Đóng"><Icon name="x" :size="18" /></button>

                    <div class="grid max-h-[92vh] overflow-y-auto sm:grid-cols-2">
                        <!-- Gallery + zoom -->
                        <div>
                            <div
                                class="relative cursor-zoom-in overflow-hidden bg-cream-100"
                                @mouseenter="zooming = true"
                                @mouseleave="zooming = false; origin = { x: 50, y: 50 }"
                                @mousemove="onZoomMove"
                            >
                                <img
                                    :src="images[activeImg] || product.image"
                                    :alt="product.name"
                                    class="aspect-[4/5] w-full object-cover"
                                    :style="{
                                        transform: zooming ? 'scale(2.1)' : 'scale(1)',
                                        transformOrigin: origin.x + '% ' + origin.y + '%',
                                        transition: 'transform 0.18s ease-out',
                                    }"
                                />
                                <span v-if="onSale" class="sf-badge absolute left-3 top-3 bg-gradient-to-r from-clay-500 to-clay-600 text-white">-{{ discountPct }}%</span>
                                <span class="pointer-events-none absolute bottom-3 right-3 rounded-full bg-ink-900/60 px-2.5 py-1 text-[10px] font-medium text-cream-50 backdrop-blur">+ Hover để phóng to</span>
                            </div>
                            <div v-if="images.length > 1" class="flex gap-2 p-3">
                                <button v-for="(img, i) in images" :key="i" @click="activeImg = i" class="h-16 w-16 shrink-0 overflow-hidden rounded-xl border-2 transition" :class="i === activeImg ? 'border-brand-500' : 'border-transparent opacity-70 hover:opacity-100'">
                                    <img :src="img" :alt="product.name" class="h-full w-full object-cover" />
                                </button>
                            </div>
                        </div>

                        <!-- Info -->
                        <div class="flex flex-col p-6">
                            <div v-if="loading" class="animate-pulse space-y-3"><div class="h-4 w-1/3 rounded bg-cream-200"></div><div class="h-6 w-2/3 rounded bg-cream-200"></div><div class="h-4 w-1/2 rounded bg-cream-200"></div></div>
                            <template v-else>
                                <div class="flex items-center gap-2 text-xs text-ink-500">
                                    <span v-if="product.brand" class="font-semibold uppercase tracking-wide text-brand-600">{{ product.brand }}</span>
                                    <span v-if="product.category">· {{ product.category }}</span>
                                </div>
                                <h2 class="mt-1 font-display text-2xl font-semibold leading-snug text-ink-900">{{ product.name }}</h2>

                                <div class="mt-2 flex items-center gap-2">
                                    <div class="flex items-center gap-0.5 text-amber-400"><Icon v-for="i in 5" :key="i" name="star" :size="13" :fill="true" :stroke-width="0" :class="i <= Math.round(Number(product.rating_avg || 0)) ? 'text-amber-400' : 'text-cream-300'" /></div>
                                    <span v-if="product.rating_count" class="text-xs text-ink-500">({{ product.rating_count }})</span>
                                    <span class="ml-auto text-xs text-ink-500">SKU: {{ product.sku || '—' }}</span>
                                </div>

                                <div class="mt-3 flex items-baseline gap-2">
                                    <span class="font-display text-2xl font-semibold text-ink-900">{{ formatMoney(price) }}</span>
                                    <span v-if="onSale" class="text-sm text-ink-500 line-through">{{ formatMoney(compare) }}</span>
                                </div>

                                <!-- Stock + shipping estimate -->
                                <p class="mt-2 text-sm" :class="product.in_stock ? 'text-brand-700' : 'text-red-600'">{{ stockText }}</p>
                                <p v-if="product.shipping_estimate" class="mt-1 inline-flex items-center gap-1.5 text-xs text-ink-500"><Icon name="truck" :size="15" /> Ước tính giao trong {{ product.shipping_estimate.days }}–{{ product.shipping_estimate.days + 2 }} ngày</p>

                                <p v-if="product.short_description" class="mt-3 line-clamp-3 text-sm leading-relaxed text-ink-500">{{ product.short_description }}</p>

                                <!-- Variants -->
                                <div v-if="product.variants && product.variants.length" class="mt-4">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-ink-700">Lựa chọn</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <button v-for="v in product.variants" :key="v.id" @click="selectedVariantId = v.id" class="rounded-full border px-3 py-1.5 text-sm font-medium transition" :class="selectedVariantId === v.id ? 'border-brand-500 bg-brand-600/10 text-brand-700' : 'border-cream-200 text-ink-700 hover:border-brand-300'">{{ v.name }}</button>
                                    </div>
                                </div>

                                <!-- Specs -->
                                <div v-if="Object.keys(attrs).length" class="mt-4">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-ink-700">Thông số</p>
                                    <div class="mt-2 grid grid-cols-2 gap-2">
                                        <div v-for="(val, key) in attrs" :key="key" class="rounded-xl border border-cream-200 bg-white/60 px-3 py-2"><p class="text-[11px] uppercase tracking-wide text-ink-500">{{ key }}</p><p class="mt-0.5 text-sm font-medium text-ink-900">{{ val }}</p></div>
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="mt-5 flex items-center gap-3">
                                    <div class="inline-flex items-center rounded-full border border-cream-200 bg-white">
                                        <button @click="qty = Math.max(1, qty - 1)" class="grid h-10 w-10 place-items-center rounded-full text-ink-700 hover:bg-cream-100" aria-label="Giảm"><Icon name="minus" :size="15" /></button>
                                        <span class="min-w-8 text-center font-semibold">{{ qty }}</span>
                                        <button @click="qty = Math.min(99, qty + 1)" class="grid h-10 w-10 place-items-center rounded-full text-ink-700 hover:bg-cream-100" aria-label="Tăng"><Icon name="plus" :size="15" /></button>
                                    </div>
                                    <button :disabled="!product.in_stock" @click="addToCart" class="sf-btn sf-btn-primary flex-1 !py-3" :class="{ '!opacity-50 pointer-events-none': !product.in_stock }"><Icon name="cart" :size="17" /> {{ product.in_stock ? 'Thêm vào giỏ' : 'Hết hàng' }}</button>
                                    <button @click="toggleWishlist" class="grid h-11 w-11 shrink-0 place-items-center rounded-full glass-strong text-ink-900" :class="store.wishlistHas(product.id) ? 'text-clay-500' : ''" aria-label="Yêu thích"><Icon name="heart" :size="18" :fill="true" :stroke-width="0" /></button>
                                </div>

                                <a :href="product.url" class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-brand-700 hover:underline">Xem chi tiết sản phẩm <Icon name="arrow-right" :size="15" /></a>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.quickview-enter-active, .quickview-leave-active { transition: opacity 0.2s ease; }
.quickview-enter-from, .quickview-leave-to { opacity: 0; }
</style>
