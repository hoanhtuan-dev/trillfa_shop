@extends('layouts.app')

@section('title', 'Điều khoản')

@section('content')
<div class="container-x py-12">
    <div class="mx-auto max-w-3xl">
        <x-breadcrumb :items="[['label' => 'Trang chủ', 'url' => route('home')], ['label' => 'Điều khoản']]" />
        <h1 class="mt-6 font-display text-4xl font-semibold text-ink-900">Điều khoản sử dụng</h1>
        <p class="mt-3 text-sm text-ink-500">Cập nhật lần cuối: {{ date('d/m/Y') }}</p>

        <div class="prose-content mt-8">
            <h2>1. Chấp nhận điều khoản</h2>
            <p>Bằng cách truy cập và sử dụng website Trillfa Fa, bạn đồng ý tuân thủ các điều khoản và chính sách được quy định tại đây.</p>

            <h2>2. Đơn hàng & thanh toán</h2>
            <p>Mọi đơn hàng đều phải được xác nhận qua hệ thống. Chúng tôi có quyền từ chối đơn hàng trong trường hợp thông tin không hợp lệ hoặc sản phẩm hết hàng.</p>

            <h2>3. Giá cả & thanh toán</h2>
            <p>Giá hiển thị đã bao gồm thuế VAT. Chi phí vận chuyển được tính riêng tại trang thanh toán tùy theo phương thức bạn chọn.</p>

            <h2>4. Quyền sở hữu trí tuệ</h2>
            <p>Toàn bộ nội dung trên website (hình ảnh, thiết kế, nội dung) thuộc quyền sở hữu của Trillfa Fa. Người dùng không được sao chép khi chưa có sự cho phép.</p>

            <h2>5. Trách nhiệm</h2>
            <p>Chúng tôi luôn nỗ lực cung cấp thông tin chính xác, tuy nhiên không chịu trách nhiệm cho các sai sót ngoài ý muốn trong quá trình vận hành.</p>
        </div>
    </div>
</div>
@endsection
