<script setup>
import { watch, onMounted } from 'vue';
import { useStudioStore } from '../store.js';
const store = useStudioStore();
onMounted(() => store.loadPalette(store.previewId));
watch(() => store.previewId, (id) => store.loadPalette(id));
async function copyColor(c) { try { await navigator.clipboard.writeText(c); store.toast('Đã copy ' + c); } catch (e) { store.toast('Lỗi copy.', 'error'); } }
</script>
<template>
  <div class="card p-5" style="border:1px solid var(--color-brand-500); background: linear-gradient(160deg, rgba(90,160,200,.12), rgba(74,122,144,.06));">
    <h2 class="mb-2 font-display text-sm font-semibold text-cream-200">🎨 Color Palette</h2>
    <div class="flex items-center gap-1.5" v-if="store.palette.length">
      <button v-for="c in store.palette" :key="c" type="button" @click="copyColor(c)" class="h-7 w-7 rounded-full border border-ink-700 transition hover:scale-110" :style="{ background: c }" :title="'Nhấn để copy ' + c"></button>
    </div>
    <p v-else class="text-xs text-cream-300/50">Chọn ảnh ở Outputs để trích màu.</p>
    <h2 class="mb-2 mt-4 font-display text-sm font-semibold text-cream-200">🧵 Texture</h2>
    <input type="range" min="0" max="10" v-model.number="store.texture" class="w-full accent-brand-500">
  </div>
</template>
