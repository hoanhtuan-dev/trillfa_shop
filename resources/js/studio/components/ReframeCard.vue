<script setup>
import { ref } from 'vue';
import { useStudioStore } from '../store.js';
const store = useStudioStore();
const ratios = ['1:1','3:4','4:5','9:16','16:9','2:3'];
function cropMetrics() {
  const img = store.cvImg, cont = store.canvasZoom;
  if (!img || !cont) return null;
  const ir = img.getBoundingClientRect(), cr = cont.getBoundingClientRect();
  const iw = img.naturalWidth || 1, ih = img.naturalHeight || 1;
  const ia = iw / ih, box = ir.width / ir.height;
  let vw, vh;
  if (ia > box) { vw = ir.width; vh = ir.width / ia; } else { vh = ir.height; vw = ir.height * ia; }
  return { vw, vh, vx: ir.left - cr.left + (ir.width - vw)/2, vy: ir.top - cr.top + (ir.height - vh)/2, crW: cr.width, crH: cr.height, ia };
}
function toggleCrop() {
  store.cropMode = !store.cropMode;
  if (store.cropMode) {
    const m = cropMetrics();
    const ia = m ? m.vw / m.vh : 0.8;
    const r = Number(store.reframeRatio.split(':')[0]) / Number(store.reframeRatio.split(':')[1]);
    const ratioFrac = r / ia;
    let h = 0.7; if (h * ratioFrac > 1) { h = 1 / ratioFrac; }
    const w = h * ratioFrac;
    store.cropBox = { x: (1 - w)/2, y: (1 - h)/2, w, h };
  }
}
function cropStart(e, key) {
  e.preventDefault(); e.stopPropagation();
  store._cropDrag = { key, sx: e.clientX, sy: e.clientY, box: { ...store.cropBox } };
  const move = (ev) => cropMove(ev);
  const up = () => { store._cropDrag = null; window.removeEventListener('mousemove', move); window.removeEventListener('mouseup', up); window.removeEventListener('touchmove', move); window.removeEventListener('touchend', up); };
  window.addEventListener('mousemove', move); window.addEventListener('mouseup', up);
  window.addEventListener('touchmove', move, { passive: false }); window.addEventListener('touchend', up);
}
function cropMove(e) {
  const d = store._cropDrag; if (!d || !store.cropMode) return;
  const m = cropMetrics(); if (!m) return;
  const bx = (e.clientX - d.sx) / m.vw, by = (e.clientY - d.sy) / m.vh;
  const b = { ...d.box };
  if (d.key === 'move') {
    b.x = Math.max(0, Math.min(1 - b.w, b.x + bx)); b.y = Math.max(0, Math.min(1 - b.h, b.y + by));
  } else {
    const r = Number(store.reframeRatio.split(':')[0]) / Number(store.reframeRatio.split(':')[1]);
    const ratioFrac = r / m.ia;
    const step = Math.max(bx, by);
    let nh = Math.max(0.05, b.h + step); let nw = nh * ratioFrac;
    if (nw > 1) { nw = 1; nh = nw / ratioFrac; }
    if (b.y + nh > 1) { nh = Math.max(0.05, 1 - b.y); nw = nh * ratioFrac; }
    if (b.x + nw > 1) { nw = Math.max(0.05, 1 - b.x); nh = nw / ratioFrac; }
    b.w = Math.max(0.05, Math.min(1, nw)); b.h = Math.max(0.05, Math.min(1, nh));
  }
  store.cropBox = b;
}
async function confirmCrop() {
  if (!store.cropMode) return;
  const img = store.cvImg; if (!img) return;
  const iw = img.naturalWidth, ih = img.naturalHeight;
  const x = Math.round(store.cropBox.x * iw), y = Math.round(store.cropBox.y * ih), w = Math.max(1, Math.round(store.cropBox.w * iw)), h = Math.max(1, Math.round(store.cropBox.h * ih));
  store.reframing = true;
  try { const d = await store.api('/studio/reframe', { image: store.upscaleSrc, ratio: store.reframeRatio, x, y, w, h }); store.addGen({ id:d.generation_id, type:'image', status:'completed', model:'reframe', provider:'reframe', media_url:d.media_url, error:null, credits_cost:0, created_at:'Vừa cắt' }); store.cropMode = false; store.toast('Đã cắt vùng đã chọn.'); }
  catch(err){ store.toast(err.message || 'Lỗi cắt.', 'error'); }
  finally { store.reframing = false; }
}
async function runReframeCenter() {
  if (!store.upscaleSrc || store.reframing) return;
  store.reframing = true;
  try { const d = await store.api('/studio/reframe', { image: store.upscaleSrc, ratio: store.reframeRatio }); store.addGen({ id:d.generation_id, type:'image', status:'completed', model:'reframe', provider:'reframe', media_url:d.media_url, error:null, credits_cost:0, created_at:'Vừa cắt' }); store.toast('Đã cắt giữa ' + store.reframeRatio + '.'); }
  catch(e){ store.toast(e.message || 'Lỗi cắt.', 'error'); }
  finally { store.reframing = false; }
}
</script>
<template>
  <div class="card p-5" style="border:1px solid var(--color-brand-500); background: linear-gradient(160deg, rgba(90,140,170,.13), rgba(74,122,144,.06));">
    <h2 class="mb-1 font-display text-base font-semibold text-brand-300">📐 Reframe / Crop</h2>
    <p class="text-[11px] text-ink-500">Cắt lại khung theo tỷ lệ hoặc chọn vùng trên canvas.</p>
    <label class="label mt-3">Tỷ lệ khung</label>
    <div class="flex flex-wrap gap-1.5">
      <button v-for="r in ratios" :key="r" type="button" @click="store.reframeRatio = r" class="rounded-full border px-3 py-1.5 text-xs transition-colors" :class="store.reframeRatio === r ? 'border-brand-600 bg-brand-600 font-semibold text-white' : 'border-ink-700 text-cream-200 hover:border-brand-400'">{{ r }}</button>
    </div>
    <button @click="runReframeCenter" :disabled="store.reframing || !store.upscaleSrc" class="btn-outline btn-sm mt-3 w-full whitespace-nowrap">📐 Cắt giữa</button>
    <button @click="toggleCrop" :disabled="store.reframing || !store.upscaleSrc" class="mt-1.5 w-full whitespace-nowrap rounded-2xl border py-2 text-sm font-semibold transition-colors" :class="store.cropMode ? 'border-brand-500 bg-brand-600 text-white' : 'border-ink-700 text-cream-200 hover:border-brand-400'">{{ store.cropMode ? '✂️ Đang chọn vùng… (Hủy)' : '✂️ Chọn vùng trên canvas' }}</button>
    <template v-if="store.cropMode"><button @click="confirmCrop" :disabled="store.reframing || !store.upscaleSrc" class="btn-brand mt-1.5 w-full whitespace-nowrap">{{ store.reframing ? 'Đang cắt…' : '✅ Áp dụng vùng đã chọn' }}</button></template>
  </div>
</template>
