<script setup>
import { computed } from 'vue';
import { useStudioStore } from '../store.js';
import BaseModal from './BaseModal.vue';
const store = useStudioStore();
const promptPreview = computed(() => { const t = store.imagePromptEn || ''; return t.length > 54 ? t.slice(0, 54) + '…' : (t || 'Nhập/áp dụng prompt…'); });
</script>
<template>
  <div class="card p-5" style="border:1px solid var(--color-brand-500); background: linear-gradient(160deg, rgba(124,58,237,.12), rgba(74,122,144,.06));">
    <button @click="store.promptOpen = true" class="flex w-full items-center justify-between rounded-2xl border border-white/10 bg-white/5 p-3 text-left transition hover:border-brand-400">
      <span class="flex-1"><span class="text-sm font-semibold text-brand-300">🎛 Prompt Tạo Ảnh</span><br><span class="block max-w-full truncate text-[11px] text-ink-500">{{ promptPreview }}</span></span><span class="text-lg text-cream-200">›</span>
    </button>
    <!-- Prompt popup (shared for AI generation) -->
    <BaseModal :model-value="store.promptOpen" @update:model-value="store.promptOpen = $event" title="🎛 Prompt Tạo Ảnh">
        <textarea v-model="store.imagePromptEn" rows="5" class="input !text-xs" placeholder="Nhập ý tưởng / prompt (EN hoặc VI)."></textarea>
        <div class="mt-3 rounded-2xl border border-ink-700 bg-ink-800 px-3 py-2 text-xs">
          <p class="mb-1 font-medium text-cream-200">Sáng tạo <span class="float-right font-semibold text-cream-50">{{ store.creativeLevel }}/10</span></p>
          <input type="range" min="1" max="10" v-model.number="store.creativeLevel" class="w-full cursor-pointer accent-brand-500">
        </div>
        <div class="mt-2 grid grid-cols-2 gap-2">
          <select v-model="store.imageRatio" class="input !py-2"><option v-for="r in ['1:1','4:3','3:4','9:16','16:9','4:5','21:9']" :key="r" :value="r">{{ r }}</option></select>
          <select v-model="store.imageRes" class="input !py-2"><option value="1K">1K</option><option value="2K">2K</option></select>
        </div>
        <label class="label mt-2">Số biến thể / lần tạo</label>
        <div class="flex items-center gap-3 rounded-2xl border border-ink-700 bg-ink-800 px-3 py-2 text-xs"><span class="shrink-0 font-medium text-cream-200">Biến thể</span><input type="range" min="1" max="4" step="1" v-model.number="store.variantCount" class="h-2 w-full cursor-pointer accent-brand-500"><span class="shrink-0 font-semibold text-cream-50">{{ store.variantCount }}</span></div>
        <button @click="store.promptOpen = false; store.generateImage()" :disabled="store.generating || !store.imagePromptEn" class="btn-brand mt-3 w-full whitespace-nowrap">{{ store.generating ? 'Đang gửi…' : '🎨 Tạo Ảnh 2D' }}</button>
    </BaseModal>
  </div>
</template>
