@extends('layouts.app')

@section('title', 'Thanh toán nhanh')

@section('content')
<div class="container-x py-8">
    <x-breadcrumb :items="[['label' => 'Trang chủ', 'url' => route('home')], ['label' => 'Giỏ hàng', 'url' => route('cart.show')], ['label' => 'Thanh toán nhanh']]" />
    <div class="mt-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-display text-3xl font-semibold text-ink-900 sm:text-4xl">Thanh toán nhanh</h1>
            <p class="mt-1 text-ink-500">Chỉ cần <strong class="text-ink-900">số điện thoại</strong> và <strong class="text-ink-900">họ tên</strong>. Nhân viên sẽ gọi xác nhận đơn.</p>
        </div>
        <span class="badge bg-brand-600 text-white">Thanh toán khi nhận hàng (COD)</span>
    </div>

    <form method="POST" action="{{ route('checkout.quick.store') }}" class="mt-8 grid gap-8 lg:grid-cols-[1fr_420px]">
        @csrf

        <div class="space-y-6">
            <div class="card p-6">
                <h2 class="mb-4 font-display text-lg font-semibold text-ink-900">Thông tin nhận hàng</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label">Họ và tên *</label>
                        <input type="text" name="name" value="{{ old('name', auth()->user()?->name) }}" class="input" required placeholder="Nguyễn Văn A" autocomplete="name">
                        <x-error name="name" />
                    </div>
                    <div>
                        <label class="label">Số điện thoại *</label>
                        <input type="tel" name="phone" value="{{ old('phone', auth()->user()?->phone) }}" class="input" required placeholder="09xx xxx xxx" autocomplete="tel" inputmode="tel">
                        <x-error name="phone" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">Địa chỉ giao hàng <span class="font-normal text-ink-500">(tùy chọn)</span></label>
                        <input type="text" name="address" value="{{ old('address') }}" class="input" placeholder="Số nhà, tên đường, phường/xã..." autocomplete="street-address">
                        <x-error name="address" />
                        <p class="mt-1 text-xs text-ink-500">Để trống nếu nhân viên sẽ gọi xác nhận địa chỉ.</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">Ghi chú (tùy chọn)</label>
                        <textarea name="note" rows="2" class="input" placeholder="Ghi chú cho đơn hàng...">{{ old('note') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-cream-200 bg-white p-5 text-sm">
                <div class="flex items-start gap-3 text-ink-700">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/></svg>
                    <span>Đặt hàng thành công, nhân viên bán hàng sẽ gọi điện để <strong class="text-ink-900">xác nhận đơn và địa chỉ giao hàng</strong>. Bạn không cần thanh toán trước.</span>
                </div>
            </div>

            <label class="flex items-start gap-3 text-sm text-ink-700">
                <input type="checkbox" name="terms" class="mt-0.5 h-4 w-4 accent-brand-600" required>
                <span>Tôi đồng ý với <a href="{{ route('page.terms') }}" class="link">điều khoản</a> và <a href="{{ route('page.privacy') }}" class="link">chính sách bảo mật</a> của Trillfa Fa.</span>
            </label>
        </div>

        <div x-data class="lg:sticky lg:top-24 lg:self-start">
            <div class="card p-6">
                <h2 class="font-display text-lg font-semibold text-ink-900">Đơn hàng của bạn</h2>
                <div class="mt-4 max-h-72 space-y-3 overflow-y-auto">
                    <template x-for="item in $store.cart.items" :key="item.id">
                        <div class="flex items-center gap-3">
                            <div class="relative h-14 w-14 shrink-0 overflow-hidden rounded-xl bg-cream-100">
                                <img :src="item.image" class="h-full w-full object-cover" alt="">
                                <span class="absolute -right-1 -top-1 grid h-5 min-w-5 place-items-center rounded-full bg-ink-900 px-1 text-[10px] font-bold text-white" x-text="item.quantity"></span>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-ink-900 line-clamp-2" x-text="item.name"></p>
                                <p class="text-xs text-ink-500" x-text="item.variant_name || ''"></p>
                            </div>
                            <span class="text-sm font-semibold text-ink-900" x-text="$money(item.line_total)"></span>
                        </div>
                    </template>
                </div>

                <div class="mt-5 space-y-2 border-t border-cream-200 pt-4 text-sm">
                    <div class="flex justify-between text-ink-500"><span>Tạm tính</span><span x-text="$money($store.cart.subtotal)"></span></div>
                    <div class="flex justify-between text-brand-600" x-show="$store.cart.discount > 0"><span>Giảm giá</span><span x-text="'-' + $money($store.cart.discount)"></span></div>
                    <div class="flex justify-between text-ink-500"><span>Phí vận chuyển</span><span x-text="$store.cart.shippingFee > 0 ? $money($store.cart.shippingFee) : 'Miễn phí'"></span></div>
                    <div class="flex justify-between border-t border-cream-200 pt-3 text-lg font-semibold text-ink-900"><span>Tổng cộng</span><span x-text="$money($store.cart.total)"></span></div>
                    <div class="flex justify-between text-xs text-ink-500"><span>Thanh toán</span><span>Khi nhận hàng (COD)</span></div>
                </div>

                <button type="submit" class="btn-brand mt-5 w-full">Đặt hàng ngay</button>
                <a href="{{ route('cart.show') }}" class="btn-ghost mt-2 w-full text-ink-500">Quay lại giỏ hàng</a>
            </div>
        </div>
    </form>
</div>
@endsection