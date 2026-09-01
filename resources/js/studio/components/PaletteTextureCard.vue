<script setup>
import { useStudioStore } from '../store.js';
const store = useStudioStore();
</script>
<template>
  <div class="card p-5" style="border:1px solid var(--color-brand-500); background: linear-gradient(160deg, rgba(106,143,106,.12), rgba(74,122,144,.06));">
    <h2 class="mb-2 font-display text-sm font-semibold text-cream-200">🧵 Texture</h2>
    <input type="range" min="0" max="10" v-model.number="store.texture" class="w-full accent-brand-500">
    <div class="mt-2 flex items-center justify-between text-xs"><span class="text-cream-300">0 · Mịn</span><span class="font-semibold text-cream-50">{{ store.texture }}</span><span class="text-cream-300">10 · Chi tiết</span></div>
  </div>
</template>
