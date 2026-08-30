<!DOCTYPE html>
<html lang="vi" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#34322b">
    <meta name="color-scheme" content="light">
    <title>@yield('title', 'Trillfa Studio') · Trillfa Fa</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    @if(file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <style>body{font-family:system-ui,sans-serif;background:#f4f2ec}.wrap{max-width:1200px;margin:auto;padding:24px}</style>
    @endif
    @stack('head')
    @php
        $u = auth()->user();
        $credits = $u?->credits_balance ?? 0;
        $pendingCount = $u ? $u->generations()->whereIn('status', ['pending', 'processing'])->count() : 0;
        $creditsUsed = $u ? (int) $u->generations()->where('status', 'completed')->sum('credits_cost') : 0;
        $connected = (bool) (studio_api_key('gemini') || studio_api_key('fal') || studio_api_key('replicate')
            || studio_api_key('wan') || studio_api_key('qwen') || studio_api_key('dashscope') || studio_api_key('veo'));
        $active = [
            'studio.index' => 'Garment Gen',
            'studio.library' => 'Asset Library',
            'studio.settings' => 'Cài đặt',
            'studio.api' => 'API',
        ];
        $routeName = request()->route()?->getName();
    @endphp
</head>
<body class="min-h-screen bg-ink-800 text-cream-100 antialiased">
    <div class="flex min-h-screen" x-data="{ sidebarCollapsed: false, navOpen: { ai: true, pm: true, catwalk: true, asset: true, sys: true } }">
        <!-- ===== Left sidebar ===== -->
        <aside class="hidden shrink-0 flex-col border-r border-ink-700 bg-ink-800 transition-[width] duration-300" :class="sidebarCollapsed ? 'lg:flex lg:w-16' : 'lg:flex lg:w-60'">
            <a href="{{ route('studio.index') }}" class="flex items-center gap-2 border-b border-ink-700 px-3 py-4">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-brand-600 text-white">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
                </span>
                <span class="font-display text-lg font-bold tracking-tight text-cream-50" x-show="!sidebarCollapsed">Trillfa<span class="text-brand-400"> Studio</span></span>
            </a>
            <nav class="flex-1 space-y-1 overflow-y-auto px-2 py-3 text-sm sm:px-3">
                <a href="{{ route('studio.index') }}" class="studio-nav {{ $routeName === 'studio.index' ? 'is-active' : '' }}" :class="sidebarCollapsed ? 'justify-center px-0' : ''" :title="sidebarCollapsed ? 'Dashboard' : null"><svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/></svg><span class="truncate" x-show="!sidebarCollapsed">Dashboard</span></a>
<div class="studio-nav-head flex cursor-pointer items-center justify-between" @click="navOpen.ai = !navOpen.ai" x-show="!sidebarCollapsed">AI Design Tools <span x-text="navOpen.ai ? '▾' : '▴'"></span></div>
                <a x-show="navOpen.ai || sidebarCollapsed" href="{{ route('studio.index') }}" class="studio-nav {{ $routeName === 'studio.index' ? 'is-active' : '' }}" :class="sidebarCollapsed ? 'justify-center px-1.5' : ''" :title="sidebarCollapsed ? 'Garment Gen' : null"><svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M9 3l3 2 3-2 5 3-2 4-1.5-.8V21h-9V9.2L6 10 4 6l5-3z"/></svg><span class="truncate" x-show="!sidebarCollapsed">Garment Gen <span class="ml-auto text-[10px] text-brand-600">Tạo trang phục</span></span></a>
                <a x-show="navOpen.ai || sidebarCollapsed" href="{{ route('studio.pattern') }}" class="studio-nav {{ $routeName === 'studio.pattern' ? 'is-active' : '' }}" :class="sidebarCollapsed ? 'justify-center px-1.5' : ''" :title="sidebarCollapsed ? 'Pattern Maker' : null"><svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7l4 4-4 4M11 7v10M8 6h12"/></svg><span class="truncate" x-show="!sidebarCollapsed">Pattern Maker</span></a>
                <a x-show="navOpen.ai || sidebarCollapsed" href="{{ route('studio.tryon') }}" class="studio-nav {{ $routeName === 'studio.tryon' ? 'is-active' : '' }}" :class="sidebarCollapsed ? 'justify-center px-1.5' : ''" :title="sidebarCollapsed ? 'Virtual Try-On' : null"><svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.25a7.5 7.5 0 0115 0"/></svg><span class="truncate" x-show="!sidebarCollapsed">Virtual Try-On <span class="ml-auto text-[10px] text-brand-600">Beta</span></span></a>
                <a x-show="navOpen.ai || sidebarCollapsed" href="{{ route('studio.presets') }}" class="studio-nav {{ $routeName === 'studio.presets' ? 'is-active' : '' }}" :class="sidebarCollapsed ? 'justify-center px-1.5' : ''" :title="sidebarCollapsed ? 'Prompt Templates' : null"><svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l1.9 5.8L19.5 10l-5.6 1.2L12 17l-1.9-5.8L4.5 10l5.6-1.2L12 3zM5 17l.8 2.4L8 20l-2.2.6L5 23l-.8-2.4L2 20l2.2-.6L5 17z"/></svg><span class="truncate" x-show="!sidebarCollapsed">Prompt Templates <span class="ml-auto text-[10px] text-brand-600">Key:Value</span></span></a>
<div class="studio-nav-head flex cursor-pointer items-center justify-between" @click="navOpen.pm = !navOpen.pm" x-show="!sidebarCollapsed">Project Manager <span x-text="navOpen.pm ? '▾' : '▴'"></span></div>
                <a x-show="navOpen.pm || sidebarCollapsed" href="{{ route('studio.index') }}" class="studio-nav {{ $routeName === 'studio.index' ? 'is-active' : '' }}" :class="sidebarCollapsed ? 'justify-center px-1.5' : ''" :title="sidebarCollapsed ? 'Active' : null"><svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12a9 9 0 1118 0v.75m-18 0H5a1.5 1.5 0 011.5 1.5v3.75A1.5 1.5 0 015 19.5H2.25m18 0H19a1.5 1.5 0 01-1.5-1.5v-3.75A1.5 1.5 0 0119 12.75h2.25"/></svg><span class="truncate" x-show="!sidebarCollapsed">Active <span class="ml-auto text-[10px] text-ink-500">{{ $u?->projects()->count() }} dự án</span></span></a>
                <a x-show="navOpen.pm || sidebarCollapsed" href="{{ route('studio.library') }}" class="studio-nav {{ $routeName === 'studio.library' ? 'is-active' : '' }}" :class="sidebarCollapsed ? 'justify-center px-1.5' : ''" :title="sidebarCollapsed ? 'Collections' : null"><svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.25-5.25 4.5 4.5 3.75-3.75 3 3M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><span class="truncate" x-show="!sidebarCollapsed">Collections</span></a>
<div class="studio-nav-head flex cursor-pointer items-center justify-between" @click="navOpen.catwalk = !navOpen.catwalk" x-show="!sidebarCollapsed">Catwalk Renderer <span x-text="navOpen.catwalk ? '▾' : '▴'"></span></div>
                <a href="{{ route('studio.index') }}#catwalk" class="studio-nav" :class="sidebarCollapsed ? 'justify-center px-1.5' : ''" :title="sidebarCollapsed ? 'Catwalk Video' : null"><svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4.5h16M6 4.5v5M18 4.5v5M3 15.75l3.5-4h11l3.5 4M7.5 15.75v3.5M16.5 15.75v3.5"/></svg><span class="truncate" x-show="!sidebarCollapsed">Catwalk Video <span class="ml-auto text-[10px] text-ink-500">{{ $connected ? 'AI' : 'Stub' }}</span></span></a>
<div class="studio-nav-head flex cursor-pointer items-center justify-between" @click="navOpen.asset = !navOpen.asset" x-show="!sidebarCollapsed">Asset Library <span x-text="navOpen.asset ? '▾' : '▴'"></span></div>
                <a x-show="navOpen.asset || sidebarCollapsed" href="{{ route('studio.library') }}" class="studio-nav {{ $routeName === 'studio.library' ? 'is-active' : '' }}" :class="sidebarCollapsed ? 'justify-center px-1.5' : ''" :title="sidebarCollapsed ? 'Fabrics / Models / Poses' : null"><svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M3 12h18M3 18h18"/></svg><span class="truncate" x-show="!sidebarCollapsed">Fabrics / Models / Poses</span></a>
<div class="studio-nav-head flex cursor-pointer items-center justify-between" @click="navOpen.sys = !navOpen.sys" x-show="!sidebarCollapsed">Hệ thống <span x-text="navOpen.sys ? '▾' : '▴'"></span></div>
                <a x-show="navOpen.sys || sidebarCollapsed" href="{{ route('studio.settings') }}" class="studio-nav {{ $routeName === 'studio.settings' ? 'is-active' : '' }}" :class="sidebarCollapsed ? 'justify-center px-1.5' : ''" :title="sidebarCollapsed ? 'Cài đặt' : null"><svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg><span class="truncate" x-show="!sidebarCollapsed">Cài đặt</span></a>
                <a x-show="navOpen.sys || sidebarCollapsed" href="{{ route('studio.api') }}" class="studio-nav {{ $routeName === 'studio.api' ? 'is-active' : '' }}" :class="sidebarCollapsed ? 'justify-center px-1.5' : ''" :title="sidebarCollapsed ? 'API Keys' : null"><svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m-13.5 6a3 3 0 01-3-3m9.75 6a3 3 0 003 3M9 12a3 3 0 11-6 0 3 3 0 016 0zm12 0a3 3 0 11-6 0 3 3 0 016 0zm-6-8.25a3 3 0 11-6 0 3 3 0 016 0z"/></svg><span class="truncate" x-show="!sidebarCollapsed">API Keys</span></a>
                <a href="{{ route('admin.dashboard') }}" class="studio-nav" :class="sidebarCollapsed ? 'justify-center px-1.5' : ''" :title="sidebarCollapsed ? 'Quản trị shop' : null"><svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7l1.5-3h15L21 7M4 7v13h16V7M9 20v-6h6v6"/></svg><span class="truncate" x-show="!sidebarCollapsed">Quản trị shop</span></a>
            </nav>
            <div class="border-t border-ink-700 px-3 py-3" x-show="!sidebarCollapsed">
                <div class="flex items-center justify-between text-xs"><span class="text-cream-300">Tín dụng</span><span class="font-semibold text-cream-50">{{ $credits }}</span></div>
                <div class="mt-1 flex items-center gap-1 text-[11px] text-cream-300"><span class="inline-block h-2 w-2 rounded-full {{ $connected ? 'bg-emerald-500' : 'bg-amber-400' }}"></span>{{ $connected ? 'Kết nối AI' : 'Chế độ Stub' }}</div>
            </div>
            <div class="border-t border-ink-700 px-3 py-3 text-center" x-show="sidebarCollapsed">
                <span class="block text-sm font-semibold text-cream-50">{{ $credits }}</span>
                <span class="mt-1 inline-block h-2 w-2 rounded-full {{ $connected ? 'bg-emerald-500' : 'bg-amber-400' }}"></span>
            </div>
        </aside>

        <!-- ===== Main region ===== -->
        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-40 flex h-14 items-center justify-between gap-4 border-b border-ink-700 bg-ink-800/95 px-4 backdrop-blur">
                <div class="flex items-center gap-2 text-sm">
                    <button @click="sidebarCollapsed = !sidebarCollapsed" class="rounded-full border border-ink-700 px-3 py-1.5 text-xs font-semibold text-cream-100 transition-colors hover:bg-ink-700 hover:text-white" :title="sidebarCollapsed ? 'Hiện thanh bên' : 'Ẩn thanh bên'"><span x-text="sidebarCollapsed ? '»' : '«'"></span></button>
                    <span class="font-display text-sm font-semibold text-cream-50">@yield('title', 'Trillfa Studio')</span>
                    <span class="hidden badge bg-cream-200 text-ink-500 sm:inline-flex">{{ $connected ? 'AI Connected' : 'Stub' }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="badge bg-brand-50 text-brand-800">Tín dụng: <span class="font-bold">{{ $credits }}</span></span>
                    <span class="hidden text-xs text-cream-300 md:inline">{{ $u?->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">@csrf
                        <button type="submit" class="rounded-full border border-ink-700 px-3 py-1.5 text-xs font-semibold text-cream-100 transition-colors hover:bg-ink-700 hover:text-white">Đăng xuất</button>
                    </form>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto bg-cream-50 p-4 sm:p-6">
                @yield('content')
            </main>

            <!-- ===== Status bar ===== -->
            <footer class="flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-ink-700 bg-ink-800 px-4 py-2 text-[11px] text-cream-300">
                <span>User: <b class="text-cream-50">{{ $u?->name }}</b></span>
                <span>Credits: <b class="text-cream-50">{{ $credits }}</b></span>
                <span>Used: <b class="text-cream-50">{{ $creditsUsed }}</b></span>
                <span>Job Queue: <b class="text-cream-50">{{ $pendingCount }}</b> pending</span>
                <span>Sync Status: <b class="{{ $connected ? 'text-emerald-400' : 'text-amber-400' }}">{{ $connected ? 'Connected' : 'Stub' }}</b></span>
                <span class="ml-auto"><a href="{{ route('studio.settings') }}" class="link">Trợ giúp</a></span>
            </footer>
        </div>
    </div>

    @include('partials.toasts')
    @stack('scripts')
</body>
</html>
