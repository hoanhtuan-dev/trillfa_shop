<script setup>
import { ref, onMounted } from 'vue';
import { useStudioStore } from '../store.js';
const store = useStudioStore();
const showResults = ref(false), showProducts = ref(false);
const products = ref([]);
const fileRef = ref(null);
onMounted(async () => { try { const r = await fetch('/studio/references', { headers: { Accept: 'application/json' } }); const d = await r.json(); products.value = d.items || []; } catch(e){} });
function onFile(e) { const f = e.target.files?.[0]; if (f) store.uploadRef(f); if (fileRef.value) fileRef.value.value = ''; }
</script>
<template>
  <div class="card p-5" style="border:1px solid var(--color-brand-500); background: linear-gradient(160deg, rgba(90,140,170,.14), rgba(74,122,144,.06));">
    <h2 class="mb-1 font-display text-base font-semibold text-brand-300">🖼 Nguồn ảnh</h2>
    <p class="text-[11px] text-ink-500">Nhập ảnh để xử lý (swap/film-look/reframe/inpaint).</p>
    <div v-if="store.editSource" class="mt-3 flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 p-2.5">
      <img :src="store.editSource.url" class="h-16 w-16 rounded-xl bg-ink-900 object-cover">
      <div class="min-w-0 text-xs text-cream-200"><p class="truncate font-semibold">{{ store.editSource.name }}</p><button @click="store.editSource = null" class="text-red-300 hover:text-red-200">Bỏ chọn</button></div>
    </div>
    <div class="mt-3 grid grid-cols-2 gap-1.5">
      <button @click="fileRef.click()" class="btn-outline btn-sm whitespace-nowrap">⬆ Tải ảnh</button>
      <button @click="showResults = !showResults" class="btn-outline btn-sm whitespace-nowrap">📂 Từ kết quả</button>
      <button @click="showProducts = !showProducts" class="btn-outline btn-sm col-span-2 whitespace-nowrap">🛍 Từ sản phẩm</button>
    </div>
    <input ref="fileRef" type="file" accept="image/*" @change="onFile" class="hidden">
    <div v-if="showResults" class="mt-2 grid grid-cols-4 gap-1.5">
      <button v-for="g in store.generations.slice(0,12)" :key="g.id" @click="store.pickFromResult(g)" class="relative h-14 w-14 overflow-hidden rounded-lg border border-ink-700"><img :src="g.media_url" class="h-full w-full bg-ink-900 object-cover"></button>
    </div>
    <div v-if="showProducts" class="mt-2 grid grid-cols-4 gap-1.5">
      <button v-for="p in products" :key="p.id" @click="store.pickFromProduct(p)" class="relative h-14 w-14 overflow-hidden rounded-lg border border-ink-700"><img :src="p.url" class="h-full w-full bg-ink-900 object-cover" loading="lazy"><span class="absolute inset-x-0 bottom-0 truncate bg-black/60 px-1 text-[8px] text-cream-200">{{ p.name }}</span></button>
    </div>
    <button @click="store.suggestStyle(store.upscaleSrc)" :disabled="store.suggesting || !store.upscaleSrc" class="btn-brand btn-sm mt-3 w-full">{{ store.suggesting ? 'Đang gợi ý…' : '💡 Gợi ý phong cách' }}</button>
  </div>
</template>
