<script setup>
import { computed } from 'vue';

const props = defineProps({
    variant: { type: String, default: 'primary' }, // primary | dark | ghost | soft
    size: { type: String, default: 'md' }, // sm | md | lg
    href: { type: String, default: null },
    type: { type: String, default: 'button' },
    block: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['click']);

const sizeClass = computed(() => ({
    sm: 'px-4 py-2 text-xs',
    md: 'px-6 py-3 text-sm',
    lg: 'px-7 py-3.5 text-base',
}[props.size] || 'px-6 py-3 text-sm'));

const variantClass = computed(() => ({
    primary: 'sf-btn-primary',
    dark: 'sf-btn-dark',
    ghost: 'sf-btn-ghost',
    soft: 'sf-btn-soft',
}[props.variant] || 'sf-btn-primary'));

const cls = computed(() => [
    'sf-btn',
    variantClass.value,
    sizeClass.value,
    props.block ? 'w-full' : '',
    props.disabled ? 'pointer-events-none' : '',
]);
</script>

<template>
    <a
        v-if="href"
        :href="href"
        :class="cls"
        :aria-disabled="disabled || undefined"
        @click="$emit('click', $event)"
    >
        <slot />
    </a>
    <button
        v-else
        :type="type"
        :class="cls"
        :disabled="disabled"
        @click="$emit('click', $event)"
    >
        <slot />
    </button>
</template>
