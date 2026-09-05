<script setup>
import { ref, computed } from 'vue';
import { useStudioStore } from '../store.js';
import SourceLibraryPicker from './SourceLibraryPicker.vue';
const store = useStudioStore();
const uploadOpen = ref(false), productOpen = ref(false), products = ref([]);
const pquery = ref(''), pCols = ref(4), pSort = ref('newest');
const selProds = ref([]);

async function loadProducts() { try { const r = await fetch('/studio/references?_=' + Date.now(), { headers: { Accept: 'application/json' } }); const d = await r.json(); products.value = d.items || []; } catch(e){} }
function openProducts() { productOpen.value = true; pquery.value = ''; selProds.value = []; loadProducts(); }

function toggleProd(p) { const i = selProds.value.findIndex((x) => x.id === p.id); if (i >= 0) selProds.value.splice(i, 1); else selProds.value.push(p); }
const isSelProd = (p) => selProds.value.some((x) => x.id === p.id);
function addSelProds() { if (!selProds.value.length) return; store.addImagesToCanvas(selProds.value); const n = selProds.value.length; selProds.value = []; store.toast('Đã thêm ' + n + ' ảnh sản phẩm vào canvas.'); }

const productSortOptions = [
  { value: 'newest', label: 'Mới nhất' },
  { value: 'name_asc', label: 'Tên A→Z' },
  { value: 'name_desc', label: 'Tên Z→A' },
];
const sortedProducts = computed(() => {
  const q = pquery.value.trim().toLowerCase();
  let list = products.value.slice();
  if (q) list = list.filter((p) => (p.name || '').toLowerCase().includes(q));
  const k = pSort.value;
  if (k === 'name_asc') list.sort((a, b) => (a.name || '').localeCompare(b.name || ''));
  else if (k === 'name_desc') list.sort((b, a) => (a.name || '').localeCompare(b.name || ''));
  return list;
});
</script>
<template>
  <div class="card p-2">
    <div class="flex items-center justify-between">
      <span class="flex items-center gap-1 text-[11px] font-semibold text-brand-300"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>Nguồn</span>
      <button v-if="store.editSource" @click="store.removeEditSource()" class="grid h-7 w-7 place-items-center rounded-lg bg-ink-800 text-red-300 transition-colors hover:bg-red-600 hover:text-white" title="Bỏ ảnh nguồn khỏi canvas"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
    </div>
    <div v-if="store.editSource" class="mt-2 flex items-center gap-2"><img :src="store.editSource.url" class="h-10 w-10 rounded-lg bg-ink-900 object-cover"><span class="truncate text-[10px] text-cream-200">{{ store.editSource.name }}</span></div>
    <div class="mt-2 flex gap-1.5">
      <button @click="uploadOpen = true" class="flex h-8 flex-1 items-center justify-center gap-1.5 rounded-lg bg-ink-800 text-cream-200 transition-colors hover:bg-ink-700" title="Thư viện ảnh đã tải lên"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg><span class="text-[10px] font-medium">Tải lên</span></button>
      <button @click="openProducts" class="flex h-8 flex-1 items-center justify-center gap-1.5 rounded-lg bg-ink-800 text-cream-200 transition-colors hover:bg-ink-700" title="Chọn ảnh từ sản phẩm"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg><span class="text-[10px] font-medium">Sản phẩm</span></button>
    </div>

    <!-- Thư viện ảnh nguồn (dùng chung) -->
    <SourceLibraryPicker v-model="uploadOpen" title="Thư viện ảnh nguồn" mode="multi" @add="store.addImagesToCanvas" />

    <!-- ══ Products popup ══ -->
    <div v-if="productOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" @click.self="productOpen=false">
      <div class="flex h-[82vh] w-full max-w-2xl flex-col rounded-2xl border border-ink-700 bg-ink-900 p-4 shadow-2xl" style="height: min(82vh, 680px)">
        <div class="mb-3 flex items-start justify-between">
          <div class="flex items-center gap-2.5">
            <div class="grid h-9 w-9 place-items-center rounded-xl bg-emerald-600/15 text-emerald-300"><svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg></div>
            <div>
              <p class="text-sm font-semibold text-cream-100">Chọn ảnh sản phẩm</p>
              <p class="text-[11px] text-cream-300/60">{{ sortedProducts.length }} sản phẩm</p>
            </div>
          </div>
          <button @click="productOpen=false" class="grid h-8 w-8 place-items-center rounded-full bg-ink-800 text-cream-300 transition-colors hover:bg-ink-700 hover:text-white" title="Đóng"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
        </div>
        <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center">
          <div class="relative flex-1">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-cream-300/50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <input v-model="pquery" placeholder="Tìm sản phẩm…" class="h-9 w-full rounded-xl border border-ink-700 bg-ink-800/60 pl-9 pr-3 text-xs text-cream-100 placeholder:text-cream-300/40 focus:border-brand-500 focus:outline-none">
          </div>
          <div class="relative">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-cream-300/50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M6 12h12"/><path d="M10 18h4"/></svg>
            <select v-model="pSort" class="h-9 w-full appearance-none rounded-xl border border-ink-700 bg-ink-800/60 pl-9 pr-8 text-xs text-cream-100 focus:border-brand-500 focus:outline-none sm:w-40">
              <option v-for="o in productSortOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
            <svg class="pointer-events-none absolute right-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-cream-300/50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
          </div>
          <div class="flex h-9 items-center gap-2 rounded-xl border border-ink-700 bg-ink-800/60 px-3" title="Kích thước ô ảnh">
            <svg class="h-4 w-4 shrink-0 text-cream-300/50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            <input type="range" min="2" max="8" step="1" v-model.number="pCols" class="h-1.5 w-24 cursor-pointer accent-brand-500">
          </div>
        </div>
        <div class="scrollbar-hide -mr-1 grid min-h-0 flex-1 content-start gap-2.5 overflow-y-auto overscroll-contain pr-1" :style="{ gridTemplateColumns: 'repeat(' + pCols + ', minmax(0, 1fr))' }">
          <div v-for="p in sortedProducts" :key="p.id" class="group relative cursor-pointer overflow-hidden rounded-xl border transition-colors" :class="isSelProd(p) ? 'border-emerald-400 ring-2 ring-emerald-400/70' : 'border-ink-700 hover:border-ink-600'" :title="p.name" style="padding-bottom: 100%" @click="toggleProd(p)">
            <img :src="p.url" class="absolute inset-0 h-full w-full bg-ink-900 object-cover" loading="lazy" alt="">
            <span v-if="isSelProd(p)" class="pointer-events-none absolute inset-0 grid place-items-center bg-emerald-500/15"><span class="grid h-9 w-9 place-items-center rounded-full bg-emerald-500 text-white shadow-lg ring-2 ring-white/50"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span></span>
            <span class="absolute inset-x-0 bottom-0 truncate bg-black/60 px-1 py-0.5 text-[9px] text-cream-200">{{ p.name }}</span>
          </div>
          <p v-if="!sortedProducts.length" class="col-span-full py-10 text-center text-xs text-cream-300/50">{{ products.length ? 'Không có sản phẩm khớp tìm kiếm.' : 'Chưa có sản phẩm.' }}</p>
        </div>
        <div class="mt-3 flex shrink-0 items-center justify-between gap-2">
          <span class="text-[11px] text-cream-300/70">{{ selProds.length ? 'Đã chọn ' + selProds.length + ' sản phẩm' : 'Nhấn chọn 1 hoặc nhiều sản phẩm để thêm vào canvas' }}</span>
          <button @click="addSelProds" :disabled="!selProds.length" class="flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-40" title="Thêm sản phẩm đã chọn vào canvas (không xóa ảnh cũ)">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>Thêm vào canvas ({{ selProds.length }})
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
