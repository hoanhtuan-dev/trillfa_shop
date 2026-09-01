<script setup>
import { ref, onMounted, onBeforeUnmount, computed, watch } from 'vue';
import { useStudioStore } from './store.js';
import SourceCard from './components/SourceCard.vue';
import SuggestCard from './components/SuggestCard.vue';
import ConceptCard from './components/ConceptCard.vue';
import StylistCard from './components/StylistCard.vue';
import PaletteTextureCard from './components/PaletteTextureCard.vue';
import UpscaleCard from './components/UpscaleCard.vue';
import FilmLookCard from './components/FilmLookCard.vue';
import ReframeCard from './components/ReframeCard.vue';
import SwapCard from './components/SwapCard.vue';
import InpaintCard from './components/InpaintCard.vue';
import RegionTools from './components/RegionTools.vue';
import DirectorCard from './components/DirectorCard.vue';
import SourcePanel from './components/SourcePanel.vue';
import OutputModule from './components/OutputModule.vue';
import GalleryModal from './components/GalleryModal.vue';
const store = useStudioStore();
function copyColor(c) { try { navigator.clipboard.writeText(c); store.toast('Đã copy ' + c); } catch (e) { store.toast('Lỗi copy.', 'error'); } }
const stepNav = [['1','Concept'],['2','Fitting Room'],['3','Director']];
const menuOpen = ref(false);
const outputOpen = ref(false);
onMounted(async () => { store.load(); store.loadPalette(store.previewId); window.addEventListener('keydown', onCanvasKey); });
onBeforeUnmount(() => { window.removeEventListener('keydown', onCanvasKey); });
watch(() => store.previewId, (id) => { store.loadPalette(id); });
// Template refs -> store: StudioApp owns the canvas DOM; the store needs the elements for crop geometry.
const cvImg = ref(null);
const canvasZoom = ref(null);
watch([cvImg, canvasZoom], ([img, zoom]) => { store.setCanvasRefs(img, zoom); });
// While crop mode is on: re-fit the box when the ratio changes, re-init when the image changes.
watch(() => store.reframeRatio, () => { if (store.cropMode) store.refitCropBox(); });
watch(() => store.upscaleSrc, () => { if (store.cropMode) store.initCropBox(); });
function onCanvasKey(e) {
  if (!store.cropMode && !store.regionMode) return;
  const t = e.target;
  if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT' || t.isContentEditable)) return;
  if (e.key === 'Escape') { if (store.regionMode) store.stopRegionSelect(); else store.toggleCrop(); }
  else if (e.key === 'Enter' && !(t && t.tagName === 'BUTTON') && !store.regionMode) store.confirmCrop();
}
const bgClass = computed(() => ({ grid: 'cvs-checker', dark: 'bg-ink-950', white: 'bg-white', cream: 'bg-cream-100' }[store.canvasBg] || 'cvs-checker'));
const panel = computed(() => store.step === 1 ? [StylistCard, SuggestCard, ConceptCard] : store.step === 2 ? [SwapCard, InpaintCard, UpscaleCard, FilmLookCard, ReframeCard] : [DirectorCard]);
</script>
<template>
  <div class="studio-dark flex h-full flex-col bg-ink-950 text-cream-100">
    <!-- toast (copy/status) -->
    <div v-if="store.flashMsg" class="pointer-events-none fixed left-1/2 top-4 z-[90] -translate-x-1/2 rounded-full px-4 py-2 text-xs font-semibold shadow-2xl" :class="store.flashType === 'error' ? 'bg-red-600 text-white' : 'bg-ink-800 text-cream-100 border border-brand-500/40'">{{ store.flashMsg }}</div>
    <!-- Mobile top bar -->
    <div class="flex items-center justify-between border-b border-ink-700 bg-ink-900/80 px-3 py-2 lg:hidden">
      <button @click="menuOpen = true" class="grid h-9 w-9 place-items-center rounded-lg bg-ink-700 text-cream-200">☰</button>
      <span class="font-display text-sm font-semibold">Studio (Vue)</span>
      <button @click="outputOpen = true" class="rounded-lg bg-ink-700 px-3 py-1.5 text-xs font-semibold text-cream-200">Kết quả ({{ store.generations.length }})</button>
    </div>
    <div class="flex flex-1 overflow-hidden">
      <!-- Left sidebar (desktop) -->
      <aside class="scrollbar-hide hidden w-80 shrink-0 flex-col overflow-y-auto border-r border-ink-700 bg-ink-900/70 p-3 lg:flex">
        <div class="mb-3 flex items-center justify-between"><span class="font-display text-sm font-semibold">🎨 Studio</span><span class="text-[10px] text-cream-300/50">Credit {{ store.creditsLeft }}</span></div>
        <div class="mb-3 flex gap-1 rounded-2xl bg-ink-800 p-1">
          <button v-for="s in stepNav" :key="s[0]" @click="store.step = Number(s[0])" class="flex-1 rounded-xl px-2 py-1.5 text-xs font-semibold" :class="store.step === Number(s[0]) ? 'bg-brand-600 text-white' : 'text-cream-200 hover:bg-ink-700'">{{ s[1] }}</button>
        </div>
        <div class="scrollbar-hide space-y-3">
          <component :is="c" v-for="(c,i) in panel" :key="i" />
        </div>
      </aside>
      <!-- Center canvas -->
      <main class="relative flex-1 min-w-0 p-3">
        <div class="relative h-full overflow-hidden rounded-2xl border border-ink-700" :class="bgClass">
          <!-- Floating region tools (left edge icons + panel) -->
          <RegionTools />
          <!-- active image indicator + actions -->
          <div v-if="store.upscaleSrc" class="absolute left-3 top-3 z-30 flex items-center gap-1.5 rounded-full bg-ink-900/85 px-2.5 py-1.5 text-xs shadow-lg">
            <span class="text-[10px] text-cream-300/60">{{ store.editSource ? 'Nguồn:' : 'Kết quả:' }}</span>
            <span class="max-w-40 truncate font-semibold text-cream-100">{{ store.editSource ? (store.editSource.name || 'Ảnh nguồn') : ('Ảnh #' + store.preview?.id) }}</span>
            <button v-if="store.editSource" @click="store.editSource = null" class="grid h-6 w-6 place-items-center rounded-full bg-ink-700 text-cream-200 hover:bg-red-600" title="Bỏ ảnh nguồn">✕</button>
            <button v-else @click="store.deleteGen(store.preview)" class="grid h-6 w-6 place-items-center rounded-full bg-ink-700 text-red-200 hover:bg-red-600" title="Xóa ảnh kết quả này">🗑</button>
          </div>
          <div ref="canvasZoom" class="absolute inset-0 grid place-items-center p-4 cursor-grab active:cursor-grabbing" style="touch-action:none" @wheel.prevent="store.wheelZoom($event.deltaY)" @pointerdown="store.panStart($event)" @pointermove="store.panMove($event)" @pointerup="store.panEnd" @pointerleave="store.panEnd">
            <img v-if="store.upscaleSrc" ref="cvImg" :src="store.upscaleSrc" class="max-h-full max-w-full select-none object-contain" :style="{ transform: 'translate(' + store.pan.x + 'px, ' + store.pan.y + 'px) scale(' + store.zoom + ')', transformOrigin: 'center' }" draggable="false" @load="store.onCanvasImgLoad()" />
            <p v-else class="text-sm text-cream-300/60">Chọn/hiện một ảnh (Nguồn hoặc Kết quả) để làm việc.</p>
            <div v-if="store.cropMode && store.upscaleSrc" class="pointer-events-none absolute inset-0" style="z-index:30">
              <div class="absolute cursor-move select-none" style="pointer-events:auto; touch-action:none" :style="store.cropStyle()" @pointerdown.stop="store.cropStart($event,'move')" @dblclick="store.toggleCrop" title="Kéo để di chuyển · nhấn đúp để hủy">
                <div class="pointer-events-none absolute inset-0 border-2 border-white/85" style="box-shadow: 0 0 0 9999px rgba(0,0,0,0.5);"></div>
                <div class="pointer-events-none absolute inset-0 opacity-40">
                  <div class="absolute left-1/3 top-0 h-full w-px bg-white/60"></div>
                  <div class="absolute left-2/3 top-0 h-full w-px bg-white/60"></div>
                  <div class="absolute left-0 top-1/3 h-px w-full bg-white/60"></div>
                  <div class="absolute left-0 top-2/3 h-px w-full bg-white/60"></div>
                </div>
                <div class="pointer-events-none absolute inset-0 flex items-center justify-center"><span class="rounded bg-white/70 px-1 py-0.5 text-[10px] font-semibold text-ink-900">{{ store.cropSizeLabel() }}</span></div>
                <div class="absolute -left-1.5 -top-1.5 h-3 w-3 cursor-nwse-resize rounded-sm bg-white shadow" style="pointer-events:auto; touch-action:none" @pointerdown.stop="store.cropStart($event,'nw')" @dblclick.stop></div>
                <div class="absolute -right-1.5 -top-1.5 h-3 w-3 cursor-nesw-resize rounded-sm bg-white shadow" style="pointer-events:auto; touch-action:none" @pointerdown.stop="store.cropStart($event,'ne')" @dblclick.stop></div>
                <div class="absolute -bottom-1.5 -left-1.5 h-3 w-3 cursor-nesw-resize rounded-sm bg-white shadow" style="pointer-events:auto; touch-action:none" @pointerdown.stop="store.cropStart($event,'sw')" @dblclick.stop></div>
                <div class="absolute -bottom-1.5 -right-1.5 h-3 w-3 cursor-nwse-resize rounded-sm bg-white shadow" style="pointer-events:auto; touch-action:none" @pointerdown.stop="store.cropStart($event,'se')" @dblclick.stop></div>
              </div>
            </div>
            <!-- Region selection overlay (xóa/thay vùng) -->
            <div v-if="store.regionMode && store.upscaleSrc" class="absolute inset-0" style="z-index:31; cursor:crosshair" @pointerdown="store.regionStart($event)" @pointermove="store.regionMove($event)" @pointerup="store.regionStop()" @pointercancel="store.regionStop()">
              <div class="pointer-events-none absolute inset-0 bg-black/45"></div>
              <div class="pointer-events-none absolute" :style="store.regionStyle()">
                <div class="absolute -inset-px border-2 border-dashed border-brand-300" style="box-shadow: 0 0 0 9999px rgba(0,0,0,0.45);"></div>
                <div class="absolute -top-6 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full bg-ink-900/90 px-2 py-0.5 text-[10px] font-semibold text-brand-200">Kéo để chọn vùng · Esc để hủy</div>
                <div class="absolute -bottom-6 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full bg-ink-900/90 px-2 py-0.5 text-[10px] font-semibold text-cream-100">{{ Math.round((store.regionBox.w || 0) * 100) }}% × {{ Math.round((store.regionBox.h || 0) * 100) }}%</div>
              </div>
            </div>
          </div>
          <!-- Canvas toolbar -->
          <div class="absolute bottom-3 left-3 z-20 flex items-center gap-1 rounded-full bg-ink-900/85 px-2 py-1.5 shadow-lg">
            <button @click="store.zoomOut()" class="grid h-8 w-8 place-items-center rounded-full text-cream-200 hover:bg-ink-700">−</button>
            <button @click="store.zoomFit()" class="rounded-full px-2 py-1 text-xs text-cream-200 hover:bg-ink-700">Vừa</button>
            <button @click="store.zoomIn()" class="grid h-8 w-8 place-items-center rounded-full text-cream-200 hover:bg-ink-700">+</button>
            <span class="px-1 text-xs text-cream-200">{{ Math.round(store.zoom * 100) }}%</span>
            <template v-if="store.upscaleSrc">
              <span class="mx-0.5 h-4 w-px bg-ink-700"></span>
              <button v-for="(op, key) in store.regionOps" :key="key" @click="store.startRegionSelect(key)" class="rounded-full px-2 py-1 text-[11px] font-semibold transition-colors" :class="store.regionMode === key ? 'bg-brand-600 text-white' : 'text-cream-200 hover:bg-ink-700'" :title="op.hint">{{ op.label }}</button>
            </template>
          </div>
          <!-- right column: layers (top) + floating palette (below, same width) -->
          <div class="absolute right-3 top-3 z-20 flex w-40 flex-col gap-1.5">
            <div v-if="store.canvasLayers.length" class="flex flex-col gap-1.5 rounded-2xl bg-ink-900/85 p-2 shadow-lg">
              <p class="px-0.5 text-[10px] font-semibold text-cream-300/60">Layers ({{ store.canvasLayers.length }})</p>
              <div class="scrollbar-hide flex max-h-40 flex-col gap-1.5 overflow-y-auto">
                <div v-for="l in store.canvasLayers" :key="l.id" class="group relative flex items-center gap-1.5 rounded-lg border p-1" :class="store.activeLayerId === l.id ? 'border-brand-500 bg-brand-600/20' : 'border-ink-700/60'">
                  <button @click="store.selectLayer(l)" class="flex min-w-0 items-center gap-1.5"><img :src="l.image" class="h-7 w-7 shrink-0 rounded bg-ink-900 object-cover"><span class="truncate text-[10px] text-cream-100">{{ l.name }}</span></button>
                  <button @click="store.deleteLayer(l)" class="ml-auto grid h-5 w-5 shrink-0 place-items-center rounded-full bg-red-600/25 text-red-200 opacity-0 hover:bg-red-600 group-hover:opacity-100">🗑</button>
                </div>
              </div>
            </div>
            <div v-if="store.palette.length && store.step !== 3 && store.previewId" class="rounded-2xl bg-ink-900/90 px-2.5 py-1.5 shadow-xl">
              <div class="mb-1 text-[10px] font-semibold text-cream-300/60">🎨 Palette</div>
              <div class="grid grid-cols-3 gap-1.5">
                <button v-for="c in store.palette.slice(0, 8)" :key="c" @click="copyColor(c)" class="h-7 w-full rounded-md border border-ink-700 transition hover:scale-105" :style="{ background: c }" :title="'Nhấn để copy ' + c"></button>
              </div>
            </div>
          </div>
          <!-- canvas bg + crop toggles -->
          <div class="absolute right-3 bottom-3 z-20 flex items-center gap-1 rounded-full bg-ink-900/85 px-2 py-1.5 shadow-lg">
            <button v-for="b in ['grid','dark','white','cream']" :key="b" @click="store.canvasBg = b" class="h-6 w-6 rounded-full border" :class="store.canvasBg === b ? 'border-white' : 'border-ink-600'" :style="{ background: b === 'grid' ? 'repeating-conic-gradient(#888 0 25%, #ccc 0 50%) 0 / 10px 10px' : b === 'dark' ? '#0a0a0f' : b === 'white' ? '#fff' : '#f5ead9' }" :title="b"></button>
          </div>
          <!-- variant slider (bottom, only when multiple variants) -->
          <div v-if="store.showBatch && store.activeBatch.length > 1" class="absolute bottom-14 left-1/2 z-20 -translate-x-1/2 rounded-2xl bg-ink-900/90 px-2.5 py-1.5 shadow-xl">
            <div class="flex items-center gap-1.5">
              <span class="text-[10px] text-cream-300/60">{{ store.activeBatch.length }} biến thể</span>
              <button v-for="v in store.activeBatch" :key="v.id" @click="store.select(v)" class="relative h-12 w-12 overflow-hidden rounded-lg border-2" :class="store.previewId === v.id ? 'border-brand-500' : 'border-ink-700'"><img :src="v.media_url" class="h-full w-full bg-ink-900 object-cover"><span v-if="v.status !== 'completed'" class="absolute inset-0 grid place-items-center bg-black/60 text-[9px] text-cream-200">{{ v.status }}</span></button>
              <button @click="store.hideBatch()" class="ml-1 grid h-6 w-6 place-items-center rounded-full bg-ink-700 text-cream-200 hover:bg-red-600">✕</button>
            </div>
          </div>
        </div>
      </main>
      <!-- Right outputs (desktop) -->
      <aside class="scrollbar-hide hidden w-80 shrink-0 flex-col space-y-3 overflow-y-auto border-l border-ink-700 bg-ink-900/70 p-3 lg:flex">
        <SourcePanel />
        <OutputModule />
      </aside>
    </div>
    <!-- Mobile menu overlay -->
    <div v-if="menuOpen" class="fixed inset-0 z-50 lg:hidden" @click="menuOpen=false">
      <div class="absolute inset-0 bg-black/60"></div>
      <div class="absolute left-0 top-0 h-full w-80 scrollbar-hide overflow-y-auto bg-ink-900 p-3" @click.stop>
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
      <div class="absolute right-0 top-0 h-full w-80 scrollbar-hide overflow-y-auto bg-ink-900 p-3" @click.stop>
        <div class="mb-2 flex items-center justify-between"><p class="text-xs font-semibold">Outputs ({{ store.generations.length }})</p><button @click="outputOpen=false" class="grid h-8 w-8 place-items-center rounded-full bg-ink-700 text-cream-200">✕</button></div>
        <SourcePanel />
        <OutputModule />
      </div>
    </div>
    <GalleryModal v-if="store.viewer" />
  </div>
</template>
<style scoped>
.cvs-checker { background: repeating-conic-gradient(#3a3a44 0 25%, #2a2a31 0 50%) 0 / 18px 18px; }
</style>
