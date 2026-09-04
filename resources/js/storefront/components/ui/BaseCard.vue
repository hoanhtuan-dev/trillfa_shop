<script setup>
import { computed } from 'vue';

const props = defineProps({
    // glass | glass-strong | flat | dark | gradient
    variant: { type: String, default: 'glass' },
    hover: { type: Boolean, default: false },
    as: { type: String, default: 'div' },
});

const cls = computed(() => ({
    glass: 'glass rounded-[1.75rem]',
    'glass-strong': 'glass-strong rounded-[1.75rem]',
    flat: 'rounded-[1.75rem] border border-cream-200 bg-white shadow-sm shadow-ink-900/5',
    dark: 'rounded-[1.75rem] bg-ink-900 text-cream-50 overflow-hidden',
    gradient: 'rounded-[1.75rem] bg-gradient-to-br from-brand-600 to-ink-900 text-cream-50 overflow-hidden',
}[props.variant] || 'glass rounded-[1.75rem]'));

const hoverCls = computed(() => (props.hover ? 'card-surface-hover' : ''));
</script>

<template>
    <component :is="as" :class="[cls, hoverCls, $attrs.class]">
        <slot />
    </component>
</template>
