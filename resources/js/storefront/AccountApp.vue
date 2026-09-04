<script setup>
import { ref, computed, onMounted } from 'vue';
import { useStorefrontStore } from './store.js';
import { csrfToken } from './composables/useApi.js';
import { formatMoney } from './composables/useFormat.js';
import StorefrontLayout from './components/layout/StorefrontLayout.vue';
import Icon from './components/ui/Icon.vue';
import BaseBadge from './components/ui/BaseBadge.vue';

const store = useStorefrontStore();
store.ensureBoot();

const boot = window.__STORE_BOOT__ || {};
const view = boot.view || 'dashboard';
const user = boot.user || {};
const counts = boot.counts || {};
const orders = ref(boot.orders || []);
const order = boot.order || {};
const profile = boot.profile || {};
const addresses = ref(boot.addresses || []);
const reviews = ref(boot.reviews || []);
const shippingFee = computed(() => formatMoney(order.shipping_fee));

const sLabel = { pending:'Chờ xử lý', processing:'Đang xử lý', shipped:'Đang giao', completed:'Hoàn thành', cancelled:'Đã hủy' };
const quickLinks = [
  ['/tai-khoan/don-hang','Đơn hàng','bag','Xem lịch sử đơn hàng'],
  ['/yeu-thich','Yêu thích','heart','Sản phẩm bạn đã thích'],
  ['/tai-khoan/dia-chi','Địa chỉ','map-pin','Sổ địa chỉ giao hàng'],
  ['/tai-khoan/ho-so','Hồ sơ','user','Thông tin cá nhân'],
];
const navLinks = [
  ['/tai-khoan','Tổng quan'],
  ['/tai-khoan/don-hang','Đơn hàng'],
  ['/tai-khoan/ho-so','Hồ sơ'],
  ['/tai-khoan/dia-chi','Địa chỉ'],
  ['/tai-khoan/mat-khau','Mật khẩu'],
  ['/tai-khoan/danh-gia','Đánh giá'],
];

// Profile form state
const p = ref({ name: profile.name || user.name || '', email: profile.email || user.email || '', phone: profile.phone || user.phone || '' });
const activeLabel = computed(() => ({ dashboard:'Tổng quan', orders:'Đơn hàng', order:'Đơn hàng', profile:'Hồ sơ', addresses:'Địa chỉ', reviews:'Đánh giá' }[view] || 'Tổng quan'));
// Address form state
const addr = ref({ name:'', phone:'', address:'', ward:'', district:'', province:'', is_default:false });
const editingAddressId = ref(null);
function editAddress(a) {
  editingAddressId.value = a.id;
  addr.value = { ...a };
}
function resetAddress() { editingAddressId.value = null; addr.value = { name:'', phone:'', address:'', ward:'', district:'', province:'', is_default:false }; }
function submitAddress() { document.querySelector('#address-form').submit(); }

onMounted(() => store.fetchCart());
</script>

