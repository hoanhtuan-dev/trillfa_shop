<script setup>
import { ref, computed } from 'vue';
import { useStudioStore } from '../store.js';
import { thumbUrl, onThumbError } from '../composables/useStudioThumb.js';
import SourceLibraryPicker from './SourceLibraryPicker.vue';
import StudioIcon from './StudioIcon.vue';
const store = useStudioStore();
const uploadOpen = ref(false), productOpen = ref(false), products = ref([]);
const pquery = ref(''), pCols = ref(4), pSort = ref('newest');
const selProds = ref([]);

async function loadProducts() { try { const r = await fetch('/studio/references?_=' + Date.now(), { headers: { Accept: 'application/json' } }); if (!r.ok) throw new Error('HTTP ' + r.status); const d = await r.json(); products.value = d.items || []; } catch(e){ console.error('studio request failed', e); } }
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
      <span class="flex items-center gap-1 text-[11px] font-semibold text-brand-300"><StudioIcon name="image" size="h-4 w-4"/>Nguồn</span>
      <button v-if="store.editSource" @click="store.removeEditSource()" class="grid h-7 w-7 place-items-center rounded-lg bg-ink-800 text-red-300 transition-colors hover:bg-red-600 hover:text-white" title="Bỏ ảnh nguồn khỏi canvas" :aria-label="'Bỏ ảnh nguồn khỏi canvas'"><StudioIcon name="x" size="h-3.5 w-3.5"/></button>
    </div>
    <div v-if="store.editSource" class="mt-2 flex items-center gap-2"><img :src="store.editSource.url" class="h-10 w-10 rounded-lg bg-ink-900 object-cover"><span class="truncate text-[10px] text-cream-200">{{ store.editSource.name }}</span></div>
    <div class="mt-2 flex gap-1.5">
      <button @click="uploadOpen = true" class="flex h-8 flex-1 items-center justify-center gap-1.5 rounded-lg bg-ink-800 text-cream-200 transition-colors hover:bg-ink-700" title="Thư viện ảnh đã tải lên"><StudioIcon name="imagePlus" size="h-4 w-4"/><span class="text-[10px] font-medium">Tải lên</span></button>
      <button @click="openProducts" class="flex h-8 flex-1 items-center justify-center gap-1.5 rounded-lg bg-ink-800 text-cream-200 transition-colors hover:bg-ink-700" title="Chọn ảnh từ sản phẩm"><StudioIcon name="shirt" size="h-4 w-4"/><span class="text-[10px] font-medium">Sản phẩm</span></button>
    </div>

    <!-- Thư viện ảnh nguồn (dùng chung) -->
    <SourceLibraryPicker v-model="uploadOpen" title="Thư viện ảnh nguồn" mode="multi" @add="store.addImagesToCanvas" />

    <!-- ══ Products popup ══ -->
    <div v-if="productOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" @click.self="productOpen=false">
      <div class="flex h-[82vh] w-full max-w-2xl flex-col rounded-2xl border border-ink-700 bg-ink-900 p-4 shadow-2xl" style="height: min(82vh, 680px)">
        <div class="mb-3 flex items-start justify-between">
          <div class="flex items-center gap-2.5">
            <div class="grid h-9 w-9 place-items-center rounded-xl bg-emerald-600/15 text-emerald-300"><StudioIcon name="shirt" size="h-4.5 w-4.5"/></div>
            <div>
              <p class="text-sm font-semibold text-cream-100">Chọn ảnh sản phẩm</p>
              <p class="text-[11px] text-cream-300/60">{{ sortedProducts.length }} sản phẩm</p>
            </div>
          </div>
          <button @click="productOpen=false" class="grid h-8 w-8 place-items-center rounded-full bg-ink-800 text-cream-300 transition-colors hover:bg-ink-700 hover:text-white" title="Đóng" :aria-label="'Đóng'"><StudioIcon name="x" size="h-4 w-4"/></button>
        </div>
        <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center">
          <div class="relative flex-1">
            <StudioIcon name="search" size="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-cream-300/50"/>
            <input v-model="pquery" placeholder="Tìm sản phẩm…" class="h-9 w-full rounded-xl border border-ink-700 bg-ink-800/60 pl-9 pr-3 text-xs text-cream-100 placeholder:text-cream-300/40 focus:border-brand-500 focus:outline-none">
          </div>
          <div class="relative">
            <StudioIcon name="sliders" size="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-cream-300/50"/>
            <select v-model="pSort" class="h-9 w-full appearance-none rounded-xl border border-ink-700 bg-ink-800/60 pl-9 pr-8 text-xs text-cream-100 focus:border-brand-500 focus:outline-none sm:w-40">
              <option v-for="o in productSortOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
            <StudioIcon name="chevronDown" size="pointer-events-none absolute right-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-cream-300/50"/>
          </div>
          <div class="flex h-9 items-center gap-2 rounded-xl border border-ink-700 bg-ink-800/60 px-3" title="Kích thước ô ảnh">
            <StudioIcon name="grid" size="h-4 w-4 shrink-0 text-cream-300/50"/>
            <input type="range" min="2" max="8" step="1" v-model.number="pCols" class="h-1.5 w-24 cursor-pointer accent-brand-500">
          </div>
        </div>
        <div class="scrollbar-hide -mr-1 grid min-h-0 flex-1 content-start gap-2.5 overflow-y-auto overscroll-contain pr-1" :style="{ gridTemplateColumns: 'repeat(' + pCols + ', minmax(0, 1fr))' }">
          <div v-for="p in sortedProducts" :key="p.id" class="group relative cursor-pointer overflow-hidden rounded-xl border transition-colors" :class="isSelProd(p) ? 'border-emerald-400 ring-2 ring-emerald-400/70' : 'border-ink-700 hover:border-ink-600'" :title="p.name" style="padding-bottom: 100%" @click="toggleProd(p)">
            <img :src="thumbUrl(p.url)" class="absolute inset-0 h-full w-full bg-ink-900 object-cover" loading="lazy" alt="" @error="onThumbError($event, p.url)">
            <span v-if="isSelProd(p)" class="pointer-events-none absolute inset-0 grid place-items-center bg-emerald-500/15"><span class="grid h-9 w-9 place-items-center rounded-full bg-emerald-500 text-white shadow-lg ring-2 ring-white/50"><StudioIcon name="check" size="h-5 w-5"/></span></span>
            <span class="absolute inset-x-0 bottom-0 truncate bg-black/60 px-1 py-0.5 text-[9px] text-cream-200">{{ p.name }}</span>
          </div>
          <p v-if="!sortedProducts.length" class="col-span-full py-10 text-center text-xs text-cream-300/50">{{ products.length ? 'Không có sản phẩm khớp tìm kiếm.' : 'Chưa có sản phẩm.' }}</p>
        </div>
        <div class="mt-3 flex shrink-0 items-center justify-between gap-2">
          <span class="text-[11px] text-cream-300/70">{{ selProds.length ? 'Đã chọn ' + selProds.length + ' sản phẩm' : 'Nhấn chọn 1 hoặc nhiều sản phẩm để thêm vào canvas' }}</span>
          <button @click="addSelProds" :disabled="!selProds.length" class="flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-40" title="Thêm sản phẩm đã chọn vào canvas (không xóa ảnh cũ)">
            <StudioIcon name="plus" size="h-4 w-4"/>Thêm vào canvas ({{ selProds.length }})
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
