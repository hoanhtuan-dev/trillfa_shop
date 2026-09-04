<script setup>
import { ref, computed } from 'vue';
import { useStorefrontStore } from '../../store.js';
import { formatMoney } from '../../composables/useFormat.js';
import Icon from '../ui/Icon.vue';

const props = defineProps({
    product: { type: Object, default: null },
});
const emit = defineEmits(['close']);

const store = useStorefrontStore();
const qty = ref(1);
const selectedVariantId = ref(props.product?.variants?.[0]?.id || null);

const variant = computed(() => (props.product?.variants || []).find((v) => v.id === selectedVariantId.value) || null);
const price = computed(() => (variant.value ? variant.value.price : props.product?.price) || 0);
const compare = computed(() => props.product?.compare_price || null);
const onSale = computed(() => compare.value && compare.value > price.value);

function addToCart() {
    store.addToCart(props.product.id, qty.value, props.product);
    emit('close');
}
function toggleWishlist() { store.toggleWishlist(props.product.id); }
</script>

<template>
    <Teleport to="body">
        <Transition name="quickview">
            <div v-if="product" class="fixed inset-0 z-[92] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-ink-900/50 backdrop-blur-sm" @click="$emit('close')"></div>
                <div class="relative w-full max-w-3xl overflow-hidden rounded-[2rem] bg-white shadow-2xl">
                    <button @click="$emit('close')" class="glass-strong absolute right-3 top-3 z-10 grid h-9 w-9 place-items-center rounded-full text-ink-700" aria-label="Đóng"><Icon name="x" :size="18" /></button>
                    <div class="grid max-h-[90vh] overflow-y-auto sm:grid-cols-2">
                        <!-- Image -->
                        <div class="relative bg-cream-100">
                            <img :src="product.image" :alt="product.name" class="aspect-[4/5] w-full object-cover" />
                            <span v-if="onSale" class="sf-badge absolute left-3 top-3 bg-gradient-to-r from-clay-500 to-clay-600 text-white">-{{ Math.round((1 - price / compare) * 100) }}%</span>
                        </div>
                        <!-- Info -->
                        <div class="flex flex-col p-6">
                            <p v-if="product.brand" class="text-[11px] font-semibold uppercase tracking-[0.22em] text-brand-600">{{ product.brand }}</p>
                            <h2 class="mt-1 font-display text-2xl font-semibold leading-snug text-ink-900">{{ product.name }}</h2>
                            <div class="mt-2 flex items-center gap-2">
                                <div class="flex items-center gap-0.5 text-amber-400"><Icon v-for="i in 5" :key="i" name="star" :size="13" :fill="true" :stroke-width="0" :class="i <= Math.round(Number(product.rating_avg || 0)) ? 'text-amber-400' : 'text-cream-300'" /></div>
                                <span v-if="product.rating_count" class="text-xs text-ink-500">({{ product.rating_count }})</span>
                            </div>
                            <div class="mt-3 flex items-baseline gap-2">
                                <span class="font-display text-2xl font-semibold text-ink-900">{{ formatMoney(price) }}</span>
                                <span v-if="onSale" class="text-sm text-ink-500 line-through">{{ formatMoney(compare) }}</span>
                            </div>
                            <p v-if="product.short_description" class="mt-3 line-clamp-3 text-sm leading-relaxed text-ink-500">{{ product.short_description }}</p>

                            <div v-if="product.variants && product.variants.length" class="mt-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-ink-700">Lựa chọn</p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <button v-for="v in product.variants" :key="v.id" @click="selectedVariantId = v.id" class="rounded-full border px-3 py-1.5 text-sm font-medium transition" :class="selectedVariantId === v.id ? 'border-brand-500 bg-brand-600/10 text-brand-700' : 'border-cream-200 text-ink-700 hover:border-brand-300'">{{ v.name }}</button>
                                </div>
                            </div>

                            <div class="mt-5 flex items-center gap-3">
                                <div class="inline-flex items-center rounded-full border border-cream-200 bg-white">
                                    <button @click="qty = Math.max(1, qty - 1)" class="grid h-10 w-10 place-items-center rounded-full text-ink-700 hover:bg-cream-100" aria-label="Giảm"><Icon name="minus" :size="15" /></button>
                                    <span class="min-w-8 text-center font-semibold">{{ qty }}</span>
                                    <button @click="qty = Math.min(99, qty + 1)" class="grid h-10 w-10 place-items-center rounded-full text-ink-700 hover:bg-cream-100" aria-label="Tăng"><Icon name="plus" :size="15" /></button>
                                </div>
                                <button @click="addToCart" class="sf-btn sf-btn-primary flex-1 !py-3"><Icon name="cart" :size="17" /> Thêm vào giỏ</button>
                                <button @click="toggleWishlist" class="grid h-11 w-11 shrink-0 place-items-center rounded-full glass-strong text-ink-900" :class="store.wishlistHas(product.id) ? 'text-clay-500' : ''" aria-label="Yêu thích"><Icon name="heart" :size="18" :fill="true" :stroke-width="0" /></button>
                            </div>
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
