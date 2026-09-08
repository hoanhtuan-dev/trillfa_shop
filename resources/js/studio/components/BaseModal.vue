<script setup>
defineProps({ modelValue: { type: Boolean, default: false }, title: { type: String, default: '' }, wide: { type: Boolean, default: false } });
const emit = defineEmits(['update:modelValue']);
</script>
<template>
  <div v-if="modelValue" class="fixed inset-0 z-[70] flex items-center justify-center bg-black/70 p-4" @click.self="emit('update:modelValue', false)">
    <div class="w-full overflow-y-auto overscroll-contain rounded-3xl border border-brand-500/40 bg-ink-900 shadow-2xl" :class="wide ? 'max-w-3xl' : 'max-w-lg'" style="max-height: 88vh" @click.stop>
      <!-- Sticky header (cố định khi cuộn nội dung) -->
      <div class="sticky top-0 z-20 flex h-14 shrink-0 items-center justify-between border-b border-ink-700 bg-ink-900 px-5">
        <span class="text-sm font-semibold text-brand-300">{{ title }}</span>
        <button @click="emit('update:modelValue', false)" class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-ink-700 text-cream-200 transition hover:bg-red-600 hover:text-white" title="Đóng">✕</button>
      </div>
      <div class="p-5">
        <slot />
      </div>
    </div>
  </div>
</template>
