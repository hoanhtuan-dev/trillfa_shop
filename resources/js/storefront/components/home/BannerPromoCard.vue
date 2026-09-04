<script setup>
import Icon from '../ui/Icon.vue';

const props = defineProps({
    banner: { type: Object, required: true },
});
</script>

<template>
    <a :href="banner.link" class="group relative block overflow-hidden rounded-[2rem]">
        <img :src="banner.image" :alt="banner.title" class="h-64 w-full object-cover transition-transform duration-500 group-hover:scale-105" loading="lazy" />
        <div class="absolute inset-0 bg-gradient-to-t from-ink-950/80 via-ink-900/20 to-transparent"></div>
        <div class="absolute inset-x-0 bottom-0 p-6">
            <p class="text-xs font-medium uppercase tracking-wide text-brand-200">{{ banner.subtitle }}</p>
            <h3 class="mt-1 font-display text-2xl font-semibold text-white">{{ banner.title }}</h3>
            <span v-if="banner.button_text" class="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-white underline underline-offset-4">
                {{ banner.button_text }} <Icon name="arrow-right" :size="16" />
            </span>
        </div>
    </a>
</template>
