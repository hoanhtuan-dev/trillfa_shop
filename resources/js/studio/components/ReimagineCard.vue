<script setup>
import { ref, computed } from 'vue';
import { useStudioStore } from '../store.js';
const store = useStudioStore();

const prompt = ref('');
const similarity = ref(70);
const variants = ref(2);
const busy = ref(false);

const src = computed(() => store.upscaleSrc);
const srcName = computed(() => store.editSource ? (store.editSource.name || 'Ảnh nguồn') : ('Ảnh #' + store.preview?.id));

async function run() {
  if (!src.value || busy.value) return;
  busy.value = true;
  await store.reimagine(src.value, prompt.value, similarity.value, variants.value);
  busy.value = false;
}
</script>
<template>
  <div class="card p-5" style="border:1px solid var(--color-brand-500); background: linear-gradient(160deg, rgba(147,140,255,.13), rgba(74,122,144,.06));">
    <h2 class="mb-1 font-display text-base font-semibold text-brand-300">🔄 Tạo lại ảnh (Reimagine)</h2>
    <p class="text-[11px] text-ink-500">Dùng ảnh đang chọn làm gốc — AI tạo biến thể mới theo mô tả, giữ ý tưởng chủ thể.</p>

    <!-- Ảnh gốc -->
    <div v-if="src" class="mt-3 flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 p-2.5">
      <img :src="src" class="h-14 w-14 rounded-xl bg-ink-900 object-cover">
      <div class="min-w-0 text-xs text-cream-200">
        <p class="truncate font-semibold">{{ srcName }}</p>
        <p class="text-cream-300/60">Ảnh gốc để tạo biến thể</p>
      </div>
    </div>
    <div v-else class="mt-3 rounded-2xl border border-dashed border-white/15 bg-white/5 p-3 text-xs text-cream-300/60">Chọn một ảnh (Nguồn / Kết quả) để tạo lại.</div>

    <label class="label mt-3">Mô tả thay đổi</label>
    <textarea v-model="prompt" rows="3" maxlength="1000" class="input !text-xs" placeholder="VD: phong cách điện ảnh, ánh sáng hoàng hôn, chất liệu lụa…"></textarea>

    <div class="mt-2 flex items-center gap-3 rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs">
      <span class="shrink-0 font-medium text-cream-200">Giữ nguyên bố cục</span>
      <input type="range" min="0" max="100" step="5" v-model.number="similarity" class="h-2 w-full cursor-pointer accent-brand-500">
      <span class="shrink-0 font-semibold text-cream-50">{{ similarity }}%</span>
    </div>

    <div class="mt-2 flex items-center gap-1.5 text-xs text-cream-200">
      <span class="mr-1">Số biến thể:</span>
      <button v-for="n in [1,2,3,4]" :key="n" @click="variants = n"
              :class="variants === n ? 'bg-brand-600 text-white' : 'bg-ink-800 text-cream-200 hover:bg-ink-700'"
              class="h-7 w-7 rounded-full font-semibold transition-colors">{{ n }}</button>
    </div>

    <button @click="run" :disabled="busy || !src || !prompt.trim()" class="btn-brand mt-3 w-full whitespace-nowrap">
      {{ busy ? 'Đang tạo biến thể…' : '🔄 Tạo lại ảnh' }}
    </button>
  </div>
</template>
