@php
    $siteName = setting('site_name', 'Trillfa Fa');
    $siteEmail = setting('site_email', 'hello@trillfa.com');
    $sitePhone = setting('site_phone', '1900 0000');
    $siteAddress = setting('site_address', '123 Nguyễn Huệ, Quận 1, TP.HCM');
@endphp

<footer class="mt-24 border-t border-cream-200 bg-white">
    <!-- Newsletter -->
    <div class="container-x py-12">
        <div class="card-hover card flex flex-col items-center gap-6 bg-brand-50 !border-brand-100 p-8 text-center sm:p-12">
            <div>
                <p class="kicker mb-2">Nhận ưu đãi độc quyền</p>
                <h3 class="font-display text-2xl font-semibold text-ink-900 sm:text-3xl">Đăng ký nhận bản tin Trillfa Fa</h3>
                <p class="mt-2 text-ink-500">Thông tin mới nhất về bộ sưu tập, khuyến mãi và ưu đãi thành viên.</p>
            </div>
            <form method="POST" action="#" @submit.prevent="$store.toast.show('Cảm ơn bạn đã đăng ký bản tin!')" class="flex w-full max-w-md flex-col gap-3 sm:flex-row">
                <input type="email" required placeholder="Email của bạn" class="input flex-1">
                <button type="submit" class="btn-brand">Đăng ký</button>
            </form>
        </div>
    </div>

    <div class="container-x grid grid-cols-2 gap-8 py-12 md:grid-cols-4 lg:grid-cols-5">
        <div class="col-span-2 lg:col-span-2">
            <a href="{{ route('home') }}" class="flex items-center gap-2">
                <img src="{{ asset('images/logo.png') }}" alt="Trillfa Fa" class="h-10 w-10 rounded-full object-cover" loading="lazy">
                <span class="font-display text-2xl font-bold tracking-tight text-ink-900">Trillfa<span class="text-brand-600"> Fa</span></span>
            </a>
            <p class="mt-4 max-w-sm text-sm leading-relaxed text-ink-500">{{ setting('site_tagline', 'Thời trang & Phong cách sống cho người Việt hiện đại. Trải nghiệm mua sắm tối giản, tinh tế.') }}</p>
            <div class="mt-5 flex gap-2">
                @php
                    $socials = [['facebook','https://facebook.com'],['instagram','https://instagram.com'],['tiktok','https://tiktok.com'],['youtube','https://youtube.com']];
                @endphp
                @foreach($socials as $s)
                    <a href="{{ setting($s[0], $s[1]) }}" target="_blank" class="grid h-9 w-9 place-items-center rounded-full border border-cream-200 text-ink-700 transition hover:border-brand-500 hover:bg-brand-500 hover:text-white" aria-label="{{ $s[0] }}">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ match($s[0]) {
                            'facebook' => 'M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z',
                            'instagram' => 'M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z',
                            'tiktok' => 'M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z',
                            'youtube' => 'M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z',
                            default => ''
                        } }}" /></svg>
                    </a>
                @endforeach
            </div>
        </div>

        <div>
            <h4 class="mb-4 text-sm font-semibold uppercase tracking-wide text-ink-900">Mua sắm</h4>
            <ul class="space-y-2.5 text-sm text-ink-500">
                @forelse(menu_tree('footer') as $fitem)
                    <li><a href="{{ $fitem->url }}" class="transition hover:text-brand-700">{{ $fitem->label }}</a></li>
                @empty
                    @foreach ($navbarCategories->take(5) as $cat)
                        <li><a href="{{ route('shop.category', $cat->slug) }}" class="transition hover:text-brand-700">{{ $cat->name }}</a></li>
                    @endforeach
                    <li><a href="{{ route('shop.index') }}" class="transition hover:text-brand-700">Tất cả sản phẩm</a></li>
                @endforelse
            </ul>
        </div>

        <div>
            <h4 class="mb-4 text-sm font-semibold uppercase tracking-wide text-ink-900">Hỗ trợ</h4>
            <ul class="space-y-2.5 text-sm text-ink-500">
                <li><a href="{{ route('page.faq') }}" class="transition hover:text-brand-700">Câu hỏi thường gặp</a></li>
                <li><a href="{{ route('page.contact') }}" class="transition hover:text-brand-700">Liên hệ</a></li>
                <li><a href="{{ route('cart.show') }}" class="transition hover:text-brand-700">Giỏ hàng</a></li>
                <li><a href="{{ route('account.orders') }}" class="transition hover:text-brand-700">Tra cứu đơn hàng</a></li>
                <li><a href="{{ route('page.privacy') }}" class="transition hover:text-brand-700">Chính sách bảo mật</a></li>
                <li><a href="{{ route('page.terms') }}" class="transition hover:text-brand-700">Điều khoản</a></li>
            </ul>
        </div>

        <div>
            <h4 class="mb-4 text-sm font-semibold uppercase tracking-wide text-ink-900">Liên hệ</h4>
            <ul class="space-y-3 text-sm text-ink-500">
                <li class="flex items-start gap-2"><svg class="mt-0.5 h-4 w-4 shrink-0 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21s-7-4.35-7-11a7 7 0 1114 0c0 6.65-7 11-7 11zm0-7a2.5 2.5 0 100-5 2.5 2.5 0 000 5z"/></svg><span>{{ $siteAddress }}</span></li>
                <li class="flex items-center gap-2"><svg class="h-4 w-4 shrink-0 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/></svg><a href="tel:{{ $sitePhone }}" class="hover:text-brand-700">{{ $sitePhone }}</a></li>
                <li class="flex items-center gap-2"><svg class="h-4 w-4 shrink-0 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg><a href="mailto:{{ $siteEmail }}" class="hover:text-brand-700">{{ $siteEmail }}</a></li>
            </ul>
        </div>
    </div>

    <div class="border-t border-cream-200">
        <div class="container-x flex flex-col items-center justify-between gap-3 py-6 text-xs text-ink-500 sm:flex-row">
            <p>&copy; {{ date('Y') }} {{ $siteName }}. Bảo lưu mọi quyền.</p>
            <div class="flex items-center gap-2">
                <span class="rounded-md border border-cream-200 px-2 py-1">COD</span>
                <span class="rounded-md border border-cream-200 px-2 py-1">VNPay</span>
                <span class="rounded-md border border-cream-200 px-2 py-1">MoMo</span>
                <span class="rounded-md border border-cream-200 px-2 py-1">Bank</span>
            </div>
        </div>
    </div>
</footer>