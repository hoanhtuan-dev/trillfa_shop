<script setup>
import { ref, computed, onMounted } from 'vue';
import { useStorefrontStore } from './store.js';
import { apiFetch, csrfToken } from './composables/useApi.js';
import { formatMoney } from './composables/useFormat.js';
import { trackBeginCheckout } from './composables/useAnalytics.js';
import StorefrontLayout from './components/layout/StorefrontLayout.vue';
import Icon from './components/ui/Icon.vue';

const store = useStorefrontStore();
store.ensureBoot();

const boot = window.__STORE_BOOT__ || {};
const shippingMethods = ref(boot.shipping_methods || []);
const paymentMethods = ref(boot.payment_methods || []);
const defaultAddress = boot.default_address || {};

// Form state (prefill from default address + account).
const form = ref({
    name: boot.user?.name || defaultAddress.name || '',
    email: boot.user?.email || '',
    phone: defaultAddress.phone || boot.user?.phone || '',
    address: defaultAddress.address || '',
    ward: defaultAddress.ward || '',
    district: defaultAddress.district || '',
    province: defaultAddress.province || '',
    note: '',
    shipping_method: store.cart.shippingMethod?.code || (shippingMethods.value[0]?.code || ''),
    payment_method: paymentMethods.value[0]?.code || '',
    terms: false,
});

const submitting = ref(false);
const items = computed(() => store.cart.items);
const subtotal = computed(() => formatMoney(store.cart.subtotal));
const shippingFee = computed(() => formatMoney(store.cart.shippingFee));
const total = computed(() => formatMoney(store.cart.total));

async function selectShipping(code) {
    form.value.shipping_method = code;
    await store.setShipping(code);
}

// Native submit — the form POSTs to /thanh-toan (existing CheckoutController).
function onSubmit() {
    if (!form.value.terms) {
        store.toast('Bạn cần đồng ý với điều khoản.', 'error');
        return;
    }
    submitting.value = true;
    // Build a FormData and submit natively so Laravel handles validation +
    // order creation + redirect (pay/success) exactly as before.
    const formEl = document.querySelector('#checkout-form');
    if (formEl) {
        formEl.submit();
    }
}

onMounted(() => {
    store.fetchCart();
    trackBeginCheckout(store.cart);
});
</script>