<template>
  <StorefrontLayout>
    <div class="sf-container py-8 sm:py-10">
      <h1 class="font-display text-2xl font-semibold text-ink-900 sm:text-3xl">Tài khoản của tôi</h1>

      <!-- Sub-nav -->
      <nav class="mt-4 flex gap-1.5 overflow-x-auto no-scrollbar pb-1">
        <a v-for="l in navLinks" :key="l[0]" :href="l[0]" class="shrink-0 whitespace-nowrap rounded-full px-4 py-1.5 text-sm font-medium transition" :class="l[1] === activeLabel ? 'bg-brand-600 text-white shadow-md shadow-brand-600/30' : 'text-ink-700 hover:bg-cream-100'">{{ l[1] }}</a>
      </nav>

      <!-- ===== Dashboard ===== -->
      <div v-if="view === 'dashboard'">
        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <div class="card-surface col-span-1 flex items-center gap-4 rounded-[1.75rem] p-5 lg:col-span-2">
            <span class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-brand-600 to-brand-500 text-white shadow-lg shadow-brand-600/30"><Icon name="user" :size="26" /></span>
            <div class="min-w-0">
              <p class="truncate font-semibold text-ink-900">{{ user.name }}</p>
              <p class="truncate text-sm text-ink-500">{{ user.email }}</p>
            </div>
          </div>
          <a href="/tai-khoan/don-hang" class="card-surface card-surface-hover rounded-[1.75rem] p-5"><div class="flex items-center gap-2 text-ink-500"><Icon name="bag" :size="18" /><span class="text-sm">Đơn hàng</span></div><p class="mt-2 text-2xl font-semibold text-ink-900">{{ counts.orders || 0 }}</p></a>
          <a href="/yeu-thich" class="card-surface card-surface-hover rounded-[1.75rem] p-5"><div class="flex items-center gap-2 text-ink-500"><Icon name="heart" :size="18" /><span class="text-sm">Yêu thích</span></div><p class="mt-2 text-2xl font-semibold text-ink-900">{{ counts.wishlist || 0 }}</p></a>
        </div>
        <div class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
          <a v-for="(link, i) in quickLinks" :key="link[0]" :href="link[0]" class="card-surface card-surface-hover flex flex-col gap-2 rounded-2xl p-4"><span class="grid h-10 w-10 place-items-center rounded-xl bg-brand-600/10 text-brand-600"><Icon :name="link[2]" :size="20" /></span><span class="text-sm font-semibold text-ink-900">{{ link[1] }}</span><span class="text-xs text-ink-500">{{ link[3] }}</span></a>
        </div>
        <section class="mt-10">
          <div class="mb-5 flex items-end justify-between"><h2 class="font-display text-xl font-semibold text-ink-900">Đơn hàng gần đây</h2><a href="/tai-khoan/don-hang" class="text-sm font-medium text-brand-700 hover:text-brand-800">Tất cả</a></div>
          <div v-if="orders.length" class="card-surface overflow-hidden rounded-[1.75rem]">
            <div v-for="o in orders" :key="o.id" class="flex items-center justify-between gap-3 border-b border-cream-200 px-5 py-4 last:border-0">
              <a :href="o.url" class="flex min-w-0 flex-1 items-center gap-3"><span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-cream-100 text-brand-600"><Icon name="bag" :size="20" /></span><span class="min-w-0"><span class="block truncate text-sm font-medium text-ink-900">{{ o.order_number }}</span><span class="text-xs text-ink-500">{{ o.created_at }} · {{ o.items_count }} sản phẩm</span></span></a>
              <span class="font-semibold text-ink-900">{{ formatMoney(o.total) }}</span>
              <BaseBadge :variant="o.status === 'completed' ? 'brand' : o.status === 'cancelled' ? 'ink' : 'clay'">{{ sLabel[o.status] || o.status }}</BaseBadge>
            </div>
          </div>
          <div v-else class="card-surface rounded-[1.75rem] p-12 text-center text-ink-500"><p class="text-sm">Bạn chưa có đơn hàng nào.</p><a href="/shop" class="sf-btn sf-btn-primary mt-4">Mua sắm ngay</a></div>
        </section>
      </div>

      <!-- ===== Orders list ===== -->
      <div v-else-if="view === 'orders'">
        <div v-if="orders.length" class="card-surface mt-6 overflow-hidden rounded-[1.75rem]">
          <div v-for="o in orders" :key="o.id" class="flex items-center justify-between gap-3 border-b border-cream-200 px-5 py-4 last:border-0">
            <a :href="o.url" class="flex min-w-0 flex-1 items-center gap-3"><span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-cream-100 text-brand-600"><Icon name="bag" :size="20" /></span><span class="min-w-0"><span class="block truncate text-sm font-medium text-ink-900">{{ o.order_number }}</span><span class="text-xs text-ink-500">{{ o.created_at }} · {{ o.items_count }} sản phẩm</span></span></a>
            <span class="font-semibold text-ink-900">{{ formatMoney(o.total) }}</span>
            <BaseBadge :variant="o.status === 'completed' ? 'brand' : o.status === 'cancelled' ? 'ink' : 'clay'">{{ sLabel[o.status] || o.status }}</BaseBadge>
          </div>
        </div>
        <div v-else class="card-surface rounded-[1.75rem] p-12 text-center text-ink-500"><p class="text-sm">Bạn chưa có đơn hàng nào.</p></div>
      </div>

      <!-- ===== Order detail ===== -->
      <div v-else-if="view === 'order'">
        <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_320px]">
          <div>
            <div class="card-surface rounded-[1.75rem] p-6">
              <div class="flex items-center justify-between"><h2 class="font-display text-lg font-semibold text-ink-900">Đơn hàng {{ order.order_number }}</h2><BaseBadge :variant="order.status === 'completed' ? 'brand' : order.status === 'cancelled' ? 'ink' : 'clay'">{{ sLabel[order.status] || order.status }}</BaseBadge></div>
              <p class="mt-1 text-sm text-ink-500">Đặt lúc {{ order.created_at }}</p>
              <div class="mt-5 space-y-3">
                <div v-for="(it, i) in order.items" :key="i" class="flex items-center gap-3">
                  <div class="h-14 w-14 shrink-0 overflow-hidden rounded-xl bg-cream-100"><img v-if="it.image" :src="it.image" :alt="it.name" class="h-full w-full object-cover" /><Icon v-else name="bag" :size="20" class="p-3 text-brand-600" /></div>
                  <div class="min-w-0 flex-1"><p class="truncate text-sm font-medium text-ink-900">{{ it.name }}</p><p class="text-xs text-ink-500">{{ it.sku }} · x{{ it.quantity }}</p></div>
                  <span class="text-sm font-medium text-ink-900">{{ formatMoney(it.subtotal) }}</span>
                </div>
              </div>
            </div>
          </div>
          <aside class="card-surface h-fit rounded-[1.75rem] p-6">
            <h2 class="font-display text-lg font-semibold text-ink-900">Tổng đơn</h2>
            <div class="mt-4 space-y-2 text-sm">
              <div class="flex justify-between text-ink-500"><span>Tạm tính</span><span>{{ formatMoney(order.subtotal) }}</span></div>
              <div v-if="order.discount" class="flex justify-between text-clay-600"><span>Giảm giá</span><span>-{{ formatMoney(order.discount) }}</span></div>
              <div class="flex justify-between text-ink-500"><span>Vận chuyển</span><span>{{ shippingFee }}</span></div>
              <div class="flex justify-between pt-2 text-lg font-semibold text-ink-900"><span>Tổng</span><span>{{ formatMoney(order.total) }}</span></div>
            </div>
            <div class="mt-4 space-y-1 border-t border-cream-200 pt-4 text-sm text-ink-500">
              <p>Thanh toán: {{ order.payment_method }} · {{ order.payment_status === 'paid' ? 'Đã thanh toán' : 'Chưa thanh toán' }}</p>
              <p>Vận chuyển: {{ order.shipping_method }}</p>
            </div>
            <form v-if="order.can_cancel" method="POST" :action="'/tai-khoan/don-hang/' + order.id + '/huy'">
              <input type="hidden" name="_token" :value="csrfToken()" />
              <button type="submit" class="sf-btn sf-btn-soft mt-4 w-full !text-red-600">Hủy đơn hàng</button>
            </form>
          </aside>
        </div>
      </div>

      <!-- ===== Profile ===== -->
      <div v-else-if="view === 'profile'">
        <form method="POST" action="/tai-khoan/ho-so" class="card-surface mt-6 max-w-xl rounded-[1.75rem] p-6">
          <input type="hidden" name="_token" :value="csrfToken()" />
          <h2 class="font-display text-lg font-semibold text-ink-900">Hồ sơ cá nhân</h2>
          <div class="mt-4 space-y-3">
            <div><label class="label">Họ tên</label><input v-model="p.name" name="name" required class="sf-input" /></div>
            <div><label class="label">Email</label><input v-model="p.email" name="email" type="email" required class="sf-input" /></div>
            <div><label class="label">Số điện thoại</label><input v-model="p.phone" name="phone" class="sf-input" /></div>
          </div>
          <button type="submit" class="sf-btn sf-btn-primary mt-5 w-full">Lưu thay đổi</button>
        </form>
      </div>

      <!-- ===== Addresses ===== -->
      <div v-else-if="view === 'addresses'">
        <div class="mt-6 grid gap-3 sm:grid-cols-2">
          <div v-for="a in addresses" :key="a.id" class="card-surface rounded-2xl p-5">
            <div class="flex justify-between"><p class="font-semibold text-ink-900">{{ a.name }} <span v-if="a.is_default" class="ml-1 text-xs font-semibold text-brand-700">Mặc định</span></p><div class="flex gap-1"><button @click="editAddress(a)" class="text-xs text-brand-700 hover:underline">Sửa</button></div></div>
            <p class="mt-1 text-sm text-ink-500">{{ a.phone }}</p>
            <p class="text-sm text-ink-500">{{ a.address }}, {{ a.ward }} {{ a.district }} {{ a.province }}</p>
            <form method="POST" :action="'/tai-khoan/dia-chi/' + a.id" class="mt-3"><input type="hidden" name="_token" :value="csrfToken()" /><input type="hidden" name="_method" value="DELETE" /><button type="submit" class="text-xs text-red-600 hover:underline">Xóa</button></form>
          </div>
        </div>
        <form id="address-form" method="POST" :action="editingAddressId ? '/tai-khoan/dia-chi/' + editingAddressId : '/tai-khoan/dia-chi'" class="card-surface mt-6 max-w-xl rounded-[1.75rem] p-6">
          <input type="hidden" name="_token" :value="csrfToken()" />
          <input v-if="editingAddressId" type="hidden" name="_method" value="PUT" />
          <h2 class="font-display text-lg font-semibold text-ink-900">{{ editingAddressId ? 'Sửa địa chỉ' : 'Thêm địa chỉ' }}</h2>
          <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <div><label class="label">Họ tên</label><input v-model="addr.name" name="name" required class="sf-input" /></div>
            <div><label class="label">Điện thoại</label><input v-model="addr.phone" name="phone" required class="sf-input" /></div>
            <div class="sm:col-span-2"><label class="label">Địa chỉ</label><input v-model="addr.address" name="address" required class="sf-input" /></div>
            <div><label class="label">Phường</label><input v-model="addr.ward" name="ward" class="sf-input" /></div>
            <div><label class="label">Quận</label><input v-model="addr.district" name="district" class="sf-input" /></div>
            <div><label class="label">Tỉnh/TP</label><input v-model="addr.province" name="province" class="sf-input" /></div>
            <label class="flex items-center gap-2 text-sm text-ink-700"><input type="checkbox" name="is_default" value="1" v-model="addr.is_default" class="h-4 w-4 accent-brand-600" /> Đặt làm mặc định</label>
          </div>
          <div class="mt-4 flex gap-2">
            <button type="button" @click="submitAddress" class="sf-btn sf-btn-primary flex-1">{{ editingAddressId ? 'Cập nhật' : 'Thêm địa chỉ' }}</button>
            <button v-if="editingAddressId" type="button" @click="resetAddress" class="sf-btn sf-btn-soft">Hủy</button>
          </div>
        </form>
      </div>

      <!-- ===== Reviews ===== -->
      <div v-else-if="view === 'reviews'">
        <div v-if="reviews.length" class="mt-6 space-y-3">
          <div v-for="r in reviews" :key="r.id" class="card-surface rounded-2xl p-5">
            <div class="flex items-center justify-between"><a :href="r.product_url" class="text-sm font-semibold text-brand-700 hover:underline">{{ r.product }}</a><span class="text-xs text-ink-500">{{ r.created_at }}</span></div>
            <div class="mt-1 flex items-center gap-0.5 text-amber-400"><Icon v-for="s in r.rating" :key="s" name="star" :size="13" :fill="true" :stroke-width="0" /></div>
            <p class="mt-1 text-sm font-medium text-ink-900">{{ r.title }}</p>
            <p class="mt-1 text-sm text-ink-500">{{ r.body }}</p>
          </div>
        </div>
        <div v-else class="card-surface rounded-[2rem] p-12 text-center text-ink-500"><p class="text-sm">Bạn chưa có đánh giá nào.</p></div>
      </div>

      <!-- ===== Password ===== -->
      <div v-else-if="view === 'password'">
        <form method="POST" action="/tai-khoan/mat-khau" class="card-surface mt-6 max-w-xl rounded-[1.75rem] p-6">
          <input type="hidden" name="_token" :value="csrfToken()" />
          <h2 class="font-display text-lg font-semibold text-ink-900">Đổi mật khẩu</h2>
          <div class="mt-4 space-y-3">
            <div><label class="label">Mật khẩu hiện tại</label><input name="current_password" type="password" required class="sf-input" /></div>
            <div><label class="label">Mật khẩu mới</label><input name="password" type="password" required class="sf-input" /></div>
            <div><label class="label">Nhập lại mật khẩu mới</label><input name="password_confirmation" type="password" required class="sf-input" /></div>
          </div>
          <button type="submit" class="sf-btn sf-btn-primary mt-5 w-full">Đổi mật khẩu</button>
        </form>
      </div>
    </div>
  </StorefrontLayout>
</template>
