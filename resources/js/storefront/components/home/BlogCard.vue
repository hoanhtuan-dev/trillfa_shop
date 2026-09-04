<script setup>
import Icon from '../ui/Icon.vue';
import BaseBadge from '../ui/BaseBadge.vue';

const props = defineProps({
    post: { type: Object, required: true },
});
</script>

<template>
    <a :href="post.url" class="glass card-surface-hover group flex h-full flex-col overflow-hidden rounded-[1.75rem]">
        <div class="aspect-[16/10] overflow-hidden">
            <img :src="post.image" :alt="post.title" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105" loading="lazy" />
        </div>
        <div class="flex flex-1 flex-col p-5">
            <BaseBadge v-if="post.category" variant="brand" class="self-start">{{ post.category }}</BaseBadge>
            <h3 class="mt-3 font-display text-lg font-semibold text-ink-900 group-hover:text-brand-700">{{ post.title }}</h3>
            <p class="mt-2 line-clamp-2 text-sm text-ink-500">{{ post.excerpt }}</p>
            <div class="mt-auto flex items-center gap-3 pt-4 text-xs text-ink-500">
                <span>{{ post.published_at }}</span>
                <span>·</span>
                <span>{{ post.reading_time }} phút đọc</span>
            </div>
        </div>
    </a>
</template>
