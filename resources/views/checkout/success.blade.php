@extends('layouts.app')

@section('title', 'Đặt hàng thành công')

@section('content')
<div class="container-x flex min-h-[70vh] items-center justify-center py-16">
    <div class="card w-full max-w-2xl p-8 sm:p-12">
        <div class="flex flex-col items-center text-center">
            <div class="grid h-20 w-20 place-items-center rounded-full bg-brand-600 text-white">
                <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
            </div>
            <h1 class="mt-6 font-display text-3xl font-semibold text-ink-900">Cảm ơn bạn đã đặt hàng!</h1>
            <p class="mt-2 max-w-md text-ink-500">Đơn hàng của bạn đã được tiếp nhận. Chúng tôi sẽ xử lý và thông báo trong thời gian sớm nhất.</p>
        </div>

        <div class="mt-8 rounded-2xl bg-cream-100 p-6 text-sm">
            <div class="flex justify-between"><span class="text-ink-500">Mã đơn hàng</span><span class="font-semibold text-ink-900">{{ $order->order_number }}</span></div>
            <div class="mt-2 flex justify-between"><span class="text-ink-500">Trạng thái thanh toán</span>
                <span class="badge {{ $order->payment_status === 'paid' ? 'bg-brand-600 text-white' : 'bg-amber-500 text-white' }}">{{ $order->payment_status_label }}</span>
            </div>
            <div class="mt-2 flex justify-between"><span class="text-ink-500">Tổng cộng</span><span class="font-semibold text-brand-700">{{ format_price($order->total) }}</span></div>
        </div>

        <div class="mt-8">
            <h2 class="mb-4 font-display text-lg font-semibold text-ink-900">Chi tiết đơn hàng</h2>
            <div class="divide-y divide-cream-200">
                @foreach($order->items as $item)
                    <div class="flex items-center gap-4 py-3">
                        <img src="{{ $item->image_url ?: asset('images/placeholder.svg') }}" class="h-14 w-14 rounded-xl object-cover" alt="">
                        <div class="flex-1">
                            <p class="text-sm font-medium text-ink-900">{{ $item->product_name }}</p>
                            @if($item->options)<p class="text-xs text-ink-500">{{ is_array($item->options) ? implode(' / ', $item->options) : $item->options }}</p>@endif
                        </div>
                        <div class="text-right text-sm">
                            <p class="text-ink-500">x{{ $item->quantity }}</p>
                            <p class="font-semibold text-ink-900">{{ format_price($item->subtotal) }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="mt-8 flex flex-col gap-2 sm:flex-row">
            <a href="{{ route('account.orders') }}" class="btn-primary flex-1">Theo dõi đơn hàng</a>
            <a href="{{ route('shop.index') }}" class="btn-outline flex-1">Tiếp tục mua sắm</a>
        </div>
    </div>
</div>
@endsection
