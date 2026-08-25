@extends('layouts.app')

@section('title', 'Hoàn thiện tài khoản')

@section('content')
<div class="container-x flex min-h-[70vh] items-center justify-center py-16">
    <div class="card w-full max-w-md p-8 sm:p-10">
        <div class="text-center">
            <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-brand-100 text-brand-700">
                <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
            </div>
            <h1 class="mt-4 font-display text-2xl font-semibold text-ink-900">Hoàn thiện tài khoản</h1>
            <p class="mt-2 text-sm text-ink-500">Đơn hàng <strong class="text-ink-900">{{ $order->order_number }}</strong> đã được tạo. Nhập email và mật khẩu để kích hoạt tài khoản và quản lý đơn hàng.</p>
        </div>

        @if($errors->any())
            <div class="mt-5 rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('account.complete.store', $order) }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label class="label">Họ và tên</label>
                <input type="text" value="{{ $user->name }}" class="input bg-cream-100" disabled>
            </div>
            <div>
                <label class="label">Số điện thoại</label>
                <input type="text" value="{{ $user->phone }}" class="input bg-cream-100" disabled>
            </div>
            <div>
                <label class="label">Email *</label>
                <input type="email" name="email" value="{{ old('email') }}" class="input" required placeholder="ban@email.com" autocomplete="email">
            </div>
            <div>
                <label class="label">Mật khẩu *</label>
                <input type="password" name="password" class="input" required placeholder="Tối thiểu 8 ký tự" autocomplete="new-password">
            </div>
            <div>
                <label class="label">Xác nhận mật khẩu *</label>
                <input type="password" name="password_confirmation" class="input" required placeholder="Nhập lại mật khẩu" autocomplete="new-password">
            </div>
            <button type="submit" class="btn-brand w-full">Hoàn thiện &amp; đăng nhập</button>
        </form>

        <a href="{{ route('shop.index') }}" class="btn-ghost mt-3 w-full text-ink-500">Tiếp tục mua sắm</a>
    </div>
</div>
@endsection
