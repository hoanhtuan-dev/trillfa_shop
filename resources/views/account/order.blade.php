@extends('layouts.account')

@section('title', 'Chi tiết đơn hàng')

@section('account_content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="font-display text-2xl font-semibold text-ink-900">Đơn hàng {{ $order->order_number }}</h1>
            <p class="mt-1 text-sm text-ink-500">Đặt ngày {{ $order->created_at?->format('d/m/Y H:i') }}</p>
        </div>
        <a href="{{ route('account.orders') }}" class="btn-outline btn-sm">← Quay lại</a>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-xl bg-brand-50 p-4 text-sm text-brand-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ session('error') }}</div>
    @endif

    <!-- Status -->
    <div class="card mb-6 p-6">
        <div class="mb-4 flex items-center justify-between">
            <span class="badge {{ $order->status === 'completed' ? 'bg-brand-600 text-white' : ($order->status === 'cancelled' ? 'bg-red-100 text-red-600' : 'bg-amber-100 text-amber-700') }}">{{ $order->status_label }}</span>
            <span class="text-sm text-ink-500">{{ $order->payment_status_label }}</span>
        </div>
        <div class="flex items-center">
            @php
                $steps = ['pending', 'processing', 'shipped', 'completed'];
                $current = array_search($order->status, $steps);
                $current = $current === false ? 0 : $current;
            @endphp
            @foreach($steps as $i => $step)
                <div class="flex flex-1 items-center">
                    <div class="flex flex-col items-center">
                        <span class="grid h-8 w-8 place-items-center rounded-full text-xs font-bold transition {{ $order->status === $step || $i < $current ? 'bg-brand-600 text-white' : 'bg-cream-100 text-ink-500' }}">{{ $i + 1 }}</span>
                        <span class="mt-1 text-[11px] {{ $i <= $current ? 'text-brand-700' : 'text-ink-500' }}">{{ ['Chờ xử lý','Đang xử lý','Đang giao','Hoàn thành'][$i] }}</span>
                    </div>
                    @if($i < count($steps) - 1)
                        <div class="mx-2 mb-4 h-0.5 flex-1 {{ $i < $current ? 'bg-brand-600' : 'bg-cream-200' }}"></div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <!-- Items -->
            <div class="card p-6">
                <h2 class="mb-4 font-display text-lg font-semibold text-ink-900">Sản phẩm</h2>
                <div class="divide-y divide-cream-200">
                    @foreach($order->items as $item)
                        <div class="flex items-center gap-4 py-4">
                            <img src="{{ $item->image_url ?: asset('images/placeholder.svg') }}" class="h-16 w-16 rounded-2xl object-cover" alt="">
                            <div class="flex-1">
                                <a href="{{ $item->product ? route('product.show', $item->product->slug) : '#' }}" class="text-sm font-medium text-ink-900 hover:text-brand-700">{{ $item->product_name }}</a>
                                @if($item->options)<p class="text-xs text-ink-500">{{ is_array($item->options) ? implode(' / ', $item->options) : $item->options }}</p>@endif
                            </div>
                            <div class="text-right text-sm">
                                <p class="text-ink-500">x{{ $item->quantity }}</p>
                                <p class="font-semibold text-ink-900">{{ format_price($item->subtotal) }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4 space-y-2 border-t border-cream-200 pt-4 text-sm">
                    <div class="flex justify-between"><span class="text-ink-500">Tạm tính</span><span class="text-ink-900">{{ format_price($order->subtotal) }}</span></div>
                    @if($order->discount > 0)<div class="flex justify-between"><span class="text-ink-500">Giảm giá</span><span class="text-brand-700">-{{ format_price($order->discount) }}</span></div>@endif
                    <div class="flex justify-between"><span class="text-ink-500">Phí vận chuyển</span><span class="text-ink-900">{{ format_price($order->shipping_fee) }}</span></div>
                    <div class="flex justify-between border-t border-cream-200 pt-3 text-base font-semibold"><span class="text-ink-900">Tổng cộng</span><span class="text-brand-700">{{ format_price($order->total) }}</span></div>
                </div>
            </div>

            @if($order->can_cancel)
                <form method="POST" action="{{ route('account.order.cancel', $order) }}" onsubmit="return confirm('Bạn chắc chắn muốn hủy đơn hàng này?')" class="flex justify-end">
                    @csrf
                    <button type="submit" class="btn-outline !border-red-300 !text-red-600 hover:!bg-red-600 hover:!text-white">Hủy đơn hàng</button>
                </form>
            @endif
        </div>

        <div class="space-y-6">
            <div class="card p-6">
                <h2 class="mb-4 font-display text-lg font-semibold text-ink-900">Giao hàng</h2>
                <div class="space-y-2 text-sm">
                    <p class="font-medium text-ink-900">{{ $order->name }}</p>
                    <p class="text-ink-500">{{ $order->phone }}</p>
                    <p class="text-ink-500">{{ $order->address }}</p>
                    @if($order->ward || $order->district || $order->province)<p class="text-ink-500">{{ implode(', ', array_filter([$order->ward, $order->district, $order->province])) }}</p>@endif
                </div>
            </div>
            <div class="card p-6">
                <h2 class="mb-4 font-display text-lg font-semibold text-ink-900">Thanh toán</h2>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-ink-500">Phương thức</span><span class="font-medium text-ink-900">{{ strtoupper($order->payment_method) }}</span></div>
                    <div class="flex justify-between"><span class="text-ink-500">Trạng thái</span><span class="font-medium text-ink-900">{{ $order->payment_status_label }}</span></div>
                    @if($order->tracking_code)<div class="flex justify-between"><span class="text-ink-500">Mã vận đơn</span><span class="font-medium text-ink-900">{{ $order->tracking_code }}</span></div>@endif
                </div>
            </div>
        </div>
    </div>
@endsection
