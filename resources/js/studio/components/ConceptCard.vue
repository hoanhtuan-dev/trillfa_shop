<script setup>
import { computed, ref } from 'vue';
import { useStudioStore } from '../store.js';
import BaseModal from './BaseModal.vue';
const store = useStudioStore();
const showAdvanced = ref(false);
const promptLoading = ref(false);
const promptPreview = computed(() => { const t = store.imagePromptEn || ''; return t.length > 54 ? t.slice(0, 54) + '…' : (t || 'Nhập/áp dụng prompt…'); });
const textureLabel = computed(() => {
  const t = store.texture;
  if (t <= 0) return 'Không';
  if (t <= 2) return 'Mịn phẳng';
  if (t <= 4) return 'Dệt nhẹ';
  if (t <= 6) return 'Rõ vừa';
  if (t <= 8) return 'Chi tiết cao';
  return 'Siêu chi tiết';
});
// Credit cost estimate
const creditEstimate = computed(() => {
  const base = 1; // base image credit
  return base * store.variantCount;
});
async function openPrompt() {
  // Store defaults are loaded once in store.load() — no duplicate fetch needed.
  // If store.defaults aren't loaded yet (edge case), load them now with loading state.
  if (!store.defaultsLoaded) {
    promptLoading.value = true;
    try {
      await store.loadDefaults();
    } catch (e) { /* keep current values */ }
    finally { promptLoading.value = false; }
  }
  store.promptOpen = true;
}
// Reset all prompt fields to system defaults
function resetToDefaults() {
  store.applyDefaults();
  store.toast('Đã đặt lại về mặc định hệ thống.');
}
</script>
<template>
  <div class="card p-5" style="border:1px solid var(--color-brand-500); background: linear-gradient(160deg, rgba(124,58,237,.12), rgba(74,122,144,.06));">
    <button @click="openPrompt" class="flex w-full items-center justify-between rounded-2xl border border-white/10 bg-white/5 p-3 text-left transition hover:border-brand-400">
      <span class="min-w-0 flex-1 overflow-hidden"><span class="block truncate text-sm font-semibold text-brand-300">🎛 Prompt Tạo Ảnh</span><span class="mt-0.5 block w-full truncate text-[11px] text-ink-500">{{ promptPreview }}</span></span><span class="ml-1 shrink-0 text-lg text-cream-200">›</span>
    </button>
    <!-- Prompt popup (shared for AI generation) -->
    <BaseModal :model-value="store.promptOpen" @update:model-value="store.promptOpen = $event" title="🎛 Prompt Tạo Ảnh">
        <!-- Loading state -->
        <div v-if="promptLoading" class="py-8 text-center text-sm text-cream-300/60">⏳ Đang tải cài đặt mặc định…</div>
        <template v-else>
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
        <label class="label mt-2">🧵 Texture: <span class="font-semibold text-brand-300">{{ textureLabel }}</span></label>
        <div class="flex items-center gap-3 rounded-2xl border border-ink-700 bg-ink-800 px-3 py-2 text-xs"><span class="shrink-0 font-medium text-cream-200">Texture</span><input type="range" min="0" max="10" step="1" v-model.number="store.texture" class="h-2 w-full cursor-pointer accent-brand-500"><span class="shrink-0 font-semibold text-cream-50">{{ store.texture }}</span></div>
        <!-- Advanced: Negative prompt -->
        <button @click="showAdvanced = !showAdvanced" class="mt-2 flex w-full items-center gap-1 text-xs text-cream-300/60 hover:text-cream-200">
          <span>{{ showAdvanced ? '▾' : '▸' }}</span> Nâng cao (negative prompt)
        </button>
        <div v-if="showAdvanced" class="mt-2 rounded-2xl border border-ink-700 bg-ink-800 p-3">
          <label class="label">Negative prompt (điều model KHÔNG nên tạo)</label>
          <textarea v-model="store.negativePromptEn" rows="2" class="input !text-xs" placeholder="blurry, low quality, distorted proportions, extra limbs, deformed hands, watermark, text, logo..."></textarea>
          <p class="mt-1 text-[10px] text-cream-300/50">Để trống để dùng negative prompt mặc định từ Cài đặt.</p>
        </div>
        <!-- Credit info + reset -->
        <div class="mt-3 flex items-center justify-between text-[10px] text-cream-300/50">
          <span>💰 ~{{ creditEstimate }} credit / {{ store.variantCount }} biến thể</span>
          <button @click="resetToDefaults" class="underline hover:text-brand-300">↺ Đặt lại mặc định</button>
        </div>
        <button @click="store.promptOpen = false; store.generateImage()" :disabled="store.generating || !store.imagePromptEn" class="btn-brand mt-2 w-full whitespace-nowrap">{{ store.generating ? 'Đang gửi…' : '🎨 Tạo Ảnh 2D' }}</button>
        </template>
    </BaseModal>
  </div>
</template>
