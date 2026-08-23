@extends('layouts.app')

@section('title', 'Thanh toán')

@section('content')
<div class="container-x py-8">
    <x-breadcrumb :items="[['label' => 'Trang chủ', 'url' => route('home')], ['label' => 'Giỏ hàng', 'url' => route('cart.show')], ['label' => 'Thanh toán']]" />
    <h1 class="mt-6 font-display text-3xl font-semibold text-ink-900 sm:text-4xl">Thanh toán</h1>

    <form method="POST" action="{{ route('checkout.store') }}" class="mt-8 grid gap-8 lg:grid-cols-[1fr_420px]">
        @csrf
        <div class="space-y-6">
            <!-- Contact -->
            <div class="card p-6">
                <h2 class="mb-4 font-display text-lg font-semibold text-ink-900">1. Thông tin liên hệ</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label">Họ và tên *</label>
                        <input type="text" name="name" value="{{ old('name', $address?->name ?? auth()->user()->name) }}" class="input" required>
                        <x-error name="name" />
                    </div>
                    <div>
                        <label class="label">Số điện thoại *</label>
                        <input type="text" name="phone" value="{{ old('phone', $address?->phone ?? auth()->user()->phone) }}" class="input" required placeholder="09xx xxx xxx">
                        <x-error name="phone" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">Email *</label>
                        <input type="email" name="email" value="{{ old('email', auth()->user()->email) }}" class="input" required>
                        <x-error name="email" />
                    </div>
                </div>
            </div>

            <!-- Shipping address -->
            <div class="card p-6">
                <h2 class="mb-4 font-display text-lg font-semibold text-ink-900">2. Địa chỉ giao hàng</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="label">Địa chỉ cụ thể *</label>
                        <input type="text" name="address" value="{{ old('address', $address?->address) }}" class="input" required placeholder="Số nhà, tên đường, phường/xã...">
                        <x-error name="address" />
                    </div>
                    <div>
                        <label class="label">Phường / Xã</label>
                        <input type="text" name="ward" value="{{ old('ward', $address?->ward) }}" class="input">
                    </div>
                    <div>
                        <label class="label">Quận / Huyện</label>
                        <input type="text" name="district" value="{{ old('district', $address?->district) }}" class="input">
                    </div>
                    <div>
                        <label class="label">Tỉnh / Thành phố</label>
                        <input type="text" name="province" value="{{ old('province', $address?->province ?? 'TP. Hồ Chí Minh') }}" class="input">
                    </div>
                    <div>
                        <label class="label">Ghi chú</label>
                        <input type="text" name="note" value="{{ old('note') }}" class="input" placeholder="Ghi chú cho đơn hàng">
                    </div>
                </div>
            </div>

            <!-- Shipping method -->
            <div class="card p-6" x-data>
                <h2 class="mb-4 font-display text-lg font-semibold text-ink-900">3. Phương thức vận chuyển</h2>
                <div class="space-y-2">
                    @foreach($shippingMethods as $m)
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border p-4 transition" :class="$store.cart.shippingMethod && $store.cart.shippingMethod.code === '{{ $m->code }}' ? 'border-brand-600 bg-brand-50' : 'border-cream-200'">
                            <input type="radio" name="shipping_method" value="{{ $m->code }}" :checked="$store.cart.shippingMethod && $store.cart.shippingMethod.code === '{{ $m->code }}'" @change="$store.cart.setShipping('{{ $m->code }}')" class="accent-brand-600">
                            <div class="flex-1">
                                <p class="text-sm font-semibold text-ink-900">{{ $m->name }}</p>
                                <p class="text-xs text-ink-500">{{ $m->description }} @if($m->estimated_days) · Giao trong {{ $m->estimated_days }} ngày @endif</p>
                            </div>
                            <span class="text-sm font-semibold text-ink-900">{{ $m->fee > 0 ? format_price($m->fee) : 'Miễn phí' }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <!-- Payment method -->
            <div class="card p-6" x-data="{ pay: '{{ $paymentMethods->first()?->code ?? 'cod' }}' }">
                <h2 class="mb-4 font-display text-lg font-semibold text-ink-900">4. Phương thức thanh toán</h2>
                <div class="space-y-2">
                    @foreach($paymentMethods as $m)
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border p-4 transition" :class="pay === '{{ $m->code }}' ? 'border-brand-600 bg-brand-50' : 'border-cream-200'">
                            <input type="radio" name="payment_method" value="{{ $m->code }}" x-model="pay" class="accent-brand-600">
                            <div class="flex-1">
                                <p class="text-sm font-semibold text-ink-900">{{ $m->name }}</p>
                                <p class="text-xs text-ink-500">{{ $m->description }}</p>
                            </div>
                            <span class="badge bg-cream-100 text-ink-700">{{ $m->code === 'cod' ? 'COD' : ($m->code === 'bank' ? 'Bank' : 'Online') }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <label class="flex items-start gap-3 text-sm text-ink-700">
                <input type="checkbox" name="terms" class="mt-0.5 h-4 w-4 accent-brand-600" required>
                <span>Tôi đồng ý với <a href="{{ route('page.terms') }}" class="link">điều khoản</a> và <a href="{{ route('page.privacy') }}" class="link">chính sách bảo mật</a> của Trillfa Fa.</span>
            </label>
        </div>

        <!-- Summary -->
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
                </div>

                <button type="submit" class="btn-brand mt-5 w-full">Đặt hàng ngay</button>
                <a href="{{ route('cart.show') }}" class="btn-ghost mt-2 w-full text-ink-500">Quay lại giỏ hàng</a>
            </div>
        </div>
    </form>
</div>
@endsection