<script setup>
import { ref, computed, onMounted } from 'vue';
import { useStorefrontStore } from './store.js';
import StorefrontLayout from './components/layout/StorefrontLayout.vue';
import BlogCard from './components/home/BlogCard.vue';
import Icon from './components/ui/Icon.vue';

const store = useStorefrontStore();
store.ensureBoot();

const boot = window.__STORE_BOOT__ || {};
const posts = ref(boot.posts || []);
const total = boot.total || 0;

const featured = computed(() => posts.value[0]);
const rest = computed(() => posts.value.slice(1));

onMounted(() => store.fetchCart());
</script>

<template>
    <StorefrontLayout>
        <div class="sf-container py-8 sm:py-10">
            <!-- Heading -->
            <div class="mb-8">
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-brand-600">Blog</p>
                <h1 class="mt-1 font-display text-3xl font-semibold text-ink-900 sm:text-4xl">Câu chuyện & Phong cách</h1>
                <p class="mt-2 text-sm text-ink-500">{{ total }} bài viết</p>
            </div>

            <!-- Featured -->
            <a v-if="featured" :href="featured.url" class="group card-surface card-surface-hover mb-8 grid overflow-hidden rounded-[2rem] md:grid-cols-2">
                <div class="overflow-hidden">
                    <img :src="featured.image" :alt="featured.title" class="h-full w-full object-cover transition-transform duration-700 group-hover:scale-105" />
                </div>
                <div class="flex flex-col justify-center p-6 sm:p-10">
                    <span class="inline-flex w-fit items-center gap-1 rounded-full bg-brand-600/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-wide text-brand-700">{{ featured.category }}</span>
                    <h2 class="mt-4 font-display text-2xl font-semibold leading-tight text-ink-900 group-hover:text-brand-700 sm:text-3xl">{{ featured.title }}</h2>
                    <p class="mt-3 line-clamp-3 text-ink-500">{{ featured.excerpt }}</p>
                    <span class="mt-5 inline-flex items-center gap-1.5 text-sm font-medium text-brand-700">Đọc tiếp <Icon name="arrow-right" :size="16" /></span>
                </div>
            </a>

            <!-- Grid -->
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <BlogCard v-for="p in rest" :key="p.id" :post="p" />
            </div>
        </div>
    </StorefrontLayout>
</template>
