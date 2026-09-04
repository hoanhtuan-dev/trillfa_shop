<script setup>
import { computed } from 'vue';

const props = defineProps({
    variant: { type: String, default: 'brand' }, // brand | clay | ink | glass
});

const cls = computed(() => ({
    brand: 'bg-brand-600/10 text-brand-700 ring-brand-600/20',
    clay: 'bg-clay-500/10 text-clay-600 ring-clay-500/20',
    ink: 'bg-ink-900/80 text-cream-50 ring-ink-900/20',
    glass: 'glass text-ink-700',
}[props.variant] || 'bg-brand-600/10 text-brand-700 ring-brand-600/20'));
</script>

<template>
    <span class="sf-badge ring-1 ring-inset" :class="cls">
        <slot />
    </span>
</template>
