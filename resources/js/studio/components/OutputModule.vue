<script setup>
import { useStudioStore } from '../store.js';
const store = useStudioStore();
function openGallery() { store.viewer = store.preview || store.generations[0] || null; }
function goLibrary() { window.location.href = '/studio/library'; }
</script>
<template>
  <div class="card flex flex-1 flex-col p-3" style="min-height:0">
    <div class="flex items-center justify-between gap-1"><p class="text-xs font-semibold text-cream-200">Outputs <span class="text-cream-300/50">({{ store.generations.length }})</span></p><button @click="goLibrary" class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-ink-800 text-cream-200 transition-colors hover:bg-ink-700" title="Xem thư viện"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg></button></div>
    <div class="scrollbar-hide mt-2 grid flex-1 auto-rows-min grid-cols-2 gap-1.5 overflow-y-auto">
      <div v-for="g in store.generations" :key="g.id" class="group relative aspect-square overflow-hidden rounded-lg border-2" :class="store.previewId === g.id ? 'border-brand-500' : (g.id === store.composeBestId ? 'border-emerald-400' : 'border-ink-700')">
        <button @click="store.viewer = g" class="absolute inset-0"><img :src="g.media_url" class="h-full w-full bg-ink-900 object-cover" loading="lazy"><span v-if="g.status !== 'completed'" class="absolute inset-0 flex flex-col items-center justify-center gap-1 bg-black/60 text-[10px] text-cream-200"><span v-if="['pending','processing'].includes(g.status)" class="h-4 w-4 animate-spin rounded-full border-2 border-cream-200 border-t-transparent"></span><span>{{ store.statusLabel(g.status) }}</span></span></button>
        <span v-if="store.genScore(g) != null" class="absolute right-1 top-1 z-10 rounded-full px-1.5 py-0.5 text-[9px] font-bold" :class="g.id === store.composeBestId ? 'bg-emerald-500 text-black' : 'bg-ink-900/85 text-cream-100'" :title="'Giữ đồ ' + (g.meta.qa.garment_preservation ?? '-') + ' · Đúng dáng ' + (g.meta.qa.pose_accuracy ?? '-') + ' · Mặt ' + (g.meta.qa.face_quality ?? '-') + ' · Thẩm mỹ ' + (g.meta.qa.overall_aesthetic ?? '-')">{{ store.genScore(g).toFixed(1) }}<span class="opacity-70">/10</span></span>
        <span v-if="g.id === store.composeBestId" class="absolute left-1 top-1 z-10 rounded-full bg-emerald-500 px-1.5 py-0.5 text-[9px] font-bold text-black">★ Tốt nhất</span>
        <span v-if="g.media_url" class="absolute bottom-1 left-1 max-w-[92%] truncate rounded-full bg-black/60 px-1.5 py-0.5 text-[9px] text-cream-100">{{ store.genName(g) }}</span>
      </div>
    </div>
  </div>
</template>