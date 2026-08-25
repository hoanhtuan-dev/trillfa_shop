@extends('layouts.app')

@section('title', 'Đặt hàng thành công')

@section('content')
<div class="container-x py-12">
    <div class="mx-auto w-full max-w-2xl">
        <div class="card p-8 sm:p-10">
            <div class="flex flex-col items-center text-center">
                <div class="grid h-20 w-20 place-items-center rounded-full bg-brand-600 text-white">
                    <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                </div>
                <h1 class="mt-6 font-display text-3xl font-semibold text-ink-900">Đặt hàng thành công!</h1>
                <p class="mt-2 max-w-md text-ink-500">Đơn hàng của bạn đã được tiếp nhận. Cảm ơn bạn đã chọn Trillfa Fa.</p>
            </div>

            <div class="mt-8 rounded-2xl bg-cream-100 p-6 text-sm">
                <div class="flex justify-between"><span class="text-ink-500">Mã đơn hàng</span><span class="font-semibold text-ink-900">{{ $order->order_number }}</span></div>
                <div class="mt-2 flex justify-between"><span class="text-ink-500">Thanh toán</span><span class="font-semibold text-ink-900">Khi nhận hàng (COD)</span></div>
                <div class="mt-2 flex justify-between"><span class="text-ink-500">Tổng cộng</span><span class="font-semibold text-brand-700">{{ format_price($order->total) }}</span></div>
            </div>

            <!-- Staff confirmation notice -->
            <div class="mt-6 flex items-start gap-3 rounded-2xl border border-brand-200 bg-brand-50 p-5 text-sm text-ink-800">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/></svg>
                <span><strong class="font-semibold text-ink-900">Nhân viên bán hàng sẽ gọi điện thoại</strong> ({{ $order->phone }}) để xác nhận đơn và địa chỉ giao hàng trong thời gian sớm nhất. Bạn có thể tiếp tục mua sắm ngay.</span>
            </div>

            <!-- Upsell: register / complete account -->
            @if($pendingUser)
                <div class="mt-6 rounded-2xl border border-cream-200 bg-white p-6 text-center">
                    <h2 class="font-display text-lg font-semibold text-ink-900">Tạo tài khoản để quản lý đơn tốt hơn</h2>
                    <p class="mt-2 text-sm text-ink-500">Chúng tôi đã lưu thông tin của bạn. Hoàn thiện tài khoản để xem lịch sử đơn hàng, theo dõi trạng thái và đặt hàng nhanh hơn.</p>
                    <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                        <a href="{{ route('account.complete', $order) }}" class="btn-brand flex-1">Hoàn thiện tài khoản</a>
                        <a href="{{ route('shop.index') }}" class="btn-outline flex-1">Tiếp tục mua sắm</a>
                    </div>
                    <p class="mt-3 text-xs text-ink-400">Có thể hoàn thiện sau bất cứ lúc nào từ trang này. Nhân viên vẫn sẽ xác nhận đơn dù bạn chưa đăng ký.</p>
                </div>
            @else
                <div class="mt-6 rounded-2xl border border-cream-200 bg-white p-6 text-center">
                    @auth
                        <h2 class="font-display text-lg font-semibold text-ink-900">Đơn hàng của bạn</h2>
                        <p class="mt-2 text-sm text-ink-500">Bạn có thể theo dõi trạng thái đơn hàng trong tài khoản.</p>
                        <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                            <a href="{{ route('account.orders') }}" class="btn-brand flex-1">Theo dõi đơn hàng</a>
                            <a href="{{ route('shop.index') }}" class="btn-outline flex-1">Tiếp tục mua sắm</a>
                        </div>
                    @else
                        <h2 class="font-display text-lg font-semibold text-ink-900">Đăng ký để quản lý đơn tốt hơn</h2>
                        <p class="mt-2 text-sm text-ink-500">Tạo tài khoản hoặc đăng nhập để xem lịch sử đơn hàng và đặt hàng nhanh hơn.</p>
                        <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                            <a href="{{ route('register') }}" class="btn-brand flex-1">Đăng ký ngay</a>
                            <a href="{{ route('shop.index') }}" class="btn-outline flex-1">Tiếp tục mua sắm</a>
                        </div>
                    @endauth
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
