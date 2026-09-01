<script setup>
import { useStudioStore } from '../store.js';
const store = useStudioStore();
</script>
<template>
  <div class="card p-5" style="border:1px solid var(--color-brand-500); background: linear-gradient(160deg, rgba(124,58,237,.12), rgba(74,122,144,.06));">
    <h2 class="mb-1 font-display text-base font-semibold text-brand-300">🎛 Prompt</h2>
    <textarea v-model="store.imagePromptEn" rows="5" class="input !text-xs" placeholder="Nhập ý tưởng / prompt (tiếng Việt hoặc tiếng Anh)."></textarea>
    <div class="mt-2 rounded-2xl border border-ink-700 bg-ink-800 px-3 py-2 text-xs">
      <p class="mb-1 font-medium text-cream-200">Sáng tạo <span class="float-right font-semibold text-cream-50">{{ store.creativeLevel }}/10</span></p>
      <input type="range" min="1" max="10" v-model.number="store.creativeLevel" class="w-full cursor-pointer accent-brand-500">
    </div>
    <div class="mt-2 grid grid-cols-2 gap-2">
      <select v-model="store.imageRatio" class="input !py-2"><option v-for="r in ['1:1','4:3','3:4','9:16','16:9','4:5','21:9']" :key="r" :value="r">{{ r }}</option></select>
      <select v-model="store.imageRes" class="input !py-2"><option value="1K">1K</option><option value="2K">2K</option></select>
    </div>
    <label class="label mt-2">Số biến thể / lần tạo</label>
    <div class="flex items-center gap-3 rounded-2xl border border-ink-700 bg-ink-800 px-3 py-2 text-xs"><span class="shrink-0 font-medium text-cream-200">Biến thể</span><input type="range" min="1" max="4" step="1" v-model.number="store.variantCount" class="h-2 w-full cursor-pointer accent-brand-500"><span class="shrink-0 font-semibold text-cream-50">{{ store.variantCount }}</span></div>
    <button @click="store.generateImage()" :disabled="store.generating || !store.imagePromptEn" class="btn-brand mt-3 w-full whitespace-nowrap">{{ store.generating ? 'Đang gửi…' : 'Tạo Ảnh 2D' }}</button>
  </div>
</template>
