<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#1f372b">
    @include('partials.remove-service-worker')
    <title>Studio — Trillfa</title>
    @vite(['resources/css/app.css', 'resources/js/studio/app.js'])
</head>
<body class="h-screen overflow-hidden bg-ink-900 text-cream-100 antialiased">
    <div class="flex h-full items-center justify-center text-sm text-cream-300/60">Đang tải Studio Vue…</div>
    <div id="studio-root" class="absolute inset-0"></div>
</body>
</html>
