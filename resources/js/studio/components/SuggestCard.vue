<script setup>
import { ref, computed } from 'vue';
import { useStudioStore } from '../store.js';
import StudioIcon from './StudioIcon.vue';
const store = useStudioStore();
const lang = ref(store.suggestLang === 'vi' ? 'vi' : 'en');

const r = computed(() => store.suggestResult || {});
const palette = computed(() => (r.value.color_palette || []).filter(Boolean));
const keywords = computed(() => (r.value.keywords || []).filter(Boolean));
const adherenceLabel = computed(() => {
  const a = Number(r.value.adherence ?? 0);
  if (!a) return store.suggestAdherence ? adherenceText(Number(store.suggestAdherence)) : 'Tự động (theo sáng tạo)';
  return adherenceText(a);
});
function adherenceText(a) {
  if (a <= 3) return 'Thấp — sáng tạo lại';
  if (a <= 6) return 'Trung bình — tinh chỉnh tinh tế';
  return 'Cao — tái tạo chính xác gốc';
}
function applyPrompt() {
  const p = lang.value === 'vi' ? store.suggestResult?.prompt_vi : store.suggestResult?.image_prompt_en;
  if (p) { store.imagePromptEn = p; store.promptOpen = true; }
  else { store.toast('Chưa có prompt.', 'error'); }
}
</script>
<template>
  <div class="card p-5" style="border:1px solid var(--color-brand-500); background: linear-gradient(160deg, rgba(80,150,150,.13), rgba(74,122,144,.06));">
    <h2 class="flex items-center gap-2 font-display text-base font-semibold text-brand-300"><StudioIcon name="lightbulb" /> Gợi ý từ ảnh</h2>
    <div v-if="!store.suggestEnabled" class="mt-3 rounded-2xl border border-red-500/40 bg-red-900/25 p-2.5 text-xs text-red-100">Tính năng đang tắt — bật lại trong <b>Cài đặt Studio → Gợi ý từ ảnh</b>.</div>
    <div v-if="store.upscaleSrc" class="mt-3 flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 p-2.5"><img :src="store.upscaleSrc" class="h-16 w-16 rounded-xl bg-ink-900 object-cover"><span class="truncate text-xs text-cream-200">{{ store.upscaleName }}</span></div>
    <div v-else class="mt-3 text-xs text-cream-300/60">Chọn ảnh nguồn để gợi ý.</div>

    <!-- Điều khiển bám ảnh + mức chi tiết -->
    <div class="mt-3 space-y-2.5 rounded-2xl border border-white/10 bg-white/5 p-2.5 text-[11px]">
      <div>
        <div class="mb-1 flex items-center justify-between text-cream-200">
          <span class="flex items-center gap-1 font-semibold"><StudioIcon name="target" size="h-3.5 w-3.5" /> Bám trang phục gốc</span>
          <span class="text-cream-300/70">{{ store.suggestAdherence === 0 ? 'Tự động' : store.suggestAdherence + '/10' }}</span>
        </div>
        <input type="range" min="0" max="10" step="1" v-model.number="store.suggestAdherence" class="w-full accent-brand-500" title="0 = tự theo mức sáng tạo; cao = tái tạo chính xác trang phục gốc (màu/đường may/hoạ tiết)" />
      </div>
      <div>
        <div class="mb-1 flex items-center justify-between text-cream-200">
          <span class="flex items-center gap-1 font-semibold"><StudioIcon name="search" size="h-3.5 w-3.5" /> Mức chi tiết phân tích</span>
          <span class="text-cream-300/70">{{ store.suggestDetailLevel }}/10</span>
        </div>
        <input type="range" min="1" max="10" step="1" v-model.number="store.suggestDetailLevel" class="w-full accent-brand-500" title="Cao = vision liệt kê đầy đủ màu/đường may/hoạ tiết/độ dài/cổ/tay" />
      </div>
    </div>

    <button @click="store.suggestStyle(store.upscaleSrc)" :disabled="store.suggesting || !store.upscaleSrc" title="Phân tích ảnh và gợi ý phong cách, prompt" class="btn-brand mt-3 w-full">{{ store.suggesting ? 'Đang phân tích…' : 'Gợi ý phong cách & prompt' }}</button>

    <div v-if="store.suggestResult && (store.suggestResult.styles?.length || store.suggestResult.background || store.suggestResult.image_prompt_en)" class="relative mt-3 rounded-2xl border border-emerald-500/40 bg-emerald-900/25 p-3 text-xs">
      <button @click="store.suggestResult = null; lang='en'" class="absolute right-2 top-2 grid h-6 w-6 place-items-center rounded-full bg-ink-700 text-cream-200 hover:bg-red-600" title="Xóa gợi ý"><StudioIcon name="x" size="h-3.5 w-3.5" /></button>

      <!-- Tóm tắt bám ảnh -->
      <div class="mb-2 flex flex-wrap items-center gap-1.5 text-[10px]">
        <span v-if="adherenceLabel" class="rounded-full bg-brand-600/30 px-2 py-0.5 font-semibold text-brand-200">🎯 Bám: {{ adherenceLabel }}</span>
        <span v-if="r.garment_type" class="rounded-full bg-ink-700/70 px-2 py-0.5 font-semibold text-cream-200">👕 {{ r.garment_type }}</span>
        <span v-if="r.embellishment" class="rounded-full bg-ink-700/70 px-2 py-0.5 font-semibold text-cream-200">✨ {{ r.embellishment }}</span>
      </div>

      <p v-if="store.suggestResult.styles?.length" class="mb-1"><span class="text-cream-300/60">Phong cách:</span> {{ store.suggestResult.styles.join(', ') }}</p>
      <p v-if="store.suggestResult.background" class="mb-1"><span class="text-cream-300/60">Bối cảnh:</span> {{ store.suggestResult.background }}</p>
      <p v-if="store.suggestResult.fabric" class="mb-1"><span class="text-cream-300/60">Chất liệu:</span> {{ store.suggestResult.fabric }}</p>
      <p v-if="store.suggestResult.silhouette" class="mb-1"><span class="text-cream-300/60">Dáng:</span> {{ store.suggestResult.silhouette }}</p>
      <p v-if="store.suggestResult.camera" class="mb-1"><span class="text-cream-300/60">Góc máy:</span> {{ store.suggestResult.camera }}</p>

      <!-- Bảng màu gốc -->
      <div v-if="palette.length" class="mb-2 mt-1">
        <span class="text-cream-300/60">Bảng màu:</span>
        <div class="mt-1 flex flex-wrap gap-1">
          <span v-for="c in palette" :key="c" class="rounded-full bg-ink-700/70 px-2 py-0.5 text-[10px] text-cream-200">{{ c }}</span>
        </div>
      </div>

      <!-- Ghi chú chi tiết gốc -->
      <p v-if="r.detail_notes" class="mb-2 mt-1 rounded-xl border border-white/10 bg-white/5 p-2 leading-relaxed text-cream-100"><span class="text-cream-300/60">Chi tiết gốc:</span> {{ r.detail_notes }}</p>

      <!-- Từ khoá -->
      <div v-if="keywords.length" class="mb-2 mt-1 flex flex-wrap gap-1">
        <span v-for="k in keywords" :key="k" class="rounded-full bg-emerald-800/40 px-2 py-0.5 text-[10px] text-emerald-100">#{{ k }}</span>
      </div>

      <div v-if="store.suggestResult.image_prompt_en" class="mt-2">
        <div class="mb-1 flex items-center gap-1.5">
          <button @click="lang='en'" title="Hiển thị tiếng Anh" :class="lang==='en' ? 'bg-brand-600 text-white' : 'bg-ink-700 text-cream-200'" class="rounded-full px-3 py-1 text-xs font-semibold">EN</button>
          <button @click="lang='vi'; if (!store.suggestResult.prompt_vi) store.translate(store.suggestResult.image_prompt_en)" title="Hiển thị tiếng Việt" :class="lang==='vi' ? 'bg-brand-600 text-white' : 'bg-ink-700 text-cream-200'" class="rounded-full px-3 py-1 text-xs font-semibold">VI</button>
        </div>
        <p class="max-h-36 overflow-y-auto rounded-xl border border-white/10 bg-white/5 p-2 leading-relaxed text-cream-100">{{ lang === 'vi' ? (store.suggestResult.prompt_vi || 'Đang dịch…') : store.suggestResult.image_prompt_en }}</p>
        <button @click="applyPrompt" title="Đưa prompt vào ô Prompt Tạo Ảnh" class="btn-brand btn-sm mt-2 w-full">Áp dụng → Tạo Ảnh</button>
      </div>
    </div>
  </div>
</template>
