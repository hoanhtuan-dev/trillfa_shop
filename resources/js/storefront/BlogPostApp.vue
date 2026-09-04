<script setup>
import { onMounted } from 'vue';
import { useStorefrontStore } from './store.js';
import StorefrontLayout from './components/layout/StorefrontLayout.vue';
import Icon from './components/ui/Icon.vue';

const store = useStorefrontStore();
store.ensureBoot();

const boot = window.__STORE_BOOT__ || {};
const post = boot.post || {};

onMounted(() => store.fetchCart());
</script>

<template>
    <StorefrontLayout>
        <article class="sf-container py-8 sm:py-10">
            <!-- Breadcrumb -->
            <nav class="mb-5 flex items-center gap-1.5 text-xs text-ink-500">
                <a href="/" class="transition hover:text-brand-700">Trang chủ</a>
                <Icon name="chevron-right" :size="12" />
                <a href="/blog" class="transition hover:text-brand-700">Blog</a>
            </nav>

            <!-- Header -->
            <div class="mx-auto max-w-3xl text-center">
                <span v-if="post.category" class="inline-flex w-fit items-center rounded-full bg-brand-600/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-wide text-brand-700">{{ post.category }}</span>
                <h1 class="mt-4 font-display text-3xl font-semibold leading-tight text-ink-900 sm:text-4xl">{{ post.title }}</h1>
                <div class="mt-4 flex items-center justify-center gap-3 text-xs text-ink-500">
                    <span v-if="post.author">{{ post.author }}</span>
                    <span>·</span>
                    <span>{{ post.published_at }}</span>
                    <span>·</span>
                    <span>{{ post.reading_time }} phút đọc</span>
                </div>
            </div>

            <!-- Cover -->
            <div class="card-surface mt-8 overflow-hidden rounded-[2rem]">
                <img :src="post.image" :alt="post.title" class="max-h-[520px] w-full object-cover" />
            </div>

            <!-- Body -->
            <div class="prose-content mx-auto mt-10 max-w-3xl" v-html="post.body"></div>
        </article>
    </StorefrontLayout>
</template>
