<script setup>
import { useStudioStore } from '../store.js';
const store = useStudioStore();
</script>
<template>
  <div class="card p-5" style="border:1px solid var(--color-brand-500); background: linear-gradient(160deg, rgba(80,150,150,.13), rgba(74,122,144,.06));">
    <h2 class="mb-1 font-display text-base font-semibold text-brand-300">💡 Gợi ý từ ảnh</h2>
    <p class="text-[11px] text-ink-500">Phân tích ảnh nguồn → gợi ý phong cách/nhà thiết kế.</p>
    <div v-if="store.upscaleSrc" class="mt-3 flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 p-2.5"><img :src="store.upscaleSrc" class="h-16 w-16 rounded-xl bg-ink-900 object-cover"><span class="truncate text-xs text-cream-200">{{ store.upscaleName }}</span></div>
    <button @click="store.suggestStyle(store.upscaleSrc)" :disabled="store.suggesting || !store.upscaleSrc" class="btn-brand mt-3 w-full">{{ store.suggesting ? 'Đang gợi ý…' : '💡 Gợi ý phong cách' }}</button>
    <div v-if="store.suggestResult && (store.suggestResult.styles?.length || store.suggestResult.background)" class="mt-3 rounded-2xl border border-mint-500/40 bg-mint-900/30 p-3 text-xs text-mint-100">
      <p v-if="store.suggestResult.styles?.length" class="mb-1"><span class="text-cream-300/60">Phong cách:</span> {{ store.suggestResult.styles.join(', ') }}</p>
      <p v-if="store.suggestResult.background" class="mb-1"><span class="text-cream-300/60">Bối cảnh:</span> {{ store.suggestResult.background }}</p>
      <p v-if="store.suggestResult.prompt"><span class="text-cream-300/60">Prompt:</span> <span class="text-cream-100">{{ store.suggestResult.prompt }}</span></p>
      <button v-if="store.suggestResult.prompt" @click="store.imagePromptEn = store.suggestResult.prompt; store.toast('Đã đưa vào ô Tạo Ảnh.')" class="btn-outline btn-sm mt-2 w-full">➡ Đưa vào Tạo Ảnh</button>
    </div>
  </div>
</template>
