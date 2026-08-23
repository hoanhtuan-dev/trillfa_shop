@extends('layouts.app')

@section('title', 'Đăng ký')

@section('content')
<div class="container-x flex min-h-[75vh] items-center justify-center py-16">
    <div class="card w-full max-w-md p-8 sm:p-10">
        <h1 class="font-display text-2xl font-semibold text-ink-900">Tạo tài khoản</h1>
        <p class="mt-2 text-sm text-ink-500">Tham gia cộng đồng Trillfa Fa để mua sắm dễ dàng hơn.</p>

        <form method="POST" action="{{ route('register.store') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label class="label">Họ và tên</label>
                <input type="text" name="name" value="{{ old('name') }}" class="input" required autofocus placeholder="Nguyễn Văn A">
                <x-error name="name" />
            </div>
            <div>
                <label class="label">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" class="input" required placeholder="email@example.com">
                <x-error name="email" />
            </div>
            <div>
                <label class="label">Số điện thoại</label>
                <input type="text" name="phone" value="{{ old('phone') }}" class="input" placeholder="09xx xxx xxx">
                <x-error name="phone" />
            </div>
            <div>
                <label class="label">Mật khẩu</label>
                <input type="password" name="password" class="input" required placeholder="Ít nhất 8 ký tự">
                <x-error name="password" />
            </div>
            <div>
                <label class="label">Xác nhận mật khẩu</label>
                <input type="password" name="password_confirmation" class="input" required>
            </div>
            <button type="submit" class="btn-brand w-full">Đăng ký</button>
        </form>

        <p class="mt-6 text-center text-sm text-ink-500">
            Đã có tài khoản? <a href="{{ route('login') }}" class="link font-medium">Đăng nhập</a>
        </p>
    </div>
</div>
@endsection
