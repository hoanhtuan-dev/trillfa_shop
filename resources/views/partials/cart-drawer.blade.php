<div x-data x-show="$store.cart.open" @keydown.escape.window="$store.cart.closeDrawer()" class="fixed inset-0 z-[60]" style="display:none">
    <!-- Backdrop -->
    <div x-show="$store.cart.open" x-transition.opacity.duration.200ms @click="$store.cart.closeDrawer()" class="absolute inset-0 bg-ink-900/40 backdrop-blur-sm"></div>

    <!-- Panel -->
    <div x-show="$store.cart.open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full" class="absolute right-0 top-0 flex h-full w-full max-w-md flex-col bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-cream-200 px-6 py-5">
            <h2 class="font-display text-lg font-semibold text-ink-900">
                Giỏ hàng <span class="text-ink-500" x-show="$store.cart.count > 0" x-text="'(' + $store.cart.count + ')'"></span>
            </h2>
            <button @click="$store.cart.closeDrawer()" class="btn-ghost !p-2" aria-label="Đóng">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- Items -->
        <div class="flex-1 overflow-y-auto px-6 py-4">
            <template x-if="!$store.cart.loading && $store.cart.isEmpty">
                <div class="flex h-full flex-col items-center justify-center text-center">
                    <div class="grid h-20 w-20 place-items-center rounded-full bg-cream-100 text-ink-500">
                        <svg class="h-9 w-9" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272"/></svg>
                    </div>
                    <p class="mt-4 font-medium text-ink-900">Giỏ hàng của bạn đang trống</p>
                    <p class="mt-1 text-sm text-ink-500">Khám phá bộ sưu tập mới nhất tại Trillfa Fa.</p>
                    <a @click="$store.cart.closeDrawer()" href="{{ route('shop.index') }}" class="btn-primary mt-6">Mua sắm ngay</a>
                </div>
            </template>

            <div class="space-y-5">
                <template x-for="item in $store.cart.items" :key="item.id">
                    <div class="flex gap-4">
                        <a :href="item.url" class="h-20 w-20 shrink-0 overflow-hidden rounded-2xl bg-cream-100">
                            <img :src="item.image" class="h-full w-full object-cover" alt="" onerror="this.src='/images/placeholder.svg'" loading="lazy">
                        </a>
                        <div class="flex flex-1 flex-col">
                            <div class="flex justify-between gap-2">
                                <a :href="item.url" class="text-sm font-medium text-ink-900 hover:text-brand-700" x-text="item.name"></a>
                                <button @click="$store.cart.remove(item.id)" class="text-ink-500 hover:text-red-600" aria-label="Xóa">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                                </button>
                            </div>
                            <template x-if="item.variant_name">
                                <p class="mt-0.5 text-xs text-ink-500" x-text="item.variant_name"></p>
                            </template>
                            <div class="mt-auto flex items-center justify-between pt-2">
                                <div class="flex items-center rounded-full border border-cream-200">
                                    <button @click="$store.cart.updateQuantity(item.id, item.quantity - 1)" class="px-3 py-1.5 text-ink-700 hover:text-ink-900">−</button>
                                    <span class="min-w-6 text-center text-sm font-medium" x-text="item.quantity"></span>
                                    <button @click="$store.cart.updateQuantity(item.id, item.quantity + 1)" class="px-3 py-1.5 text-ink-700 hover:text-ink-900">+</button>
                                </div>
                                <span class="text-sm font-semibold text-ink-900" x-text="$money(item.line_total)"></span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Footer -->
        <div class="border-t border-cream-200 px-6 py-5" x-show="!$store.cart.isEmpty">
            <!-- Coupon -->
            <div x-data="{ code: '', applying: false, applied: false }" x-show="!$store.cart.coupon" class="mb-4">
                <div class="flex gap-2">
                    <input x-model="code" @keydown.enter.prevent="apply" placeholder="Nhập mã giảm giá" class="input !py-2.5">
                    <button @click="apply" :disabled="applying" class="btn-outline btn-sm shrink-0">
                        <span x-show="!applying">Áp dụng</span>
                        <span x-show="applying">...</span>
                    </button>
                </div>
                <p x-show="applied" x-text="applied" class="mt-2 text-xs text-brand-600"></p>
            </div>
            <template x-if="$store.cart.coupon">
                <div class="mb-4 flex items-center justify-between rounded-xl bg-brand-50 px-4 py-2.5 text-sm">
                    <span class="font-medium text-brand-800">Mã <span x-text="$store.cart.coupon.code"></span></span>
                    <button @click="$store.cart.removeCoupon()" class="text-brand-700 underline">Xóa</button>
                </div>
            </template>

            <div class="space-y-1.5 text-sm">
                <div class="flex justify-between text-ink-500"><span>Tạm tính</span><span x-text="$money($store.cart.subtotal)"></span></div>
                <div class="flex justify-between text-brand-600" x-show="$store.cart.discount > 0"><span>Giảm giá</span><span x-text="'-' + $money($store.cart.discount)"></span></div>
                <div class="flex justify-between pt-2 text-base font-semibold text-ink-900"><span>Tổng cộng</span><span x-text="$money($store.cart.total)"></span></div>
            </div>
            @guest
                <a href="{{ route('checkout.quick') }}" class="btn-brand mt-4 w-full">Thanh toán nhanh <span class="font-normal opacity-80">(chỉ cần SĐT)</span></a>
                <a href="{{ route('checkout.show') }}" class="btn-outline mt-2 w-full">Thanh toán đầy đủ</a>
            @else
                <a href="{{ route('checkout.show') }}" class="btn-brand mt-4 w-full">Thanh toán</a>
                <a href="{{ route('checkout.quick') }}" class="btn-outline mt-2 w-full">Thanh toán nhanh</a>
            @endguest
            <button @click="$store.cart.closeDrawer()" class="btn-ghost mt-2 w-full text-ink-500">Tiếp tục mua sắm</button>
        </div>
    </div>
</div>