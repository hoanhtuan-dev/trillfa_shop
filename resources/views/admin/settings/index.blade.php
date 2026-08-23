@extends('layouts.admin')

@section('title', 'Cài đặt')
@section('page_title', 'Cài đặt cửa hàng')

@section('content')
    @if(session('success'))
        <div class="mb-4 rounded-xl bg-brand-50 p-4 text-sm text-brand-800">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.settings.update') }}" class="grid gap-6 lg:grid-cols-2">
        @csrf
        <div class="card p-6 space-y-4">
            <h2 class="font-display text-lg font-semibold text-ink-900">Thông tin cửa hàng</h2>
            <div><label class="label">Tên cửa hàng *</label><input type="text" name="site_name" value="{{ setting('site_name', 'Trillfa Fa') }}" class="input" required></div>
            <div><label class="label">Slogan</label><input type="text" name="site_tagline" value="{{ setting('site_tagline') }}" class="input"></div>
            <div><label class="label">Email</label><input type="email" name="site_email" value="{{ setting('site_email') }}" class="input"></div>
            <div><label class="label">Số điện thoại</label><input type="text" name="site_phone" value="{{ setting('site_phone') }}" class="input"></div>
            <div><label class="label">Địa chỉ</label><input type="text" name="site_address" value="{{ setting('site_address') }}" class="input"></div>
        </div>

        <div class="card p-6 space-y-4">
            <h2 class="font-display text-lg font-semibold text-ink-900">Mạng xã hội</h2>
            @foreach(['facebook','instagram','tiktok','youtube'] as $s)
                <div><label class="label">{{ ucfirst($s) }}</label><input type="url" name="{{ $s }}" value="{{ setting($s) }}" class="input"></div>
            @endforeach
        </div>

        <div class="card p-6 space-y-4">
            <h2 class="font-display text-lg font-semibold text-ink-900">Vận chuyển</h2>
            <div><label class="label">Phí vận chuyển mặc định (đ)</label><input type="number" name="default_shipping_fee" value="{{ setting('default_shipping_fee') }}" class="input" step="0.01" min="0"></div>
            <div><label class="label">Ngưỡng miễn phí vận chuyển (đ)</label><input type="number" name="free_shipping_threshold" value="{{ setting('free_shipping_threshold') }}" class="input" min="0"></div>
        </div>

        <div class="card p-6 flex flex-col justify-end">
            <button type="submit" class="btn-brand w-full">Lưu cài đặt</button>
        </div>
    </form>
@endsection
