<script setup>
import { computed, onMounted } from 'vue';
import { useStorefrontStore } from './store.js';
import { csrfToken } from './composables/useApi.js';
import { formatMoney } from './composables/useFormat.js';
import StorefrontLayout from './components/layout/StorefrontLayout.vue';
import Icon from './components/ui/Icon.vue';

const store = useStorefrontStore();
store.ensureBoot();

const boot = window.__STORE_BOOT__ || {};
const view = boot.view || 'success';
const order = boot.order || {};
const isPay = computed(() => view === 'pay');
const pmLabel = computed(() => ({ cod:'Thanh toán khi nhận hàng (COD)', bank:'Chuyển khoản ngân hàng', vnpay:'VNPay', momo:'Ví MoMo' }[order.payment_method] || order.payment_method));

onMounted(() => store.fetchCart());
</script>

<template>
  <StorefrontLayout>
    <div class="sf-container flex justify-center py-12">
      <div class="card-surface w-full max-w-lg rounded-[2rem] p-8 text-center">
        <div class="mx-auto grid h-20 w-20 place-items-center rounded-full" :class="isPay ? 'bg-amber-100 text-amber-600' : 'bg-brand-600/10 text-brand-600'">
          <Icon :name="isPay ? 'credit-card' : 'check'" :size="36" />
        </div>

        <!-- Success -->
        <template v-if="!isPay">
          <h1 class="mt-5 font-display text-2xl font-semibold text-ink-900">Cảm ơn bạn đã đặt hàng!</h1>
          <p class="mt-2 text-sm text-ink-500">Đơn hàng <span class="font-semibold text-ink-900">{{ order.order_number }}</span> đã được tiếp nhận.</p>
          <div class="mt-6 space-y-2 rounded-2xl bg-cream-50 p-5 text-sm">
            <div class="flex justify-between text-ink-500"><span>Mã đơn hàng</span><span class="font-medium text-ink-900">{{ order.order_number }}</span></div>
            <div class="flex justify-between text-ink-500"><span>Số sản phẩm</span><span class="font-medium text-ink-900">{{ order.items_count }}</span></div>
            <div class="flex justify-between text-ink-500"><span>Tổng cộng</span><span class="font-semibold text-ink-900">{{ formatMoney(order.total) }}</span></div>
            <div class="flex justify-between text-ink-500"><span>Thanh toán</span><span class="font-medium text-ink-900">{{ pmLabel }}</span></div>
          </div>
          <div class="mt-6 flex gap-2">
            <a href="/" class="sf-btn sf-btn-soft flex-1">Về trang chủ</a>
            <a href="/shop" class="sf-btn sf-btn-primary flex-1">Mua sắm tiếp</a>
          </div>
        </template>

        <!-- Pay -->
        <template v-else>
          <h1 class="mt-5 font-display text-2xl font-semibold text-ink-900">Hoàn tất thanh toán</h1>
          <p class="mt-2 text-sm text-ink-500">Đơn hàng <span class="font-semibold text-ink-900">{{ order.order_number }}</span> · {{ formatMoney(order.total) }}</p>
          <p class="mt-4 rounded-2xl bg-cream-50 p-4 text-sm text-ink-700">{{ pmLabel }}</p>
          <form method="POST" :action="'/thanh-toan/' + order.id + '/thanh-toan'" class="mt-6">
            <input type="hidden" name="_token" :value="csrfToken()" />
            <button type="submit" class="sf-btn sf-btn-primary w-full">Xác nhận đã thanh toán</button>
          </form>
          <a href="/tai-khoan/don-hang" class="sf-btn sf-btn-ghost mt-3 w-full">Xem đơn hàng</a>
        </template>
      </div>
    </div>
  </StorefrontLayout>
</template>
