@extends('layouts.admin')

@section('title', 'Trang Liên hệ')
@section('page_title', 'Nội dung trang Liên hệ')

@section('content')
<form method="POST" action="{{ route('admin.pages.contact.update') }}" class="max-w-3xl space-y-6">
    @csrf
    <div class="card p-6 space-y-4">
        <h2 class="font-display text-lg font-semibold text-ink-900">Nội dung trang Liên hệ</h2>
        <div>
            <label class="label">Tiêu đề (H1) *</label>
            <input type="text" name="contact_heading" value="{{ old('contact_heading', setting('contact_heading', 'Chúng tôi luôn lắng nghe')) }}" class="input" required>
        </div>
        <div>
            <label class="label">Mở đầu</label>
            <textarea name="contact_intro" rows="4" class="input">{{ old('contact_intro', setting('contact_intro')) }}</textarea>
        </div>
        <div>
            <label class="label">Giờ làm việc</label>
            <input type="text" name="contact_hours" value="{{ old('contact_hours', setting('contact_hours', 'T2 - CN: 9:00 - 21:00')) }}" class="input">
        </div>
        <p class="rounded-lg bg-cream-100 p-3 text-xs text-ink-500">
            Địa chỉ, điện thoại, email hiển thị ở khối thông tin lấy từ <strong>Cài đặt → Cửa hàng</strong> (site_address / site_phone / site_email).
        </p>
    </div>
    <div class="flex items-center gap-2">
        <button type="submit" class="btn-brand">Lưu nội dung</button>
        <a href="{{ route('page.contact') }}" target="_blank" class="btn-outline">Xem trang</a>
    </div>
</form>
@endsection
