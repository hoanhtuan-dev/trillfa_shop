<!DOCTYPE html>
<html lang="vi" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#1f372b">
    <meta name="color-scheme" content="light">
    @include('partials.remove-service-worker')
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Trillfa Fa">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icons/favicon-32x32.png" sizes="32x32" type="image/png">
    <link rel="shortcut icon" href="/favicon.ico">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <title>@yield('title', 'Quản trị') — Trillfa Fa</title>
    @if(file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <style>body{font-family:system-ui,sans-serif;background:#f4f2ec;color:#1b1a17;margin:0;padding:48px;text-align:center}h1{color:#33674d}</style>
    @endif
    @stack('head')
</head>
<body class="bg-cream-50 text-ink-900 antialiased" x-data="{ sidebar: false }">
    <!-- Mobile sidebar overlay -->
    <div x-show="sidebar" x-transition.opacity.duration.200ms @click="sidebar = false" class="fixed inset-0 z-40 bg-ink-900/50 lg:hidden"></div>

    <aside class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col bg-ink-900 text-cream-50 transition-transform lg:translate-x-0" :class="sidebar ? 'translate-x-0' : '-translate-x-full'">
        <div class="flex h-16 items-center gap-2 border-b border-white/10 px-6">
            <img src="{{ asset('images/logo.png') }}" alt="Trillfa Fa" class="h-9 w-9 rounded-full object-cover">
            <span class="font-display text-lg font-bold">Trillfa <span class="text-brand-400">Fa</span></span>
        </div>

        <nav class="flex-1 space-y-6 overflow-y-auto px-4 py-6 text-sm">
            @php
                $menu = [
                    'Tổng quan' => [
                        ['Tổng quan', route('admin.dashboard'), 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75'],
                        ['Báo cáo', route('admin.reports.index'), 'M3 3v18h18M7.5 15V9m4.5 6V6m4.5 9V12'],
                    ],
                    'Sản phẩm' => [
                        ['Sản phẩm', route('admin.products.index'), 'M12 4.5L21 9l-9 4.5L3 9l9-4.5zM3 9v6l9 4.5 9-4.5V9'],
                        ['Danh mục', route('admin.categories.index'), 'M9 12h6m-6 3h6M4.5 4.5h15a1.5 1.5 0 011.5 1.5v12a1.5 1.5 0 01-1.5 1.5h-15a1.5 1.5 0 01-1.5-1.5V6a1.5 1.5 0 011.5-1.5z'],
                        ['Banner', route('admin.banners.index'), 'M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A1.5 1.5 0 0021.75 19.5V4.5A1.5 1.5 0 0020.25 3H3.75A1.5 1.5 0 002.25 4.5v15A1.5 1.5 0 003.75 21z'],
                    ],
                    'Bán hàng' => [
                        ['Đơn hàng', route('admin.orders.index'), 'M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z'],
                        ['Mã giảm giá', route('admin.coupons.index'), 'M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0c1.1.128 1.907 1.077 1.907 2.185z'],
                        ['Vận chuyển', route('admin.shipping.index'), 'M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0c-.566.058-.987.538-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12'],
                        ['Thanh toán', route('admin.payments.index'), 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z'],
                    ],
                    'Nội dung' => [
                        ['Bài viết', route('admin.posts.index'), 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z'],
                        ['Trang đích', route('admin.pages.index'), 'M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5'],
                        ['Trang Giới thiệu', route('admin.pages.about'), 'M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z'],
                        ['Trang Liên hệ', route('admin.pages.contact'), 'M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z'],
                        ['Đánh giá', route('admin.reviews.index'), 'M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z'],
                    ],
                    'Khách hàng' => [
                        ['Người dùng', route('admin.users.index'), 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z'],
                    ],
                    'Giao diện' => [
                        ['Widget', route('admin.widgets.index'), 'M3.75 6A2.25 2.25 0 016 3.75h12A2.25 2.25 0 0120.25 6v12A2.25 2.25 0 0118 20.25H6A2.25 2.25 0 013.75 18V6zM9 3.75v16.5M15 3.75v16.5M3.75 9h16.5M3.75 15h16.5'],
                        ['Menu', route('admin.menu.index'), 'M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5'],
                    ],
                    'Hệ thống' => [
                        ['Trillfa Studio', route('studio.index'), 'M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z'],
                        ['Cài đặt', route('admin.settings.index'), 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281zM15 12a3 3 0 11-6 0 3 3 0 016 0z'],
                    ],
                ];
            @endphp
            @foreach($menu as $group => $items)
                <div>
                    <p class="mb-2 px-2 text-[11px] font-semibold uppercase tracking-wider text-white/40">{{ $group }}</p>
                    <ul class="space-y-1">
                        @foreach($items as $item)
                            <li>
                                <a href="{{ $item[1] }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 transition {{ (request()->path() === ltrim(parse_url($item[1], PHP_URL_PATH), '/') || (substr_count(ltrim(parse_url($item[1], PHP_URL_PATH), '/'), '/') >= 1 && str_starts_with(request()->path(), ltrim(parse_url($item[1], PHP_URL_PATH), '/').'/'))) ? 'bg-brand-600 text-white' : 'text-cream-100/80 hover:bg-white/10' }}">
                                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $item[2] }}"/></svg>
                                    <span>{{ $item[0] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>

        <div class="border-t border-white/10 p-4">
            <div class="flex items-center gap-3">
                <span class="grid h-9 w-9 place-items-center rounded-full bg-brand-600 font-bold">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">{{ auth()->user()->name }}</p>
                    <p class="truncate text-xs text-white/50">{{ auth()->user()->email }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="mt-3">
                @csrf
                <button type="submit" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-cream-100/80 transition hover:bg-white/10 hover:text-white">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/></svg>
                    Đăng xuất
                </button>
            </form>
        </div>
    </aside>

    <div class="lg:pl-64">
        <!-- Topbar -->
        <header class="sticky top-0 z-30 border-b border-cream-200 bg-white/90 backdrop-blur">
            <div class="flex h-16 items-center justify-between gap-4 px-4 sm:px-6">
                <div class="flex items-center gap-3">
                    <button @click="sidebar = !sidebar" class="btn-ghost !p-2 lg:hidden" aria-label="Menu">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"/></svg>
                    </button>
                    <h1 class="font-display text-lg font-semibold text-ink-900">@yield('page_title', 'Quản trị Trillfa Fa')</h1>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('home') }}" target="_blank" class="btn-outline btn-sm">Xem cửa hàng</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn-ghost btn-sm" title="Đăng xuất">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/></svg>
                            <span class="hidden sm:inline">Đăng xuất</span>
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <main class="p-4 sm:p-6">
            @yield('content')
        </main>
    </div>

    @include('partials.toasts')
    @if(session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" x-transition.opacity.duration.500ms class="fixed bottom-5 left-1/2 z-[80] w-full max-w-sm -translate-x-1/2 px-4">
            <div class="flex items-center gap-3 rounded-2xl border border-brand-200 bg-white px-4 py-3 shadow-xl">
                <span class="grid h-7 w-7 place-items-center rounded-full bg-brand-100 text-brand-700">✓</span>
                <p class="flex-1 text-sm font-medium text-ink-900">{{ session('success') }}</p>
            </div>
        </div>
    @endif
    @if(session('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" x-transition.opacity.duration.500ms class="fixed bottom-5 left-1/2 z-[80] w-full max-w-sm -translate-x-1/2 px-4">
            <div class="flex items-center gap-3 rounded-2xl border border-red-200 bg-white px-4 py-3 shadow-xl">
                <span class="grid h-7 w-7 place-items-center rounded-full bg-red-100 text-red-600">!</span>
                <p class="flex-1 text-sm font-medium text-ink-900">{{ session('error') }}</p>
            </div>
        </div>
    @endif

    @stack('scripts')
</body>
</html>