@extends('layouts.admin')

@section('title', 'Đơn hàng '.$order->order_number)
@section('page_title', 'Đơn hàng '.$order->order_number)

@section('content')
    <div class="mb-5 flex items-center justify-between">
        <a href="{{ route('admin.orders.index') }}" class="btn-outline btn-sm">← Quay lại</a>
        <div class="flex items-center gap-2">
            <span class="badge {{ $order->payment_status === 'paid' ? 'bg-brand-600 text-white' : 'bg-amber-500 text-white' }}">{{ $order->payment_status_label }}</span>
            <span class="badge {{ $order->status === 'completed' ? 'bg-brand-600 text-white' : ($order->status === 'cancelled' ? 'bg-red-100 text-red-600' : 'bg-amber-100 text-amber-700') }}">{{ $order->status_label }}</span>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <!-- Items -->
            <div class="card p-6">
                <h2 class="mb-4 font-display text-lg font-semibold text-ink-900">Sản phẩm</h2>
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
                <div class="mt-4 space-y-2 border-t border-cream-200 pt-4 text-sm">
                    <div class="flex justify-between"><span class="text-ink-500">Tạm tính</span><span>{{ format_price($order->subtotal) }}</span></div>
                    @if($order->discount > 0)<div class="flex justify-between"><span class="text-ink-500">Giảm giá</span><span class="text-brand-700">-{{ format_price($order->discount) }}</span></div>@endif
                    <div class="flex justify-between"><span class="text-ink-500">Vận chuyển</span><span>{{ format_price($order->shipping_fee) }}</span></div>
                    <div class="flex justify-between border-t border-cream-200 pt-2 text-base font-semibold"><span>Tổng</span><span class="text-brand-700">{{ format_price($order->total) }}</span></div>
                </div>
            </div>

            <!-- Update status -->
            <div class="card p-6">
                <h2 class="mb-4 font-display text-lg font-semibold text-ink-900">Cập nhật trạng thái</h2>
                <form method="POST" action="{{ route('admin.orders.update', $order) }}" class="grid gap-4 sm:grid-cols-3">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="label">Trạng thái đơn</label>
                        <select name="status" class="input">
                            @foreach(['pending'=>'Chờ xử lý','processing'=>'Đang xử lý','shipped'=>'Đang giao','completed'=>'Hoàn thành','cancelled'=>'Đã hủy','refunded'=>'Đã hoàn'] as $k => $v)
                                <option value="{{ $k }}" @selected($order->status === $k)>{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">Trạng thái thanh toán</label>
                        <select name="payment_status" class="input">
                            @foreach(['unpaid'=>'Chưa thanh toán','paid'=>'Đã thanh toán','failed'=>'Thất bại','refunded'=>'Đã hoàn'] as $k => $v)
                                <option value="{{ $k }}" @selected($order->payment_status === $k)>{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">Mã vận đơn</label>
                        <input type="text" name="tracking_code" value="{{ $order->tracking_code }}" class="input">
                    </div>
                    <div class="sm:col-span-3"><button type="submit" class="btn-brand">Lưu trạng thái</button></div>
                </form>
            </div>
        </div>

        <div class="space-y-6">
            <div class="card p-6">
                <h2 class="mb-4 font-display text-lg font-semibold text-ink-900">Khách hàng</h2>
                <div class="space-y-1 text-sm">
                    <p class="font-medium text-ink-900">{{ $order->name }}</p>
                    <p class="text-ink-500">{{ $order->email }}</p>
                    <p class="text-ink-500">{{ $order->phone }}</p>
                </div>
            </div>
            <div class="card p-6">
                <h2 class="mb-4 font-display text-lg font-semibold text-ink-900">Giao hàng</h2>
                <p class="text-sm leading-relaxed text-ink-500">{{ $order->address }}</p>
                <p class="mt-1 text-sm text-ink-500">{{ implode(', ', array_filter([$order->ward, $order->district, $order->province])) }}</p>
                @if($order->note)<p class="mt-2 rounded-lg bg-cream-100 p-2 text-xs text-ink-500">Ghi chú: {{ $order->note }}</p>@endif
            </div>
            <div class="card p-6">
                <h2 class="mb-4 font-display text-lg font-semibold text-ink-900">Thanh toán</h2>
                <div class="space-y-1 text-sm">
                    <div class="flex justify-between"><span class="text-ink-500">Phương thức</span><span class="font-medium">{{ strtoupper($order->payment_method) }}</span></div>
                    <div class="flex justify-between"><span class="text-ink-500">Ngày đặt</span><span>{{ $order->placed_at?->format('d/m/Y H:i') }}</span></div>
                    @if($order->paid_at)<div class="flex justify-between"><span class="text-ink-500">Thanh toán</span><span>{{ $order->paid_at->format('d/m/Y') }}</span></div>@endif
                </div>
            </div>
        </div>
    </div>
@endsection
