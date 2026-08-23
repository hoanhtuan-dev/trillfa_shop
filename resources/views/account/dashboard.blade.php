@extends('layouts.account')

@section('title', 'Tài khoản')

@section('account_content')
    <div class="mb-6">
        <h1 class="font-display text-2xl font-semibold text-ink-900">Xin chào, {{ auth()->user()->name }}</h1>
        <p class="mt-1 text-sm text-ink-500">Đây là tổng quan tài khoản của bạn tại Trillfa Fa.</p>
    </div>

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        @php
            $stats = [
                ['Đơn hàng', $orderCount, 'order'],
                ['Yêu thích', $wishlistCount, 'heart'],
                ['Địa chỉ', $addressCount, 'pin'],
                ['Đánh giá', $user->reviews()->count(), 'star'],
            ];
        @endphp
        @foreach($stats as $s)
            <div class="card p-5">
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-brand-50 text-brand-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $s[2] === 'order' ? 'M9 12h6m-6 3h6m-6-6h.008M13.5 3H13a4.5 4.5 0 00-4.5 4.5V7.5A4.5 4.5 0 004 12v7.5A1.5 1.5 0 005.5 21h13a1.5 1.5 0 001.5-1.5V12a4.5 4.5 0 00-4.5-4.5H10.5A1.5 1.5 0 009 6V4.5A1.5 1.5 0 0010.5 3H13z' : ($s[2] === 'heart' ? 'M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z' : ($s[2] === 'pin' ? 'M15 10.5a3 3 0 11-6 0 3 3 0 016 0z M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z' : 'M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z')) }}" /></svg>
                </span>
                <p class="mt-3 font-display text-2xl font-semibold text-ink-900">{{ $s[1] }}</p>
                <p class="text-xs text-ink-500">{{ $s[0] }}</p>
            </div>
        @endforeach
    </div>

    <div class="card mt-8 p-6">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="font-display text-lg font-semibold text-ink-900">Đơn hàng gần đây</h2>
            <a href="{{ route('account.orders') }}" class="link text-sm">Xem tất cả</a>
        </div>
        @if($orders->isEmpty())
            <p class="py-8 text-center text-sm text-ink-500">Bạn chưa có đơn hàng nào.</p>
        @else
            <div class="divide-y divide-cream-200">
                @foreach($orders as $order)
                    <a href="{{ route('account.order', $order) }}" class="flex items-center justify-between gap-4 py-3">
                        <div>
                            <p class="text-sm font-medium text-ink-900">{{ $order->order_number }}</p>
                            <p class="text-xs text-ink-500">{{ $order->created_at?->format('d/m/Y H:i') }} · {{ $order->items_count }} sản phẩm</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="badge {{ $order->status === 'completed' ? 'bg-brand-600 text-white' : ($order->status === 'cancelled' ? 'bg-red-100 text-red-600' : 'bg-amber-100 text-amber-700') }}">{{ $order->status_label }}</span>
                            <span class="text-sm font-semibold text-ink-900">{{ format_price($order->total) }}</span>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
@endsection
