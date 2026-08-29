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
    <div class="flex min-h-screen" x-data="{ sidebarCollapsed: false }">
        <!-- ===== Left sidebar ===== -->
        <aside class="hidden w-60 shrink-0 flex-col border-r border-ink-700 bg-ink-800" :class="sidebarCollapsed ? 'lg:hidden' : 'lg:flex'">
            <a href="{{ route('studio.index') }}" class="flex items-center gap-2 border-b border-ink-700 px-4 py-4">
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-brand-600 text-white">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
                </span>
                <span class="font-display text-lg font-bold tracking-tight text-cream-50">Trillfa<span class="text-brand-400"> Studio</span></span>
            </a>

            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-3 text-sm">
                <a href="{{ route('studio.index') }}" class="studio-nav {{ $routeName === 'studio.index' ? 'is-active' : '' }}">Dashboard</a>

                <div class="studio-nav-head">AI Design Tools</div>
                <a href="{{ route('studio.index') }}" class="studio-nav {{ $routeName === 'studio.index' ? 'is-active' : '' }}">Garment Gen <span class="ml-auto text-[10px] text-brand-600">Tạo trang phục</span></a>
                <a href="{{ route('studio.pattern') }}" class="studio-nav {{ $routeName === 'studio.pattern' ? 'is-active' : '' }}">Pattern Maker</a>
                <a href="{{ route('studio.tryon') }}" class="studio-nav {{ $routeName === 'studio.tryon' ? 'is-active' : '' }}">Virtual Try-On <span class="ml-auto text-[10px] text-brand-600">Beta</span></a>
                <a href="{{ route('studio.presets') }}" class="studio-nav {{ $routeName === 'studio.presets' ? 'is-active' : '' }}">Prompt Templates <span class="ml-auto text-[10px] text-brand-600">Key:Value</span></a>

                <div class="studio-nav-head">Project Manager</div>
                <a href="{{ route('studio.index') }}" class="studio-nav {{ $routeName === 'studio.index' ? 'is-active' : '' }}">Active <span class="ml-auto text-[10px] text-ink-500">{{ $u?->projects()->count() }} dự án</span></a>
                <a href="{{ route('studio.library') }}" class="studio-nav {{ $routeName === 'studio.library' ? 'is-active' : '' }}">Collections</a>

                <div class="studio-nav-head">Catwalk Renderer</div>
                <a href="{{ route('studio.index') }}#catwalk" class="studio-nav">Catwalk Video <span class="ml-auto text-[10px] text-ink-500">{{ $connected ? 'AI' : 'Stub' }}</span></a>

                <div class="studio-nav-head">Asset Library</div>
                <a href="{{ route('studio.library') }}" class="studio-nav {{ $routeName === 'studio.library' ? 'is-active' : '' }}">Fabrics / Models / Poses</a>

                <div class="studio-nav-head">Hệ thống</div>
                <a href="{{ route('studio.settings') }}" class="studio-nav {{ $routeName === 'studio.settings' ? 'is-active' : '' }}">Cài đặt</a>
                <a href="{{ route('studio.api') }}" class="studio-nav {{ $routeName === 'studio.api' ? 'is-active' : '' }}">API Keys</a>
                <a href="{{ route('admin.dashboard') }}" class="studio-nav">Quản trị shop</a>
            </nav>

            <div class="border-t border-ink-700 px-4 py-3">
                <div class="flex items-center justify-between text-xs">
                    <span class="text-cream-300">Tín dụng</span>
                    <span class="font-semibold text-cream-50">{{ $credits }}</span>
                </div>
                <div class="mt-1 flex items-center gap-1 text-[11px] text-cream-300">
                    <span class="inline-block h-2 w-2 rounded-full {{ $connected ? 'bg-emerald-500' : 'bg-amber-400' }}"></span>
                    {{ $connected ? 'Kết nối AI' : 'Chế độ Stub' }}
                </div>
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
