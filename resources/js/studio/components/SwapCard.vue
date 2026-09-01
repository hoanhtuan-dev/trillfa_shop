<script setup>
import { ref, onMounted, computed } from 'vue';
import { useStudioStore } from '../store.js';
const store = useStudioStore();
const open = ref(false);
const models = ref([]), poses = ref([]), bgs = ref([]), assets = ref([]);
const addType = ref('model'); const addName = ref(''); const addFile = ref(null); const addFileEl = ref(null);
const CSRF = () => (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
const swapBg = ref('');
onMounted(async () => {
  try { const r = await fetch('/studio/swap-models', { headers: { Accept: 'application/json' } }); const d = await r.json(); models.value = d.items || []; } catch(e){}
  try { const r = await fetch('/studio/swap-poses', { headers: { Accept: 'application/json' } }); const d = await r.json(); poses.value = d.items || []; } catch(e){}
  try { const r = await fetch('/studio/swap-backgrounds', { headers: { Accept: 'application/json' } }); const d = await r.json(); bgs.value = d.items || []; } catch(e){}
  try { const r = await fetch('/studio/assets', { headers: { Accept: 'application/json' } }); const d = await r.json(); assets.value = d.items || []; } catch(e){}
});
const faceList = computed(() => [...models.value, ...assets.value.filter(a => a.type === 'model').map(a => ({ id: String(a.id), name: a.name, image: a.path, custom: true }))]);
const poseList = computed(() => [...poses.value, ...assets.value.filter(a => a.type === 'pose').map(a => ({ id: String(a.id), name: a.name, image: a.path, custom: true }))]);
function toggle(list, id, e) { e.stopPropagation(); const i = list.indexOf(id); if (i >= 0) list.splice(i,1); else list.push(id); }
function selFace(id) { toggle(store.swapModelIds, id, arguments[1]); }
function selPose(id) { toggle(store.swapPoseIds, id, arguments[1]); }
const selFaceImgs = computed(() => faceList.value.filter(f => store.swapModelIds.includes(f.id)));
const selPoseImgs = computed(() => poseList.value.filter(p => store.swapPoseIds.includes(p.id)));
async function addAsset() {
  if (!addName.value || !addFile.value) { store.toast('Nhập tên + chọn file.', 'error'); return; }
  const fd = new FormData(); fd.append('type', addType.value); fd.append('name', addName.value); fd.append('image', addFile.value);
  const res = await fetch('/studio/assets', { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF(), Accept: 'application/json' }, body: fd });
  const d = await res.json().catch(() => ({})); if (!res.ok) { store.toast(d.message || 'Lỗi thêm.', 'error'); return; }
  addName.value = ''; addFile.value = null; if (addFileEl.value) addFileEl.value.value = '';
  const r = await fetch('/studio/assets', { headers: { Accept: 'application/json' } }); assets.value = (await r.json()).items || [];
  store.toast('Đã thêm ' + (addType.value === 'model' ? 'khuôn mặt' : 'dáng') + '.');
}
async function delAsset(a) { const r = await fetch('/studio/assets/' + a.id, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': CSRF(), Accept: 'application/json' } }); if (r.ok) { assets.value = assets.value.filter(x => x.id !== a.id); store.toast('Đã xóa.'); } }
</script>
<template>
  <div class="card p-5" style="border:1px solid var(--color-brand-500); background: linear-gradient(160deg, rgba(232,87,125,.12), rgba(74,122,144,.06));">
    <h2 class="mb-1 font-display text-base font-semibold text-brand-300">🪄 Thay Đổi Người Mẫu</h2>
    <div class="mt-2 space-y-1.5 text-xs">
      <div class="flex items-center gap-2"><span class="text-cream-300/60">Khuôn mặt:</span><span class="text-cream-100">{{ selFaceImgs.map(f=>f.name).join(', ') || '—' }}</span></div>
      <div class="flex items-center gap-2"><span class="text-cream-300/60">Dáng:</span><span class="text-cream-100">{{ selPoseImgs.map(p=>p.name).join(', ') || '—' }}</span></div>
      <div class="flex items-center gap-2"><span class="text-cream-300/60">Bối cảnh:</span><span class="text-cream-100">{{ swapBg ? (bgs.find(b=>b.value===swapBg)?.label || swapBg) : '—' }}</span></div>
    </div>
    <div class="mt-3 grid grid-cols-2 gap-1.5"><button @click="open=true" class="btn-outline btn-sm">🪄 Đổi người mẫu</button><button @click="store.runSwap({ background: swapBg })" :disabled="store.swapLoading || !store.swapModelIds.length" class="btn-brand btn-sm">{{ store.swapLoading ? 'Đang ghép…' : 'Áp dụng' }}</button></div>
    <div v-if="open" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/70 p-4" @click.self="open=false">
      <div class="scrollbar-hide max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-3xl border border-brand-500/30 bg-ink-900 p-5" @click.stop>
        <div class="mb-3 flex items-center justify-between"><span class="text-sm font-semibold text-brand-300">🪄 Chọn người mẫu / dáng / bối cảnh</span><button @click="open=false" class="grid h-8 w-8 place-items-center rounded-full bg-ink-700 text-cream-200">✕</button></div>
        <!-- Khuôn mặt -->
        <p class="mb-2 text-xs font-semibold text-cream-200">👩 Khuôn mặt</p>
        <div class="flex flex-wrap gap-2">
          <button v-for="f in faceList" :key="f.id" @click="toggle(store.swapModelIds, f.id, $event)" class="relative h-20 w-16 overflow-hidden rounded-xl border-2" :class="store.swapModelIds.includes(f.id) ? 'border-brand-500' : 'border-ink-700'"><img :src="f.image || '/images/placeholder.svg'" class="h-full w-full bg-ink-900 object-cover"><span class="absolute inset-x-0 bottom-0 bg-black/60 px-1 py-0.5 text-[9px] text-cream-200">{{ f.name }}</span><button v-if="f.custom" @click.stop="delAsset({id:f.id})" class="absolute right-1 top-1 grid h-5 w-5 place-items-center rounded-full bg-red-600/90 text-[9px] text-white">🗑</button></button>
        </div>
        <!-- Dáng -->
        <p class="mb-2 mt-4 text-xs font-semibold text-cream-200">🧍 Dáng</p>
        <div class="flex flex-wrap gap-2">
          <button v-for="p in poseList" :key="p.id" @click="toggle(store.swapPoseIds, p.id, $event)" class="relative h-20 w-16 overflow-hidden rounded-xl border-2" :class="store.swapPoseIds.includes(p.id) ? 'border-brand-500' : 'border-ink-700'"><img :src="p.image || '/images/placeholder.svg'" class="h-full w-full bg-ink-900 object-cover"><span class="absolute inset-x-0 bottom-0 bg-black/60 px-1 py-0.5 text-[9px] text-cream-200">{{ p.name }}</span><button v-if="p.custom" @click.stop="delAsset({id:p.id})" class="absolute right-1 top-1 grid h-5 w-5 place-items-center rounded-full bg-red-600/90 text-[9px] text-white">🗑</button></button>
        </div>
        <!-- Bối cảnh -->
        <p class="mb-2 mt-4 text-xs font-semibold text-cream-200">🖼 Bối cảnh</p>
        <div class="flex flex-wrap gap-1.5">
          <button v-for="b in bgs" :key="b.value" @click="swapBg = swapBg === b.value ? '' : b.value" class="rounded-full border px-3 py-1.5 text-xs" :class="swapBg === b.value ? 'border-brand-600 bg-brand-600 text-white' : 'border-ink-700 text-cream-200 hover:border-brand-400'">{{ b.label }}</button>
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
      </div>
    </div>
  </div>
</template>
