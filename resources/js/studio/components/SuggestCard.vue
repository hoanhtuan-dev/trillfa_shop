<script setup>
import { ref } from 'vue';
import { useStudioStore } from '../store.js';
const store = useStudioStore();
const lang = ref(store.suggestLang === 'vi' ? 'vi' : 'en');
function applyPrompt() { const p = lang.value === 'vi' ? store.suggestResult?.prompt_vi : store.suggestResult?.image_prompt_en; if (p) { store.imagePromptEn = p; store.promptOpen = true; } else { store.toast('Chưa có prompt.', 'error'); } }
</script>
<template>
  <div class="card p-5" style="border:1px solid var(--color-brand-500); background: linear-gradient(160deg, rgba(80,150,150,.13), rgba(74,122,144,.06));">
    <h2 class="mb-1 font-display text-base font-semibold text-brand-300">💡 Gợi ý từ ảnh</h2>
    <div v-if="!store.suggestEnabled" class="mt-3 rounded-2xl border border-red-500/40 bg-red-900/25 p-2.5 text-xs text-red-100">Tính năng này đang bị tắt — bật lại trong <b>Cài đặt Studio → 💡 Gợi ý từ ảnh</b>.</div>
    <div v-if="store.upscaleSrc" class="mt-3 flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 p-2.5"><img :src="store.upscaleSrc" class="h-16 w-16 rounded-xl bg-ink-900 object-cover"><span class="truncate text-xs text-cream-200">{{ store.upscaleName }}</span></div>
    <div v-else class="mt-3 text-xs text-cream-300/60">Chọn ảnh nguồn (tải lên / sản phẩm / kết quả) để gợi ý.</div>
    <button @click="store.suggestStyle(store.upscaleSrc)" :disabled="store.suggesting || !store.upscaleSrc" class="btn-brand mt-3 w-full">{{ store.suggesting ? 'Đang phân tích…' : '💡 Gợi ý phong cách & prompt' }}</button>
    <div v-if="store.suggestResult && (store.suggestResult.styles?.length || store.suggestResult.background || store.suggestResult.image_prompt_en)" class="relative mt-3 rounded-2xl border border-emerald-500/40 bg-emerald-900/25 p-3 text-xs">
      <button @click="store.suggestResult = null; lang='en'" class="absolute right-2 top-2 grid h-6 w-6 place-items-center rounded-full bg-ink-700 text-cream-200 hover:bg-red-600" title="Xóa gợi ý">✕</button>
      <p v-if="store.suggestResult.styles?.length" class="mb-1"><span class="text-cream-300/60">Phong cách:</span> {{ store.suggestResult.styles.join(', ') }}</p>
      <p v-if="store.suggestResult.background" class="mb-1"><span class="text-cream-300/60">Bối cảnh:</span> {{ store.suggestResult.background }}</p>
      <div v-if="store.suggestResult.image_prompt_en" class="mt-2">
        <div class="mb-1 flex items-center gap-1.5">
          <button @click="lang='en'" :class="lang==='en' ? 'bg-brand-600 text-white' : 'bg-ink-700 text-cream-200'" class="rounded-full px-3 py-1 text-xs font-semibold">🇬🇧 EN</button>
          <button @click="lang='vi'; if (!store.suggestResult.prompt_vi) store.translate(store.suggestResult.image_prompt_en)" :class="lang==='vi' ? 'bg-brand-600 text-white' : 'bg-ink-700 text-cream-200'" class="rounded-full px-3 py-1 text-xs font-semibold">🇻🇳 VI</button>
        </div>
        <p class="max-h-28 overflow-y-auto rounded-xl border border-white/10 bg-white/5 p-2 leading-relaxed text-cream-100">{{ lang === 'vi' ? (store.suggestResult.prompt_vi || 'Đang dịch…') : store.suggestResult.image_prompt_en }}</p>
        <button @click="applyPrompt" class="btn-brand btn-sm mt-2 w-full">➡ Áp dụng → Tạo Ảnh</button>
      </div>
    </div>
  </div>
</template>
