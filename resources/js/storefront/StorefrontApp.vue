<script setup>
import { computed } from 'vue';
import { useStorefrontStore } from './store.js';

import Navbar from './components/nav/Navbar.vue';
import MobileMenu from './components/nav/MobileMenu.vue';
import Footer from './components/footer/Footer.vue';
import CartDrawer from './components/cart/CartDrawer.vue';
import ToastHost from './components/ui/ToastHost.vue';
import SkeletonCard from './components/ui/SkeletonCard.vue';
import SectionHeading from './components/ui/SectionHeading.vue';
import BaseButton from './components/ui/BaseButton.vue';
import Icon from './components/ui/Icon.vue';

import HeroCarousel from './components/home/HeroCarousel.vue';
import BenefitsStrip from './components/home/BenefitsStrip.vue';
import CategoryGrid from './components/home/CategoryGrid.vue';
import ProductGrid from './components/home/ProductGrid.vue';
import BannerPromoCard from './components/home/BannerPromoCard.vue';
import BlogCard from './components/home/BlogCard.vue';
import CtaCard from './components/home/CtaCard.vue';

const store = useStorefrontStore();
store.ensureBoot();

// Current path for bottom-nav active state.
const path = typeof window !== 'undefined' ? window.location.pathname : '/';
const isActive = (p) => (p === '/' ? path === '/' : path.startsWith(p));

// Reactive slices of the boot payload.
const boot = computed(() => store.boot || {});
const hero = computed(() => boot.value.hero || { enabled: false, slides: [] });
const categories = computed(() => boot.value.categories || { enabled: false, items: [] });
const featured = computed(() => boot.value.featured || { enabled: false, products: [] });
const secondary = computed(() => boot.value.secondary || { enabled: false, banners: [] });
const newArrivals = computed(() => boot.value.new_arrivals || { enabled: false, products: [] });
const onSale = computed(() => boot.value.on_sale || { enabled: false, products: [] });
const bestsellers = computed(() => boot.value.bestsellers || { enabled: false, products: [] });
const blog = computed(() => boot.value.blog || { enabled: false, posts: [] });
const cta = computed(() => boot.value.cta || { enabled: false });
</script>

<template>
    <div class="sf-shell flex min-h-screen flex-col">
        <Navbar />

        <main class="flex-1">
            <HeroCarousel v-if="hero.enabled && hero.slides.length" :slides="hero.slides" />

            <BenefitsStrip :benefits="boot.benefits || []" />

            <!-- Categories -->
            <section v-if="categories.enabled && categories.items.length" class="sf-container py-8">
                <SectionHeading
                    :kicker="categories.kicker"
                    :title="categories.title"
                    :link-text="categories.link_text"
                    link-href="/shop"
                />
                <CategoryGrid :categories="categories.items" />
            </section>

            <!-- Featured -->
            <section v-if="featured.enabled && featured.products.length" class="sf-container py-8">
                <SectionHeading
                    :kicker="featured.kicker"
                    :title="featured.title"
                    :link-text="featured.link_text"
                    link-href="/shop"
                />
                <ProductGrid :products="featured.products" />
            </section>

            <!-- Secondary banners -->
            <section v-if="secondary.enabled && secondary.banners.length" class="sf-container py-8">
                <div class="grid gap-4 sm:grid-cols-2">
                    <BannerPromoCard v-for="b in secondary.banners.slice(0, 2)" :key="b.id" :banner="b" />
                </div>
            </section>

            <!-- New arrivals -->
            <section v-if="newArrivals.enabled && newArrivals.products.length" class="sf-container py-8">
                <SectionHeading
                    :kicker="newArrivals.kicker"
                    :title="newArrivals.title"
                    :link-text="newArrivals.link_text"
                    link-href="/shop"
                />
                <ProductGrid :products="newArrivals.products" />
            </section>

            <!-- Sale -->
            <section v-if="onSale.enabled && onSale.products.length" class="sf-container py-8">
                <div class="card-surface relative overflow-hidden !rounded-[2rem] bg-ink-900 p-6 text-white sm:p-10">
                    <div class="pointer-events-none absolute -right-20 -top-20 h-72 w-72 rounded-full bg-brand-600/30 blur-3xl"></div>
                    <div class="pointer-events-none absolute -bottom-24 -left-16 h-64 w-64 rounded-full bg-clay-500/20 blur-3xl"></div>
                    <div class="relative">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-300">{{ onSale.kicker }}</p>
                        <h2 class="mt-2 font-display text-3xl font-semibold sm:text-4xl">{{ onSale.title }}</h2>
                        <div class="mt-8">
                            <ProductGrid :products="onSale.products.slice(0, 4)" />
                        </div>
                    </div>
                </div>
            </section>

            <!-- Bestsellers -->
            <section v-if="bestsellers.enabled && bestsellers.products.length" class="sf-container py-8">
                <SectionHeading
                    :kicker="bestsellers.kicker"
                    :title="bestsellers.title"
                    :link-text="bestsellers.link_text"
                    link-href="/shop"
                />
                <ProductGrid :products="bestsellers.products" />
            </section>

            <!-- Blog -->
            <section v-if="blog.enabled && blog.posts.length" class="sf-container py-8">
                <SectionHeading
                    :kicker="blog.kicker"
                    :title="blog.title"
                    :link-text="blog.link_text"
                    link-href="/blog"
                />
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <BlogCard v-for="p in blog.posts" :key="p.id" :post="p" />
                </div>
            </section>

            <!-- CTA -->
            <CtaCard v-if="cta.enabled" :cta="cta" />

            <!-- Loading skeleton when boot not yet ready -->
            <section v-if="!store.boot" class="sf-container py-8">
                <SkeletonCard :count="4" />
            </section>
        </main>

        <Footer />

        <!-- Spacer so the fixed mobile bottom nav never covers footer content -->
        <div class="h-16 md:hidden"></div>

        <!-- Floating contact / mobile bottom nav -->
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
        <ToastHost />
    </div>
</template>
