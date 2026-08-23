<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#1f372b">
    <link rel="icon" href="{{ asset('icons/favicon-32x32.png') }}">
    <title>@yield('code') — {{ config('app.name', 'Trillfa Fa') }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif; background: #f4f2ec; color: #1b1a17; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .err { max-width: 460px; width: 100%; text-align: center; }
        .logo { width: 92px; height: 92px; border-radius: 50%; object-fit: cover; box-shadow: 0 12px 30px rgba(31,55,43,.18); }
        h1 { font-family: Georgia, 'Times New Roman', serif; font-size: 88px; font-weight: 700; color: #33674d; margin: 18px 0 6px; line-height: 1; }
        .msg { color: #3a382f; font-size: 16px; line-height: 1.55; margin-bottom: 26px; }
        .btn { display: inline-block; background: #1f372b; color: #faf9f6; text-decoration: none; padding: 13px 26px; border-radius: 999px; font-weight: 600; font-size: 15px; transition: all .2s; }
        .btn:hover { background: #2a533f; transform: translateY(-1px); }
        a.link { color: #33674d; }
    </style>
</head>
<body>
    <main class="err">
        <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name', 'Trillfa Fa') }}" class="logo">
        <h1>@yield('code')</h1>
        <p class="msg">@yield('message')</p>
        <a href="{{ url('/') }}" class="btn">Về trang chủ</a>
    </main>
</body>
</html>
