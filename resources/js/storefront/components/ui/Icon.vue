<script setup>
import { computed } from 'vue';
import { getIcon } from './icons.js';

const props = defineProps({
    name: { type: String, required: true },
    size: { type: [String, Number], default: 24 },
    strokeWidth: { type: [String, Number], default: 1.7 },
    fill: { type: Boolean, default: false },
});

const paths = computed(() => getIcon(props.name));

// Some icons (stars) need solid fill; the rest are stroke-based outlines.
const isStroke = computed(() => !props.fill);
</script>

<template>
    <svg
        :width="size"
        :height="size"
        viewBox="0 0 24 24"
        :fill="fill ? 'currentColor' : 'none'"
        :stroke="isStroke ? 'currentColor' : 'none'"
        :stroke-width="strokeWidth"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <path v-for="(d, i) in paths" :key="i" :d="d" />
    </svg>
</template>
