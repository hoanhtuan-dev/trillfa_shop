<script setup>
import { onMounted, computed } from 'vue';
import { useStudioStore } from './store.js';
import ConceptCard from './components/ConceptCard.vue';
import PaletteTextureCard from './components/PaletteTextureCard.vue';
import UpscaleCard from './components/UpscaleCard.vue';
import FilmLookCard from './components/FilmLookCard.vue';
import ReframeCard from './components/ReframeCard.vue';
import SwapCard from './components/SwapCard.vue';
import DirectorCard from './components/DirectorCard.vue';
const store = useStudioStore();
const stepNav = [['1','Concept'],['2','Fitting Room'],['3','Director']];
onMounted(async () => { store.load(); });
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
function cropStyle() {
  const m = cropMetrics(); if (!m) return {};
  return { left: ((m.vx + store.cropBox.x * m.vw) / m.crW * 100) + '%', top: ((m.vy + store.cropBox.y * m.vh) / m.crH * 100) + '%', width: (store.cropBox.w * m.vw / m.crW * 100) + '%', height: (store.cropBox.h * m.vh / m.crH * 100) + '%' };
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
  if (d.key === 'move') { b.x = Math.max(0, Math.min(1 - b.w, b.x + bx)); b.y = Math.max(0, Math.min(1 - b.h, b.y + by)); }
  else {
    const r = Number(store.reframeRatio.split(':')[0]) / Number(store.reframeRatio.split(':')[1]);
    const ratioFrac = r / m.ia; const step = Math.max(bx, by);
    let nh = Math.max(0.05, b.h + step); let nw = nh * ratioFrac;
    if (nw > 1) { nw = 1; nh = nw / ratioFrac; }
    if (b.y + nh > 1) { nh = Math.max(0.05, 1 - b.y); nw = nh * ratioFrac; }
    if (b.x + nw > 1) { nw = Math.max(0.05, 1 - b.x); nh = nw / ratioFrac; }
    b.w = Math.max(0.05, Math.min(1, nw)); b.h = Math.max(0.05, Math.min(1, nh));
  }
  store.cropBox = b;
}
</script>
<template>
  <div class="flex h-full flex-col gap-3 p-3 md:flex-row">
    <!-- Canvas -->
    <div class="relative min-h-[420px] flex-1 overflow-hidden rounded-2xl bg-ink-900/60">
      <div ref="canvasZoom" class="absolute inset-0 grid place-items-center p-4">
        <img v-if="store.preview?.media_url" ref="cvImg" :src="store.preview.media_url" class="max-h-full max-w-full object-contain" />
        <p v-else class="text-sm text-cream-300/60">Chọn/hiện một ảnh để làm việc.</p>
        <div v-if="store.cropMode && store.preview?.media_url" class="pointer-events-none absolute inset-0" style="z-index:30">
          <div class="absolute cursor-move" style="pointer-events:auto" :style="cropStyle()" @mousedown.stop="cropStart($event,'move')" @touchstart.stop="cropStart($event,'move')">
            <div class="pointer-events-none absolute inset-0 border-2 border-white/85" style="box-shadow: 0 0 0 9999px rgba(0,0,0,0.5);"></div>
            <div class="pointer-events-none absolute inset-0 flex items-center justify-center"><span class="rounded bg-white/70 px-1 py-0.5 text-[10px] font-semibold text-ink-900">{{ store.reframeRatio }}</span></div>
            <div class="absolute -bottom-3 -right-3 h-6 w-6 cursor-nwse-resize rounded-sm bg-white shadow" style="pointer-events:auto" @mousedown.stop="cropStart($event,'resize')" @touchstart.stop="cropStart($event,'resize')"></div>
          </div>
        </div>
      </div>
    </div>
    <!-- Right panel -->
    <div class="w-full shrink-0 space-y-3 overflow-y-auto md:w-[360px]">
      <div class="flex items-center gap-1 rounded-2xl bg-ink-900/60 p-1.5">
        <button v-for="s in stepNav" :key="s[0]" @click="store.step = Number(s[0])" class="flex-1 rounded-xl px-3 py-1.5 text-xs font-semibold" :class="store.step === Number(s[0]) ? 'bg-brand-600 text-white' : 'text-cream-200 hover:bg-ink-700'">{{ s[1] }}</button>
      </div>
      <!-- Outputs -->
      <div class="card p-3">
        <p class="mb-2 text-xs font-semibold text-cream-200">Outputs <span class="text-cream-300/50">({{ store.generations.length }})</span></p>
        <div class="flex flex-wrap gap-2">
          <button v-for="g in store.generations" :key="g.id" @click="store.select(g)" class="relative h-14 w-14 overflow-hidden rounded-lg border" :class="store.previewId === g.id ? 'border-brand-500' : 'border-ink-700'"><img :src="g.media_url" class="h-full w-full object-cover bg-ink-900" loading="lazy"><span v-if="g.status !== 'completed'" class="absolute inset-0 grid place-items-center bg-black/60 text-[10px] text-cream-200">{{ g.status }}</span></button>
        </div>
      </div>
      <!-- cards per step -->
      <template v-if="store.step === 1"><ConceptCard /></template>
      <template v-else-if="store.step === 2"><SwapCard /><PaletteTextureCard /><UpscaleCard /><FilmLookCard /><ReframeCard /></template>
      <template v-else-if="store.step === 3"><DirectorCard /></template>
    </div>
  </div>
</template>
