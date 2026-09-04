<script setup>
import { ref, computed, onMounted } from 'vue';
import { useStorefrontStore } from './store.js';
import { formatMoney } from './composables/useFormat.js';
import StorefrontLayout from './components/layout/StorefrontLayout.vue';
import BaseButton from './components/ui/BaseButton.vue';
import BaseBadge from './components/ui/BaseBadge.vue';
import Icon from './components/ui/Icon.vue';

const store = useStorefrontStore();
store.ensureBoot();

const couponCode = ref(store.cart.coupon?.code || '');
const couponMsg = ref('');

const items = computed(() => store.cart.items);
const isEmpty = computed(() => store.cartIsEmpty);
const subtotal = computed(() => formatMoney(store.cart.subtotal));
const discount = computed(() => formatMoney(store.cart.discount));
const shippingFee = computed(() => formatMoney(store.cart.shippingFee));
const total = computed(() => formatMoney(store.cart.total));

async function applyCoupon() {
    if (!couponCode.value.trim()) return;
    const ok = await store.applyCoupon(couponCode.value.trim());
    couponMsg.value = ok ? 'Đã áp dụng mã.' : 'Mã không hợp lệ.';
}
async function removeCoupon() {
    await store.removeCoupon();
    couponCode.value = '';
    couponMsg.value = '';
}
async function setShipping(code) { await store.setShipping(code); }
async function changeQty(item, qty) {
    if (qty > 0) await store.updateCartItem(item.id, qty);
    else await store.removeCartItem(item.id);
}

onMounted(() => store.fetchCart());
</script>

