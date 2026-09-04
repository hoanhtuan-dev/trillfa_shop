<script setup>
import Icon from '../ui/Icon.vue';
import BaseBadge from '../ui/BaseBadge.vue';

const props = defineProps({
    post: { type: Object, required: true },
});
</script>

<template>
    <a :href="post.url" class="group card-surface card-surface-hover flex h-full flex-col overflow-hidden rounded-[1.75rem]">
        <div class="relative aspect-[16/10] overflow-hidden">
            <img :src="post.image" :alt="post.title" class="h-full w-full object-cover transition-transform duration-700 ease-out group-hover:scale-105" loading="lazy" />
            <div class="absolute inset-0 bg-gradient-to-t from-ink-950/40 to-transparent opacity-60 transition-opacity group-hover:opacity-30"></div>
            <div class="absolute left-3 top-3">
                <BaseBadge v-if="post.category" variant="glass" class="!bg-white/80 !text-ink-800 backdrop-blur">{{ post.category }}</BaseBadge>
            </div>
        </div>
        <div class="flex flex-1 flex-col p-5">
            <h3 class="font-display text-lg font-semibold leading-snug text-ink-900 transition-colors group-hover:text-brand-700">{{ post.title }}</h3>
            <p class="mt-2 line-clamp-2 text-sm leading-relaxed text-ink-500">{{ post.excerpt }}</p>
            <div class="mt-auto flex items-center gap-3 pt-4 text-xs text-ink-500">
                <span class="inline-flex items-center gap-1.5"><Icon name="calendar" :size="14" />{{ post.published_at }}</span>
                <span>·</span>
                <span>{{ post.reading_time }} phút đọc</span>
            </div>
        </div>
    </a>
</template>
