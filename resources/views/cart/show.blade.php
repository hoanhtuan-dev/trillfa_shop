@extends('layouts.app')

@section('title', 'Giỏ hàng')

@section('content')
<div class="container-x py-8">
    <x-breadcrumb :items="[['label' => 'Trang chủ', 'url' => route('home')], ['label' => 'Giỏ hàng']]" />
    <h1 class="mt-6 font-display text-3xl font-semibold text-ink-900 sm:text-4xl">Giỏ hàng</h1>

    <div class="mt-8 grid gap-8 lg:grid-cols-[1fr_360px]">
        <!-- Items -->
        <div x-data>
            <template x-if="!$store.cart.loading && $store.cart.isEmpty">
                <div class="card flex flex-col items-center justify-center p-16 text-center">
                    <div class="grid h-20 w-20 place-items-center rounded-full bg-cream-100 text-ink-500">
                        <svg class="h-9 w-9" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M5.106 5.272L7.5 14.25m0 0h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84m-1.098 8.978a10.5 10.5 0 11-2.66 4.968"/></svg>
                    </div>
                    <p class="mt-4 font-medium text-ink-900">Giỏ hàng của bạn đang trống</p>
                    <p class="mt-1 text-sm text-ink-500">Hãy khám phá bộ sưu tập mới nhất tại Trillfa Fa.</p>
                    <a href="{{ route('shop.index') }}" class="btn-primary mt-6">Mua sắm ngay</a>
                </div>
            </template>

            <div class="card overflow-hidden">
                <div class="hidden grid-cols-12 gap-4 border-b border-cream-200 bg-cream-100 px-6 py-3 text-xs font-semibold uppercase tracking-wide text-ink-500 sm:grid">
                    <div class="col-span-6">Sản phẩm</div>
                    <div class="col-span-2 text-center">Đơn giá</div>
                    <div class="col-span-2 text-center">Số lượng</div>
                    <div class="col-span-2 text-right">Tổng</div>
                </div>
                <div class="divide-y divide-cream-200">
                    <template x-for="item in $store.cart.items" :key="item.id">
                        <div class="grid grid-cols-12 items-center gap-4 p-6">
                            <div class="col-span-12 flex items-center gap-4 sm:col-span-6">
                                <a :href="item.url" class="h-20 w-20 shrink-0 overflow-hidden rounded-2xl bg-cream-100">
                                    <img :src="item.image" class="h-full w-full object-cover" alt="">
                                </a>
                                <div class="min-w-0">
                                    <a :href="item.url" class="line-clamp-2 text-sm font-medium text-ink-900 hover:text-brand-700" x-text="item.name"></a>
                                    <template x-if="item.variant_name">
                                        <p class="mt-0.5 text-xs text-ink-500" x-text="item.variant_name"></p>
                                    </template>
                                    <button @click="$store.cart.remove(item.id)" class="mt-2 inline-flex items-center gap-1 text-xs text-red-500 hover:text-red-700">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                                        Xóa
                                    </button>
                                </div>
                            </div>
                            <div class="col-span-4 text-center sm:col-span-2"><span class="text-sm text-ink-700 sm:hidden">Đơn giá: </span><span class="text-sm font-medium text-ink-900" x-text="$money(item.price)"></span></div>
                            <div class="col-span-4 flex items-center justify-end sm:col-span-2 sm:justify-center">
                                <div class="flex items-center rounded-full border border-cream-300">
                                    <button @click="$store.cart.updateQuantity(item.id, item.quantity - 1)" class="px-3 py-2 text-ink-700 hover:text-ink-900">−</button>
                                    <span class="min-w-7 text-center text-sm font-medium" x-text="item.quantity"></span>
                                    <button @click="$store.cart.updateQuantity(item.id, item.quantity + 1)" class="px-3 py-2 text-ink-700 hover:text-ink-900">+</button>
                                </div>
                            </div>
                            <div class="col-span-4 text-right sm:col-span-2"><span class="text-sm font-semibold text-ink-900" x-text="$money(item.line_total)"></span></div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Summary -->
        <div x-data class="lg:sticky lg:top-24 lg:self-start" x-show="!$store.cart.isEmpty">
            <div class="card p-6">
                <h2 class="font-display text-lg font-semibold text-ink-900">Tóm tắt đơn hàng</h2>

                <!-- Coupon -->
                <div class="mt-5" x-data="{ code: '', applying: false }" x-show="!$store.cart.coupon">
                    <p class="label">Mã giảm giá</p>
                    <div class="flex gap-2">
                        <input x-model="code" @keydown.enter.prevent="apply" placeholder="Nhập mã" class="input !py-2.5">
                        <button @click="apply" :disabled="applying" class="btn-outline btn-sm shrink-0">
                            <span x-show="!applying">Áp dụng</span><span x-show="applying">...</span>
                        </button>
                    </div>
                </div>
                <template x-if="$store.cart.coupon">
                    <div class="mt-5 flex items-center justify-between rounded-xl bg-brand-50 px-4 py-2.5 text-sm">
                        <span class="font-medium text-brand-800">Mã <span x-text="$store.cart.coupon.code"></span> (-<span x-text="$money($store.cart.discount)"></span>)</span>
                        <button @click="$store.cart.removeCoupon()" class="text-brand-700 underline">Xóa</button>
                    </div>
                </template>

                <!-- Shipping method -->
                <div class="mt-6">
                    <p class="label">Vận chuyển</p>
                    <div class="space-y-2">
                        <template x-for="m in $store.cart.shippingMethods" :key="m.code">
                            <label class="flex cursor-pointer items-center gap-3 rounded-xl border p-3 transition" :class="$store.cart.shippingMethod && $store.cart.shippingMethod.code === m.code ? 'border-brand-600 bg-brand-50' : 'border-cream-200'">
                                <input type="radio" name="shipping" :value="m.code" :checked="$store.cart.shippingMethod && $store.cart.shippingMethod.code === m.code" @change="$store.cart.setShipping(m.code)" class="accent-brand-600">
                                <span class="flex-1 text-sm font-medium text-ink-900" x-text="m.name"></span>
                                <span class="text-sm text-ink-700" x-text="m.fee > 0 ? $money(m.fee) : 'Miễn phí'"></span>
                            </label>
                        </template>
                    </div>
                </div>

                <div class="mt-6 space-y-2 border-t border-cream-200 pt-4 text-sm">
                    <div class="flex justify-between text-ink-500"><span>Tạm tính</span><span x-text="$money($store.cart.subtotal)"></span></div>
                    <div class="flex justify-between text-brand-600" x-show="$store.cart.discount > 0"><span>Giảm giá</span><span x-text="'-' + $money($store.cart.discount)"></span></div>
                    <div class="flex justify-between text-ink-500"><span>Phí vận chuyển</span><span x-text="$store.cart.shippingFee > 0 ? $money($store.cart.shippingFee) : 'Miễn phí'"></span></div>
                    <div class="flex justify-between border-t border-cream-200 pt-3 text-base font-semibold text-ink-900"><span>Tổng cộng</span><span x-text="$money($store.cart.total)"></span></div>
                </div>

                <a href="{{ route('checkout.show') }}" class="btn-brand mt-5 w-full">Tiến hành thanh toán</a>
                <a href="{{ route('checkout.quick') }}" class="btn-outline mt-2 w-full">Thanh toán nhanh <span class="font-normal opacity-80">(chỉ cần SĐT)</span></a>
                <a href="{{ route('shop.index') }}" class="btn-ghost mt-2 w-full text-ink-500">Tiếp tục mua sắm</a>
            </div>
        </div>
    </div>
</div>
@endsection