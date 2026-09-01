<script setup>
import { useStudioStore } from '../store.js';
const store = useStudioStore();
const ratios = ['1:1','3:4','4:5','9:16','16:9','2:3'];
</script>
<template>
  <div class="card p-5" style="border:1px solid var(--color-brand-500); background: linear-gradient(160deg, rgba(90,140,170,.13), rgba(74,122,144,.06));">
    <h2 class="mb-1 font-display text-base font-semibold text-brand-300">📐 Reframe / Crop</h2>
    <p class="text-[11px] text-ink-500">Cắt lại khung theo tỷ lệ hoặc chọn vùng trên canvas.</p>
    <label class="label mt-3">Tỷ lệ khung</label>
    <div class="flex flex-wrap gap-1.5">
      <button v-for="r in ratios" :key="r" type="button" @click="store.reframeRatio = r" class="rounded-full border px-3 py-1.5 text-xs transition-colors" :class="store.reframeRatio === r ? 'border-brand-600 bg-brand-600 font-semibold text-white' : 'border-ink-700 text-cream-200 hover:border-brand-400'">{{ r }}</button>
    </div>
    <button @click="store.reframeCenter" :disabled="store.reframing || !store.upscaleSrc" class="btn-outline btn-sm mt-3 w-full whitespace-nowrap">📐 Cắt giữa</button>
    <button @click="store.toggleCrop" :disabled="store.reframing || !store.upscaleSrc" class="mt-1.5 w-full whitespace-nowrap rounded-2xl border py-2 text-sm font-semibold transition-colors" :class="store.cropMode ? 'border-brand-500 bg-brand-600 text-white' : 'border-ink-700 text-cream-200 hover:border-brand-400'">{{ store.cropMode ? '✂️ Đang chọn vùng… (Hủy)' : '✂️ Chọn vùng trên canvas' }}</button>
    <template v-if="store.cropMode">
      <button @click="store.confirmCrop" :disabled="store.reframing || !store.upscaleSrc" class="btn-brand mt-1.5 w-full whitespace-nowrap">{{ store.reframing ? 'Đang cắt…' : '✅ Áp dụng vùng đã chọn' }}</button>
      <p class="mt-1.5 text-center text-[10px] text-ink-500">Kéo khung để di chuyển · kéo góc để đổi kích thước · đúp / Esc để hủy</p>
    </template>
  </div>
</template>
