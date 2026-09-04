<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useStorefrontStore } from '../../store.js';
import { csrfToken } from '../../composables/useApi.js';
import Icon from '../ui/Icon.vue';
import SearchBox from './SearchBox.vue';
import MenuNav from './MenuNav.vue';

const store = useStorefrontStore();

const scrolled = ref(false);
const accountOpen = ref(false);
const accountRef = ref(null);

const nav = computed(() => store.boot?.nav || []);
const site = computed(() => store.site);
const user = computed(() => store.user);

function onScroll() {
    scrolled.value = window.scrollY > 8;
}

function onDocClick(e) {
    if (accountOpen.value && accountRef.value && !accountRef.value.contains(e.target)) {
        accountOpen.value = false;
    }
}

onMounted(() => {
    window.addEventListener('scroll', onScroll, { passive: true });
    document.addEventListener('click', onDocClick);
    onScroll();
    store.fetchCart();
});
onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
    document.removeEventListener('click', onDocClick);
});
</script>

<template>
    <header class="sticky top-0 z-40">
        <!-- Announcement bar -->
        <div
            v-if="site.announcement_enabled"
            class="bg-gradient-to-r from-ink-900 via-brand-800 to-ink-900 bg-ink-900 text-cream-50"
        >
            <div class="sf-container flex h-9 items-center justify-center gap-2 overflow-hidden text-xs font-medium">
                <span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-brand-600/30 text-brand-200">
                    <Icon name="bolt" :size="14" />
                </span>
                <span class="truncate">{{ site.announcement_text }}</span>
            </div>
        </div>

        <!-- Main bar -->
        <div
            class="border-b border-cream-200/70 backdrop-blur-xl transition-shadow"
            :class="scrolled ? 'bg-white/85 shadow-lg shadow-ink-900/5' : 'bg-white/60'"
        >
            <div class="sf-container">
                <div class="flex h-16 items-center justify-between gap-4 lg:h-20">
                    <!-- Left: mobile menu + logo -->
                    <div class="flex items-center gap-2">
                        <button
                            @click="store.openMenu()"
                            class="sf-btn sf-btn-ghost !p-2 lg:hidden"
                            aria-label="Mở menu"
                        >
                            <Icon name="menu" :size="24" />
                        </button>
                        <a href="/" class="flex items-center gap-2.5">
                            <img
                                :src="site.logo"
                                :alt="site.name"
                                class="h-9 w-auto object-contain"
                                loading="eager"
                            />
                            <span class="hidden font-display text-2xl font-bold tracking-tight text-ink-900 sm:inline">
                                Trillfa<span class="sf-gradient-text"> Fa</span>
                            </span>
                        </a>
                    </div>

                    <!-- Center: desktop search -->
                    <div class="hidden max-w-xl flex-1 md:block">
                        <SearchBox id="nav-search" />
                    </div>

                    <!-- Right actions -->
                    <div class="flex items-center gap-0.5 sm:gap-1.5">
                        <!-- Mobile search toggle -->
                        <button
                            @click="store.searchOpen = !store.searchOpen"
                            class="sf-btn sf-btn-ghost !p-2 md:hidden"
                            aria-label="Tìm kiếm"
                        >
                            <Icon name="search" :size="22" />
                        </button>

                        <!-- Account (always visible; hover + click) -->
                        <div v-if="user.authed" ref="accountRef" class="relative">
                            <button
                                @click="accountOpen = !accountOpen"
                                @mouseenter="accountOpen = true"
                                class="sf-btn sf-btn-ghost !p-2"
                                :aria-expanded="accountOpen"
                                aria-label="Tài khoản"
                            >
                                <Icon name="user" :size="22" />
                            </button>
                            <Transition name="fade">
                                <div
                                    v-if="accountOpen"
                                    class="glass-strong absolute right-0 top-full z-50 mt-2 w-60 overflow-hidden rounded-2xl py-1"
                                >
                                    <div class="border-b border-cream-200 px-4 py-3">
                                        <p class="text-sm font-semibold text-ink-900">{{ user.name }}</p>
                                        <p class="truncate text-xs text-ink-500">{{ user.email }}</p>
                                    </div>
                                    <a href="/tai-khoan" class="block px-4 py-2.5 text-sm text-ink-700 hover:bg-cream-100">Tài khoản</a>
                                    <a href="/tai-khoan/don-hang" class="block px-4 py-2.5 text-sm text-ink-700 hover:bg-cream-100">Đơn hàng</a>
                                    <a href="/yeu-thich" class="block px-4 py-2.5 text-sm text-ink-700 hover:bg-cream-100">Yêu thích</a>
                                    <a v-if="user.is_admin" href="/admin" class="block px-4 py-2.5 text-sm font-semibold text-brand-700 hover:bg-cream-100">Quản trị</a>
                                    <form method="POST" action="/dang-xuat">
                                        <input type="hidden" name="_token" :value="csrfToken()" />
                                        <button type="submit" class="block w-full px-4 py-2.5 text-left text-sm text-red-600 hover:bg-cream-100">Đăng xuất</button>
                                    </form>
                                </div>
                            </Transition>
                        </div>
                        <a v-else href="/dang-nhap" class="sf-btn sf-btn-ghost !p-2" aria-label="Đăng nhập">
                            <Icon name="user" :size="22" />
                        </a>

                        <!-- Wishlist -->
                        <a href="/yeu-thich" class="sf-btn sf-btn-ghost relative !p-2 transition-colors" aria-label="Yêu thích">
                            <Icon name="heart" :size="22" />
                            <span
                                v-if="store.wishlistCount > 0"
                                class="absolute -right-0.5 -top-0.5 grid h-4 min-w-4 place-items-center rounded-full bg-gradient-to-r from-brand-600 to-brand-500 px-1 text-[10px] font-bold text-white"
                            >{{ store.wishlistCount }}</span>
                        </a>

                        <!-- Cart -->
                        <button
                            @click="store.openCart()"
                            class="sf-btn sf-btn-ghost relative !p-2 transition-colors"
                            aria-label="Giỏ hàng"
                        >
                            <Icon name="cart" :size="22" />
                            <span
                                v-if="store.cartCount > 0"
                                class="absolute -right-0.5 -top-0.5 grid h-4 min-w-4 place-items-center rounded-full bg-gradient-to-r from-brand-600 to-brand-500 px-1 text-[10px] font-bold text-white"
                            >{{ store.cartCount }}</span>
                        </button>
                    </div>
                </div>

                <!-- Mobile search expandable -->
                <div v-if="store.searchOpen" class="pb-3 md:hidden">
                    <SearchBox id="nav-mobile-search" />
                </div>

                <!-- Desktop nav (shared, nested menu) -->
                <MenuNav v-if="nav.length" :items="nav" />
            </div>
        </div>
    </header>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
