<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { useStudioStore } from '../store.js';
import BaseModal from './BaseModal.vue';
const store = useStudioStore();
const open = ref(false);
const models = ref([]), poses = ref([]), bgs = ref([]), assets = ref([]);
const addType = ref('model'); const addName = ref(''); const addFile = ref(null); const addFileEl = ref(null);
const CSRF = () => (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
const swapBg = ref('');
const swapTone = ref('none'); // Hiệu ứng tông màu mặc định: không áp dụng
const changeFace = ref(false); // MẶC ĐỊNH giữ nguyên khuôn mặt gốc; bật = đổi khuôn mặt theo người mẫu

const toneOptions = [
  { v: 'auto', label: '🎨 Tự động (theo bối cảnh)' },
  { v: 'warm', label: '☀️ Ấm' },
  { v: 'cool', label: '❄️ Lạnh' },
  { v: 'cinematic', label: '🎬 Điện ảnh' },
  { v: 'film', label: '🎞️ Film' },
  { v: 'dramatic', label: '🌑 Kịch tính' },
  { v: 'mono', label: '⚪ Trắng đen' },
  { v: 'none', label: '🚫 Không' },
];
// Persist swap settings across sessions (like upscale memory).
const SWAP_KEY = 'trillfa.swap';
function loadSwapMemory() {
  try {
    const m = JSON.parse(localStorage.getItem(SWAP_KEY) || '{}');
    if (m.tone) swapTone.value = m.tone;
    if (typeof m.bg === 'string') swapBg.value = m.bg;
    if (typeof m.changeFace === 'boolean') changeFace.value = m.changeFace;
  } catch (e) {}
}
function saveSwapMemory() {
  try {
    localStorage.setItem(SWAP_KEY, JSON.stringify({ tone: swapTone.value, bg: swapBg.value, changeFace: changeFace.value }));
  } catch (e) {}
}
watch([swapBg, swapTone, changeFace], saveSwapMemory);
onMounted(async () => {
  loadSwapMemory();
  try { const r = await fetch('/studio/swap-models', { headers: { Accept: 'application/json' } }); const d = await r.json(); models.value = d.items || []; } catch(e){}
  try { const r = await fetch('/studio/swap-poses', { headers: { Accept: 'application/json' } }); const d = await r.json(); poses.value = d.items || []; } catch(e){}
  try { const r = await fetch('/studio/swap-backgrounds', { headers: { Accept: 'application/json' } }); const d = await r.json(); bgs.value = d.items || []; } catch(e){}
  try { const r = await fetch('/studio/assets', { headers: { Accept: 'application/json' } }); const d = await r.json(); assets.value = d.items || []; } catch(e){}
});
const faceList = computed(() => [...models.value, ...assets.value.filter(a => a.type === 'model').map(a => ({ id: String(a.id), name: a.name, image: a.path, custom: true }))]);
const poseList = computed(() => [...poses.value, ...assets.value.filter(a => a.type === 'pose').map(a => ({ id: String(a.id), name: a.name, image: a.path, custom: true }))]);
// Face: single-select (one reference face). Poses: multi-select (apply creates one result per pose).
function selectOne(list, id, e) { e.stopPropagation(); if (list.length === 1 && list[0] === id) { list.splice(0, 1); } else { list.splice(0, list.length); list.push(id); } }
function toggle(list, id, e) { e.stopPropagation(); const i = list.indexOf(id); if (i >= 0) list.splice(i,1); else list.push(id); }
const selFaceImgs = computed(() => faceList.value.filter(f => store.swapModelIds.includes(f.id)));
const selPoseImgs = computed(() => poseList.value.filter(p => store.swapPoseIds.includes(p.id)));
const canApply = computed(() => !store.swapLoading && store.swapPoseIds.length > 0 && (changeFace.value ? store.swapModelIds.length > 0 : true));
const hasSelection = computed(() => store.swapModelIds.length > 0 || store.swapPoseIds.length > 0 || !!swapBg.value);
function resetSelections() {
  store.swapModelIds = [];
  store.swapPoseIds = [];
  swapBg.value = '';
  swapTone.value = 'none';
  store.toast('Đã bỏ lựa chọn khuôn mặt / dáng / bối cảnh.');
}
async function addAsset() {
  if (!addName.value || !addFile.value) { store.toast('Nhập tên + chọn file.', 'error'); return; }
  const fd = new FormData(); fd.append('type', addType.value); fd.append('name', addName.value); fd.append('image', addFile.value);
  const res = await fetch('/studio/assets', { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF(), Accept: 'application/json' }, body: fd });
  const d = await res.json().catch(() => ({})); if (!res.ok) { store.toast(d.message || 'Lỗi thêm.', 'error'); return; }
  addName.value = ''; addFile.value = null; if (addFileEl.value) addFileEl.value.value = '';
  const r = await fetch('/studio/assets', { headers: { Accept: 'application/json' } }); assets.value = (await r.json()).items || [];
  store.toast('Đã thêm ' + (addType.value === 'model' ? 'khuôn mặt' : 'dáng') + '.');
}
async function delAsset(a) { const r = await fetch('/studio/assets/' + a.id, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': CSRF(), Accept: 'application/json' } }); if (r.ok) { assets.value = assets.value.filter(x => x.id !== a.id); const id = String(a.id); if (store.swapModelIds.includes(id)) { store.swapModelIds = store.swapModelIds.filter(x => x !== id); } if (store.swapPoseIds.includes(id)) { store.swapPoseIds = store.swapPoseIds.filter(x => x !== id); } store.toast('Đã xóa.'); } }
</script>
<template>
  <div class="card p-5" style="border:1px solid var(--color-brand-500); background: linear-gradient(160deg, rgba(232,87,125,.12), rgba(74,122,144,.06));">
    <h2 class="mb-1 font-display text-base font-semibold text-brand-300">🪄 Thay Đổi Người Mẫu</h2>
    <div v-if="!changeFace || store.swapModelIds.length || store.swapPoseIds.length || swapBg" class="scrollbar-hide mt-2 max-h-40 space-y-1.5 overflow-y-auto text-xs">
      <div v-if="!changeFace" class="flex items-center gap-2"><span class="shrink-0 text-cream-300/60">Khuôn mặt:</span><span class="grid h-10 w-8 shrink-0 place-items-center rounded bg-ink-800 text-base">👩</span><span class="truncate text-cream-100">Giữ nguyên khuôn mặt gốc</span></div>
      <div v-if="changeFace && store.swapModelIds.length" class="flex items-center gap-2"><span class="shrink-0 text-cream-300/60">Khuôn mặt:</span><img v-if="selFaceImgs[0]?.image" :src="selFaceImgs[0].image" class="h-10 w-8 shrink-0 rounded bg-ink-900 object-cover"><span v-else class="grid h-10 w-8 shrink-0 place-items-center rounded bg-ink-800 text-base">👩</span><span class="truncate text-cream-100">{{ selFaceImgs.map(f=>f.name).join(', ') }}</span></div>
      <div v-if="store.swapPoseIds.length" class="flex items-center gap-2"><span class="shrink-0 text-cream-300/60">Dáng:</span><img v-if="selPoseImgs[0]?.image" :src="selPoseImgs[0].image" class="h-10 w-8 shrink-0 rounded bg-ink-900 object-cover"><span v-else class="grid h-10 w-8 shrink-0 place-items-center rounded bg-ink-800 text-base">🧍</span><span class="truncate text-cream-100">{{ selPoseImgs.map(p=>p.name).join(', ') }}</span></div>
      <div v-if="swapBg" class="flex items-center gap-2"><span class="shrink-0 text-cream-300/60">Bối cảnh:</span><span class="truncate text-cream-100">{{ bgs.find(b=>b.value===swapBg)?.label || swapBg }}</span></div>
    </div>
    <div class="mt-3 grid grid-cols-2 gap-1.5"><button @click="open=true" class="btn-outline btn-sm">🪄 Đổi người mẫu</button><button v-if="store.swapLoading || store.swapProcessing" @click="store.cancelSwap()" class="btn-brand btn-sm" title="Bấm để hủy">{{ store.swapLoading ? ('⏳ ' + store.swapDone + '/' + store.swapTotal + ' · Hủy') : '⏳ Đang xử lý nền… · Hủy' }}</button><button v-else @click="store.runSwap({ background: swapBg, tone: swapTone, change_face: changeFace })" :disabled="!canApply" class="btn-brand btn-sm">Áp dụng</button></div>
    <div v-if="hasSelection && !store.swapLoading && !store.swapProcessing" class="mt-1.5 flex justify-end"><button type="button" @click="resetSelections" class="text-[11px] text-cream-300/60 underline-offset-2 transition hover:text-red-300 hover:underline" title="Bỏ lựa chọn khuôn mặt / dáng / bối cảnh đã chọn">↺ Bỏ chọn lựa</button></div>
    <p v-if="!canApply" class="mt-1 text-[10px] text-cream-300/60">{{ changeFace ? 'Chọn 1 khuôn mặt + ít nhất 1 dáng để Áp dụng.' : 'Chọn ít nhất 1 dáng để Áp dụng (khuôn mặt gốc được giữ nguyên).' }}</p>
    <BaseModal v-model="open" title="🪄 Chọn người mẫu / dáng / bối cảnh" wide>
        <div class="mb-4 flex items-center gap-3 rounded-2xl border p-3" :class="changeFace ? 'border-brand-500 bg-brand-600/20' : 'border-ink-700 bg-ink-800/50'">
          <div class="min-w-0 flex-1">
            <p class="text-xs font-semibold text-cream-200">👩 Đổi khuôn mặt theo người mẫu</p>
            <p class="mt-0.5 text-[10px] leading-snug text-cream-300/60">{{ changeFace ? 'Khuôn mặt sẽ được thay bằng khuôn mặt người mẫu đã chọn.' : 'Mặc định: giữ nguyên khuôn mặt gốc (bảo toàn đặc điểm khuôn mặt).' }}</p>
          </div>
          <button id="swap-change-face-toggle" type="button" @click="changeFace = !changeFace" class="relative h-6 w-11 shrink-0 rounded-full transition-colors" :class="changeFace ? 'bg-brand-500' : 'bg-ink-600'" :title="changeFace ? 'Bỏ đổi khuôn mặt' : 'Bật đổi khuôn mặt'"><span class="absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-all" :class="changeFace ? 'left-[22px]' : 'left-0.5'"></span></button>
        </div>
        <!-- Khuôn mặt -->
        <template v-if="changeFace">
        <p class="mb-2 text-xs font-semibold text-cream-200">👩 Khuôn mặt</p>
        <div class="flex flex-wrap gap-2">
          <button v-for="f in faceList" :key="f.id" @click="selectOne(store.swapModelIds, f.id, $event)" class="relative h-20 w-16 overflow-hidden rounded-xl border-2" :class="store.swapModelIds.includes(f.id) ? 'border-brand-500' : 'border-ink-700'"><img v-if="f.image" :src="f.image" class="h-full w-full bg-ink-900 object-cover"><span v-else class="grid h-full w-full place-items-center bg-ink-800 text-xl">👩</span><span class="absolute inset-x-0 bottom-0 bg-black/60 px-1 py-0.5 text-[9px] text-cream-200">{{ f.name }}</span><button v-if="f.custom" @click.stop="delAsset({id:f.id})" class="absolute right-1 top-1 grid h-5 w-5 place-items-center rounded-full bg-red-600/90 text-[9px] text-white">🗑</button></button>
        </div>
        </template>
        <div v-else class="mb-px rounded-2xl border border-dashed border-ink-600 bg-ink-800/60 p-3 text-xs leading-relaxed text-cream-300/70">👩 Đang <b class="text-cream-100">giữ nguyên khuôn mặt gốc</b> — chỉ đổi dáng, hậu cảnh và tông màu. Bật nút phía trên để chọn khuôn mặt người mẫu khác.</div>
        <!-- Dáng -->
        <p class="mb-2 mt-4 text-xs font-semibold text-cream-200">🧍 Dáng</p>
        <div class="flex flex-wrap gap-2">
          <button v-for="p in poseList" :key="p.id" @click="toggle(store.swapPoseIds, p.id, $event)" class="relative h-20 w-16 overflow-hidden rounded-xl border-2" :class="store.swapPoseIds.includes(p.id) ? 'border-brand-500' : 'border-ink-700'"><img v-if="p.image" :src="p.image" class="h-full w-full bg-ink-900 object-cover"><span v-else class="grid h-full w-full place-items-center bg-ink-800 text-xl">🧍</span><span class="absolute inset-x-0 bottom-0 bg-black/60 px-1 py-0.5 text-[9px] text-cream-200">{{ p.name }}</span><button v-if="p.custom" @click.stop="delAsset({id:p.id})" class="absolute right-1 top-1 grid h-5 w-5 place-items-center rounded-full bg-red-600/90 text-[9px] text-white">🗑</button></button>
        </div>
        <!-- Bối cảnh -->
        <p class="mb-2 mt-4 text-xs font-semibold text-cream-200">🖼 Bối cảnh</p>
        <div class="flex flex-wrap gap-1.5">
          <button v-for="b in bgs" :key="b.value" @click="swapBg = swapBg === b.value ? '' : b.value" class="rounded-full border px-3 py-1.5 text-xs" :class="swapBg === b.value ? 'border-brand-600 bg-brand-600 text-white' : 'border-ink-700 text-cream-200 hover:border-brand-400'">{{ b.label }}</button>
        </div>
        <!-- Hiệu ứng tông màu -->
        <div class="mt-4">
          <p class="mb-1 text-xs font-semibold text-cream-200">🎨 Hiệu ứng tông màu <span class="text-cream-300/60">(hợp với bối cảnh)</span></p>
          <div class="flex flex-wrap gap-1.5">
            <button v-for="t in toneOptions" :key="t.v" @click="swapTone = t.v" class="rounded-full border px-3 py-1.5 text-xs" :class="swapTone === t.v ? 'border-brand-600 bg-brand-600 text-white' : 'border-ink-700 text-cream-200 hover:border-brand-400'">{{ t.label }}</button>
          </div>
        </div>
        <!-- Thêm / xóa -->
        <div class="mt-5 rounded-2xl border border-dashed border-cream-300/40 p-3">
          <p class="mb-2 text-xs font-semibold text-cream-200">➕ Thêm {{ addType === 'model' ? 'khuôn mặt' : 'dáng' }} (bộ sưu tập riêng)</p>
          <div class="flex flex-wrap items-center gap-2">
            <select v-model="addType" class="input !py-1.5 !w-32"><option value="model">Khuôn mặt</option><option value="pose">Dáng</option></select>
            <input v-model="addName" class="input !py-1.5 !w-40" placeholder="Tên">
            <input ref="addFileEl" type="file" accept="image/*" @change="e => addFile = e.target.files?.[0]" class="input !py-1.5 !w-48">
            <button @click="addAsset" class="btn-brand btn-sm">Thêm</button>
          </div>
        </div>
        <button @click="open=false" class="btn-brand mt-4 w-full">✅ Chọn xong</button>
    </BaseModal>
  </div>
</template>
