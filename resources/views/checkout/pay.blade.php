@extends('layouts.app')

@section('title', 'Thanh toán đơn hàng')

@section('content')
<div class="flex min-h-[70vh] items-center justify-center px-4 py-16">
    <div class="card w-full max-w-md p-8">
        <div class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-brand-50 text-brand-600">
            <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/></svg>
        </div>
        <h1 class="mt-6 text-center font-display text-2xl font-semibold text-ink-900">Thanh toán đơn hàng</h1>
        <p class="mt-2 text-center text-sm text-ink-500">Đơn hàng <span class="font-semibold text-ink-900">{{ $order->order_number }}</span></p>

        <div class="mt-6 space-y-2 rounded-2xl bg-cream-100 p-5 text-sm">
            <div class="flex justify-between"><span class="text-ink-500">Sản phẩm</span><span class="font-medium text-ink-900">{{ $order->items->count() }}</span></div>
            <div class="flex justify-between"><span class="text-ink-500">Phương thức</span><span class="font-medium text-ink-900">{{ strtoupper($order->payment_method) }}</span></div>
            <div class="flex justify-between border-t border-cream-300 pt-2"><span class="text-ink-900">Tổng cộng</span><span class="font-semibold text-brand-700">{{ format_price($order->total) }}</span></div>
        </div>

        {{-- Mock gateway note --}}
        <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs leading-relaxed text-amber-700">
            Đây là cổng thanh toán <strong>mô phỏng</strong> cho bản demo. Nhấn nút bên dưới để xác nhận thanh toán.
        </div>

        <form method="POST" action="{{ route('checkout.confirm', $order) }}" class="mt-6">
            @csrf
            <button type="submit" class="btn-brand w-full">Xác nhận thanh toán {{ format_price($order->total) }}</button>
        </form>
        <a href="{{ route('account.orders') }}" class="btn-ghost mt-2 w-full text-ink-500">Hủy & quay lại đơn hàng</a>
    </div>
</div>
@endsection
