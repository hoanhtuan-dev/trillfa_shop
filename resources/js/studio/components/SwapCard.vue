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
const swapBuild = ref(6); // Tỷ lệ dáng mặc định: 6 (cân đối) — 0 lùn-nở -> 10 cao-thon chuẩn người mẫu
const swapTone = ref('none'); // Hiệu ứng tông màu mặc định: không áp dụng
const swapToneLevel = ref(5); // Mức độ ảnh hưởng mặc định: 5 — 0-10
const swapFabric = ref(0); // Vân vải hậu kỳ: 0 = tắt (mặc định, bảo vệ khuôn mặt) — 1-10 cường độ
const swapFacePass = ref(false); // 2-pass ghép khuôn mặt theo ảnh (mặc định TẮT — với qwen-edit-max pass 2 có thể làm giảm độ chính xác dáng)
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
    if (m.build != null) swapBuild.value = Number(m.build);
    if (m.tone) swapTone.value = m.tone;
    if (m.toneLevel != null) swapToneLevel.value = Number(m.toneLevel);
    if (m.fabric != null) swapFabric.value = Number(m.fabric);
    if (typeof m.bg === 'string') swapBg.value = m.bg;
    if (m.facePass != null) swapFacePass.value = !!m.facePass;
  } catch (e) {}
}
function saveSwapMemory() {
  try {
    localStorage.setItem(SWAP_KEY, JSON.stringify({ build: swapBuild.value, tone: swapTone.value, toneLevel: swapToneLevel.value, fabric: swapFabric.value, bg: swapBg.value, facePass: swapFacePass.value }));
  } catch (e) {}
}
watch([swapBg, swapBuild, swapTone, swapToneLevel, swapFabric, swapFacePass], saveSwapMemory);
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
const canApply = computed(() => !store.swapLoading && store.swapModelIds.length > 0 && store.swapPoseIds.length > 0);
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
    <div v-if="store.swapModelIds.length || store.swapPoseIds.length || swapBg" class="scrollbar-hide mt-2 max-h-40 space-y-1.5 overflow-y-auto text-xs">
      <div v-if="store.swapModelIds.length" class="flex items-center gap-2"><span class="shrink-0 text-cream-300/60">Khuôn mặt:</span><img v-if="selFaceImgs[0]?.image" :src="selFaceImgs[0].image" class="h-10 w-8 shrink-0 rounded bg-ink-900 object-cover"><span v-else class="grid h-10 w-8 shrink-0 place-items-center rounded bg-ink-800 text-base">👩</span><span class="truncate text-cream-100">{{ selFaceImgs.map(f=>f.name).join(', ') }}</span></div>
      <div v-if="store.swapPoseIds.length" class="flex items-center gap-2"><span class="shrink-0 text-cream-300/60">Dáng:</span><img v-if="selPoseImgs[0]?.image" :src="selPoseImgs[0].image" class="h-10 w-8 shrink-0 rounded bg-ink-900 object-cover"><span v-else class="grid h-10 w-8 shrink-0 place-items-center rounded bg-ink-800 text-base">🧍</span><span class="truncate text-cream-100">{{ selPoseImgs.map(p=>p.name).join(', ') }}</span></div>
      <div v-if="swapBg" class="flex items-center gap-2"><span class="shrink-0 text-cream-300/60">Bối cảnh:</span><span class="truncate text-cream-100">{{ bgs.find(b=>b.value===swapBg)?.label || swapBg }}</span></div>
      <div v-if="swapFabric > 0" class="flex items-center gap-2"><span class="shrink-0 text-cream-300/60">Vân vải hậu kỳ:</span><span class="truncate text-cream-100">{{ swapFabric }}</span></div>
    </div>
    <div class="mt-3 grid grid-cols-2 gap-1.5"><button @click="open=true" class="btn-outline btn-sm">🪄 Đổi người mẫu</button><button v-if="store.swapLoading || store.swapProcessing" @click="store.cancelSwap()" class="btn-brand btn-sm" title="Bấm để hủy">{{ store.swapLoading ? ('⏳ ' + store.swapDone + '/' + store.swapTotal + ' · Hủy') : '⏳ Đang xử lý nền… · Hủy' }}</button><button v-else @click="store.runSwap({ background: swapBg, build: swapBuild, tone: swapTone, tone_level: swapToneLevel, fabric_detail: swapFabric, face_pass: swapFacePass })" :disabled="!canApply" class="btn-brand btn-sm">Áp dụng</button></div>
    <p v-if="!store.swapModelIds.length || !store.swapPoseIds.length" class="mt-1 text-[10px] text-cream-300/60">Chọn 1 khuôn mặt + ít nhất 1 dáng để Áp dụng.</p>
    <BaseModal v-model="open" title="🪄 Chọn người mẫu / dáng / bối cảnh" wide>
        <!-- Khuôn mặt -->
        <p class="mb-2 text-xs font-semibold text-cream-200">👩 Khuôn mặt</p>
        <div class="flex flex-wrap gap-2">
          <button v-for="f in faceList" :key="f.id" @click="selectOne(store.swapModelIds, f.id, $event)" class="relative h-20 w-16 overflow-hidden rounded-xl border-2" :class="store.swapModelIds.includes(f.id) ? 'border-brand-500' : 'border-ink-700'"><img v-if="f.image" :src="f.image" class="h-full w-full bg-ink-900 object-cover"><span v-else class="grid h-full w-full place-items-center bg-ink-800 text-xl">👩</span><span class="absolute inset-x-0 bottom-0 bg-black/60 px-1 py-0.5 text-[9px] text-cream-200">{{ f.name }}</span><button v-if="f.custom" @click.stop="delAsset({id:f.id})" class="absolute right-1 top-1 grid h-5 w-5 place-items-center rounded-full bg-red-600/90 text-[9px] text-white">🗑</button></button>
        </div>
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
        <!-- Tỷ lệ dáng (chiều cao / thân hình) -->
        <div class="mt-4">
          <p class="mb-1 text-xs font-semibold text-cream-200">📏 Tỷ lệ dáng <span class="text-cream-300/60">(0 lùn-nở → 10 cao-thon chuẩn mẫu)</span></p>
          <div class="flex items-center gap-3">
            <input type="range" min="0" max="10" v-model.number="swapBuild" class="h-1.5 flex-1 cursor-pointer appearance-none rounded-full bg-ink-700 accent-brand-600">
            <span class="w-6 text-right text-xs text-cream-200">{{ swapBuild }}</span>
          </div>
          <p class="mt-1 text-[10px] text-cream-300/50">▲ 8-10: cao, thon, chân dài chuẩn người mẫu · 5-7: cân đối · 0-4: thấp/nở hơn (chống dáng bị ép lùn).</p>
        </div>
        <!-- Hiệu ứng tông màu -->
        <div class="mt-4">
          <p class="mb-1 text-xs font-semibold text-cream-200">🎨 Hiệu ứng tông màu <span class="text-cream-300/60">(hợp với bối cảnh)</span></p>
          <div class="flex flex-wrap gap-1.5">
            <button v-for="t in toneOptions" :key="t.v" @click="swapTone = t.v" class="rounded-full border px-3 py-1.5 text-xs" :class="swapTone === t.v ? 'border-brand-600 bg-brand-600 text-white' : 'border-ink-700 text-cream-200 hover:border-brand-400'">{{ t.label }}</button>
          </div>
          <div class="mt-2 flex items-center gap-3">
            <span class="shrink-0 text-[10px] text-cream-300/60">Mức độ</span>
            <input type="range" min="0" max="10" v-model.number="swapToneLevel" class="h-1.5 flex-1 cursor-pointer appearance-none rounded-full bg-ink-700 accent-brand-600">
            <span class="w-6 text-right text-xs text-cream-200">{{ swapToneLevel }}</span>
          </div>
          <p class="mt-1 text-[10px] text-cream-300/50">0 = không áp dụng · 5 = vừa phải (mặc định) · 10 = đậm nhất. Film/Cinematic nhẹ để tránh cháy sáng.</p>
        </div>
        <!-- Vân vải hậu kỳ (mặc định TẮT — bảo vệ khuôn mặt) -->
        <div class="mt-4">
          <p class="mb-1 text-xs font-semibold text-cream-200">🪡 Vân vải hậu kỳ <span class="text-cream-300/60">(0 tắt · thêm vân sợi lên ảnh sau khi tạo — chỉ nên bật khi cần, có thể ảnh hưởng vùng da)</span></p>
          <div class="flex items-center gap-3">
            <input type="range" min="0" max="10" v-model.number="swapFabric" class="h-1.5 flex-1 cursor-pointer appearance-none rounded-full bg-ink-700 accent-brand-600">
            <span class="w-6 text-right text-xs text-cream-200">{{ swapFabric === 0 ? 'Tắt' : swapFabric }}</span>
          </div>
          <p class="mt-1 text-[10px] text-cream-300/50">Mặc định 0 (tắt) để khuôn mặt luôn sạch. Bật 1-10 nếu muốn vải rõ vân hơn; không áp dụng lên vùng da.</p>
        </div>
        <!-- Ghép khuôn mặt theo ảnh (2-pass) -->
        <div class="mt-4 flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-3 py-2.5">
          <div>
            <p class="text-xs font-semibold text-cream-200">👤 Ghép khuôn mặt theo ảnh</p>
            <p class="text-[10px] text-cream-300/50">Thử bước 2 thay mặt theo ảnh đã chọn. Mặc định TẮT vì qwen-edit-max có thể làm giảm độ chính xác dáng/trang phục. Để mặt chuẩn nhất, hãy mở model qwen-image-3.0-pro trong QwenCloud (bỏ chế độ "free tier only").</p>
          </div>
          <label class="relative inline-flex cursor-pointer items-center">
            <input type="checkbox" v-model="swapFacePass" class="peer sr-only">
            <span class="h-5 w-9 rounded-full bg-ink-700 peer-checked:bg-brand-600 after:absolute after:left-0.5 after:top-0.5 after:h-4 after:w-4 after:rounded-full after:bg-white after:transition-all peer-checked:after:translate-x-4"></span>
          </label>
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
