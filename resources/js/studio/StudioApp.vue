<script setup>
import { ref, onMounted, computed } from 'vue';
import { useStudioStore } from './store.js';
import ConceptCard from './components/ConceptCard.vue';
import StylistCard from './components/StylistCard.vue';
import PaletteTextureCard from './components/PaletteTextureCard.vue';
import UpscaleCard from './components/UpscaleCard.vue';
import FilmLookCard from './components/FilmLookCard.vue';
import ReframeCard from './components/ReframeCard.vue';
import SwapCard from './components/SwapCard.vue';
import InpaintCard from './components/InpaintCard.vue';
import DirectorCard from './components/DirectorCard.vue';
const store = useStudioStore();
const stepNav = [['1','Concept'],['2','Fitting Room'],['3','Director']];
const menuOpen = ref(false);
const outputOpen = ref(false);
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
function cropStart(e, key) { e.preventDefault(); e.stopPropagation(); store._cropDrag = { key, sx: e.clientX, sy: e.clientY, box: { ...store.cropBox } }; const move = (ev) => cropMove(ev); const up = () => { store._cropDrag = null; window.removeEventListener('mousemove', move); window.removeEventListener('mouseup', up); window.removeEventListener('touchmove', move); window.removeEventListener('touchend', up); }; window.addEventListener('mousemove', move); window.addEventListener('mouseup', up); window.addEventListener('touchmove', move, { passive: false }); window.addEventListener('touchend', up); }
function cropMove(e) {
  const d = store._cropDrag; if (!d || !store.cropMode) return;
  const m = cropMetrics(); if (!m) return;
  const bx = (e.clientX - d.sx) / m.vw, by = (e.clientY - d.sy) / m.vh;
  const b = { ...d.box };
  if (d.key === 'move') { b.x = Math.max(0, Math.min(1 - b.w, b.x + bx)); b.y = Math.max(0, Math.min(1 - b.h, b.y + by)); }
  else { const r = Number(store.reframeRatio.split(':')[0]) / Number(store.reframeRatio.split(':')[1]); const ratioFrac = r / m.ia; const step = Math.max(bx, by); let nh = Math.max(0.05, b.h + step); let nw = nh * ratioFrac; if (nw > 1) { nw = 1; nh = nw / ratioFrac; } if (b.y + nh > 1) { nh = Math.max(0.05, 1 - b.y); nw = nh * ratioFrac; } if (b.x + nw > 1) { nw = Math.max(0.05, 1 - b.x); nh = nw / ratioFrac; } b.w = Math.max(0.05, Math.min(1, nw)); b.h = Math.max(0.05, Math.min(1, nh)); }
  store.cropBox = b;
}
const bgClass = computed(() => ({ grid: 'cvs-checker', dark: 'bg-ink-950', white: 'bg-white', cream: 'bg-cream-100' }[store.canvasBg] || 'cvs-checker'));
const panel = computed(() => store.step === 1 ? [StylistCard, ConceptCard] : store.step === 2 ? [SwapCard, InpaintCard, PaletteTextureCard, UpscaleCard, FilmLookCard, ReframeCard] : [DirectorCard]);
</script>
<template>
  <div class="flex h-full flex-col bg-ink-900 text-cream-100">
    <!-- Mobile top bar -->
    <div class="flex items-center justify-between border-b border-ink-700 bg-ink-900/80 px-3 py-2 lg:hidden">
      <button @click="menuOpen = true" class="grid h-9 w-9 place-items-center rounded-lg bg-ink-700 text-cream-200">☰</button>
      <span class="font-display text-sm font-semibold">Studio (Vue)</span>
      <button @click="outputOpen = true" class="rounded-lg bg-ink-700 px-3 py-1.5 text-xs font-semibold text-cream-200">Kết quả ({{ store.generations.length }})</button>
    </div>
    <div class="flex flex-1 overflow-hidden">
      <!-- Left sidebar (desktop) -->
      <aside class="hidden w-64 shrink-0 flex-col overflow-y-auto border-r border-ink-700 bg-ink-900/70 p-3 lg:flex">
        <div class="mb-3 flex items-center justify-between"><span class="font-display text-sm font-semibold">🎨 Studio</span><span class="text-[10px] text-cream-300/50">Credit {{ store.creditsLeft }}</span></div>
        <div class="mb-3 flex gap-1 rounded-2xl bg-ink-800 p-1">
          <button v-for="s in stepNav" :key="s[0]" @click="store.step = Number(s[0])" class="flex-1 rounded-xl px-2 py-1.5 text-xs font-semibold" :class="store.step === Number(s[0]) ? 'bg-brand-600 text-white' : 'text-cream-200 hover:bg-ink-700'">{{ s[1] }}</button>
        </div>
        <div class="space-y-3">
          <component :is="c" v-for="(c,i) in panel" :key="i" />
        </div>
      </aside>
      <!-- Center canvas -->
      <main class="relative flex-1 min-w-0 p-3">
        <div class="relative h-full overflow-hidden rounded-2xl border border-ink-700" :class="bgClass">
          <div ref="canvasZoom" class="absolute inset-0 grid place-items-center p-4 cursor-grab active:cursor-grabbing" @pointerdown="store.panStart($event)" @pointermove="store.panMove($event)" @pointerup="store.panEnd" @pointerleave="store.panEnd">
            <img v-if="store.preview?.media_url" ref="cvImg" :src="store.preview.media_url" class="max-h-full max-w-full select-none object-contain" :style="{ transform: 'translate(' + store.pan.x + 'px, ' + store.pan.y + 'px) scale(' + store.zoom + ')', transformOrigin: 'center' }" draggable="false" />
            <p v-else class="text-sm text-cream-300/60">Chọn/hiện một ảnh để làm việc.</p>
            <div v-if="store.cropMode && store.preview?.media_url" class="pointer-events-none absolute inset-0" style="z-index:30">
              <div class="absolute cursor-move" style="pointer-events:auto" :style="cropStyle()" @mousedown.stop="cropStart($event,'move')" @touchstart.stop="cropStart($event,'move')">
                <div class="pointer-events-none absolute inset-0 border-2 border-white/85" style="box-shadow: 0 0 0 9999px rgba(0,0,0,0.5);"></div>
                <div class="pointer-events-none absolute inset-0 flex items-center justify-center"><span class="rounded bg-white/70 px-1 py-0.5 text-[10px] font-semibold text-ink-900">{{ store.reframeRatio }}</span></div>
                <div class="absolute -bottom-3 -right-3 h-6 w-6 cursor-nwse-resize rounded-sm bg-white shadow" style="pointer-events:auto" @mousedown.stop="cropStart($event,'resize')" @touchstart.stop="cropStart($event,'resize')"></div>
              </div>
            </div>
          </div>
          <!-- Canvas toolbar -->
          <div class="absolute bottom-3 left-3 z-20 flex items-center gap-1 rounded-full bg-ink-900/85 px-2 py-1.5 shadow-lg">
            <button @click="store.zoomOut()" class="grid h-8 w-8 place-items-center rounded-full text-cream-200 hover:bg-ink-700">−</button>
            <button @click="store.zoomFit()" class="rounded-full px-2 py-1 text-xs text-cream-200 hover:bg-ink-700">Vừa</button>
            <button @click="store.zoomIn()" class="grid h-8 w-8 place-items-center rounded-full text-cream-200 hover:bg-ink-700">+</button>
            <span class="px-1 text-xs text-cream-200">{{ Math.round(store.zoom * 100) }}%</span>
          </div>
          <!-- canvas bg + crop toggles -->
          <div class="absolute right-3 bottom-3 z-20 flex items-center gap-1 rounded-full bg-ink-900/85 px-2 py-1.5 shadow-lg">
            <button v-for="b in ['grid','dark','white','cream']" :key="b" @click="store.canvasBg = b" class="h-6 w-6 rounded-full border" :class="store.canvasBg === b ? 'border-white' : 'border-ink-600'" :style="{ background: b === 'grid' ? 'repeating-conic-gradient(#888 0 25%, #ccc 0 50%) 0 / 10px 10px' : b === 'dark' ? '#0a0a0f' : b === 'white' ? '#fff' : '#f5ead9' }" :title="b"></button>
          </div>
        </div>
      </main>
      <!-- Right outputs (desktop) -->
      <aside class="hidden w-72 shrink-0 flex-col overflow-y-auto border-l border-ink-700 bg-ink-900/70 p-3 lg:flex">
        <p class="mb-2 text-xs font-semibold text-cream-200">Outputs <span class="text-cream-300/50">({{ store.generations.length }})</span></p>
        <div class="flex flex-wrap gap-2">
          <button v-for="g in store.generations" :key="g.id" @click="store.select(g)" class="relative h-14 w-14 overflow-hidden rounded-lg border" :class="store.previewId === g.id ? 'border-brand-500' : 'border-ink-700'"><img :src="g.media_url" class="h-full w-full bg-ink-900 object-cover" loading="lazy"><span v-if="g.status !== 'completed'" class="absolute inset-0 grid place-items-center bg-black/60 text-[10px] text-cream-200">{{ g.status }}</span></button>
        </div>
      </aside>
    </div>
    <!-- Mobile menu overlay -->
    <div v-if="menuOpen" class="fixed inset-0 z-50 lg:hidden" @click="menuOpen=false">
      <div class="absolute inset-0 bg-black/60"></div>
      <div class="absolute left-0 top-0 h-full w-72 overflow-y-auto bg-ink-900 p-3" @click.stop>
        <div class="mb-2 flex items-center justify-between"><span class="font-display text-sm font-semibold">🎨 Studio</span><button @click="menuOpen=false" class="grid h-8 w-8 place-items-center rounded-full bg-ink-700 text-cream-200">✕</button></div>
        <div class="mb-3 flex gap-1 rounded-2xl bg-ink-800 p-1">
          <button v-for="s in stepNav" :key="s[0]" @click="store.step = Number(s[0]); menuOpen=false" class="flex-1 rounded-xl px-2 py-1.5 text-xs font-semibold" :class="store.step === Number(s[0]) ? 'bg-brand-600 text-white' : 'text-cream-200 hover:bg-ink-700'">{{ s[1] }}</button>
        </div>
        <div class="space-y-3"><component :is="c" v-for="(c,i) in panel" :key="i" /></div>
      </div>
    </div>
    <!-- Mobile outputs overlay -->
    <div v-if="outputOpen" class="fixed inset-0 z-50 lg:hidden" @click="outputOpen=false">
      <div class="absolute inset-0 bg-black/60"></div>
      <div class="absolute right-0 top-0 h-full w-72 overflow-y-auto bg-ink-900 p-3" @click.stop>
        <div class="mb-2 flex items-center justify-between"><p class="text-xs font-semibold">Outputs ({{ store.generations.length }})</p><button @click="outputOpen=false" class="grid h-8 w-8 place-items-center rounded-full bg-ink-700 text-cream-200">✕</button></div>
        <div class="flex flex-wrap gap-2">
          <button v-for="g in store.generations" :key="g.id" @click="store.select(g); outputOpen=false" class="relative h-14 w-14 overflow-hidden rounded-lg border" :class="store.previewId === g.id ? 'border-brand-500' : 'border-ink-700'"><img :src="g.media_url" class="h-full w-full bg-ink-900 object-cover" loading="lazy"></button>
        </div>
      </div>
    </div>
  </div>
</template>
<style scoped>
.cvs-checker { background: repeating-conic-gradient(#3a3a44 0 25%, #2a2a31 0 50%) 0 / 18px 18px; }
</style>
