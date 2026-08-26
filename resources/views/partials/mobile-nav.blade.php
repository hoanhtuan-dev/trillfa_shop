@php $isLogged = auth()->check(); @endphp
<nav class="fixed inset-x-0 bottom-0 z-[70] border-t border-cream-200 bg-white/95 backdrop-blur md:hidden" aria-label="Điều hướng di động">
    <div class="grid grid-cols-5">
        <a href="{{ route('home') }}" class="flex flex-col items-center gap-0.5 py-2.5 text-[11px] text-ink-700 hover:text-brand-700">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75"/></svg>
            <span class="font-medium">Trang chủ</span>
        </a>
        <a href="{{ route('shop.index') }}" class="flex flex-col items-center gap-0.5 py-2.5 text-[11px] text-ink-700 hover:text-brand-700">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5L21 9l-9 4.5L3 9l9-4.5zM3 9v6l9 4.5 9-4.5V9"/></svg>
            <span class="font-medium">Cửa hàng</span>
        </a>
        <a href="{{ route('shop.index') }}" class="flex flex-col items-center gap-0.5 py-2.5 text-[11px] text-ink-700 hover:text-brand-700">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z"/></svg>
            <span class="font-medium">Tìm kiếm</span>
        </a>
        <a href="{{ route('cart.show') }}" class="relative flex flex-col items-center gap-0.5 py-2.5 text-[11px] text-ink-700 hover:text-brand-700">
            <span class="relative">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z"/></svg>
                <span x-show="$store.cart.count > 0" x-text="$store.cart.count" class="absolute -right-1.5 -top-1 grid h-4 min-w-4 place-items-center rounded-full bg-brand-600 px-1 text-[10px] font-bold text-white"></span>
            </span>
            <span class="font-medium">Giỏ hàng</span>
        </a>
        <a href="{{ $isLogged ? route('account.dashboard') : route('login') }}" class="flex flex-col items-center gap-0.5 py-2.5 text-[11px] text-ink-700 hover:text-brand-700">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <span class="font-medium">Tài khoản</span>
        </a>
    </div>
</nav>