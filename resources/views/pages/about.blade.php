@extends('layouts.app')

@section('title', 'Về chúng tôi')

@section('content')
<div class="container-x py-12">
    <div class="mx-auto max-w-3xl text-center">
        <p class="kicker mb-3">Về Trillfa Fa</p>
        <h1 class="font-display text-4xl font-semibold text-ink-900 sm:text-5xl">Thời trang cho người Việt hiện đại</h1>
        <p class="mt-5 text-lg leading-relaxed text-ink-500">Trillfa Fa ra đời với khát vọng mang đến trải nghiệm mua sắm trực tuyến tối giản, tinh tế nhưng đầy cảm hứng — nơi mỗi sản phẩm đều là một lựa chọn phong cách cho cuộc sống của bạn.</p>
    </div>

    <div class="mt-14 grid gap-6 sm:grid-cols-3">
        @php
            $vals = [
                ['Tinh gọn & Tối giản', 'Chúng tôi tin vào sự tối giản. Mỗi sản phẩm đều được tuyển chọn kỹ lưỡng, chỉ giữ lại những gì thực sự cần thiết và đẹp nhất.'],
                ['Chất lượng bền vững', 'Chúng tôi ưu tiên chất liệu bền vững và quy trình sản xuất có trách nhiệm với môi trường.'],
                ['Phục vụ tận tâm', 'Hành trình mua sắm của bạn được đồng hành bởi đội ngũ hỗ trợ 24/7, đổi trả dễ dàng.'],
            ];
        @endphp
        @foreach($vals as $v)
            <div class="card p-6">
                <span class="grid h-11 w-11 place-items-center rounded-xl bg-brand-50 text-brand-600">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5"/></svg>
                </span>
                <h3 class="mt-4 font-display text-lg font-semibold text-ink-900">{{ $v[0] }}</h3>
                <p class="mt-2 text-sm leading-relaxed text-ink-500">{{ $v[1] }}</p>
            </div>
        @endforeach
    </div>

    <div class="card mt-14 flex flex-col items-center gap-4 bg-brand-50 !border-brand-100 p-10 text-center">
        <h2 class="font-display text-2xl font-semibold text-ink-900 sm:text-3xl">Hãy cùng nhau tạo nên phong cách</h2>
        <p class="max-w-lg text-ink-500">Khám phá bộ sưu tập của chúng tôi và tìm thấy điều "thật sự là bạn".</p>
        <a href="{{ route('shop.index') }}" class="btn-brand mt-2">Mua sắm ngay</a>
    </div>
</div>
@endsection
