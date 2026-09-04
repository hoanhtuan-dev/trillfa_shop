<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useStorefrontStore } from './store.js';
import { apiFetch } from './composables/useApi.js';
import StorefrontLayout from './components/layout/StorefrontLayout.vue';
import ProductCard from './components/home/ProductCard.vue';
import SkeletonCard from './components/ui/SkeletonCard.vue';
import BaseButton from './components/ui/BaseButton.vue';
import Icon from './components/ui/Icon.vue';

const store = useStorefrontStore();
store.ensureBoot();

const boot = window.__STORE_BOOT__ || {};
const slug = boot.slug || '';

// Reactive shop state.
const products = ref(boot.products || []);
const categories = ref(boot.categories || []);
const brands = ref(boot.brands || []);
const total = ref(boot.total || 0);
const lastPage = ref(boot.last_page || 1);
const loading = ref(false);
const filtersOpen = ref(false);

const filters = reactive({
    q: boot.active?.q || '',
    brand: boot.active?.brand || '',
    min_price: boot.active?.min_price || '',
    max_price: boot.active?.max_price || '',
    sort: boot.active?.sort || 'newest',
    page: 1,
});

const title = computed(() => boot.category_name || (filters.q ? 'Kết quả tìm kiếm' : 'Tất cả sản phẩm'));
const sortOptions = [
    ['newest', 'Mới nhất'],
    ['popular', 'Bán chạy'],
    ['rating', 'Đánh giá cao'],
    ['price_asc', 'Giá thấp → cao'],
    ['price_desc', 'Giá cao → thấp'],
];

function baseUrl() {
    return slug ? `/api/storefront/shop/${slug}` : '/api/storefront/shop';
}

async function fetchShop() {
    loading.value = true;
    try {
        const qs = new URLSearchParams();
        Object.entries(filters).forEach(([k, v]) => {
            if (v !== '' && v != null) qs.append(k, v);
        });
        const data = await apiFetch(baseUrl() + '?' + qs.toString());
        products.value = data.products || [];
        total.value = data.total || 0;
        lastPage.value = Math.max(1, data.last_page || 1);
        filters.page = filters.page > lastPage.value ? 1 : filters.page;
        window.history.replaceState(null, '', window.location.pathname + '?' + qs.toString());
    } finally {
        loading.value = false;
    }
}

