@extends('layouts.account')

@section('title', 'Hồ sơ')

@section('account_content')
    <div class="mb-6">
        <h1 class="font-display text-2xl font-semibold text-ink-900">Hồ sơ của tôi</h1>
        <p class="mt-1 text-sm text-ink-500">Cập nhật thông tin tài khoản và bảo mật.</p>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-xl bg-brand-50 p-4 text-sm text-brand-800">{{ session('success') }}</div>
    @endif

    <div class="space-y-6">
        <div class="card p-6">
            <h2 class="mb-4 font-display text-lg font-semibold text-ink-900">Thông tin cá nhân</h2>
            <form method="POST" action="{{ route('account.profile.update') }}" class="space-y-4">
                @csrf
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label">Họ và tên</label>
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" class="input" required>
                        <x-error name="name" />
                    </div>
                    <div>
                        <label class="label">Số điện thoại</label>
                        <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" class="input">
                        <x-error name="phone" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">Email</label>
                        <input type="email" name="email" value="{{ old('email', $user->email) }}" class="input" required>
                        <x-error name="email" />
                    </div>
                </div>
                <button type="submit" class="btn-brand">Cập nhật</button>
            </form>
        </div>

        <div class="card p-6">
            <h2 class="mb-4 font-display text-lg font-semibold text-ink-900">Đổi mật khẩu</h2>
            <form method="POST" action="{{ route('account.password.update') }}" class="space-y-4">
                @csrf
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label">Mật khẩu hiện tại</label>
                        <input type="password" name="current_password" class="input" required>
                        <x-error name="current_password" />
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label">Mật khẩu mới</label>
                        <input type="password" name="password" class="input" required>
                        <x-error name="password" />
                    </div>
                    <div>
                        <label class="label">Xác nhận mật khẩu mới</label>
                        <input type="password" name="password_confirmation" class="input" required>
                    </div>
                </div>
                <button type="submit" class="btn-primary">Đổi mật khẩu</button>
            </form>
        </div>
    </div>
@endsection
