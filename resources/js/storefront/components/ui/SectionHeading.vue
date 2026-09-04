<script setup>
import { computed } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({
    kicker: { type: String, default: '' },
    title: { type: String, default: '' },
    linkText: { type: String, default: '' },
    linkHref: { type: String, default: '' },
    align: { type: String, default: 'left' }, // left | center
});

const isCenter = computed(() => props.align === 'center');
</script>

<template>
    <div class="section-heading" :class="isCenter ? 'flex-col items-center text-center !items-center' : ''">
        <div>
            <p v-if="kicker" class="section-kicker mb-2">{{ kicker }}</p>
            <h2 v-if="title" class="section-title">{{ title }}</h2>
        </div>
        <a
            v-if="linkText && linkHref"
            :href="linkHref"
            class="group inline-flex shrink-0 items-center gap-1.5 text-sm font-medium text-brand-700 transition-colors hover:text-brand-800"
        >
            {{ linkText }}
            <Icon name="arrow-right" :size="16" class="transition-transform group-hover:translate-x-0.5" />
        </a>
    </div>
</template>
