<script setup>
import { useStudioStore } from '../store.js';
const store = useStudioStore();
function openGallery() { store.viewer = store.preview || store.generations.find(g => g.media_url) || null; }
</script>
<template>
  <div class="card p-3">
    <div class="flex items-center justify-between"><p class="text-xs font-semibold text-cream-200">Outputs <span class="text-cream-300/50">({{ store.generations.length }})</span></p><button @click="openGallery" class="btn-outline btn-sm whitespace-nowrap">🖼 Xem thư viện</button></div>
    <div class="scrollbar-hide mt-2 flex max-h-[42vh] flex-wrap gap-2 overflow-y-auto">
      <div v-for="g in store.generations" :key="g.id" class="group relative h-16 w-16">
        <button @click="store.select(g)" class="relative block h-full w-full overflow-hidden rounded-lg border-2" :class="store.previewId === g.id ? 'border-brand-500' : 'border-ink-700'"><img :src="g.media_url" class="h-full w-full bg-ink-900 object-cover" loading="lazy"><span v-if="g.status !== 'completed'" class="absolute inset-0 grid place-items-center bg-black/60 text-[10px] text-cream-200">{{ g.status }}</span></button>
        <div class="absolute inset-0 items-center justify-center gap-1 rounded-lg bg-black/55 opacity-0 transition group-hover:flex"><button @click="store.viewer = g" class="grid h-7 w-7 place-items-center rounded-full bg-white/90 text-xs text-ink-900">🔍</button><a :href="'/studio/generations/' + g.id + '/download'" class="grid h-7 w-7 place-items-center rounded-full bg-white/90 text-xs text-ink-900">⬇</a></div>
      </div>
    </div>
  </div>
</template>
