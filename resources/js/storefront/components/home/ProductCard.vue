<script setup>
import { computed } from 'vue';
import { useStorefrontStore } from '../../store.js';
import { formatMoney } from '../../composables/useFormat.js';
import Icon from '../ui/Icon.vue';
import BaseBadge from '../ui/BaseBadge.vue';

const props = defineProps({
    product: { type: Object, required: true },
});

const store = useStorefrontStore();

const price = computed(() => formatMoney(props.product.price));
const compare = computed(() => (props.product.compare_price ? formatMoney(props.product.compare_price) : ''));
const onSale = computed(() => !!props.product.compare_price);
const discount = computed(() => props.product.discount_percent || 0);
const isWishlisted = computed(() => store.wishlistHas(props.product.id));

function addToCart() {
    store.addToCart(props.product.id, 1);
}
</script>

<template>
    <div class="group relative flex flex-col">
        <a :href="product.url" class="relative block overflow-hidden rounded-[1.75rem] bg-cream-100">
            <div class="aspect-[4/5] w-full overflow-hidden">
                <img
                    :src="product.image"
                    :alt="product.name"
                    loading="lazy"
                    class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                />
            </div>

            <BaseBadge v-if="onSale" variant="clay" class="absolute left-3 top-3">-{{ discount }}%</BaseBadge>
            <BaseBadge v-if="!product.in_stock" variant="ink" class="absolute right-3 top-3">Hết hàng</BaseBadge>

            <!-- Glass quick actions -->
            <div class="absolute inset-x-3 bottom-3 flex translate-y-3 items-center justify-center gap-2 opacity-0 transition-all duration-300 group-hover:translate-y-0 group-hover:opacity-100">
                <button
                    v-if="product.in_stock"
                    @click.prevent.stop="addToCart"
                    class="sf-btn sf-btn-primary flex-1 !py-2.5 shadow-lg"
                >
                    <Icon name="cart" :size="17" />
                    Thêm nhanh
                </button>
                <button
                    @click.prevent.stop="store.toggleWishlist(product.id)"
                    class="grid h-10 w-10 shrink-0 place-items-center rounded-full glass-strong text-ink-900 shadow-lg transition-colors"
                    :class="isWishlisted ? 'text-clay-500' : 'hover:text-clay-500'"
                    :aria-label="isWishlisted ? 'Bỏ yêu thích' : 'Yêu thích'"
                >
                    <Icon name="heart" :size="18" :fill="true" :stroke-width="0" />
                </button>
            </div>

            <!-- Mobile always-on add button (thumb-friendly) -->
            <button
                v-if="product.in_stock"
                @click.prevent.stop="addToCart"
                class="absolute bottom-3 right-3 grid h-10 w-10 place-items-center rounded-full glass-strong text-ink-900 shadow-lg sm:hidden"
                aria-label="Thêm nhanh"
            >
                <Icon name="plus" :size="20" />
            </button>
        </a>

        <div class="flex flex-1 flex-col px-1 pt-4">
            <a :href="product.url" class="text-sm font-medium leading-snug text-ink-900 transition hover:text-brand-700">
                {{ product.name }}
            </a>

            <!-- Rating -->
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