<template>
    <StorefrontLayout>
        <div class="sf-container py-8 sm:py-10">
            <h1 class="font-display text-3xl font-semibold text-ink-900 sm:text-4xl">Giỏ hàng</h1>
            <p class="mt-2 text-sm text-ink-500">{{ store.cartCount }} sản phẩm</p>

            <div v-if="isEmpty" class="card-surface mt-8 flex flex-col items-center gap-4 rounded-[2rem] p-12 text-center text-ink-500">
                <div class="grid h-16 w-16 place-items-center rounded-full bg-cream-100"><Icon name="cart" :size="30" /></div>
                <p class="text-sm">Giỏ hàng của bạn đang trống.</p>
                <a href="/shop" class="sf-btn sf-btn-primary mt-1">Mua sắm ngay</a>
            </div>

            <div v-else class="mt-8 grid gap-8 lg:grid-cols-[1fr_320px]">
                <!-- Items -->
                <div class="space-y-4">
                    <div v-for="item in items" :key="item.id" class="card-surface flex gap-4 rounded-[1.75rem] p-4">
                        <a :href="item.url" class="h-24 w-24 shrink-0 overflow-hidden rounded-2xl bg-cream-100">
                            <img :src="item.image" :alt="item.name" class="h-full w-full object-cover" />
                        </a>
                        <div class="flex min-w-0 flex-1 flex-col">
                            <div class="flex items-start justify-between gap-2">
                                <a :href="item.url" class="line-clamp-2 text-sm font-semibold text-ink-900">{{ item.name }}</a>
                                <button @click="store.removeCartItem(item.id)" class="shrink-0 text-ink-400 transition hover:text-red-600" aria-label="Xóa">
                                    <Icon name="x" :size="18" />
                                </button>
                            </div>
                            <div class="mt-1 flex items-center gap-2 text-xs text-ink-500">
                                <span v-if="item.variant_name">{{ item.variant_name }}</span>
                                <span v-if="item.options" class="text-ink-400">·</span>
                                <span>{{ formatMoney(item.price) }}</span>
                            </div>
                            <div class="mt-auto flex items-center justify-between pt-3">
                                <div class="inline-flex items-center rounded-full border border-cream-200 bg-white">
                                    <button @click="changeQty(item, Number(item.quantity) - 1)" class="grid h-8 w-8 place-items-center rounded-full text-ink-700 hover:bg-cream-100" aria-label="Giảm"><Icon name="minus" :size="15" /></button>
                                    <span class="min-w-8 text-center text-sm font-semibold">{{ item.quantity }}</span>
                                    <button @click="changeQty(item, Number(item.quantity) + 1)" class="grid h-8 w-8 place-items-center rounded-full text-ink-700 hover:bg-cream-100" aria-label="Tăng"><Icon name="plus" :size="15" /></button>
                                </div>
                                <span class="font-semibold text-ink-900">{{ formatMoney(Number(item.price) * Number(item.quantity)) }}</span>
                            </div>
                        </div>
                    </div>

                    <a href="/shop" class="inline-flex items-center gap-1.5 text-sm font-medium text-brand-700 hover:text-brand-800">
                        <Icon name="chevron-left" :size="16" /> Tiếp tục mua sắm
                    </a>
                </div>

                <!-- Summary -->
                <aside class="card-surface h-fit rounded-[1.75rem] p-6">
                    <h2 class="font-display text-lg font-semibold text-ink-900">Tổng đơn hàng</h2>

                    <!-- Coupon -->
                    <div class="mt-5">
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-ink-700">Mã giảm giá</label>
                        <div v-if="store.cart.coupon" class="flex items-center justify-between rounded-2xl border border-brand-200 bg-brand-50 px-3 py-2.5">
                            <span class="text-sm font-semibold text-brand-700">{{ store.cart.coupon.code }}</span>
                            <button @click="removeCoupon" class="text-xs text-ink-500 hover:text-red-600">Bỏ</button>
                        </div>
                        <div v-else class="flex gap-2">
                            <input v-model="couponCode" placeholder="Nhập mã" class="sf-input" />
                            <button @click="applyCoupon" class="sf-btn sf-btn-soft shrink-0 !px-4">Áp dụng</button>
                        </div>
                        <p v-if="couponMsg" class="mt-1 text-xs text-ink-500">{{ couponMsg }}</p>
                    </div>

                    <!-- Shipping -->
                    <div class="mt-5 border-t border-cream-200 pt-5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-ink-700">Phương thức vận chuyển</p>
                        <div class="mt-2 space-y-2">
                            <button
                                v-for="m in store.cart.shippingMethods"
                                :key="m.code"
                                @click="setShipping(m.code)"
                                class="flex w-full items-center justify-between rounded-2xl border px-3 py-2.5 text-left text-sm transition"
                                :class="store.cart.shippingMethod?.code === m.code ? 'border-brand-500 bg-brand-600/5 text-ink-900' : 'border-cream-200 text-ink-700 hover:border-brand-200'"
                            >
                                <span>{{ m.name }}</span>
                                <span class="font-medium" :class="store.cart.shippingMethod?.code === m.code ? 'text-brand-700' : 'text-ink-500'">{{ m.fee > 0 ? formatMoney(m.fee) : 'Miễn phí' }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Totals -->
                    <div class="mt-5 space-y-2 border-t border-cream-200 pt-4 text-sm">
                        <div class="flex justify-between text-ink-500"><span>Tạm tính</span><span>{{ subtotal }}</span></div>
                        <div v-if="store.cart.discount > 0" class="flex justify-between text-clay-600"><span>Giảm giá</span><span>-{{ discount }}</span></div>
                        <div class="flex justify-between text-ink-500"><span>Vận chuyển</span><span>{{ shippingFee }}</span></div>
                        <div class="flex justify-between pt-2 text-lg font-semibold text-ink-900"><span>Tổng cộng</span><span>{{ total }}</span></div>
                    </div>

                    <a href="/thanh-toan" class="sf-btn sf-btn-primary mt-6 w-full">Tiến hành thanh toán</a>
                    <a href="/thanh-toan-nhanh" class="sf-btn sf-btn-soft mt-2 w-full">Mua nhanh (COD)</a>
                </aside>
            </div>
        </div>
    </StorefrontLayout>
</template>
