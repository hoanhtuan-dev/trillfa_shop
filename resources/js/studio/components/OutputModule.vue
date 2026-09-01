<script setup>
import { useStudioStore } from '../store.js';
const store = useStudioStore();
function openGallery() { store.viewer = store.preview || store.generations[0] || null; }
function goLibrary() { window.location.href = '/studio/library'; }
</script>
<template>
  <div class="card flex flex-1 flex-col p-3" style="min-height:0">
    <div class="flex items-center justify-between"><p class="text-xs font-semibold text-cream-200">Outputs <span class="text-cream-300/50">({{ store.generations.length }})</span></p><button @click="goLibrary" class="btn-outline btn-sm whitespace-nowrap">🖼 Xem thư viện</button></div>
    <div class="scrollbar-hide mt-2 grid flex-1 auto-rows-min grid-cols-3 gap-2 overflow-y-auto sm:grid-cols-4">
      <div v-for="g in store.generations" :key="g.id" class="group relative aspect-square overflow-hidden rounded-lg border-2" :class="store.previewId === g.id ? 'border-brand-500' : 'border-ink-700'">
        <button @click="store.viewer = g" class="absolute inset-0"><img :src="g.media_url" class="h-full w-full bg-ink-900 object-cover" loading="lazy"><span v-if="g.status !== 'completed'" class="absolute inset-0 flex flex-col items-center justify-center gap-1 bg-black/60 text-[10px] text-cream-200"><span v-if="['pending','processing'].includes(g.status)" class="h-4 w-4 animate-spin rounded-full border-2 border-cream-200 border-t-transparent"></span><span>{{ store.statusLabel(g.status) }}</span></span></button>
        <span v-if="g.media_url" class="absolute bottom-1 left-1 max-w-[92%] truncate rounded-full bg-black/60 px-1.5 py-0.5 text-[9px] text-cream-100">{{ (g.model || '') }}</span>
      </div>
    </div>
  </div>
</template>
