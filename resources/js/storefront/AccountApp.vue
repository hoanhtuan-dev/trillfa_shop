<script setup>
import { ref, computed, onMounted } from 'vue';
import { useStorefrontStore } from './store.js';
import StorefrontLayout from './components/layout/StorefrontLayout.vue';
import Icon from './components/ui/Icon.vue';
import BaseBadge from './components/ui/BaseBadge.vue';

const store = useStorefrontStore();
store.ensureBoot();

const boot = window.__STORE_BOOT__ || {};
const user = boot.user || {};
const orders = ref(boot.orders || []);
const counts = boot.counts || {};

const statusLabel = {
    pending: 'Chờ xử lý',
    processing: 'Đang xử lý',
    shipped: 'Đang giao',
    completed: 'Hoàn thành',
    cancelled: 'Đã hủy',
};

const quickLinks = [
    ['/tai-khoan/don-hang', 'Đơn hàng', 'bag', 'Xem lịch sử đơn hàng'],
    ['/yeu-thich', 'Yêu thích', 'heart', 'Sản phẩm bạn đã thích'],
    ['/tai-khoan/dia-chi', 'Địa chỉ', 'map-pin', 'Sổ địa chỉ giao hàng'],
    ['/tai-khoan/ho-so', 'Hồ sơ', 'user', 'Thông tin cá nhân'],
];

onMounted(() => store.fetchCart());
</script>

<template>
    <StorefrontLayout>
        <div class="sf-container py-8 sm:py-10">
            <h1 class="font-display text-3xl font-semibold text-ink-900 sm:text-4xl">Tài khoản của tôi</h1>

            <!-- Profile card + counts -->
            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="card-surface col-span-1 flex items-center gap-4 rounded-[1.75rem] p-5 lg:col-span-2">
                    <span class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-brand-600 to-brand-500 text-white shadow-lg shadow-brand-600/30"><Icon name="user" :size="26" /></span>
                    <div class="min-w-0">
                        <p class="truncate font-semibold text-ink-900">{{ user.name }}</p>
                        <p class="truncate text-sm text-ink-500">{{ user.email }}</p>
                        <p v-if="user.phone" class="text-sm text-ink-500">{{ user.phone }}</p>
                    </div>
                </div>
                <a href="/tai-khoan/don-hang" class="card-surface card-surface-hover rounded-[1.75rem] p-5">
                    <div class="flex items-center gap-2 text-ink-500"><Icon name="bag" :size="18" /><span class="text-sm">Đơn hàng</span></div>
                    <p class="mt-2 text-2xl font-semibold text-ink-900">{{ counts.orders || 0 }}</p>
                </a>
                <a href="/yeu-thich" class="card-surface card-surface-hover rounded-[1.75rem] p-5">
                    <div class="flex items-center gap-2 text-ink-500"><Icon name="heart" :size="18" /><span class="text-sm">Yêu thích</span></div>
                    <p class="mt-2 text-2xl font-semibold text-ink-900">{{ counts.wishlist || 0 }}</p>
                </a>
            </div>

            <!-- Quick links -->
            <div class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <a v-for="(link, i) in quickLinks" :key="link[0]" :href="link[0]" class="card-surface card-surface-hover flex flex-col gap-2 rounded-2xl p-4">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-brand-600/10 text-brand-600"><Icon :name="link[2]" :size="20" /></span>
                    <span class="text-sm font-semibold text-ink-900">{{ link[1] }}</span>
                    <span class="text-xs text-ink-500">{{ link[3] }}</span>
                </a>
            </div>

            <!-- Recent orders -->
            <section v-reveal class="mt-10">
                <div class="mb-5 flex items-end justify-between">
                    <h2 class="font-display text-xl font-semibold text-ink-900">Đơn hàng gần đây</h2>
                    <a href="/tai-khoan/don-hang" class="text-sm font-medium text-brand-700 hover:text-brand-800">Tất cả</a>
                </div>

                <div v-if="orders.length" class="card-surface overflow-hidden rounded-[1.75rem]">
                    <div v-for="o in orders" :key="o.id" class="flex items-center justify-between gap-3 border-b border-cream-200 px-5 py-4 last:border-0">
                        <a :href="o.url" class="flex min-w-0 flex-1 items-center gap-3">
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-cream-100 text-brand-600"><Icon name="bag" :size="20" /></span>
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium text-ink-900">{{ o.order_number }}</span>
                                <span class="text-xs text-ink-500">{{ o.created_at }} · {{ o.items_count }} sản phẩm</span>
                            </span>
                        </a>
                        <span class="font-semibold text-ink-900">{{ (o.total / 1).toLocaleString('vi-VN') }}₫</span>
                        <BaseBadge :variant="o.status === 'completed' ? 'brand' : o.status === 'cancelled' ? 'ink' : 'clay'">{{ statusLabel[o.status] || o.status }}</BaseBadge>
                    </div>
                </div>
                <div v-else class="card-surface rounded-[1.75rem] p-12 text-center text-ink-500">
                    <p class="text-sm">Bạn chưa có đơn hàng nào.</p>
                    <a href="/shop" class="sf-btn sf-btn-primary mt-4">Mua sắm ngay</a>
                </div>
            </section>
        </div>
    </StorefrontLayout>
</template>
