<script setup>
import { ref, onMounted } from 'vue';
import { useStudioStore } from '../store.js';
const store = useStudioStore();
const productOpen = ref(false), uploadOpen = ref(false), products = ref([]), refs = ref([]);
const fileRef = ref(null);
onMounted(loadRefs);
const CSRF = () => (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
async function loadRefs() { try { const r = await fetch('/studio/ref-images', { headers: { Accept: 'application/json' } }); const d = await r.json(); refs.value = d.items || []; } catch(e){} }
async function loadProducts() { try { const r = await fetch('/studio/references', { headers: { Accept: 'application/json' } }); const d = await r.json(); products.value = d.items || []; } catch(e){} }
function openUpload() { uploadOpen.value = true; loadRefs(); }
function onFile(e) { const f = e.target.files?.[0]; if (f) { store.uploadRef(f).then(() => loadRefs()); } if (fileRef.value) fileRef.value.value = ''; }
async function delRef(it) { try { const r = await fetch('/studio/ref-images/' + it.name, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': CSRF(), Accept: 'application/json' } }); const d = await r.json(); if (!r.ok) { store.toast(d.message || 'Không xóa được.', 'error'); return; } loadRefs(); store.toast('Đã xóa ảnh.'); } catch(e){ store.toast('Lỗi xóa.', 'error'); } }
function pick(it) { store.setSource(it.url, it.name); uploadOpen.value = false; }
</script>
<template>
  <div class="card p-3">
    <div class="flex items-center justify-between"><h2 class="text-sm font-semibold text-brand-300">🖼 Nguồn ảnh</h2><button v-if="store.editSource" @click="store.editSource = null" class="text-[10px] text-red-300">✕</button></div>
    <div v-if="store.editSource" class="mt-2 flex items-center gap-2"><img :src="store.editSource.url" class="h-12 w-12 rounded-lg bg-ink-900 object-cover"><span class="truncate text-xs text-cream-200">{{ store.editSource.name }}</span></div>
    <div class="mt-2 grid grid-cols-2 gap-1.5">
      <button @click="openUpload" class="btn-outline btn-sm">⬆ Tải ảnh</button>
      <button @click="loadProducts(); productOpen = true" class="btn-outline btn-sm">🛍 Từ sản phẩm</button>
    </div>
    <input ref="fileRef" type="file" accept="image/*" @change="onFile" class="hidden">
    <!-- upload popup -->
    <div v-if="uploadOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" @click.self="uploadOpen=false">
      <div class="w-full max-w-2xl rounded-2xl border border-ink-700 bg-ink-900 p-4">
        <div class="mb-2 flex items-center justify-between"><p class="text-sm font-semibold text-cream-100">⬆ Ảnh đã tải lên</p><button @click="uploadOpen=false" class="grid h-8 w-8 place-items-center rounded-full bg-ink-700 text-cream-200">✕</button></div>
        <button @click="fileRef.click()" class="btn-brand btn-sm mb-3 w-full">➕ Tải ảnh mới</button>
        <div class="scrollbar-hide grid max-h-[55vh] grid-cols-3 gap-2 overflow-y-auto sm:grid-cols-4">
          <div v-for="it in refs" :key="it.name" class="group relative h-24 overflow-hidden rounded-xl border border-ink-700">
            <button @click="pick(it)" class="absolute inset-0"><img :src="it.url" class="h-full w-full bg-ink-900 object-cover"></button>
            <button v-if="!it.used" @click="delRef(it)" class="absolute right-1 top-1 hidden h-6 w-6 place-items-center rounded-full bg-red-600/90 text-[10px] text-white group-hover:grid" title="Xóa (không dùng)">🗑</button>
            <span v-if="it.used" class="absolute left-1 top-1 rounded bg-black/70 px-1 text-[8px] text-cream-200">đang dùng</span>
          </div>
          <p v-if="!refs.length" class="col-span-4 text-xs text-cream-300/50">Chưa có ảnh tải lên.</p>
        </div>
      </div>
    </div>
    <!-- products popup -->
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
