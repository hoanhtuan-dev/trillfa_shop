<!DOCTYPE html>
<html lang="vi" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0b1210">
    <meta name="color-scheme" content="light">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="{{ setting('site_name', 'Trillfa Fa') }}">
    <link rel="icon" href="/icons/favicon-32x32.png" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <x-seo />
    @stack('meta')
    <link rel="preconnect" href="https://fonts.bunny.net">
    @if(file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css', 'resources/js/storefront/cart.js'])
    @else
        <style>body{margin:0;font-family:system-ui,sans-serif;background:#eef3f0;color:#0b1210;display:grid;min-height:100vh;place-items:center;text-align:center;padding:24px}</style>
    @endif
    @stack('head')
</head>
<body class="h-full antialiased">
    <div id="store-root">
        <div class="grid min-h-screen place-items-center bg-[#eef3f0]">
            <div class="flex flex-col items-center gap-4 text-ink-500">
                <div class="h-10 w-10 animate-spin rounded-full border-2 border-brand-600/30 border-t-brand-600"></div>
                <p class="text-sm font-medium">Đang tải…</p>
            </div>
        </div>
    </div>

    <script>window.__STORE_BOOT__ = @json($boot);</script>

    @stack('scripts')
</body>
</html>