<template>
    <StorefrontLayout>
        <div class="sf-container py-8 sm:py-10">
            <h1 class="font-display text-3xl font-semibold text-ink-900 sm:text-4xl">Thanh toán</h1>
            <p class="mt-2 text-sm text-ink-500">Hoàn tất thông tin để đặt hàng.</p>

            <form id="checkout-form" method="POST" action="/thanh-toan" @submit.prevent="onSubmit" class="mt-8 grid gap-8 lg:grid-cols-[1fr_340px]">
                <input type="hidden" name="_token" :value="csrfToken()" />

                <div class="space-y-6">
                    <!-- Contact -->
                    <section class="card-surface rounded-[1.75rem] p-6">
                        <h2 class="font-display text-lg font-semibold text-ink-900">Thông tin liên hệ</h2>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <label class="label">Họ tên</label>
                            <input v-model="form.name" name="name" required class="sf-input" placeholder="Họ và tên" />
                            <label class="label">Email</label>
                            <input v-model="form.email" name="email" type="email" required class="sf-input" placeholder="Email" />
                            <label class="label">Số điện thoại</label>
                            <input v-model="form.phone" name="phone" required class="sf-input" placeholder="Số điện thoại" />
                        </div>
                    </section>

                    <!-- Shipping address -->
                    <section class="card-surface rounded-[1.75rem] p-6">
                        <h2 class="font-display text-lg font-semibold text-ink-900">Địa chỉ giao hàng</h2>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <label class="label">Địa chỉ</label>
                            <input v-model="form.address" name="address" required class="sf-input" placeholder="Số nhà, đường" />
                            <label class="label">Phường / Xã</label>
                            <input v-model="form.ward" name="ward" class="sf-input" placeholder="Phường" />
                            <label class="label">Quận / Huyện</label>
                            <input v-model="form.district" name="district" class="sf-input" placeholder="Quận" />
                            <label class="label">Tỉnh / Thành phố</label>
                            <input v-model="form.province" name="province" class="sf-input" placeholder="Tỉnh" />
                            <label class="label">Ghi chú</label>
                            <input v-model="form.note" name="note" class="sf-input" placeholder="Ghi chú (tuỳ chọn)" />
                        </div>
                    </section>

                    <!-- Shipping method -->
                    <section class="card-surface rounded-[1.75rem] p-6">
                        <h2 class="font-display text-lg font-semibold text-ink-900">Phương thức vận chuyển</h2>
                        <div class="mt-4 space-y-2.5">
                            <label v-for="m in shippingMethods" :key="m.code" class="flex cursor-pointer items-center justify-between rounded-2xl border px-4 py-3 transition" :class="form.shipping_method === m.code ? 'border-brand-500 bg-brand-600/5' : 'border-cream-200 hover:border-brand-200'">
                                <span class="flex items-center gap-3">
                                    <input type="radio" name="shipping_method" :value="m.code" v-model="form.shipping_method" class="accent-brand-600" @change="selectShipping(m.code)" />
                                    <span>
                                        <span class="block text-sm font-semibold text-ink-900">{{ m.name }}</span>
                                        <span v-if="m.description" class="text-xs text-ink-500">{{ m.description }}</span>
                                    </span>
                                </span>
                                <span class="text-sm font-medium text-ink-700">{{ m.fee > 0 ? formatMoney(m.fee) : 'Miễn phí' }}</span>
                            </label>
                        </div>
                    </section>

                    <!-- Payment method -->
                    <section class="card-surface rounded-[1.75rem] p-6">
                        <h2 class="font-display text-lg font-semibold text-ink-900">Thanh toán</h2>
                        <div class="mt-4 space-y-2.5">
                            <label v-for="m in paymentMethods" :key="m.code" class="flex cursor-pointer items-center gap-3 rounded-2xl border px-4 py-3 transition" :class="form.payment_method === m.code ? 'border-brand-500 bg-brand-600/5' : 'border-cream-200 hover:border-brand-200'">
                                <input type="radio" name="payment_method" :value="m.code" v-model="form.payment_method" required class="accent-brand-600" />
                                <span>
                                    <span class="block text-sm font-semibold text-ink-900">{{ m.name }}</span>
                                    <span v-if="m.description" class="text-xs text-ink-500">{{ m.description }}</span>
                                </span>
                            </label>
                        </div>
                    </section>

                    <label class="flex items-center gap-2.5 text-sm text-ink-700">
                        <input type="checkbox" name="terms" value="1" v-model="form.terms" required class="h-4 w-4 accent-brand-600" />
                        Tôi đồng ý với <a href="/dieu-khoan" class="font-medium text-brand-700 hover:underline">điều khoản</a> và chính sách.
                    </label>
                </div>

                <!-- Order summary -->
                <aside class="card-surface h-fit rounded-[1.75rem] p-6">
                    <h2 class="font-display text-lg font-semibold text-ink-900">Đơn hàng</h2>
                    <div class="mt-4 max-h-64 space-y-3 overflow-y-auto">
                        <div v-for="item in items" :key="item.id" class="flex items-center gap-3">
                            <div class="relative h-14 w-14 shrink-0 overflow-hidden rounded-xl bg-cream-100">
                                <img :src="item.image" :alt="item.name" class="h-full w-full object-cover" />
                                <span class="absolute -right-1 -top-1 grid h-5 min-w-5 place-items-center rounded-full bg-ink-900 px-1 text-[10px] font-bold text-cream-50">{{ item.quantity }}</span>
                            </div>
                            <p class="line-clamp-2 flex-1 text-sm text-ink-700">{{ item.name }}</p>
                            <span class="text-sm font-medium text-ink-900">{{ formatMoney(Number(item.price) * Number(item.quantity)) }}</span>
                        </div>
                    </div>
                    <div class="mt-5 space-y-2 border-t border-cream-200 pt-4 text-sm">
                        <div class="flex justify-between text-ink-500"><span>Tạm tính</span><span>{{ subtotal }}</span></div>
                        <div v-if="store.cart.discount > 0" class="flex justify-between text-clay-600"><span>Giảm giá</span><span>-{{ formatMoney(store.cart.discount) }}</span></div>
                        <div class="flex justify-between text-ink-500"><span>Vận chuyển</span><span>{{ shippingFee }}</span></div>
                        <div class="flex justify-between pt-2 text-lg font-semibold text-ink-900"><span>Tổng cộng</span><span>{{ total }}</span></div>
                    </div>
                    <button type="submit" class="sf-btn sf-btn-primary mt-6 w-full" :disabled="submitting">
                        {{ submitting ? 'Đang đặt hàng…' : 'Đặt hàng' }}
                    </button>
                    <a href="/gio-hang" class="sf-btn sf-btn-ghost mt-2 w-full !text-ink-700">Quay lại giỏ hàng</a>
                </aside>
            </form>
        </div>
    </StorefrontLayout>
</template>
