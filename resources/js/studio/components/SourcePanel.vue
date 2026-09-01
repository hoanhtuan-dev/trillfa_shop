<script setup>
import { ref, onMounted } from 'vue';
import { useStudioStore } from '../store.js';
const store = useStudioStore();
const productOpen = ref(false), products = ref([]);
const fileRef = ref(null);
onMounted(async () => { try { const r = await fetch('/studio/references', { headers: { Accept: 'application/json' } }); const d = await r.json(); products.value = d.items || []; } catch(e){} });
function onFile(e) { const f = e.target.files?.[0]; if (f) store.uploadRef(f); if (fileRef.value) fileRef.value.value = ''; }
</script>
<template>
  <div class="card p-3">
    <div class="flex items-center justify-between"><h2 class="text-sm font-semibold text-brand-300">🖼 Nguồn ảnh</h2><button v-if="store.editSource" @click="store.editSource = null" class="text-[10px] text-red-300">✕</button></div>
    <div v-if="store.editSource" class="mt-2 flex items-center gap-2"><img :src="store.editSource.url" class="h-12 w-12 rounded-lg bg-ink-900 object-cover"><span class="truncate text-xs text-cream-200">{{ store.editSource.name }}</span></div>
    <div class="mt-2 grid grid-cols-2 gap-1.5">
      <button @click="fileRef.click()" class="btn-outline btn-sm">⬆ Tải lên</button>
      <button @click="productOpen = true" class="btn-outline btn-sm">🛍 Từ sản phẩm</button>
    </div>
    <input ref="fileRef" type="file" accept="image/*" @change="onFile" class="hidden">
    <div v-if="productOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" @click.self="productOpen=false">
      <div class="w-full max-w-2xl rounded-2xl border border-ink-700 bg-ink-900 p-4">
        <div class="mb-2 flex items-center justify-between"><p class="text-sm font-semibold text-cream-100">🛍 Chọn ảnh sản phẩm</p><button @click="productOpen=false" class="grid h-8 w-8 place-items-center rounded-full bg-ink-700 text-cream-200">✕</button></div>
        <div class="scrollbar-hide grid max-h-[60vh] grid-cols-3 gap-2 overflow-y-auto sm:grid-cols-4">
          <button v-for="p in products" :key="p.id" @click="store.pickFromProduct(p); productOpen=false" class="relative h-24 overflow-hidden rounded-xl border border-ink-700"><img :src="p.url" class="h-full w-full bg-ink-900 object-cover" loading="lazy"><span class="absolute inset-x-0 bottom-0 truncate bg-black/60 px-1 text-[9px] text-cream-200">{{ p.name }}</span></button>
        </div>
      </div>
    </div>
  </div>
</template>
