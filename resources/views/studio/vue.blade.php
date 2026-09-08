<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#193d2b">
    <meta name="color-scheme" content="dark light">
    <!-- PWA Studio: manifest riêng + icon riêng + service worker scope /studio -->
    <link rel="manifest" href="/manifest-studio.json">
    <link rel="apple-touch-icon" href="/icons/studio-apple-touch-icon.png">
    <link rel="icon" type="image/png" href="/icons/studio-favicon-32.png">
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('/sw-studio.js', { scope: '/studio' }).catch(function () {});
            });
        }
    </script>
    <title>Studio — Trillfa</title>
    @vite(['resources/css/app.css', 'resources/js/studio/app.js'])
</head>
<body class="h-screen overflow-hidden bg-ink-900 text-cream-100 antialiased">
    <div class="flex h-full items-center justify-center text-sm text-cream-300/60">Đang tải Studio Vue…</div>
    <div id="studio-root" class="absolute inset-0"></div>
    @php
        $studioUser = auth()->user();
        $studioBoot = [
            'user' => $studioUser ? [
                'id' => $studioUser->id,
                'name' => $studioUser->name,
                'email' => $studioUser->email,
                'role' => $studioUser->role,
                'role_label' => $studioUser->roleLabel(),
                'avatar' => $studioUser->avatar,
                'credits_balance' => $studioUser->credits_balance,
                'is_admin' => $studioUser->isAdmin(),
                'is_super_admin' => $studioUser->isSuperAdmin(),
            ] : null,
            'project_statuses' => app(\App\Services\ProjectWorkflowService::class)->states(),
        ];
    @endphp
    <script>
        window.__STUDIO_BOOT__ = @json($studioBoot);
    </script>
</body>
</html>
