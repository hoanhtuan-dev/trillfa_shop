<script setup>
import { onMounted, computed } from 'vue';
import { useStudioStore } from './store.js';
import GalleryModal from './components/GalleryModal.vue';
const store = useStudioStore();
onMounted(() => store.load());
const items = computed(() => store.generations);
</script>
<template>
  <div class="studio-dark min-h-screen bg-ink-950 p-4 text-cream-100">
    <div class="mx-auto max-w-6xl">
      <div class="mb-4 flex items-center justify-between">
        <h1 class="font-display text-lg font-semibold">🖼 Thư viện <span class="text-cream-300/50">({{ items.length }})</span></h1>
        <a href="/studio" class="rounded-xl bg-ink-800 px-3 py-1.5 text-xs font-semibold text-cream-200 hover:bg-ink-700">← Về Studio</a>
      </div>
      <div class="scrollbar-hide grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
        <button v-for="g in items" :key="g.id" @click="store.viewer = g" class="group relative overflow-hidden rounded-2xl border-2 border-ink-700">
          <img :src="g.media_url" class="aspect-[3/4] w-full bg-ink-900 object-cover" loading="lazy">
          <div class="absolute inset-0 opacity-0 transition group-hover:opacity-100"><div class="absolute inset-0 bg-black/40"></div><div class="absolute inset-x-0 bottom-0 p-2 text-[10px]">{{ g.model || 'Ảnh' }} <span v-if="g.status !== 'completed'" class="text-amber-300"> · {{ g.status }}</span></div></div>
        </button>
        <p v-if="!items.length" class="col-span-full py-16 text-center text-sm text-cream-300/50">Chưa có ảnh.</p>
      </div>
    </div>
    <GalleryModal v-if="store.viewer" />
  </div>
</template>
