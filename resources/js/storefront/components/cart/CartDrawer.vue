<script setup>
import { computed } from 'vue';
import { useStorefrontStore } from '../../store.js';
import { formatMoney } from '../../composables/useFormat.js';
import Icon from '../ui/Icon.vue';

const store = useStorefrontStore();

const items = computed(() => store.cart.items);
const subtotal = computed(() => formatMoney(store.cart.subtotal));
const total = computed(() => formatMoney(store.cart.total));
const freeShipThreshold = computed(() => Number(store.site.free_shipping_threshold || 0));
const freeShipProgress = computed(() => {
    if (!freeShipThreshold.value) return 0;
    return Math.min(100, Math.round((store.cart.subtotal / freeShipThreshold.value) * 100));
});
const remainingForFree = computed(() => {
    if (!freeShipThreshold.value) return 0;
    return Math.max(0, freeShipThreshold.value - store.cart.subtotal);
});

async function changeQty(item, qty) {
    if (qty > 0) await store.updateCartItem(item.id, qty);
    else await store.removeCartItem(item.id);
}
</script>

<template>
    <Teleport to="body">
        <Transition name="drawer">
            <div v-if="store.cartOpen" class="fixed inset-0 z-[90]">
                <div class="absolute inset-0 bg-ink-900/40 backdrop-blur-sm" @click="store.closeCart()"></div>
                <div class="absolute right-0 top-0 flex h-full w-full max-w-md flex-col bg-cream-50 shadow-2xl">
                    <!-- Header -->
                    <div class="flex items-center justify-between border-b border-cream-200 px-5 py-4">
                        <h3 class="font-display text-lg font-semibold text-ink-900">Giỏ hàng</h3>
                        <button @click="store.closeCart()" class="sf-btn sf-btn-ghost !p-2" aria-label="Đóng giỏ hàng">
                            <Icon name="x" :size="20" />
                        </button>
                    </div>

                    <!-- Free-shipping progress -->
                    <div v-if="freeShipThreshold" class="border-b border-cream-200 px-5 py-3">
                        <p class="text-xs text-ink-500">
                            <template v-if="remainingForFree > 0">
                                Mua thêm <span class="font-semibold text-brand-700">{{ formatMoney(remainingForFree) }}</span> để miễn phí vận chuyển.
                            </template>
                            <template v-else class="text-brand-700">🎉 Bạn được miễn phí vận chuyển!</template>
                        </p>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-cream-200">
                            <div class="h-full rounded-full bg-gradient-to-r from-brand-600 to-brand-500 transition-all" :style="{ width: freeShipProgress + '%' }"></div>
                        </div>
                    </div>

                    <!-- Items -->
                    <div class="flex-1 overflow-y-auto px-5 py-4">
                        <div v-if="store.cartIsEmpty" class="flex h-full flex-col items-center justify-center gap-3 text-center text-ink-500">
                            <div class="grid h-16 w-16 place-items-center rounded-full bg-cream-100">
                                <Icon name="cart" :size="28" />
                            </div>
                            <p class="text-sm">Giỏ hàng của bạn đang trống.</p>
                            <a href="/shop" @click="store.closeCart()" class="sf-btn sf-btn-primary mt-1">Mua sắm ngay</a>
                        </div>

                        <TransitionGroup v-else name="list" tag="div" class="space-y-4">
                            <div v-for="item in items" :key="item.id" class="flex gap-3">
                                <a :href="item.url || '/san-pham/' + item.slug" @click="store.closeCart()" class="h-20 w-20 shrink-0 overflow-hidden rounded-2xl bg-cream-100">
                                    <img :src="item.image" :alt="item.name" class="h-full w-full object-cover" />
                                </a>
                                <div class="flex min-w-0 flex-1 flex-col">
                                    <div class="flex items-start justify-between gap-2">
                                        <a :href="item.url || '/san-pham/' + item.slug" @click="store.closeCart()" class="line-clamp-2 text-sm font-medium text-ink-900">{{ item.name }}</a>
                                        <button @click="store.removeCartItem(item.id)" class="shrink-0 text-ink-500 transition hover:text-red-600" aria-label="Xóa">
                                            <Icon name="x" :size="16" />
                                        </button>
                                    </div>
                                    <p class="mt-0.5 text-xs text-ink-500">{{ formatMoney(item.price) }}</p>
                                    <div class="mt-2 flex items-center justify-between">
                                        <div class="inline-flex items-center rounded-full border border-cream-200 bg-white">
                                            <button @click="changeQty(item, Number(item.quantity) - 1)" class="grid h-7 w-7 place-items-center rounded-full text-ink-700 hover:bg-cream-100" aria-label="Giảm">
                                                <Icon name="minus" :size="14" />
                                            </button>
                                            <span class="min-w-7 text-center text-sm font-medium">{{ item.quantity }}</span>
                                            <button @click="changeQty(item, Number(item.quantity) + 1)" class="grid h-7 w-7 place-items-center rounded-full text-ink-700 hover:bg-cream-100" aria-label="Tăng">
                                                <Icon name="plus" :size="14" />
                                            </button>
                                        </div>
                                        <span class="text-sm font-semibold text-ink-900">{{ formatMoney(Number(item.price) * Number(item.quantity)) }}</span>
                                    </div>
                                </div>
                            </div>
                        </TransitionGroup>
                    </div>

                    <!-- Footer -->
                    <div v-if="!store.cartIsEmpty" class="border-t border-cream-200 bg-white/60 px-5 py-4 backdrop-blur">
                        <div class="space-y-1.5 text-sm">
                            <div class="flex justify-between text-ink-500"><span>Tạm tính</span><span>{{ subtotal }}</span></div>
                            <div class="flex justify-between text-ink-500"><span>Phí vận chuyển</span><span>Thanh toán khi nhận hàng</span></div>
                            <div class="flex justify-between pt-1 text-base font-semibold text-ink-900"><span>Tổng cộng</span><span>{{ total }}</span></div>
                        </div>
                        <a href="/thanh-toan" @click="store.closeCart()" class="sf-btn sf-btn-primary mt-4 w-full">Thanh toán</a>
                        <a href="/gio-hang" @click="store.closeCart()" class="sf-btn sf-btn-ghost mt-2 w-full !text-ink-700">Xem giỏ hàng</a>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.drawer-enter-active,
.drawer-leave-active {
    transition: opacity 0.25s ease;
}
.drawer-enter-active > div:last-child,
.drawer-leave-active > div:last-child {
    transition: transform 0.25s ease;
}
.drawer-enter-from,
.drawer-leave-to {
    opacity: 0;
}
.drawer-enter-from > div:last-child,
.drawer-leave-to > div:last-child {
    transform: translateX(100%);
}
.list-enter-active,
.list-leave-active {
    transition: all 0.2s ease;
}
.list-enter-from,
.list-leave-to {
    opacity: 0;
    transform: translateY(6px);
}
</style>
