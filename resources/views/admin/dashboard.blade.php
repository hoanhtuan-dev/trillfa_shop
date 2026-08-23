@extends('layouts.admin')

@section('title', 'Tổng quan')
@section('page_title', 'Tổng quan')

@section('content')
    <!-- Stats -->
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @php
            $cards = [
                ['Doanh thu', format_price($revenue), 'bg-brand-50 text-brand-600', 'M12 6v12m-6-6h12'],
                ['Đơn hàng', number_format($orders), 'bg-blue-50 text-blue-600', 'M9 12h6m-6 3h6m-6-6h.008'],
                ['Khách hàng', number_format($customers), 'bg-violet-50 text-violet-600', 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z'],
                ['Sản phẩm', number_format($products), 'bg-amber-50 text-amber-600', 'M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4'],
            ];
        @endphp
        @foreach($cards as $c)
            <div class="card p-5">
                <div class="flex items-center justify-between">
                    <span class="grid h-10 w-10 place-items-center rounded-xl {{ $c[2] }}">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $c[3] }}"/></svg>
                    </span>
                </div>
                <p class="mt-4 font-display text-2xl font-semibold text-ink-900">{{ $c[1] }}</p>
                <p class="text-xs text-ink-500">{{ $c[0] }}</p>
            </div>
        @endforeach
    </div>

    <!-- Row: recent orders + order status -->
    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <div class="flex items-center justify-between border-b border-cream-200 p-5">
                <h2 class="font-display text-lg font-semibold text-ink-900">Đơn hàng gần đây</h2>
                <a href="{{ route('admin.orders.index') }}" class="link text-sm">Xem tất cả</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-cream-100 text-left text-xs font-semibold uppercase tracking-wide text-ink-500">
                        <tr>
                            <th class="px-5 py-3">Mã đơn</th>
                            <th class="px-5 py-3">Khách hàng</th>
                            <th class="px-5 py-3">Tổng</th>
                            <th class="px-5 py-3">Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-cream-200">
                        @foreach($recentOrders as $order)
                            <tr class="hover:bg-cream-50">
                                <td class="px-5 py-3 font-medium text-ink-900"><a href="{{ route('admin.orders.show', $order) }}" class="hover:text-brand-700">{{ $order->order_number }}</a></td>
                                <td class="px-5 py-3 text-ink-700">{{ $order->name }}</td>
                                <td class="px-5 py-3 font-semibold text-ink-900">{{ format_price($order->total) }}</td>
                                <td class="px-5 py-3"><span class="badge {{ $order->status === 'completed' ? 'bg-brand-600 text-white' : ($order->status === 'cancelled' ? 'bg-red-100 text-red-600' : 'bg-amber-100 text-amber-700') }}">{{ $order->status_label }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card p-5">
            <h2 class="mb-4 font-display text-lg font-semibold text-ink-900">Trạng thái đơn hàng</h2>
            <div class="space-y-2.5">
                @php
                    $statusLabels = ['pending' => 'Chờ xử lý', 'processing' => 'Đang xử lý', 'shipped' => 'Đang giao', 'completed' => 'Hoàn thành', 'cancelled' => 'Đã hủy', 'refunded' => 'Đã hoàn'];
                @endphp
                @foreach($statusLabels as $key => $label)
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-ink-700">{{ $label }}</span>
                        <span class="font-semibold text-ink-900">{{ $statusCounts[$key] ?? 0 }}</span>
                    </div>
                @endforeach
            </div>
            <div class="mt-5 rounded-xl bg-amber-50 p-4 text-sm">
                <p class="font-medium text-amber-700">Cảnh báo</p>
                <p class="mt-1 text-amber-600">{{ number_format($pendingOrders) }} đơn chờ xử lý · {{ number_format($lowStock) }} sản phẩm sắp hết hàng</p>
            </div>
        </div>
    </div>

    <!-- Row: top products + recent content -->
    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="card p-5">
            <h2 class="mb-4 font-display text-lg font-semibold text-ink-900">Sản phẩm bán chạy</h2>
            <div class="space-y-3">
                @foreach($topProducts as $p)
                    <div class="flex items-center gap-3">
                        <img src="{{ $p->image_url ?: asset('images/placeholder.svg') }}" class="h-12 w-12 rounded-xl object-cover" alt="">
                        <div class="flex-1 min-w-0">
                            <p class="truncate text-sm font-medium text-ink-900">{{ $p->name }}</p>
                            <p class="text-xs text-ink-500">{{ $p->category?->name ?? 'Chung' }}</p>
                        </div>
                        <span class="text-sm font-semibold text-ink-900">{{ $p->order_items_count }} đơn</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card p-5" x-data>
            <div class="flex items-center justify-between">
                <h2 class="font-display text-lg font-semibold text-ink-900">⚠️ Cảnh báo tồn kho</h2>
                <a href="{{ route('admin.products.index') }}" class="link text-sm">Sản phẩm</a>
            </div>
            <div class="mt-3 space-y-2">
                @forelse($lowStockProducts as $lp)
                    <div class="flex items-center gap-3">
                        <img src="{{ $lp->image_url ?: asset('images/placeholder.svg') }}" class="h-10 w-10 rounded-lg object-cover" alt="">
                        <div class="flex-1 min-w-0">
                            <p class="truncate text-sm font-medium text-ink-900">{{ $lp->name }}</p>
                            <p class="text-xs text-ink-500">{{ $lp->category?->name ?? '—' }}</p>
                        </div>
                        <span class="badge {{ $lp->total_stock <= 0 ? 'bg-red-100 text-red-600' : 'bg-amber-100 text-amber-700' }}">{{ $lp->total_stock }}</span>
                    </div>
                @empty
                    <p class="py-4 text-center text-sm text-ink-500">Không có sản phẩm nào sắp hết hàng.</p>
                @endforelse
            </div>
        </div>

        <div class="space-y-6">
            <div class="card p-5">
                <div class="flex items-center justify-between">
                    <h2 class="font-display text-lg font-semibold text-ink-900">Bài viết mới</h2>
                    <a href="{{ route('admin.posts.index') }}" class="link text-sm">Quản lý</a>
                </div>
                <ul class="mt-3 divide-y divide-cream-200">
                    @foreach($recentPosts as $post)
                        <li class="flex items-center justify-between py-2.5 text-sm">
                            <span class="truncate text-ink-900">{{ $post->title }}</span>
                            <span class="badge {{ $post->status === 'published' ? 'bg-brand-100 text-brand-700' : 'bg-cream-100 text-ink-500' }}">{{ $post->status }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="card p-5">
                <div class="flex items-center justify-between">
                    <h2 class="font-display text-lg font-semibold text-ink-900">Đánh giá mới</h2>
                    <a href="{{ route('admin.reviews.index') }}" class="link text-sm">Quản lý</a>
                </div>
                <ul class="mt-3 divide-y divide-cream-200">
                    @foreach($recentReviews as $review)
                        <li class="flex items-center justify-between py-2.5 text-sm">
                            <div class="min-w-0">
                                <p class="truncate text-ink-900">{{ $review->product?->name }}</p>
                                <p class="text-xs text-ink-500">{{ $review->user?->name }} · ★{{ $review->rating }}</p>
                            </div>
                            <span class="text-xs text-ink-500">{{ $review->created_at?->format('d/m') }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endsection