function apply(page = 1) {
    filters.page = page;
    fetchShop();
    if (filtersOpen.value) filtersOpen.value = false;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function goPage(p) {
    if (p < 1 || p > lastPage.value || p === filters.page) return;
    apply(p);
}

function selectCategory(url) {
    window.location.href = url;
}

function setSort(e) {
    filters.sort = e.target.value;
    apply(1);
}

function clearFilters() {
    filters.q = '';
    filters.brand = '';
    filters.min_price = '';
    filters.max_price = '';
    apply(1);
}

function urlForCategory(cat) {
    return cat.url;
}

onMounted(() => store.fetchCart());
</script>

<template>
    <StorefrontLayout>
        <div class="sf-container py-8 sm:py-10">
            <!-- Heading -->
            <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-brand-600">Trillfa Fa</p>
                    <h1 class="mt-1 font-display text-3xl font-semibold text-ink-900 sm:text-4xl">{{ title }}</h1>
                    <p class="mt-1.5 text-sm text-ink-500">{{ total }} sản phẩm</p>
                </div>

                <!-- Sort -->
                <div class="flex items-center gap-2">
                    <label class="text-sm text-ink-500">Sắp xếp:</label>
                    <select @change="setSort" class="sf-input !w-auto !rounded-full !py-2 pr-8" :value="filters.sort">
                        <option v-for="s in sortOptions" :key="s[0]" :value="s[0]">{{ s[1] }}</option>
                    </select>
                </div>
            </div>

            <div class="grid gap-8 lg:grid-cols-[250px_1fr]">
                <!-- Filters (desktop) -->
                <aside class="hidden lg:block">
                    <div class="card-surface sticky top-24 space-y-6 rounded-[1.75rem] p-5">
                        <!-- Categories -->
                        <div>
                            <h3 class="text-sm font-semibold text-ink-900">Danh mục</h3>
                            <div class="mt-3 space-y-1">
                                <a href="/shop" class="block rounded-xl px-3 py-1.5 text-sm transition" :class="!boot.active?.category ? 'bg-brand-600/10 font-semibold text-brand-700' : 'text-ink-700 hover:bg-cream-100'">Tất cả</a>
                                <template v-for="cat in categories" :key="cat.id">
                                    <a :href="cat.url" class="block rounded-xl px-3 py-1.5 text-sm transition" :class="boot.active?.category === cat.slug ? 'bg-brand-600/10 font-semibold text-brand-700' : 'text-ink-700 hover:bg-cream-100'">{{ cat.name }}</a>
                                    <div class="ml-3 space-y-0.5 border-l border-cream-200 pl-2">
                                        <a
                                            v-for="child in cat.children"
                                            :key="child.slug"
                                            :href="child.url"
                                            class="block rounded-lg px-2 py-1 text-[13px] transition"
                                            :class="boot.active?.category === child.slug ? 'font-semibold text-brand-700' : 'text-ink-500 hover:text-brand-700'"
                                        >{{ child.name }}</a>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Brands -->
                        <div v-if="brands.length" class="border-t border-cream-200 pt-5">
                            <h3 class="text-sm font-semibold text-ink-900">Thương hiệu</h3>
                            <div class="mt-3 space-y-1">
                                <button
                                    v-for="b in brands"
                                    :key="b"
                                    @click="filters.brand = filters.brand === b ? '' : b; apply(1)"
                                    class="block w-full rounded-xl px-3 py-1.5 text-left text-sm transition"
                                    :class="filters.brand === b ? 'bg-brand-600/10 font-semibold text-brand-700' : 'text-ink-700 hover:bg-cream-100'"
                                >{{ b }}</button>
                            </div>
                        </div>

                        <!-- Price -->
                        <div class="border-t border-cream-200 pt-5">
                            <h3 class="text-sm font-semibold text-ink-900">Giá</h3>
                            <div class="mt-3 grid grid-cols-2 gap-2">
                                <input v-model="filters.min_price" type="number" placeholder="Từ" class="sf-input !py-2" />
                                <input v-model="filters.max_price" type="number" placeholder="Đến" class="sf-input !py-2" />
                            </div>
                            <button @click="apply(1)" class="sf-btn sf-btn-soft mt-3 w-full">Áp dụng</button>
                        </div>

                        <button v-if="filters.q || filters.brand || filters.min_price || filters.max_price" @click="clearFilters" class="sf-btn sf-btn-ghost w-full !text-brand-700">Xóa bộ lọc</button>
                    </div>
                </aside>

                <!-- Product grid -->
                <div>
                    <!-- Mobile filter trigger -->
                    <div class="mb-4 flex items-center gap-3 lg:hidden">
                        <button @click="filtersOpen = true" class="sf-btn sf-btn-soft">
                            <Icon name="menu" :size="16" /> Bộ lọc
                        </button>
                        <span v-if="filters.brand || filters.min_price || filters.max_price" class="text-sm text-ink-500">Đã lọc theo ({{ [filters.brand, filters.min_price, filters.max_price].filter(Boolean).length }})</span>
                    </div>

                    <SkeletonCard v-if="loading" :count="8" />

                    <template v-else>
                        <div v-if="products.length" class="grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4">
                            <ProductCard v-for="p in products" :key="p.id" :product="p" />
                        </div>
                        <div v-else class="card-surface flex flex-col items-center gap-3 rounded-[1.75rem] p-12 text-center text-ink-500">
                            <div class="grid h-16 w-16 place-items-center rounded-full bg-cream-100"><Icon name="search" :size="28" /></div>
                            <p class="text-sm">Không tìm thấy sản phẩm phù hợp.</p>
                            <button @click="clearFilters" class="sf-btn sf-btn-primary mt-1">Xóa bộ lọc</button>
                        </div>

                        <!-- Pagination -->
                        <div v-if="lastPage > 1" class="mt-10 flex items-center justify-center gap-2">
                            <button :disabled="filters.page <= 1" @click="goPage(filters.page - 1)" class="sf-btn sf-btn-soft !p-2" aria-label="Trước"><Icon name="chevron-left" :size="18" /></button>
                            <button
                                v-for="p in lastPage"
                                :key="p"
                                @click="goPage(p)"
                                class="grid h-9 w-9 place-items-center rounded-full text-sm font-medium transition"
                                :class="p === filters.page ? 'bg-gradient-to-r from-brand-600 to-brand-500 text-white shadow-md shadow-brand-600/30' : 'text-ink-700 hover:bg-cream-100'"
                            >{{ p }}</button>
                            <button :disabled="filters.page >= lastPage" @click="goPage(filters.page + 1)" class="sf-btn sf-btn-soft !p-2" aria-label="Sau"><Icon name="chevron-right" :size="18" /></button>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Mobile filter bottom-sheet -->
        <Teleport to="body">
            <Transition name="sheet">
                <div v-if="filtersOpen" class="fixed inset-0 z-[85] lg:hidden">
                    <div class="absolute inset-0 bg-ink-900/40 backdrop-blur-sm" @click="filtersOpen = false"></div>
                    <div class="absolute inset-x-0 bottom-0 max-h-[85vh] overflow-y-auto rounded-t-[2rem] bg-cream-50 p-5 pb-safe">
                        <div class="mb-4 flex items-center justify-between">
                            <h2 class="font-display text-lg font-semibold text-ink-900">Bộ lọc</h2>
                            <button @click="filtersOpen = false" class="sf-btn sf-btn-ghost !p-2" aria-label="Đóng"><Icon name="x" :size="20" /></button>
                        </div>
                        <div class="space-y-5">
                            <div>
                                <h3 class="text-sm font-semibold text-ink-900">Danh mục</h3>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <a href="/shop" class="chip" :class="!boot.active?.category ? '!border-brand-500 !text-brand-700' : ''">Tất cả</a>
                                    <a v-for="cat in categories" :key="cat.id" :href="cat.url" class="chip" :class="boot.active?.category === cat.slug ? '!border-brand-500 !text-brand-700' : ''">{{ cat.name }}</a>
                                </div>
                            </div>
                            <div v-if="brands.length">
                                <h3 class="text-sm font-semibold text-ink-900">Thương hiệu</h3>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <button v-for="b in brands" :key="b" @click="filters.brand = filters.brand === b ? '' : b" class="chip" :class="filters.brand === b ? '!border-brand-500 !text-brand-700' : ''">{{ b }}</button>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <input v-model="filters.min_price" type="number" placeholder="Giá từ" class="sf-input" />
                                <input v-model="filters.max_price" type="number" placeholder="Giá đến" class="sf-input" />
                            </div>
                            <button @click="apply(1)" class="sf-btn sf-btn-primary w-full">Áp dụng bộ lọc</button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </StorefrontLayout>
</template>

<style scoped>
.sheet-enter-active,
.sheet-leave-active { transition: opacity 0.25s ease; }
.sheet-enter-active > div:last-child,
.sheet-leave-active > div:last-child { transition: transform 0.25s ease; }
.sheet-enter-from,
.sheet-leave-to { opacity: 0; }
.sheet-enter-from > div:last-child,
.sheet-leave-to > div:last-child { transform: translateY(100%); }
</style>
