@extends('layouts.account')

@section('title', 'Đơn hàng')

@section('account_content')
    <div class="mb-6">
        <h1 class="font-display text-2xl font-semibold text-ink-900">Đơn hàng của tôi</h1>
        <p class="mt-1 text-sm text-ink-500">Theo dõi và quản lý tất cả đơn hàng của bạn.</p>
    </div>

    @if($orders->isEmpty())
        <div class="card flex flex-col items-center justify-center p-16 text-center">
            <p class="font-medium text-ink-900">Bạn chưa có đơn hàng nào</p>
            <p class="mt-1 text-sm text-ink-500">Hãy bắt đầu mua sắm và tạo đơn hàng đầu tiên của bạn.</p>
            <a href="{{ route('shop.index') }}" class="btn-primary mt-6">Mua sắm ngay</a>
        </div>
    @else
        <div class="space-y-4">
            @foreach($orders as $order)
                <div class="card p-6">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-cream-200 pb-4">
                        <div>
                            <p class="font-medium text-ink-900">{{ $order->order_number }}</p>
                            <p class="text-xs text-ink-500">Đặt ngày {{ $order->created_at?->format('d/m/Y H:i') }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="badge {{ $order->status === 'completed' ? 'bg-brand-600 text-white' : ($order->status === 'cancelled' ? 'bg-red-100 text-red-600' : 'bg-amber-100 text-amber-700') }}">{{ $order->status_label }}</span>
                            <span class="badge {{ $order->payment_status === 'paid' ? 'bg-brand-100 text-brand-700' : 'bg-amber-100 text-amber-700' }}">{{ $order->payment_status_label }}</span>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-3 pt-4">
                        <div class="flex -space-x-3">
                            @foreach($order->items->take(4) as $item)
                                <img src="{{ $item->image_url ?: asset('images/placeholder.svg') }}" class="h-12 w-12 rounded-xl border-2 border-white object-cover" alt="">
                            @endforeach
                            @if($order->items->count() > 4)
                                <span class="grid h-12 w-12 place-items-center rounded-xl border-2 border-white bg-cream-100 text-xs font-semibold text-ink-500">+{{ $order->items->count() - 4 }}</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="font-semibold text-ink-900">{{ format_price($order->total) }}</span>
                            <a href="{{ route('account.order', $order) }}" class="btn-outline btn-sm">Xem chi tiết</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-8">{{ $orders->links() }}</div>
    @endif
@endsection
