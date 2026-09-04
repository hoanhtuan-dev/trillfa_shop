<script setup>
import { ref, computed, onMounted } from 'vue';
import { useStudioStore } from '../store.js';
const store = useStudioStore();
const productOpen = ref(false), uploadOpen = ref(false), products = ref([]), refs = ref([]);
const fileRef = ref(null);
const query = ref(''), sortKey = ref('newest'), pquery = ref(''), gridCols = ref(4);
onMounted(loadRefs);
const CSRF = () => (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

async function loadRefs() { try { const r = await fetch('/studio/ref-images?_=' + Date.now(), { headers: { Accept: 'application/json' } }); const d = await r.json(); refs.value = d.items || []; } catch(e){} }
async function loadProducts() { try { const r = await fetch('/studio/references?_=' + Date.now(), { headers: { Accept: 'application/json' } }); const d = await r.json(); products.value = d.items || []; } catch(e){} }
function openUpload() { uploadOpen.value = true; query.value = ''; sortKey.value = 'newest'; loadRefs(); }
function openProducts() { productOpen.value = true; pquery.value = ''; loadProducts(); }
async function onFile(e) {
  const files = Array.from(e.target.files || []);
  if (fileRef.value) fileRef.value.value = '';
  if (!files.length) return;
  for (const f of files) { await store.uploadRef(f, false); }
  await loadRefs();
  store.toast(files.length > 1 ? 'Đã tải ' + files.length + ' ảnh.' : 'Đã tải ảnh.');
}
async function delRef(it) { try { const r = await fetch('/studio/ref-images/' + it.name, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': CSRF(), Accept: 'application/json' } }); const d = await r.json(); if (!r.ok) { store.toast(d.message || 'Không xóa được.', 'error'); return; } loadRefs(); store.toast('Đã xóa ảnh.'); } catch(e){ store.toast('Lỗi xóa.', 'error'); } }
function pick(it) { store.setSource(it.url, it.name); uploadOpen.value = false; }

const fmtSize = (b) => { if (!b) return '—'; if (b < 1024) return b + ' B'; if (b < 1048576) return (b / 1024).toFixed(0) + ' KB'; return (b / 1048576).toFixed(1) + ' MB'; };
const fmtDate = (ts) => { if (!ts) return ''; const d = new Date(ts * 1000), now = new Date(); const diff = now - d, day = 86400000; if (diff < day) return 'Hôm nay'; if (diff < 2 * day) return 'Hôm qua'; if (diff < 30 * day) return Math.floor(diff / day) + ' ngày'; return d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: '2-digit' }); };
const isCurrent = (it) => !!store.editSource && store.editSource.url === it.url;

const sortOptions = [
  { value: 'newest', label: 'Mới nhất' },
  { value: 'oldest', label: 'Cũ nhất' },
  { value: 'name_asc', label: 'Tên A→Z' },
  { value: 'name_desc', label: 'Tên Z→A' },
  { value: 'size_desc', label: 'Dung lượng lớn → nhỏ' },
  { value: 'size_asc', label: 'Dung lượng nhỏ → lớn' },
  { value: 'area_desc', label: 'Độ phân giải cao → thấp' },
];
const sortedRefs = computed(() => {
  let list = refs.value.slice();
  const q = query.value.trim().toLowerCase();
  if (q) list = list.filter((it) => (it.name || '').toLowerCase().includes(q));
  const k = sortKey.value;
  list.sort((a, b) => {
    switch (k) {
      case 'newest': return (b.mtime || 0) - (a.mtime || 0);
      case 'oldest': return (a.mtime || 0) - (b.mtime || 0);
      case 'name_asc': return (a.name || '').localeCompare(b.name || '');
      case 'name_desc': return (b.name || '').localeCompare(a.name || '');
      case 'size_desc': return (b.size || 0) - (a.size || 0);
      case 'size_asc': return (a.size || 0) - (b.size || 0);
      case 'area_desc': return ((b.width || 0) * (b.height || 0)) - ((a.width || 0) * (a.height || 0));
      default: return 0;
    }
  });
  return list;
});
const filteredProducts = computed(() => {
  const q = pquery.value.trim().toLowerCase();
  if (!q) return products.value;
  return products.value.filter((p) => (p.name || '').toLowerCase().includes(q));
});
</script>
<template>
  <div class="card p-2">
    <div class="flex items-center justify-between">
      <span class="flex items-center gap-1 text-[11px] font-semibold text-brand-300"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>Nguồn</span>
      <button v-if="store.editSource" @click="store.editSource = null" class="grid h-7 w-7 place-items-center rounded-lg bg-ink-800 text-red-300 transition-colors hover:bg-red-600 hover:text-white" title="Bỏ ảnh nguồn"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
    </div>
    <div v-if="store.editSource" class="mt-2 flex items-center gap-2"><img :src="store.editSource.url" class="h-10 w-10 rounded-lg bg-ink-900 object-cover"><span class="truncate text-[10px] text-cream-200">{{ store.editSource.name }}</span></div>
    <div class="mt-2 flex gap-1.5">
      <button @click="openUpload" class="flex h-8 flex-1 items-center justify-center gap-1.5 rounded-lg bg-ink-800 text-cream-200 transition-colors hover:bg-ink-700" title="Thư viện ảnh đã tải lên"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg><span class="text-[10px] font-medium">Tải lên</span></button>
      <button @click="openProducts" class="flex h-8 flex-1 items-center justify-center gap-1.5 rounded-lg bg-ink-800 text-cream-200 transition-colors hover:bg-ink-700" title="Chọn ảnh từ sản phẩm"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg><span class="text-[10px] font-medium">Sản phẩm</span></button>
    </div>

    <!-- ══ Upload / library popup ══ -->
    <div v-if="uploadOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" @click.self="uploadOpen=false">
      <div class="flex max-h-[90vh] w-full max-w-3xl flex-col rounded-2xl border border-ink-700 bg-ink-900 p-4 shadow-2xl">
        <div class="mb-3 flex items-start justify-between">
          <div class="flex items-center gap-2.5">
            <div class="grid h-9 w-9 place-items-center rounded-xl bg-brand-600/15 text-brand-300"><svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg></div>
            <div>
              <p class="text-sm font-semibold text-cream-100">Thư viện ảnh nguồn</p>
              <p class="text-[11px] text-cream-300/60">{{ sortedRefs.length }} ảnh<template v-if="query"> · “{{ query }}”</template></p>
            </div>
          </div>
          <button @click="uploadOpen=false" class="grid h-8 w-8 place-items-center rounded-full bg-ink-800 text-cream-300 transition-colors hover:bg-ink-700 hover:text-white" title="Đóng"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
        </div>

        <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center">
          <div class="relative flex-1">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-cream-300/50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <input v-model="query" placeholder="Tìm theo tên ảnh…" class="h-9 w-full rounded-xl border border-ink-700 bg-ink-800/60 pl-9 pr-3 text-xs text-cream-100 placeholder:text-cream-300/40 focus:border-brand-500 focus:outline-none">
          </div>
          <div class="relative">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-cream-300/50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M6 12h12"/><path d="M10 18h4"/></svg>
            <select v-model="sortKey" class="h-9 w-full appearance-none rounded-xl border border-ink-700 bg-ink-800/60 pl-9 pr-8 text-xs text-cream-100 focus:border-brand-500 focus:outline-none sm:w-52">
              <option v-for="o in sortOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
            <svg class="pointer-events-none absolute right-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-cream-300/50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
          </div>
          <div class="flex h-9 items-center gap-2 rounded-xl border border-ink-700 bg-ink-800/60 px-3" title="Kích thước ô ảnh">
            <svg class="h-4 w-4 shrink-0 text-cream-300/50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            <input type="range" min="2" max="8" step="1" v-model.number="gridCols" class="h-1.5 w-24 cursor-pointer accent-brand-500">
          </div>
        </div>

        <label class="mb-3 flex h-11 shrink-0 cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-ink-600 bg-ink-800/40 text-xs font-medium text-cream-200 transition-colors hover:border-brand-500 hover:bg-brand-600/10 hover:text-brand-200">
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          Tải ảnh mới<span class="text-cream-300/50">(chọn nhiều file được)</span>
          <input ref="fileRef" type="file" accept="image/*" multiple @change="onFile" class="hidden">
        </label>

        <div class="scrollbar-hide -mr-1 grid min-h-0 flex-1 content-start gap-2.5 overflow-y-auto pr-1" :style="{ gridTemplateColumns: 'repeat(' + gridCols + ', minmax(0, 1fr))' }">
          <div v-for="it in sortedRefs" :key="it.name" class="group relative cursor-pointer overflow-hidden rounded-xl border transition-colors" :class="isCurrent(it) ? 'border-brand-400 ring-1 ring-brand-400/60' : 'border-ink-700 hover:border-ink-600'" :title="it.name" style="padding-bottom: 100%" @click="pick(it)">
            <img :src="it.url" class="absolute inset-0 h-full w-full object-cover" loading="lazy" alt="">
            <span v-if="it.used" class="absolute left-1.5 top-1.5 flex items-center gap-0.5 rounded-md bg-black/70 px-1.5 py-0.5 text-[9px] font-medium text-emerald-300"><svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>đang dùng</span>
            <span v-else-if="isCurrent(it)" class="absolute left-1.5 top-1.5 rounded-md bg-brand-600/90 px-1.5 py-0.5 text-[9px] font-medium text-white">đang chọn</span>
            <button v-if="!it.used" @click.stop="delRef(it)" class="absolute right-1.5 top-1.5 hidden h-6 w-6 place-items-center rounded-full bg-red-600/90 text-white transition-colors hover:bg-red-500 group-hover:grid" title="Xóa ảnh"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg></button>
            <div class="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/85 via-black/40 to-transparent px-1.5 pb-1 pt-5">
              <p class="truncate text-[10px] font-medium text-cream-100">{{ it.name }}</p>
              <p class="truncate text-[9px] text-cream-300/75">{{ it.width }}×{{ it.height }} · {{ fmtSize(it.size) }} · {{ fmtDate(it.mtime) }}</p>
            </div>
          </div>
          <div v-if="!sortedRefs.length" class="col-span-full flex flex-col items-center justify-center gap-2 py-10 text-center">
            <svg class="h-10 w-10 text-cream-300/25" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
            <p class="text-xs text-cream-300/50">{{ refs.length ? 'Không có ảnh khớp tìm kiếm.' : 'Chưa có ảnh nào — tải ảnh đầu tiên lên nhé.' }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- ══ Products popup ══ -->
    <div v-if="productOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" @click.self="productOpen=false">
      <div class="flex max-h-[90vh] w-full max-w-2xl flex-col rounded-2xl border border-ink-700 bg-ink-900 p-4 shadow-2xl">
        <div class="mb-3 flex items-start justify-between">
          <div class="flex items-center gap-2.5">
            <div class="grid h-9 w-9 place-items-center rounded-xl bg-emerald-600/15 text-emerald-300"><svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg></div>
            <div>
              <p class="text-sm font-semibold text-cream-100">Chọn ảnh sản phẩm</p>
              <p class="text-[11px] text-cream-300/60">{{ filteredProducts.length }} sản phẩm</p>
            </div>
          </div>
          <button @click="productOpen=false" class="grid h-8 w-8 place-items-center rounded-full bg-ink-800 text-cream-300 transition-colors hover:bg-ink-700 hover:text-white" title="Đóng"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
        </div>
        <div class="relative mb-3">
          <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-cream-300/50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
          <input v-model="pquery" placeholder="Tìm sản phẩm…" class="h-9 w-full rounded-xl border border-ink-700 bg-ink-800/60 pl-9 pr-3 text-xs text-cream-100 placeholder:text-cream-300/40 focus:border-brand-500 focus:outline-none">
        </div>
        <div class="scrollbar-hide -mr-1 grid min-h-0 flex-1 content-start grid-cols-3 gap-2.5 overflow-y-auto pr-1 sm:grid-cols-4">
          <button v-for="p in filteredProducts" :key="p.id" @click="store.pickFromProduct(p); productOpen=false" class="group relative overflow-hidden rounded-xl border border-ink-700 transition-colors hover:border-ink-600" style="padding-bottom: 100%"><img :src="p.url" class="absolute inset-0 h-full w-full bg-ink-900 object-cover" loading="lazy" alt=""><span class="absolute inset-x-0 bottom-0 truncate bg-black/60 px-1 py-0.5 text-[9px] text-cream-200">{{ p.name }}</span></button>
          <p v-if="!filteredProducts.length" class="col-span-full py-10 text-center text-xs text-cream-300/50">{{ products.length ? 'Không có sản phẩm khớp tìm kiếm.' : 'Chưa có sản phẩm.' }}</p>
        </div>
      </div>
    </div>
  </div>
</template>
