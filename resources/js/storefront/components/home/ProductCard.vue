<script setup>
import { computed } from 'vue';
import { useStorefrontStore } from '../../store.js';
import { formatMoney } from '../../composables/useFormat.js';
import Icon from '../ui/Icon.vue';

const props = defineProps({
    product: { type: Object, required: true },
});

const store = useStorefrontStore();

const price = computed(() => formatMoney(props.product.price));
const compare = computed(() => (props.product.compare_price ? formatMoney(props.product.compare_price) : ''));
const onSale = computed(() => !!props.product.compare_price);
const discount = computed(() => props.product.discount_percent || 0);
const isWishlisted = computed(() => store.wishlistHas(props.product.id));

// A second image (first gallery photo different from the cover) shown on hover.
const hoverImage = computed(() => {
    const g = props.product.gallery || [];
    const first = g.find((img) => img !== props.product.image);
    return first || '';
});
const hasHoverImage = computed(() => !!hoverImage.value);

function addToCart() {
    store.addToCart(props.product.id, 1);
}
</script>

<template>
    <div class="group relative flex flex-col">
        <!-- Image -->
        <a
            :href="product.url"
            class="relative block overflow-hidden rounded-[1.75rem] bg-cream-100 ring-1 ring-cream-200/70 transition-shadow duration-300 group-hover:ring-brand-300/40 group-hover:shadow-lg group-hover:shadow-ink-900/10"
        >
            <div class="relative aspect-[4/5] w-full overflow-hidden">
                <img
                    :src="product.image"
                    :alt="product.name"
                    loading="lazy"
                    class="h-full w-full object-cover transition-all duration-700 ease-out group-hover:scale-[1.06]"
                    :class="hasHoverImage ? 'group-hover:opacity-0' : ''"
                />
                <img
                    v-if="hasHoverImage"
                    :src="hoverImage"
                    :alt="product.name"
                    loading="lazy"
                    class="absolute inset-0 h-full w-full object-cover opacity-0 scale-110 transition-all duration-700 ease-out group-hover:opacity-100 group-hover:scale-100"
                />
            </div>

            <!-- Sale / sold-out badges -->
            <div class="absolute left-3 top-3 flex flex-col items-start gap-1.5">
                <span v-if="onSale" class="sf-badge bg-gradient-to-r from-clay-500 to-clay-600 text-white shadow-sm">
                    -{{ discount }}%
                </span>
                <span v-if="!product.in_stock" class="sf-badge bg-ink-900/80 text-cream-50 backdrop-blur">
                    Hết hàng
                </span>
            </div>

            <!-- Wishlist (always visible) -->
            <button
                @click.prevent.stop="store.toggleWishlist(product.id)"
                class="absolute right-3 top-3 grid h-9 w-9 place-items-center rounded-full glass-strong text-ink-900 shadow-md transition-transform duration-200 active:scale-90"
                :class="isWishlisted ? 'scale-100 text-clay-500' : 'hover:scale-110 hover:text-clay-500'"
                :aria-label="isWishlisted ? 'Bỏ yêu thích' : 'Yêu thích'"
            >
                <Icon name="heart" :size="17" :fill="true" :stroke-width="0" />
            </button>

            <!-- Hover quick-add overlay (desktop) -->
            <div class="absolute inset-x-3 bottom-3 hidden translate-y-3 items-center gap-2 opacity-0 transition-all duration-300 group-hover:translate-y-0 group-hover:opacity-100 sm:flex">
                <button
                    v-if="product.in_stock"
                    @click.prevent.stop="addToCart"
                    class="sf-btn sf-btn-primary flex-1 !py-2.5 shadow-xl"
                >
                    <Icon name="cart" :size="17" />
                    Thêm nhanh
                </button>
                <button
                    @click.prevent.stop="store.quickViewProduct = product"
                    class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-ink-900/70 text-cream-50 shadow-lg backdrop-blur transition hover:bg-brand-600"
                    title="Xem nhanh"
                    aria-label="Xem nhanh"
                >
                    <Icon name="eye" :size="18" />
                </button>
            </div>

            <!-- Mobile always-on add button -->
            <button
                v-if="product.in_stock"
                @click.prevent.stop="addToCart"
                class="absolute bottom-3 right-3 grid h-10 w-10 place-items-center rounded-full glass-strong text-ink-900 shadow-lg transition-transform active:scale-90 sm:hidden"
                aria-label="Thêm nhanh"
            >
                <Icon name="plus" :size="20" />
            </button>
        </a>

        <!-- Meta -->
        <div class="flex flex-1 flex-col px-1 pt-3.5">
            <a :href="product.url" class="line-clamp-2 text-sm font-medium leading-snug text-ink-900 transition hover:text-brand-700">
                {{ product.name }}
            </a>

            <div class="mt-1.5 flex items-center gap-1">
                <div class="flex items-center gap-0.5 text-amber-400">
                    <Icon
                        v-for="i in 5"
                        :key="i"
                        name="star"
                        :size="13"
                        :fill="true"
                        :stroke-width="0"
                        :class="i <= Math.round(Number(product.rating_avg || 0)) ? 'text-amber-400' : 'text-cream-300'"
                    />
                </div>
                <span v-if="product.rating_count > 0" class="text-xs text-ink-500">({{ product.rating_count }})</span>
            </div>

            <div class="mt-2 flex items-baseline gap-2">
                <span class="font-semibold text-ink-900">{{ price }}</span>
                <span v-if="onSale" class="text-sm text-ink-500 line-through">{{ compare }}</span>
            </div>
        </div>
    </div>
</template>
