<!DOCTYPE html>
<html lang="vi">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><meta name="theme-color" content="#1f372b">@include('partials.remove-service-worker')<title>Studio Settings (Vue) — Trillfa</title>
@vite(['resources/css/app.css', 'resources/js/studio/settings.js'])
</head>
<body class="min-h-screen bg-ink-900 text-cream-100 antialiased"><div id="settings-root"></div></body>
</html>
