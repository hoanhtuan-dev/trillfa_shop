<script setup>
import { computed } from 'vue';
import { useStorefrontStore } from './store.js';
import { useRecentlyViewed } from './composables/useRecentlyViewed.js';

import StorefrontLayout from './components/layout/StorefrontLayout.vue';
import SkeletonCard from './components/ui/SkeletonCard.vue';
import SectionHeading from './components/ui/SectionHeading.vue';
import CountdownTimer from './components/ui/CountdownTimer.vue';

import HeroCarousel from './components/home/HeroCarousel.vue';
import BenefitsStrip from './components/home/BenefitsStrip.vue';
import CategoryGrid from './components/home/CategoryGrid.vue';
import ProductGrid from './components/home/ProductGrid.vue';
import BannerPromoCard from './components/home/BannerPromoCard.vue';
import BlogCard from './components/home/BlogCard.vue';
import CtaCard from './components/home/CtaCard.vue';

const store = useStorefrontStore();
store.ensureBoot();

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
const recent = useRecentlyViewed().recentlyViewed;
const saleEndsAt = computed(() => boot.value.sale_ends_at || '');
</script>

<template>
    <StorefrontLayout>
        <HeroCarousel v-if="hero.enabled && hero.slides.length" :slides="hero.slides" />

        <BenefitsStrip :benefits="boot.benefits || []" />

        <!-- Categories -->
        <section v-if="categories.enabled && categories.items.length" v-reveal class="sf-container py-8 sm:py-12">
            <SectionHeading
                :kicker="categories.kicker"
                :title="categories.title"
                :link-text="categories.link_text"
                link-href="/shop"
            />
            <CategoryGrid :categories="categories.items" />
        </section>

        <!-- Featured -->
        <section v-if="featured.enabled && featured.products.length" v-reveal class="sf-container py-8 sm:py-12">
            <SectionHeading
                :kicker="featured.kicker"
                :title="featured.title"
                :link-text="featured.link_text"
                link-href="/shop"
            />
            <ProductGrid :products="featured.products" />
        </section>

        <!-- Secondary banners -->
        <section v-if="secondary.enabled && secondary.banners.length" v-reveal class="sf-container py-8 sm:py-12">
            <div class="grid gap-4 sm:grid-cols-2">
                <BannerPromoCard v-for="b in secondary.banners.slice(0, 2)" :key="b.id" :banner="b" />
            </div>
        </section>

        <!-- New arrivals -->
        <section v-if="newArrivals.enabled && newArrivals.products.length" v-reveal class="sf-container py-8 sm:py-12">
            <SectionHeading
                :kicker="newArrivals.kicker"
                :title="newArrivals.title"
                :link-text="newArrivals.link_text"
                link-href="/shop"
            />
            <ProductGrid :products="newArrivals.products" />
        </section>

        <!-- Sale -->
        <section v-if="onSale.enabled && onSale.products.length" v-reveal class="sf-container py-8 sm:py-12">
            <div class="card-surface relative overflow-hidden !rounded-[2rem] bg-ink-900 p-6 text-white sm:p-10">
                <div class="pointer-events-none absolute -right-20 -top-20 h-72 w-72 rounded-full bg-brand-600/30 blur-3xl"></div>
                <div class="pointer-events-none absolute -bottom-24 -left-16 h-64 w-64 rounded-full bg-clay-500/20 blur-3xl"></div>
                <div class="relative">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-300">{{ onSale.kicker }}</p>
                    <h2 class="mt-2 font-display text-3xl font-semibold sm:text-4xl">{{ onSale.title }}</h2>
                    <CountdownTimer v-if="saleEndsAt" :ends-at="saleEndsAt" class="mt-3" />
                    <div class="mt-8">
                        <ProductGrid :products="onSale.products.slice(0, 4)" />
                    </div>
                </div>
            </div>
        </section>

        <!-- Bestsellers -->
        <section v-if="bestsellers.enabled && bestsellers.products.length" v-reveal class="sf-container py-8 sm:py-12">
            <SectionHeading
                :kicker="bestsellers.kicker"
                :title="bestsellers.title"
                :link-text="bestsellers.link_text"
                link-href="/shop"
            />
            <ProductGrid :products="bestsellers.products" />
        </section>

        <!-- Blog -->
        <section v-if="blog.enabled && blog.posts.length" v-reveal class="sf-container py-8 sm:py-12">
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

        <!-- Recently viewed -->
        <section v-if="recent.length" v-reveal class="sf-container py-8 sm:py-12">
            <SectionHeading kicker="Đã xem" title="Bạn đã xem" />
            <div class="grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 lg:grid-cols-4">
                <a v-for="p in recent" :key="p.id" :href="p.url" class="group card-surface card-surface-hover overflow-hidden rounded-[1.75rem]">
                    <img :src="p.image" :alt="p.name" class="aspect-[4/5] w-full object-cover" loading="lazy" />
                    <div class="p-3">
                        <p class="line-clamp-1 text-sm font-medium text-ink-900">{{ p.name }}</p>
                        <p class="mt-1 text-sm font-semibold text-ink-900">{{ new Intl.NumberFormat('vi-VN').format(p.price || 0) }}₫</p>
                    </div>
                </a>
            </div>
        </section>

        <!-- CTA -->
        <CtaCard v-if="cta.enabled" :cta="cta" />

        <!-- Loading skeleton when boot not yet ready -->
        <section v-if="!store.boot" class="sf-container py-8">
            <SkeletonCard :count="4" />
        </section>
    </StorefrontLayout>
</template>
