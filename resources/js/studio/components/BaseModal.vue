<script setup>
defineProps({ modelValue: { type: Boolean, default: false }, title: { type: String, default: '' }, wide: { type: Boolean, default: false } });
const emit = defineEmits(['update:modelValue']);
</script>
<template>
  <div v-if="modelValue" class="fixed inset-0 z-[70] flex items-center justify-center bg-black/70 p-4" @click.self="emit('update:modelValue', false)">
    <div class="scrollbar-hide max-h-[92vh] w-full overflow-y-auto rounded-3xl border border-brand-500/40 bg-ink-900 p-5 shadow-2xl" :class="wide ? 'max-w-3xl' : 'max-w-lg'" @click.stop>
      <div class="mb-3 flex items-center justify-between">
        <span class="text-sm font-semibold text-brand-300">{{ title }}</span>
        <button @click="emit('update:modelValue', false)" class="grid h-8 w-8 place-items-center rounded-full bg-ink-700 text-cream-200 hover:text-white">✕</button>
      </div>
      <slot />
    </div>
  </div>
</template>
