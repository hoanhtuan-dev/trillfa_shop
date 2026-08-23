@extends('layouts.app')

@section('title', 'Đăng nhập')

@section('content')
<div class="container-x flex min-h-[75vh] items-center justify-center py-16">
    <div class="grid w-full max-w-4xl overflow-hidden rounded-3xl border border-cream-200 bg-white lg:grid-cols-2">
        <div class="relative hidden lg:block">
            <img src="{{ asset('samples/2aOboQqGJ4Pj8CanWSj6MFVJ1xiwOYY5srLPEBjk.jpg') }}" alt="" class="h-full w-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-tr from-ink-900/70 via-ink-900/20 to-transparent"></div>
            <div class="absolute bottom-0 p-8 text-white">
                <p class="font-display text-2xl font-semibold">Chào mừng trở lại</p>
                <p class="mt-1 text-sm opacity-80">Tiếp tục trải nghiệm mua sắm tuyệt vời tại Trillfa Fa.</p>
            </div>
        </div>
        <div class="p-8 sm:p-12">
            <h1 class="font-display text-2xl font-semibold text-ink-900">Đăng nhập tài khoản</h1>
            <p class="mt-2 text-sm text-ink-500">Chào mừng bạn quay lại Trillfa Fa.</p>

            @if($errors->any())
                <div class="mt-4 rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label class="label">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="input" required autofocus placeholder="email@example.com">
                </div>
                <div>
                    <label class="label">Mật khẩu</label>
                    <input type="password" name="password" class="input" required placeholder="••••••••">
                </div>
                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 text-sm text-ink-500">
                        <input type="checkbox" name="remember" class="h-4 w-4 accent-brand-600"> Ghi nhớ đăng nhập
                    </label>
                </div>
                <button type="submit" class="btn-brand w-full">Đăng nhập</button>
            </form>

            <p class="mt-6 text-center text-sm text-ink-500">
                Chưa có tài khoản? <a href="{{ route('register') }}" class="link font-medium">Đăng ký ngay</a>
            </p>
        </div>
    </div>
</div>
@endsection