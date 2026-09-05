<!DOCTYPE html>
<html lang="vi" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#17150f">
    <title>Sản phẩm — Trillfa Fa</title>
    @vite(['resources/css/app.css', 'resources/js/admin/admin-product.js'])
</head>
<body class="min-h-full bg-cream-50 text-ink-900 antialiased">
    <div id="admin-products-root"></div>
    <script>window.__PRODUCTS_BOOT__ = @json($boot);</script>
</body>
</html>
