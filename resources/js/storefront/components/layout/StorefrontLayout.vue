<script setup>
import { computed } from 'vue';
import { useStorefrontStore } from '../../store.js';
import Navbar from '../nav/Navbar.vue';
import Footer from '../footer/Footer.vue';
import MobileMenu from '../nav/MobileMenu.vue';
import CartDrawer from '../cart/CartDrawer.vue';
import ToastHost from '../ui/ToastHost.vue';
import QuickViewModal from '../home/QuickViewModal.vue';
import Icon from '../ui/Icon.vue';

const store = useStorefrontStore();

const path = typeof window !== 'undefined' ? window.location.pathname : '/';
const isActive = (p) => (p === '/' ? path === '/' : path.startsWith(p));
const active = computed(() => path);
</script>

<template>
    <div class="sf-shell relative flex min-h-screen flex-col">
        <Navbar />

        <!-- Page content -->
        <main class="flex-1">
            <slot />
        </main>

        <Footer />

        <!-- Spacer so the fixed mobile bottom nav never covers footer content -->
        <div class="h-16 md:hidden"></div>

        <!-- Mobile bottom nav -->
        <div class="fixed inset-x-0 bottom-0 z-30 border-t border-cream-200 bg-white/80 backdrop-blur-xl md:hidden pb-safe">
            <nav class="grid grid-cols-4">
                <a href="/" class="relative flex flex-col items-center gap-1 py-2.5 text-xs transition-colors" :class="isActive('/') ? 'text-brand-700' : 'text-ink-500'">
                    <span :class="isActive('/') ? 'text-brand-600' : ''"><Icon name="home" :size="22" /></span>
                    Home
                    <span v-if="isActive('/')" class="absolute top-0 h-0.5 w-7 rounded-full bg-brand-600"></span>
                </a>
                <a href="/shop" class="relative flex flex-col items-center gap-1 py-2.5 text-xs transition-colors" :class="isActive('/shop') ? 'text-brand-700' : 'text-ink-500'">
                    <span :class="isActive('/shop') ? 'text-brand-600' : ''"><Icon name="search" :size="22" /></span>
                    Shop
                    <span v-if="isActive('/shop')" class="absolute top-0 h-0.5 w-7 rounded-full bg-brand-600"></span>
                </a>
                <a href="/yeu-thich" class="relative flex flex-col items-center gap-1 py-2.5 text-xs transition-colors" :class="isActive('/yeu-thich') ? 'text-brand-700' : 'text-ink-500'">
                    <span :class="isActive('/yeu-thich') ? 'text-brand-600' : ''"><Icon name="heart" :size="22" /></span>
                    <span v-if="store.wishlistCount" class="absolute right-1 top-0.5 grid h-4 min-w-4 place-items-center rounded-full bg-brand-600 px-1 text-[10px] font-bold text-white">{{ store.wishlistCount }}</span>
                    Yêu thích
                    <span v-if="isActive('/yeu-thich')" class="absolute top-0 h-0.5 w-7 rounded-full bg-brand-600"></span>
                </a>
                <button @click="store.openCart()" class="relative flex flex-col items-center gap-1 py-2.5 text-xs text-ink-500 transition-colors">
                    <Icon name="cart" :size="22" />
                    <span v-if="store.cartCount" class="absolute right-1 top-0.5 grid h-4 min-w-4 place-items-center rounded-full bg-brand-600 px-1 text-[10px] font-bold text-white">{{ store.cartCount }}</span>
                    Giỏ
                </button>
            </nav>
        </div>

        <MobileMenu />
        <CartDrawer />
        <QuickViewModal v-if="store.quickViewProduct" :product="store.quickViewProduct" @close="store.quickViewProduct = null" />
        <ToastHost />
    </div>
</template>
