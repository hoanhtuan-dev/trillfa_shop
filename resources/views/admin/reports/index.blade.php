@extends('layouts.admin')

@section('title', 'Báo cáo')
@section('page_title', 'Báo cáo thống kê')

@section('content')
<div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <form method="GET" class="flex flex-wrap items-end gap-2">
        <div>
            <label class="label">Từ ngày</label>
            <input type="date" name="from" value="{{ $from }}" class="input !w-44 !py-2">
        </div>
        <div>
            <label class="label">Đến ngày</label>
            <input type="date" name="to" value="{{ $to }}" class="input !w-44 !py-2">
        </div>
        <button type="submit" class="btn-primary btn-sm">Xem</button>
        <a href="{{ route('admin.reports.index') }}" class="btn-ghost btn-sm">30 ngày</a>
    </form>
</div>

<!-- Metrics -->
<div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
    @php
        $cards = [
            ['Doanh thu', format_price($revenue), 'bg-brand-50 text-brand-600', 'M12 6v12m-6-6h12'],
            ['Đơn hàng', number_format($ordersCount), 'bg-blue-50 text-blue-600', 'M9 12h6m-6 3h6m-6-6h.008'],
            ['Giá trị TB đơn', format_price($aov), 'bg-violet-50 text-violet-600', 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.25a7.5 7.5 0 0115 0v.75a.75.75 0 01-.75.75H5.25a.75.75 0 01-.75-.75v-.75z'],
            ['Khách hàng', number_format($customers), 'bg-amber-50 text-amber-600', 'M15 10.5a3 3 0 11-6 0 3 3 0 016 0zM19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z'],
            ['Hoàn tiền', format_price($refunded), 'bg-red-50 text-red-600', 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z'],
        ];
    @endphp
    @foreach($cards as $c)
        <div class="card p-5">
            <span class="grid h-10 w-10 place-items-center rounded-xl {{ $c[2] }}">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $c[3] }}"/></svg>
            </span>
            <p class="mt-4 font-display text-2xl font-semibold text-ink-900">{{ $c[1] }}</p>
            <p class="text-xs text-ink-500">{{ $c[0] }}</p>
        </div>
    @endforeach
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-3">
    <!-- Revenue chart -->
    <div class="card p-5 lg:col-span-2">
        <h2 class="mb-4 font-display text-lg font-semibold text-ink-900">Doanh thu theo ngày</h2>
        @if($revenueByDay->isEmpty())
            <p class="py-10 text-center text-sm text-ink-500">Không có dữ liệu trong khoảng này.</p>
        @else
            <div class="flex h-56 items-end gap-1">
                @foreach($revenueByDay as $d)
                    <div class="group relative flex flex-1 flex-col items-center justify-end h-full">
                        <div class="w-full rounded-t-md bg-brand-500 transition group-hover:bg-brand-600" style="height: {{ max(4, round($d->total / $maxDay * 100)) }}%"></div>
                    </div>
                @endforeach
            </div>
            <div class="mt-2 flex justify-between text-[10px] text-ink-500">
                <span>{{ \Carbon\Carbon::parse($revenueByDay->first()->day)->format('d/m') }}</span>
                <span>{{ \Carbon\Carbon::parse($revenueByDay->last()->day)->format('d/m') }}</span>
            </div>
        @endif
    </div>

    <!-- Orders by status -->
    <div class="card p-5">
        <h2 class="mb-4 font-display text-lg font-semibold text-ink-900">Đơn hàng theo trạng thái</h2>
        <div class="space-y-2.5">
            @foreach($statusLabels as $key => $label)
                <div class="flex items-center justify-between text-sm">
                    <span class="text-ink-700">{{ $label }}</span>
                    <span class="font-semibold text-ink-900">{{ $ordersByStatus[$key] ?? 0 }}</span>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <!-- Top products -->
    <div class="card overflow-hidden">
        <div class="border-b border-cream-200 p-5"><h2 class="font-display text-lg font-semibold text-ink-900">Sản phẩm bán chạy</h2></div>
        <div class="divide-y divide-cream-200">
            @forelse($topProducts as $p)
                <div class="flex items-center gap-3 p-4">
                    <div class="flex-1 min-w-0">
                        <p class="truncate text-sm font-medium text-ink-900">{{ $p->product_name }}</p>
                        <p class="text-xs text-ink-500">{{ $p->qty }} sản phẩm</p>
                    </div>
                    <span class="text-sm font-semibold text-brand-700">{{ format_price($p->revenue) }}</span>
                </div>
            @empty
                <p class="p-8 text-center text-sm text-ink-500">Chưa có dữ liệu.</p>
            @endforelse
        </div>
    </div>

    <!-- Top customers -->
    <div class="card overflow-hidden">
        <div class="border-b border-cream-200 p-5"><h2 class="font-display text-lg font-semibold text-ink-900">Khách hàng chi tiêu nhiều</h2></div>
        <div class="divide-y divide-cream-200">
            @forelse($topCustomers as $c)
                <div class="flex items-center gap-3 p-4">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-brand-600 font-bold text-white">{{ strtoupper(substr($c->name, 0, 1)) }}</span>
                    <div class="flex-1 min-w-0">
                        <p class="truncate text-sm font-medium text-ink-900">{{ $c->name }}</p>
                        <p class="truncate text-xs text-ink-500">{{ $c->email }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold text-ink-900">{{ format_price($c->total) }}</p>
                        <p class="text-xs text-ink-500">{{ $c->orders_count }} đơn</p>
                    </div>
                </div>
            @empty
                <p class="p-8 text-center text-sm text-ink-500">Chưa có dữ liệu.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
