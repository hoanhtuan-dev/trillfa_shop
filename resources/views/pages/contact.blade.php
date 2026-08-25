@extends('layouts.app')

@section('title', 'Liên hệ')

@section('content')
<div class="container-x py-12">
    <div class="mx-auto max-w-3xl text-center">
        <p class="kicker mb-3">Liên hệ</p>
        <h1 class="font-display text-4xl font-semibold text-ink-900 sm:text-5xl">{{ setting('contact_heading', 'Chúng tôi luôn lắng nghe') }}</h1>
        <p class="mt-4 text-ink-500">{{ setting('contact_intro', 'Mọi câu hỏi, góp ý hay hỗ trợ — hãy liên hệ với chúng tôi.') }}</p>
    </div>

    <div class="mt-12 grid gap-8 lg:grid-cols-2">
        <div class="card p-8">
            <h2 class="font-display text-lg font-semibold text-ink-900">Thông tin liên hệ</h2>
            <div class="mt-5 space-y-4 text-sm">
                <div class="flex items-center gap-3"><svg class="h-5 w-5 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21s-7-4.35-7-11a7 7 0 1114 0c0 6.65-7 11-7 11zm0-7a2.5 2.5 0 100-5 2.5 2.5 0 000 5z"/></svg><span>{{ setting('site_address', '123 Nguyễn Huệ, Q.1, TP.HCM') }}</span></div>
                <div class="flex items-center gap-3"><svg class="h-5 w-5 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/></svg><span>{{ setting('site_phone', '1900 0000') }}</span></div>
                <div class="flex items-center gap-3"><svg class="h-5 w-5 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg><span>{{ setting('site_email', 'hello@trillfa.com') }}</span></div>
            </div>
        </div>

        <div class="card p-8">
            <h2 class="font-display text-lg font-semibold text-ink-900">Gửi tin nhắn</h2>
            @if(session('success'))
                <div class="mt-4 rounded-xl bg-brand-50 p-4 text-sm text-brand-800">{{ session('success') }}</div>
            @endif
            <form method="POST" action="{{ route('page.contact.send') }}" class="mt-4 space-y-4">
                @csrf
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label class="label">Họ và tên</label><input type="text" name="name" class="input" required></div>
                    <div><label class="label">Email</label><input type="email" name="email" class="input" required></div>
                </div>
                <div><label class="label">Tiêu đề</label><input type="text" name="subject" class="input"></div>
                <div><label class="label">Nội dung</label><textarea name="message" rows="5" class="input" required></textarea></div>
                <div class="flex justify-end"><button type="submit" class="btn-brand">Gửi tin nhắn</button></div>
            </form>
        </div>
    </div>
</div>
@endsection