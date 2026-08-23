@extends('layouts.app')

@section('content')
<div class="container-x py-10">
    <div class="grid gap-8 lg:grid-cols-[260px_1fr]">
        <aside class="lg:sticky lg:top-24 lg:self-start">
            <div class="card p-5">
                <div class="flex items-center gap-3 border-b border-cream-200 pb-4">
                    <span class="grid h-12 w-12 place-items-center rounded-full bg-brand-600 font-display text-lg font-bold text-white">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                    <div class="min-w-0">
                        <p class="truncate font-medium text-ink-900">{{ auth()->user()->name }}</p>
                        <p class="truncate text-xs text-ink-500">{{ auth()->user()->email }}</p>
                    </div>
                </div>
                <nav class="mt-4 space-y-1">
                    @php
                        $nav = [
                            ['Tổng quan', route('account.dashboard'), 'home', 'dashboard'],
                            ['Đơn hàng', route('account.orders'), 'order', 'orders'],
                            ['Yêu thích', route('wishlist.index'), 'heart', 'wishlist'],
                            ['Địa chỉ', route('account.addresses'), 'pin', 'addresses'],
                            ['Hồ sơ', route('account.profile'), 'user', 'profile'],
                            ['Đánh giá', route('account.reviews'), 'star', 'reviews'],
                        ];
                    @endphp
                    @foreach($nav as $item)
                        <a href="{{ $item[1] }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition {{ isset($active) && $active === $item[3] ? 'bg-brand-50 text-brand-800' : 'text-ink-700 hover:bg-cream-100' }}">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ match($item[2]) {
                                    'home' => 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25',
                                    'order' => 'M9 12h6m-6 3h6m-6-6h.008M13.5 3H13a4.5 4.5 0 00-4.5 4.5V7.5A4.5 4.5 0 004 12v7.5A1.5 1.5 0 005.5 21h13a1.5 1.5 0 001.5-1.5V12a4.5 4.5 0 00-4.5-4.5H10.5A1.5 1.5 0 009 6V4.5A1.5 1.5 0 0010.5 3H13z',
                                    'heart' => 'M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z',
                                    'pin' => 'M15 10.5a3 3 0 11-6 0 3 3 0 016 0z M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z',
                                    'user' => 'M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z',
                                    default => 'M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z'
                                } }}" />
                            </svg>
                            {{ $item[0] }}
                        </a>
                    @endforeach
                </nav>
                <form method="POST" action="{{ route('logout') }}" class="mt-4 border-t border-cream-200 pt-4">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-red-600 hover:bg-red-50">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/></svg>
                        Đăng xuất
                    </button>
                </form>
            </div>
        </aside>

        <div>
            @yield('account_content')
        </div>
    </div>
</div>
@endsection
