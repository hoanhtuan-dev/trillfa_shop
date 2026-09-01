<script setup>
import { computed } from 'vue';
import { useStudioStore } from '../store.js';
const store = useStudioStore();
const items = computed(() => store.generations.filter(g => g.media_url));
const idx = computed(() => items.value.findIndex(g => g.id === store.viewer?.id));
const current = computed(() => items.value[idx.value] || store.viewer);
function nav(d) { const n = items.value[(idx.value + d + items.value.length) % items.value.length]; if (n) store.viewer = n; }
const fields = [{k:'model',l:'Model'},{k:'provider',l:'Provider'},{k:'ratio',l:'Tỷ lệ'},{k:'resolution',l:'Độ phân giải'},{k:'duration',l:'Thời lượng'},{k:'credits_cost',l:'Chi phí'},{k:'created_at',l:'Ngày'}];
</script>
<template>
  <div class="fixed inset-0 z-[70] flex items-center justify-center bg-black/92 p-3 sm:p-6" @click.self="store.viewer = null">
    <button @click="store.viewer = null" class="absolute right-4 top-4 z-20 grid h-10 w-10 place-items-center rounded-full bg-ink-800 text-cream-200 hover:text-white">✕</button>
    <button @click="nav(-1)" class="absolute left-3 top-1/2 z-20 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-full bg-ink-800/90 text-2xl text-cream-100 hover:bg-ink-700">‹</button>
    <button @click="nav(1)" class="absolute right-3 top-1/2 z-20 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-full bg-ink-800/90 text-2xl text-cream-100 hover:bg-ink-700">›</button>
    <div class="flex h-full w-full max-w-6xl flex-col gap-3 lg:flex-row">
      <!-- center image -->
      <div class="relative flex flex-1 items-center justify-center rounded-2xl bg-ink-900/60 p-3">
        <img v-if="current?.media_url" :src="current.media_url" class="max-h-full max-w-full rounded-xl object-contain shadow-2xl">
        <p v-else class="text-sm text-cream-300/60">Không có ảnh.</p>
      </div>
      <!-- right outputs grid -->
      <div class="flex w-full shrink-0 flex-col lg:w-64">
        <p class="mb-2 text-xs font-semibold text-cream-200">Outputs <span class="text-cream-300/50">({{ items.length }})</span></p>
        <div class="scrollbar-hide grid max-h-64 grid-cols-3 gap-1.5 overflow-y-auto lg:max-h-full lg:grid-cols-2">
          <button v-for="g in items" :key="g.id" @click="store.viewer = g" class="relative h-16 w-16 overflow-hidden rounded-lg border-2" :class="current?.id === g.id ? 'border-brand-500' : 'border-ink-700'"><img :src="g.media_url" class="h-full w-full bg-ink-900 object-cover" loading="lazy"></button>
        </div>
      </div>
      <!-- left info -->
      <div class="w-full shrink-0 lg:w-60">
        <div class="scrollbar-hide max-h-[70vh] overflow-y-auto rounded-2xl border border-ink-700 bg-ink-900/70 p-3">
          <p class="mb-2 text-xs font-semibold text-cream-200">ℹ️ Thông tin</p>
          <div v-for="f in fields" :key="f.k" class="mb-1.5 flex justify-between border-b border-ink-700/60 pb-1 text-xs">
            <span class="text-cream-300/60">{{ f.l }}</span><span class="text-cream-100">{{ current?.[f.k] ?? '—' }}</span>
          </div>
          <p class="mt-2 text-xs text-cream-200"><span class="text-cream-300/60">Prompt:</span><br>{{ current?.prompt || '—' }}</p>
          <div class="mt-3 space-y-1.5">
            <button @click="store.goEdit(current); store.viewer = null" class="w-full rounded-xl border border-brand-500/60 bg-brand-600/30 py-1.5 text-xs font-semibold text-white hover:bg-brand-600">✏️ Chỉnh sửa → Fitting Room</button>
            <button @click="store.goVideo(current); store.viewer = null" class="w-full rounded-xl border border-ink-600 bg-ink-800 py-1.5 text-xs font-semibold text-cream-200 hover:bg-ink-700">🎬 Tạo video → Director</button>
            <a :href="current ? '/studio/generations/' + current.id + '/download' : '#'" class="block w-full rounded-xl border border-ink-600 bg-ink-800 py-1.5 text-center text-xs font-semibold text-cream-200 hover:bg-ink-700">⬇ Tải xuống</a>
            <button @click="store.deleteGen(current); store.viewer = null" class="w-full rounded-xl border border-red-500/50 bg-red-600/20 py-1.5 text-xs font-semibold text-red-200 hover:bg-red-600">🗑 Xóa</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
