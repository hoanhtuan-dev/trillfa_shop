<script setup>
import { ref, onMounted, watch } from 'vue';
import { useStorefrontStore } from './store.js';
import { apiFetch } from './composables/useApi.js';
import StorefrontLayout from './components/layout/StorefrontLayout.vue';
import ProductCard from './components/home/ProductCard.vue';
import Icon from './components/ui/Icon.vue';

const store = useStorefrontStore();
store.ensureBoot();

const products = ref([]);
const loading = ref(true);

async function load() {
    const ids = store.wishlist;
    if (!ids.length) { products.value = []; loading.value = false; return; }
    loading.value = true;
    try {
        const data = await apiFetch('/api/storefront/wishlist?ids=' + encodeURIComponent(ids.join(',')));
        products.value = data.products || [];
    } catch (e) {
        products.value = [];
    } finally {
        loading.value = false;
    }
}

watch(() => store.wishlist.length, load);
onMounted(() => { load(); store.fetchCart(); });
</script>

<template>
    <StorefrontLayout>
        <div class="sf-container py-8 sm:py-10">
            <div class="mb-6 flex items-end justify-between">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-brand-600">Trillfa Fa</p>
                    <h1 class="mt-1 font-display text-3xl font-semibold text-ink-900 sm:text-4xl">Danh sách yêu thích</h1>
                    <p class="mt-1.5 text-sm text-ink-500">{{ products.length }} sản phẩm</p>
                </div>
            </div>

            <div v-if="loading" class="animate-pulse">
                <div class="grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 lg:grid-cols-4">
                    <div v-for="i in 8" :key="i" class="aspect-[4/5] rounded-[1.75rem] bg-cream-200/70"></div>
                </div>
            </div>

            <div v-else-if="products.length" class="grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 lg:grid-cols-4">
                <ProductCard v-for="p in products" :key="p.id" :product="p" />
            </div>

            <div v-else class="card-surface flex flex-col items-center gap-4 rounded-[2rem] p-12 text-center text-ink-500">
                <div class="grid h-16 w-16 place-items-center rounded-full bg-cream-100"><Icon name="heart" :size="28" /></div>
                <p class="text-sm">Chưa có sản phẩm nào trong danh sách yêu thích.</p>
                <a href="/shop" class="sf-btn sf-btn-primary mt-1">Khám phá sản phẩm</a>
            </div>
        </div>
    </StorefrontLayout>
</template>
