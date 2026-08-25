@php
    $siteName = setting('site_name', 'Trillfa Fa');
    $freeShipThreshold = (float) setting('free_shipping_threshold', 0);
    $user = auth()->user();
@endphp

<!-- Announcement bar -->
<div class="bg-ink-900 text-cream-50">
    <div class="container-x flex h-9 items-center justify-center gap-2 text-xs font-medium">
        <svg class="h-4 w-4 text-brand-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/></svg>
        <span>Miễn phí vận chuyển cho đơn hàng từ {{ number_format($freeShipThreshold > 0 ? $freeShipThreshold : 500000) }}đ</span>
    </div>
</div>

<!-- Header -->
<header x-data="navbar" class="sticky top-0 z-40 border-b border-cream-200 bg-cream-50/90 backdrop-blur-lg transition-shadow" :class="scrolled ? 'shadow-md shadow-ink-900/5' : ''">
    <div class="container-x">
        <div class="flex h-16 items-center justify-between gap-4 lg:h-20">
            <!-- Left: mobile menu + logo -->
            <div class="flex items-center gap-3">
                <button @click="mobileOpen = !mobileOpen" class="btn-ghost !p-2 lg:hidden" aria-label="Menu">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"/></svg>
                </button>
                <a href="{{ route('home') }}" class="flex items-center gap-2">
                    <img src="{{ asset('images/logo.png') }}" alt="Trillfa Fa" class="h-10 w-10 rounded-full object-cover" loading="eager">
                    <span class="hidden font-display text-2xl font-bold tracking-tight text-ink-900 sm:inline">Trillfa<span class="text-brand-600"> Fa</span></span>
                </a>
            </div>

            <!-- Center: search (desktop) -->
            <div class="hidden flex-1 max-w-xl md:block" x-data="searchBox">
                <div class="relative">
                    <input
                        x-model="query"
                        @input="onInput"
                        @keydown.enter.prevent="go()"
                        @keydown.escape="clear()"
                        @focus="open = true"
                        type="text"
                        placeholder="Tìm kiếm sản phẩm..."
                        class="input !rounded-full !py-2.5 pl-4 pr-16"
                    >
                    <button @click="clear()" x-show="query.length > 0" x-transition.opacity.duration.150ms type="button" class="absolute right-11 top-1/2 -translate-y-1/2 rounded-full p-1.5 text-ink-400 hover:text-ink-900" aria-label="Thoát tìm kiếm">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                    <button @click="go()" class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full bg-ink-900 p-2 text-cream-50 hover:bg-brand-700">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z"/></svg>
                    </button>

                    <!-- Results dropdown -->
                    <div x-show="open && results.length" x-transition.opacity.duration.150ms class="absolute z-50 mt-2 w-full overflow-hidden rounded-2xl border border-cream-200 bg-white shadow-xl">
                        <template x-for="p in results" :key="p.id">
                            <a :href="p.url" @click="clear()" class="flex items-center gap-3 border-b border-cream-100 p-3 last:border-0 hover:bg-cream-100">
                                <img :src="p.image" class="h-10 w-10 rounded-lg object-cover" alt="">
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-ink-900" x-text="p.name"></p>
                                    <p class="text-xs text-brand-600" x-text="$money(p.price)"></p>
                                </div>
                            </a>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Right: actions -->
            <div class="flex items-center gap-1 sm:gap-2">
                <button type="button" @click="mobileOpen = true" class="btn-ghost !p-2 md:hidden" aria-label="Tìm kiếm">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z"/></svg>
                </button>

                <!-- Account -->
                @guest
                    <a href="{{ route('login') }}" class="btn-ghost hidden !p-2 sm:inline-flex" aria-label="Đăng nhập">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </a>
                @else
                    <div class="relative hidden sm:block" x-data="{ open: false }" @click.outside="open = false">
                        <button @click="open = !open" class="btn-ghost !p-2" aria-label="Tài khoản">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </button>
                        <div x-show="open" x-transition.opacity.duration.150ms class="absolute right-0 z-50 mt-2 w-56 overflow-hidden rounded-2xl border border-cream-200 bg-white py-1 shadow-xl">
                            <div class="border-b border-cream-100 px-4 py-3">
                                <p class="text-sm font-semibold text-ink-900">{{ $user->name }}</p>
                                <p class="truncate text-xs text-ink-500">{{ $user->email }}</p>
                            </div>
                            <a href="{{ route('account.dashboard') }}" class="block px-4 py-2.5 text-sm text-ink-700 hover:bg-cream-100">Tài khoản của tôi</a>
                            <a href="{{ route('account.orders') }}" class="block px-4 py-2.5 text-sm text-ink-700 hover:bg-cream-100">Đơn hàng</a>
                            <a href="{{ route('wishlist.index') }}" class="block px-4 py-2.5 text-sm text-ink-700 hover:bg-cream-100">Yêu thích</a>
                            @if(auth()->user()->isAdmin())
                            <a href="{{ route('admin.dashboard') }}" class="block px-4 py-2.5 text-sm font-semibold text-brand-700 hover:bg-cream-100">Quản trị</a>
                            @endif
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="block w-full px-4 py-2.5 text-left text-sm text-red-600 hover:bg-cream-100">Đăng xuất</button>
                            </form>
                        </div>
                    </div>
                @endguest

                <!-- Wishlist -->
                <a href="{{ route('wishlist.index') }}" class="btn-ghost relative !p-2" aria-label="Yêu thích">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/></svg>
                    <span x-show="$store.wishlist.ids.length > 0" x-text="$store.wishlist.ids.length" class="absolute -right-0.5 -top-0.5 grid h-4 min-w-4 place-items-center rounded-full bg-brand-600 px-1 text-[10px] font-bold text-white"></span>
                </a>

                <!-- Cart -->
                <button @click="$store.cart.openDrawer()" class="relative inline-flex items-center justify-center rounded-full p-2 text-ink-700 transition hover:bg-cream-200/70 active:scale-95" aria-label="Giỏ hàng">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"/></svg>
                    <span x-show="$store.cart.count > 0" x-text="$store.cart.count" class="absolute -right-0.5 -top-0.5 grid h-4 min-w-4 place-items-center rounded-full bg-brand-600 px-1 text-[10px] font-bold text-white"></span>
                </button>
            </div>
        </div>

        <!-- Nav (managed via Admin -> Menu; multi-level) -->
        <nav class="hidden h-12 items-center gap-1 lg:flex">
            @include('partials.menu-flyout', ['items' => menu_tree('header'), 'isRoot' => true, 'level' => 0])
        </nav>
    </div>

    <!-- Mobile menu -->
    <div x-show="mobileOpen" x-collapse class="border-t border-cream-200 lg:hidden">
        <div class="container-x space-y-1 py-4">
            <div x-data="searchBox" class="pb-3">
                <div class="relative">
                    <input x-model="query" @input="onInput" @keydown.enter.prevent="go()" @keydown.escape="clear()" type="text" placeholder="Tìm kiếm sản phẩm..." class="input !rounded-full !py-2.5 pl-4 pr-16">
                    <button @click="clear()" x-show="query.length > 0" x-transition.opacity.duration.150ms type="button" class="absolute right-11 top-1/2 -translate-y-1/2 rounded-full p-1.5 text-ink-400 hover:text-ink-900" aria-label="Thoát tìm kiếm">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                    <button @click="go()" class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full bg-ink-900 p-2 text-cream-50">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z"/></svg>
                    </button>
                </div>
            </div>
            @include('partials.menu-mobile', ['items' => menu_tree('header')])
            <div class="pt-2">
                @auth
                    <a href="{{ route('account.dashboard') }}" class="btn-brand w-full">Tài khoản của tôi</a>
                @else
                    <div class="grid grid-cols-2 gap-2">
                        <a href="{{ route('login') }}" class="btn-outline">Đăng nhập</a>
                        <a href="{{ route('register') }}" class="btn-brand">Đăng ký</a>
                    </div>
                @endauth
            </div>
        </div>
    </div>
</header>