<!DOCTYPE html>
<html lang="vi" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f1f18">
    <meta name="color-scheme" content="light">
    <title>@yield('title', 'Trillfa Studio') · Trillfa Fa</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    @if(file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <style>body{font-family:system-ui,sans-serif;background:#f4f2ec}.wrap{max-width:1200px;margin:auto;padding:24px}</style>
    @endif
    @stack('head')
</head>
<body class="min-h-screen bg-cream-100 text-ink-900 antialiased">
    <header class="sticky top-0 z-40 border-b border-cream-200 bg-white/90 backdrop-blur">
        <div class="mx-auto flex h-14 max-w-7xl items-center justify-between gap-4 px-4">
            <a href="{{ route('studio.index') }}" class="flex items-center gap-2">
                <span class="grid h-8 w-8 place-items-center rounded-xl bg-brand-600 text-white">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
                </span>
                <span class="font-display text-lg font-bold tracking-tight">Trillfa<span class="text-brand-600"> Studio</span></span>
            </a>
            <nav class="flex items-center gap-1 text-sm">
                <a href="{{ route('studio.index') }}" class="rounded-lg px-3 py-1.5 {{ request()->routeIs('studio.index') ? 'bg-cream-100 font-semibold text-ink-900' : 'text-ink-700 hover:bg-cream-100' }}">Tạo mới</a>
                <a href="{{ route('studio.settings') }}" class="rounded-lg px-3 py-1.5 {{ request()->routeIs('studio.settings') ? 'bg-cream-100 font-semibold text-ink-900' : 'text-ink-700 hover:bg-cream-100' }}">Cài đặt</a>
                <a href="{{ route('studio.api') }}" class="rounded-lg px-3 py-1.5 {{ request()->routeIs('studio.api') ? 'bg-cream-100 font-semibold text-ink-900' : 'text-ink-700 hover:bg-cream-100' }}">API</a>
                <a href="{{ route('admin.dashboard') }}" class="hidden rounded-lg px-3 py-1.5 text-ink-700 hover:bg-cream-100 sm:inline">Quản trị</a>
            </nav>
            <div class="flex items-center gap-3">
                <span class="badge bg-brand-50 text-brand-800">Tín dụng: <span class="font-bold">{{ auth()->user()->credits_balance }}</span></span>
                <form method="POST" action="{{ route('logout') }}">@csrf
                    <button type="submit" class="btn-outline btn-sm">Đăng xuất</button>
                </form>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-6">
        @yield('content')
    </main>

    @include('partials.toasts')
    @stack('scripts')
</body>
</html>
