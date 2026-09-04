<!DOCTYPE html>
<html lang="vi">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><meta name="theme-color" content="#1f372b">@include('partials.remove-service-worker')<title>Thư viện — Trillfa</title>
@vite(['resources/css/app.css', 'resources/js/studio/library.js'])
</head>
<body class="bg-ink-950 text-cream-100 antialiased"><div id="library-root"></div></body>
</html>
