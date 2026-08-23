@extends('layouts.app')

@section('title', 'Chính sách bảo mật')

@section('content')
<div class="container-x py-12">
    <div class="mx-auto max-w-3xl">
        <x-breadcrumb :items="[['label' => 'Trang chủ', 'url' => route('home')], ['label' => 'Chính sách bảo mật']]" />
        <h1 class="mt-6 font-display text-4xl font-semibold text-ink-900">Chính sách bảo mật</h1>
        <p class="mt-3 text-sm text-ink-500">Cập nhật lần cuối: {{ date('d/m/Y') }}</p>

        <div class="prose-content mt-8">
            <h2>1. Thông tin chúng tôi thu thập</h2>
            <p>Khi bạn sử dụng Trillfa Fa, chúng tôi thu thập một số thông tin cần thiết để vận hành dịch vụ, bao gồm: tên, email, số điện thoại, địa chỉ giao hàng và lịch sử mua hàng.</p>

            <h2>2. Cách chúng tôi sử dụng thông tin</h2>
            <ul>
                <li>Xử lý và giao đơn hàng của bạn.</li>
                <li>Gửi thông báo về trạng thái đơn hàng.</li>
                <li>Cải thiện trải nghiệm và gợi ý sản phẩm phù hợp.</li>
            </ul>

            <h2>3. Bảo mật thông tin</h2>
            <p>Chúng tôi áp dụng các biện pháp kỹ thuật và tổ chức phù hợp để bảo vệ thông tin cá nhân của bạn khỏi truy cập trái phép.</p>

            <h2>4. Chia sẻ thông tin</h2>
            <p>Chúng tôi không bán hay cho thuê thông tin cá nhân của bạn cho bên thứ ba. Thông tin chỉ được chia sẻ với đối tác vận chuyển và thanh toán khi cần thiết để hoàn tất đơn hàng.</p>

            <h2>5. Quyền của bạn</h2>
            <p>Bạn có quyền truy cập, chỉnh sửa hoặc yêu cầu xóa thông tin cá nhân của mình bất cứ lúc nào bằng cách liên hệ với chúng tôi.</p>
        </div>
    </div>
</div>
@endsection
