<script setup>
import { ref, onMounted, onBeforeUnmount, computed, watch } from 'vue';
import { useStudioStore } from './store.js';
import SourceCard from './components/SourceCard.vue';
import SuggestCard from './components/SuggestCard.vue';
import ConceptCard from './components/ConceptCard.vue';
import StylistCard from './components/StylistCard.vue';
import PaletteTextureCard from './components/PaletteTextureCard.vue';
import UpscaleCard from './components/UpscaleCard.vue';
// [SWAP TẠM ẨN] import SwapCard from './components/SwapCard.vue';
import InpaintCard from './components/InpaintCard.vue';
import ComposeCard from './components/ComposeCard.vue';
import RegionTools from './components/RegionTools.vue';
import CanvasMaskTools from './components/CanvasMaskTools.vue';
import DirectorCard from './components/DirectorCard.vue';
import SourcePanel from './components/SourcePanel.vue';
import OutputModule from './components/OutputModule.vue';
import GalleryModal from './components/GalleryModal.vue';
const store = useStudioStore();
function copyColor(c) { try { navigator.clipboard.writeText(c); store.toast('Đã copy ' + c); } catch (e) { store.toast('Lỗi copy.', 'error'); } }
const stepNav = [['1','Concept'],['2','Fitting Room'],['3','Director']];
const menuOpen = ref(false);
const outputOpen = ref(false);
// Layer rename (inline edit)
const renamingId = ref(null);
const renameValue = ref('');
function startRename(l) { renamingId.value = l.id; renameValue.value = l.name || ''; }
function commitRename() { if (!renamingId.value) return; store.renameLayer(renamingId.value, renameValue.value); renamingId.value = null; }
function cancelRename() { renamingId.value = null; }
onMounted(async () => { store.load(); store.loadPalette(store.previewId); window.addEventListener('keydown', onCanvasKey); });
onBeforeUnmount(() => { window.removeEventListener('keydown', onCanvasKey); });
watch(() => store.previewId, (id) => { store.loadPalette(id); });
// Template refs -> store: StudioApp owns the canvas DOM; the store needs the elements for crop geometry.
const cvImg = ref(null);
const canvasZoom = ref(null);
watch([cvImg, canvasZoom], ([img, zoom]) => { store.setCanvasRefs(img, zoom); });
// While crop mode is on: re-fit the box when the ratio changes, re-init when the image changes.
watch(() => store.reframeRatio, () => { if (store.cropMode) store.refitCropBox(); });
// Khi ảnh hiển thị ĐỔI (chọn ảnh khác / ảnh mới sinh ra / đổi layer) → reset zoom+pan
// về mặc định để ảnh LUÔN fit trọn khung, hiển thị đầy đủ cả chiều ngang lẫn dọc.
watch(() => store.upscaleSrc, (src, old) => {
  if (src && src !== old) { store.zoom = 1; store.pan = { x: 0, y: 0 }; }
  if (store.cropMode) store.initCropBox();
});
function onCanvasKey(e) {
  if (!store.cropMode && store.inpaintMaskMode === 'none') return;
  const t = e.target;
  if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT' || t.isContentEditable)) return;
  if (e.key === 'Escape') { if (store.inpaintMaskMode !== 'none') { store.inpaintMaskMode = 'none'; store.inpaintBrushData = ''; } else store.toggleCrop(); }
  else if (e.key === 'Enter' && !(t && t.tagName === 'BUTTON') && store.inpaintMaskMode === 'none') store.confirmCrop();
}
const bgClass = computed(() => ({ grid: 'cvs-checker', dark: 'bg-ink-950', white: 'bg-white', cream: 'bg-cream-100' }[store.canvasBg] || 'cvs-checker'));
const panel = computed(() => store.step === 1 ? [StylistCard, SuggestCard, ConceptCard] : store.step === 2 ? [ComposeCard, InpaintCard, UpscaleCard] : [DirectorCard]); // [SWAP TẠM ẨN: bỏ SwapCard]
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
          <!-- Floating tools (Reframe + Film Look) -->
          <RegionTools />
          <!-- Inpaint mask overlay on canvas -->
          <CanvasMaskTools />
          <!-- active image indicator + actions -->
          <div v-if="store.upscaleSrc" class="absolute left-3 top-3 z-30 flex items-center gap-1.5 rounded-full bg-ink-900/85 px-2.5 py-1.5 text-xs shadow-lg">
            <span class="text-[10px] text-cream-300/60">{{ store.editSource ? 'Nguồn:' : 'Kết quả:' }}</span>
            <span class="max-w-40 truncate font-semibold text-cream-100">{{ store.editSource ? (store.editSource.name || 'Ảnh nguồn') : ('Ảnh #' + store.preview?.id) }}</span>
            <button v-if="store.editSource" @click="store.clearSource()" class="grid h-6 w-6 place-items-center rounded-full bg-ink-700 text-cream-200 hover:bg-red-600" title="Bỏ ảnh nguồn khỏi canvas">✕</button>
            <button v-else @click="store.cleanCanvas()" class="grid h-6 w-6 place-items-center rounded-full bg-ink-700 text-red-200 hover:bg-red-600" title="Dọn canvas (không xóa ảnh kết quả)">🗑</button>
          </div>
          <div ref="canvasZoom" class="absolute inset-0 flex items-center justify-center p-4 cursor-grab active:cursor-grabbing" style="touch-action:none" @wheel.prevent="store.wheelZoom($event)" @pointerdown="store.panStart($event)" @pointermove="store.panMove($event)" @pointerup="store.panEnd" @pointerleave="store.panEnd">
            <img v-if="store.upscaleSrc" ref="cvImg" :src="store.upscaleSrc" class="max-h-full max-w-full min-w-0 select-none object-contain" :style="{ transform: 'translate(' + store.pan.x + 'px, ' + store.pan.y + 'px) scale(' + store.zoom + ')', transformOrigin: 'center' }" draggable="false" @load="store.onCanvasImgLoad()" />
            <p v-else class="text-sm text-cream-300/60">Chọn/hiện một ảnh (Nguồn hoặc Kết quả) để làm việc.</p>
            <div v-if="store.cropMode && store.upscaleSrc" class="pointer-events-none absolute inset-0" style="z-index:30">
              <div class="absolute cursor-move select-none" style="pointer-events:auto; touch-action:none" :style="store.cropStyle()" @pointerdown.stop="store.cropStart($event,'move')" @dblclick="store.toggleCrop" title="Kéo để di chuyển · nhấn đúp để hủy">
                <div class="pointer-events-none absolute inset-0 border-2 border-dashed border-brand-300" style="box-shadow: 0 0 0 9999px rgba(0,0,0,0.55);"></div>
                <div class="pointer-events-none absolute inset-0 opacity-30">
                  <div class="absolute left-1/3 top-0 h-full w-px bg-brand-300/60"></div>
                  <div class="absolute left-2/3 top-0 h-full w-px bg-brand-300/60"></div>
                  <div class="absolute left-0 top-1/3 h-px w-full bg-brand-300/60"></div>
                  <div class="absolute left-0 top-2/3 h-px w-full bg-brand-300/60"></div>
                </div>
                <div class="pointer-events-none absolute -bottom-6 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full bg-ink-900/90 px-2 py-0.5 text-[10px] font-semibold text-brand-200">{{ store.cropSizeLabel() }}</div>
                <div class="absolute -left-2 -top-2 h-4 w-4 cursor-nwse-resize rounded-sm border-2 border-white bg-brand-400 shadow" style="pointer-events:auto; touch-action:none" @pointerdown.stop="store.cropStart($event,'nw')" @dblclick.stop></div>
                <div class="absolute -right-2 -top-2 h-4 w-4 cursor-nesw-resize rounded-sm border-2 border-white bg-brand-400 shadow" style="pointer-events:auto; touch-action:none" @pointerdown.stop="store.cropStart($event,'ne')" @dblclick.stop></div>
                <div class="absolute -bottom-2 -left-2 h-4 w-4 cursor-nesw-resize rounded-sm border-2 border-white bg-brand-400 shadow" style="pointer-events:auto; touch-action:none" @pointerdown.stop="store.cropStart($event,'sw')" @dblclick.stop></div>
                <div class="absolute -bottom-2 -right-2 h-4 w-4 cursor-nwse-resize rounded-sm border-2 border-white bg-brand-400 shadow" style="pointer-events:auto; touch-action:none" @pointerdown.stop="store.cropStart($event,'se')" @dblclick.stop></div>
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
          <!-- right column: layers (top) + floating palette (below, same width) -->
          <div class="absolute right-3 top-3 z-20 flex w-56 flex-col gap-1.5">
            <div v-if="store.canvasLayers.length" class="flex flex-col gap-1.5 rounded-2xl bg-ink-900/85 p-2 shadow-lg">
              <div class="flex items-center justify-between px-0.5">
                <p class="text-[10px] font-semibold text-cream-300/60">Layers ({{ store.canvasLayers.length }})</p>
                <button @click="store.cleanCanvas()" class="text-[9px] font-semibold text-red-300 hover:text-red-200" title="Dọn canvas — bỏ hết ảnh trên canvas (không xóa kết quả)">Dọn canvas</button>
              </div>
              <div class="scrollbar-hide flex max-h-44 flex-col gap-1.5 overflow-y-auto">
                <div v-for="(l, i) in store.canvasLayers" :key="l.id" class="group relative flex items-center gap-1 rounded-lg border p-1" :class="[store.activeLayerId === l.id ? 'border-brand-500 bg-brand-600/20' : 'border-ink-700/60', l.visible ? '' : 'opacity-40']">
                  <button @click="store.toggleLayerVisible(l.id)" class="grid h-5 w-5 shrink-0 place-items-center rounded text-cream-200 hover:bg-ink-700" :title="l.visible ? 'Ẩn layer' : 'Hiện layer'">{{ l.visible ? '👁' : '–' }}</button>
                  <button @click="store.selectLayer(l)" class="flex min-w-0 flex-1 items-center gap-1.5 text-left">
                    <img :src="l.image" class="h-7 w-7 shrink-0 rounded bg-ink-900 object-cover">
                    <span v-if="renamingId !== l.id" class="truncate text-[10px] text-cream-100">{{ l.name }}</span>
                    <input v-else v-model="renameValue" class="w-full min-w-0 rounded bg-ink-900 px-1 py-0.5 text-[10px] text-cream-100 outline-none ring-1 ring-brand-500" @keyup.enter="commitRename()" @keyup.esc="cancelRename()" @blur="commitRename()" @click.stop>
                  </button>
                  <div class="flex shrink-0 items-center gap-0.5 opacity-0 transition-opacity group-hover:opacity-100">
                    <button @click="store.toggleLayerLock(l.id)" class="grid h-5 w-5 place-items-center rounded" :class="l.locked ? 'bg-amber-500/20 text-amber-300' : 'text-cream-300 hover:bg-ink-700'" :title="l.locked ? 'Mở khóa' : 'Khóa layer'">{{ l.locked ? '🔒' : '🔓' }}</button>
                    <button @click="store.moveLayer(l.id, 'up')" :disabled="i === 0 || l.locked" class="grid h-5 w-4 place-items-center rounded text-cream-300 hover:bg-ink-700 disabled:opacity-30" title="Lên trên">▲</button>
                    <button @click="store.moveLayer(l.id, 'down')" :disabled="i === store.canvasLayers.length - 1 || l.locked" class="grid h-5 w-4 place-items-center rounded text-cream-300 hover:bg-ink-700 disabled:opacity-30" title="Xuống dưới">▼</button>
                    <button v-if="renamingId !== l.id" @click="startRename(l)" :disabled="l.locked" class="grid h-5 w-5 place-items-center rounded text-cream-300 hover:bg-ink-700 disabled:opacity-30" title="Đổi tên">✎</button>
                    <button @click="store.deleteLayer(l)" :disabled="l.locked" class="grid h-5 w-5 place-items-center rounded bg-red-600/25 text-red-200 hover:bg-red-600 disabled:opacity-30" :title="l.locked ? 'Đang khóa' : 'Gỡ khỏi canvas (không xóa kết quả)'">🗑</button>
                  </div>
                </div>
              </div>
            </div>
            <p v-else class="rounded-2xl bg-ink-900/50 px-2 py-2.5 text-center text-[10px] text-cream-300/40">Chưa có layer — thêm ảnh nguồn hoặc kết quả.</p>
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
