@extends('layouts.admin')

@section('title', 'Đơn hàng')
@section('page_title', 'Đơn hàng')

@section('content')
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <form method="GET" class="flex flex-1 items-center gap-2">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Tìm mã đơn, tên, email, SĐT..." class="input !w-full !py-2.5 sm:max-w-xs">
            <select name="status" class="input !w-auto !py-2.5">
                <option value="">Tất cả trạng thái</option>
                @foreach($statuses as $key => $label)<option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>@endforeach
            </select>
            <button type="submit" class="btn-primary btn-sm">Lọc</button>
        </form>
        <a href="{{ route('admin.orders.export') }}" class="btn-outline btn-sm"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg> Export CSV</a>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-cream-100 text-left text-xs font-semibold uppercase tracking-wide text-ink-500">
                    <tr>
                        <th class="px-5 py-3">Mã đơn</th>
                        <th class="px-5 py-3">Khách hàng</th>
                        <th class="px-5 py-3">Tổng</th>
                        <th class="px-5 py-3">Thanh toán</th>
                        <th class="px-5 py-3">Trạng thái</th>
                        <th class="px-5 py-3">Ngày</th>
                        <th class="px-5 py-3 text-right">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-cream-200">
                    @forelse($orders as $order)
                        <tr class="hover:bg-cream-50">
                            <td class="px-5 py-3"><a href="{{ route('admin.orders.show', $order) }}" class="font-medium text-brand-700 hover:underline">{{ $order->order_number }}</a></td>
                            <td class="px-5 py-3">
                                <p class="font-medium text-ink-900">{{ $order->name }}</p>
                                <p class="text-xs text-ink-500">{{ $order->phone }}</p>
                            </td>
                            <td class="px-5 py-3 font-semibold text-ink-900">{{ format_price($order->total) }}</td>
                            <td class="px-5 py-3"><span class="badge {{ $order->payment_status === 'paid' ? 'bg-brand-100 text-brand-700' : 'bg-amber-100 text-amber-700' }}">{{ $order->payment_status_label }}</span></td>
                            <td class="px-5 py-3"><span class="badge {{ $order->status === 'completed' ? 'bg-brand-600 text-white' : ($order->status === 'cancelled' ? 'bg-red-100 text-red-600' : 'bg-amber-100 text-amber-700') }}">{{ $order->status_label }}</span></td>
                            <td class="px-5 py-3 text-xs text-ink-500">{{ $order->created_at?->format('d/m/Y') }}</td>
                            <td class="px-5 py-3 text-right"><a href="{{ route('admin.orders.show', $order) }}" class="btn-outline btn-sm">Chi tiết</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-10 text-center text-ink-500">Không có đơn hàng nào.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-cream-200 p-4">{{ $orders->links() }}</div>
    </div>
@endsection