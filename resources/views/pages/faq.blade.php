@extends('layouts.app')

@section('title', 'Câu hỏi thường gặp')

@section('content')
<div class="container-x py-12">
    <div class="mx-auto max-w-3xl text-center">
        <p class="kicker mb-3">Hỗ trợ</p>
        <h1 class="font-display text-4xl font-semibold text-ink-900 sm:text-5xl">Câu hỏi thường gặp</h1>
        <p class="mt-4 text-ink-500">Những câu hỏi phổ biến nhất về mua sắm tại Trillfa Fa.</p>
    </div>

    <div class="mx-auto mt-10 max-w-3xl space-y-3" x-data="{ open: null }">
        @php
            $faqs = [
                ['Đơn hàng của tôi được giao trong bao lâu?', 'Đơn hàng thường được xử lý trong 1–2 ngày làm việc và giao trong 2–5 ngày tùy khu vực. Phương thức Express có thể giao trong 1–2 ngày.'],
                ['Làm sao để theo dõi đơn hàng?', 'Bạn có thể theo dõi trạng thái đơn hàng trong mục "Đơn hàng" của tài khoản, hoặc liên hệ hotline với mã đơn hàng để được hỗ trợ.'],
                ['Chính sách đổi trả như thế nào?', 'Bạn có thể đổi/trả trong vòng 7 ngày kể từ khi nhận hàng nếu sản phẩm còn nguyên tem, nhãn và chưa qua sử dụng.'],
                ['Tôi có thể thanh toán bằng hình thức nào?', 'Chúng tôi hỗ trợ Thanh toán khi nhận hàng (COD), chuyển khoản ngân hàng, VNPay và ví MoMo.'],
                ['Làm thế nào để sử dụng mã giảm giá?', 'Trong giỏ hàng hoặc trang thanh toán, nhập mã giảm giá vào ô "Nhập mã" và bấm Áp dụng. Mã sẽ được trừ vào tổng đơn hàng.'],
                ['Sản phẩm hết hàng thì khi nào có lại?', 'Sản phẩm hết hàng thường được bổ sung trong 1–2 tuần. Bạn có thể liên hệ để chúng tôi thông báo khi hàng về.'],
            ];
        @endphp
        @foreach($faqs as $i => $f)
            <div class="card overflow-hidden">
                <button @click="open = open === {{ $i }} ? null : {{ $i }}" class="flex w-full items-center justify-between gap-4 p-5 text-left">
                    <span class="font-medium text-ink-900">{{ $f[0] }}</span>
                    <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-cream-100 text-ink-700 transition" :class="open === {{ $i }} ? 'rotate-45 bg-brand-600 text-white' : ''">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    </span>
                </button>
                <div x-show="open === {{ $i }}" x-collapse>
                    <p class="px-5 pb-5 text-sm leading-relaxed text-ink-500">{{ $f[1] }}</p>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card mt-10 flex flex-col items-center gap-3 p-8 text-center">
        <p class="font-medium text-ink-900">Chưa tìm thấy câu trả lời?</p>
        <p class="text-sm text-ink-500">Liên hệ với chúng tôi để được hỗ trợ ngay.</p>
        <a href="{{ route('page.contact') }}" class="btn-outline mt-2">Liên hệ</a>
    </div>
</div>
@endsection
