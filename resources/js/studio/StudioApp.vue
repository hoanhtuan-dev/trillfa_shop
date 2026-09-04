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
onMounted(async () => { store.load(); store.loadPalette(store.previewId); window.addEventListener('keydown', onCanvasKey); window.addEventListener('keydown', onLayerKeys); });
onBeforeUnmount(() => { window.removeEventListener('keydown', onCanvasKey); window.removeEventListener('keydown', onLayerKeys); });
watch(() => store.previewId, (id) => { store.loadPalette(id); });
// Template refs -> store: StudioApp owns the canvas DOM; the store needs the elements for crop geometry.
const cvImg = ref(null);
const canvasZoom = ref(null);
watch([cvImg, canvasZoom], ([img, zoom]) => { store.setCanvasRefs(img, zoom); });
// While crop mode is on: re-fit the box when the ratio changes, re-init when the image changes.
watch(() => store.reframeRatio, () => { if (store.cropMode) store.refitCropBox(); });
// Khi đổi layer, KHÔNG reset zoom/pan — giữ nguyên khung nhìn của người dùng.
watch(() => store.upscaleSrc, () => {
  if (store.cropMode) store.initCropBox();
});
function onCanvasKey(e) {
  if (!store.cropMode && store.inpaintMaskMode === 'none') return;
  const t = e.target;
  if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT' || t.isContentEditable)) return;
  if (e.key === 'Escape') { if (store.inpaintMaskMode !== 'none') { store.inpaintMaskMode = 'none'; store.inpaintBrushData = ''; } else store.toggleCrop(); }
  else if (e.key === 'Enter' && !(t && t.tagName === 'BUTTON') && store.inpaintMaskMode === 'none') store.confirmCrop();
}
// Phím tắt cho layer (chế độ stack): mũi tên di chuyển, Ctrl/Cmd+D nhân đôi.
function onLayerKeys(e) {
  if (store.cropMode || store.inpaintMaskMode !== 'none') return;
  const t = e.target;
  if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT' || t.isContentEditable)) return;
  const l = store.activeLayer;
  if (!l || l.locked) return;
  if ((e.key === 'd' || e.key === 'D') && (e.ctrlKey || e.metaKey)) { e.preventDefault(); store.duplicateLayer(l.id); return; }
  const step = e.shiftKey ? 10 : 1;
  let dx = 0, dy = 0;
  if (e.key === 'ArrowLeft') dx = -step;
  else if (e.key === 'ArrowRight') dx = step;
  else if (e.key === 'ArrowUp') dy = -step;
  else if (e.key === 'ArrowDown') dy = step;
  else return;
  e.preventDefault();
  store.updateLayerTransform(l.id, { x: (l.x || 0) + dx, y: (l.y || 0) + dy });
}
const bgClass = computed(() => ({ grid: 'cvs-checker', dark: 'bg-ink-950', white: 'bg-white', cream: 'bg-cream-100' }[store.canvasBg] || 'cvs-checker'));
const panel = computed(() => store.step === 1 ? [StylistCard, SuggestCard, ConceptCard] : store.step === 2 ? [ComposeCard, InpaintCard, UpscaleCard] : [DirectorCard]); // [SWAP TẠM ẨN: bỏ SwapCard]

// ── Layer editor (composite + transform) ──
const isolateActive = computed(() => store.cropMode || store.inpaintMaskMode !== 'none');
function layerStyle(l, i) {
  return {
    transform: 'translate(-50%, -50%) translate(' + (l.x || 0) + 'px, ' + (l.y || 0) + 'px) rotate(' + (l.rotation || 0) + 'deg) scale(' + (l.scale || 1) + ')',
    transformOrigin: 'center',
    opacity: l.opacity != null ? l.opacity : 1,
    mixBlendMode: (l.blend && l.blend !== 'normal') ? l.blend : 'normal',
    zIndex: i + 1,
  };
}
function onLayerPointerDown(l, e) {
  if (l.locked) { store.selectLayer(l); return; }
  store.beginLayerDrag(l.id, e);
  const move = (ev) => store.layerDragMove(ev);
  const up = () => { store.endLayerDrag(); window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', up); window.removeEventListener('pointercancel', up); };
  window.addEventListener('pointermove', move);
  window.addEventListener('pointerup', up);
  window.addEventListener('pointercancel', up);
}
function layerCenterScreen(l) {
  const el = store.canvasZoom;
  if (!el) return { cx: 0, cy: 0 };
  const r = el.getBoundingClientRect();
  return {
    cx: r.left + r.width / 2 + (l.x || 0) * store.zoom + store.pan.x,
    cy: r.top + r.height / 2 + (l.y || 0) * store.zoom + store.pan.y,
  };
}
function onScalePointerDown(l, e) {
  e.stopPropagation();
  const c = layerCenterScreen(l);
  const startDist = Math.hypot(e.clientX - c.cx, e.clientY - c.cy) || 1;
  const startScale = l.scale || 1;
  const move = (ev) => {
    const d = Math.hypot(ev.clientX - c.cx, ev.clientY - c.cy) / startDist;
    l.scale = Math.max(0.05, Math.min(8, startScale * d));
  };
  const up = () => { store.saveLayerLayout(); window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', up); window.removeEventListener('pointercancel', up); };
  window.addEventListener('pointermove', move);
  window.addEventListener('pointerup', up);
  window.addEventListener('pointercancel', up);
}
function onRotatePointerDown(l, e) {
  e.stopPropagation();
  const c = layerCenterScreen(l);
  const startAngle = Math.atan2(e.clientY - c.cy, e.clientX - c.cx);
  const startRotation = l.rotation || 0;
  const move = (ev) => {
    const a = Math.atan2(ev.clientY - c.cy, ev.clientX - c.cx);
    let r = startRotation + (a - startAngle) * (180 / Math.PI);
    r = ((r % 360) + 360) % 360;
    if (r > 180) r -= 360;
    l.rotation = Math.round(r);
  };
  const up = () => { store.saveLayerLayout(); window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', up); window.removeEventListener('pointercancel', up); };
  window.addEventListener('pointermove', move);
  window.addEventListener('pointerup', up);
  window.addEventListener('pointercancel', up);
}
let bgDownPos = null;
function onCanvasBgDown(e) {
  store.panStart(e);
  bgDownPos = { x: e.clientX, y: e.clientY };
}
function onCanvasBgUp(e) {
  store.panEnd();
  if (bgDownPos && !isolateActive.value && Math.hypot(e.clientX - bgDownPos.x, e.clientY - bgDownPos.y) < 5) {
    store.deselectAll();
  }
  bgDownPos = null;
}
</script>
<template>
  <div class="studio-dark flex h-full flex-col bg-ink-950 text-cream-100">
    <!-- Chưa đăng nhập: studio cần session admin để tải data -->
    <div v-if="store.needsLogin" class="flex items-center justify-between gap-3 border-b border-amber-500/40 bg-amber-900/30 px-4 py-2.5">
      <p class="text-xs text-amber-100">🔒 Chưa đăng nhập — dữ liệu ✨ Trợ lý thiết kế đã load được; để tạo ảnh/video & lưu dữ liệu cần tài khoản admin.</p>
      <a href="/dang-nhap?redirect=/studio" class="shrink-0 rounded-full bg-amber-500 px-3 py-1 text-xs font-semibold text-black transition hover:bg-amber-400">Đăng nhập</a>
    </div>
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
            <span class="hidden text-[10px] text-cream-300/60 lg:inline">{{ store.editSource ? 'Nguồn:' : 'Kết quả:' }}</span>
            <span class="hidden max-w-40 truncate font-semibold text-cream-100 lg:inline">{{ store.upscaleName }}</span>
            <button @click="store.downloadActive()" class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-brand-600 text-white shadow-brand-500/40 transition-colors hover:bg-brand-500" title="Tải ảnh xuống"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></button>
            <button v-if="store.editSource" @click="store.clearSource()" class="grid h-6 w-6 place-items-center rounded-full bg-ink-700 text-cream-200 hover:bg-red-600" title="Bỏ ảnh nguồn khỏi canvas">✕</button>
          </div>
          <div ref="canvasZoom" class="absolute inset-0 cursor-grab active:cursor-grabbing" style="touch-action:none" @wheel.prevent="store.wheelZoom($event)" @pointerdown="onCanvasBgDown($event)" @pointermove="store.panMove($event)" @pointerup="onCanvasBgUp($event)" @pointerleave="store.panEnd">
            <!-- Chế độ isolate (crop/inpaint): chỉ hiển thị layer active như trước để đo vùng chính xác -->
            <div v-if="isolateActive" class="absolute inset-0 flex items-center justify-center p-4">
              <img v-if="store.upscaleSrc" ref="cvImg" :src="store.upscaleSrc" class="max-h-full max-w-full min-w-0 select-none object-contain" :style="{ transform: 'translate(' + store.pan.x + 'px, ' + store.pan.y + 'px) scale(' + store.zoom + ')', transformOrigin: 'center' }" draggable="false" @load="store.onCanvasImgLoad()" />
              <p v-else class="text-sm text-cream-300/60">Chọn/hiện một ảnh (Nguồn hoặc Kết quả) để làm việc.</p>
            </div>
            <!-- Chế độ stack: composite tất cả layer đang hiển thị -->
            <div v-else class="absolute inset-0">
              <div class="absolute left-1/2 top-1/2" :style="{ transform: 'translate(-50%, -50%) translate(' + store.pan.x + 'px, ' + store.pan.y + 'px) scale(' + store.zoom + ')' }">
                <div v-for="(l, i) in store.visibleLayers" :key="l.id" class="absolute left-0 top-0" :style="layerStyle(l, i)" @pointerdown.stop="onLayerPointerDown(l, $event)">
                  <img :src="l.image" class="relative block max-h-[512px] max-w-[512px] select-none" :class="l.id === store.activeLayerId ? 'ring-2 ring-brand-400' : ''" draggable="false" />
                  <template v-if="l.id === store.activeLayerId && !l.locked">
                    <div class="absolute -bottom-3 -right-3 h-4 w-4 cursor-nwse-resize rounded-sm border-2 border-white bg-brand-400 shadow" @pointerdown.stop="onScalePointerDown(l, $event)" title="Kéo để phóng to/thu nhỏ"></div>
                    <div class="absolute -top-7 left-1/2 h-4 w-4 -translate-x-1/2 cursor-grab rounded-full border-2 border-white bg-brand-400 shadow" @pointerdown.stop="onRotatePointerDown(l, $event)" title="Kéo để xoay"></div>
                  </template>
                </div>
              </div>
              <p v-if="!store.visibleLayers.length" class="absolute inset-0 grid place-items-center text-sm text-cream-300/60">Chọn/hiện một ảnh (Nguồn hoặc Kết quả) để làm việc.</p>
            </div>
            <!-- Đường guide khi bắt điểm (snap) -->
            <div v-if="store.snapX != null" class="pointer-events-none absolute inset-y-0 z-40 w-px bg-brand-400/80" :style="{ left: 'calc(50% + ' + (store.snapX * store.zoom + store.pan.x) + 'px)' }"></div>
            <div v-if="store.snapY != null" class="pointer-events-none absolute inset-x-0 z-40 h-px bg-brand-400/80" :style="{ top: 'calc(50% + ' + (store.snapY * store.zoom + store.pan.y) + 'px)' }"></div>
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
          <div class="absolute right-3 top-3 z-20 flex w-52 flex-col gap-1.5 lg:w-64">
            <div v-if="store.canvasLayers.length" class="flex flex-col gap-1.5 rounded-2xl bg-ink-900/85 p-2 shadow-lg">
              <div class="flex items-center justify-between px-0.5">
                <p class="text-[10px] font-semibold text-cream-300/60">Layers ({{ store.canvasLayers.length }})</p>
                <button @click="store.cleanCanvas()" class="text-[9px] font-semibold text-red-300 hover:text-red-200" title="Dọn canvas — bỏ hết ảnh trên canvas (không xóa kết quả)">Dọn canvas</button>
              </div>
              <div class="scrollbar-hide flex max-h-44 flex-col gap-1.5 overflow-y-auto">
                <div v-for="(l, i) in store.canvasLayers" :key="l.id" class="group relative flex items-center gap-1 rounded-lg border p-1" :class="[store.activeLayerId === l.id ? 'border-brand-500 bg-brand-600/20' : 'border-ink-700/60', l.visible ? '' : 'opacity-40']">
                  <button @click="store.toggleLayerVisible(l.id)" class="hidden h-5 w-5 shrink-0 place-items-center rounded text-cream-200 hover:bg-ink-700 lg:grid" :title="l.visible ? 'Ẩn layer' : 'Hiện layer'">{{ l.visible ? '👁' : '–' }}</button>
                  <button @click="store.selectLayer(l)" class="flex min-w-0 flex-1 items-center gap-1.5 text-left">
                    <img :src="l.image" class="h-7 w-7 shrink-0 rounded bg-ink-900 object-cover">
                    <span v-if="renamingId !== l.id" class="hidden truncate text-[10px] text-cream-100 lg:inline">{{ l.name }}</span>
                    <input v-else v-model="renameValue" class="w-full min-w-0 rounded bg-ink-900 px-1 py-0.5 text-[10px] text-cream-100 outline-none ring-1 ring-brand-500" @keyup.enter="commitRename()" @keyup.esc="cancelRename()" @blur="commitRename()" @click.stop>
                  </button>
                  <div class="hidden shrink-0 items-center gap-0.5 opacity-0 transition-opacity group-hover:opacity-100 lg:flex">
                    <button @click="store.toggleLayerLock(l.id)" class="grid h-5 w-5 place-items-center rounded" :class="l.locked ? 'bg-amber-500/20 text-amber-300' : 'text-cream-300 hover:bg-ink-700'" :title="l.locked ? 'Mở khóa' : 'Khóa layer'">{{ l.locked ? '🔒' : '🔓' }}</button>
                    <button @click="store.moveLayer(l.id, 'up')" :disabled="i === 0 || l.locked" class="grid h-5 w-4 place-items-center rounded text-cream-300 hover:bg-ink-700 disabled:opacity-30" title="Lên trên">▲</button>
                    <button @click="store.moveLayer(l.id, 'down')" :disabled="i === store.canvasLayers.length - 1 || l.locked" class="grid h-5 w-4 place-items-center rounded text-cream-300 hover:bg-ink-700 disabled:opacity-30" title="Xuống dưới">▼</button>
                    <button v-if="renamingId !== l.id" @click="startRename(l)" :disabled="l.locked" class="grid h-5 w-5 place-items-center rounded text-cream-300 hover:bg-ink-700 disabled:opacity-30" title="Đổi tên">✎</button>
                    <button @click="store.deleteLayer(l)" :disabled="l.locked" class="grid h-5 w-5 place-items-center rounded bg-red-600/25 text-red-200 hover:bg-red-600 disabled:opacity-30" :title="l.locked ? 'Đang khóa' : 'Gỡ khỏi canvas (không xóa kết quả)'">🗑</button>
                  </div>
                </div>
              </div>
              <div class="mt-1 grid grid-cols-2 gap-1.5 border-t border-ink-700/60 pt-1.5">
                <button @click="store.exportComposite()" :disabled="!store.visibleLayers.length" class="rounded-lg bg-ink-800 px-2 py-1.5 text-[9px] font-semibold text-cream-200 hover:bg-ink-700 disabled:opacity-40" title="Gộp tất cả layer đang hiển thị và tải xuống PNG">⬇ Xuất ảnh gộp</button>
                <button @click="store.flattenToLayer()" :disabled="!store.visibleLayers.length" class="rounded-lg bg-brand-600 px-2 py-1.5 text-[9px] font-semibold text-white hover:bg-brand-500 disabled:opacity-40" title="Gộp tất cả layer đang hiển thị thành 1 layer mới">🧩 Gộp thành layer</button>
              </div>
            </div>
            <p v-else class="rounded-2xl bg-ink-900/50 px-2 py-2.5 text-center text-[10px] text-cream-300/40">Chưa có layer — thêm ảnh nguồn hoặc kết quả.</p>
            <div v-if="store.activeLayer" class="hidden rounded-2xl bg-ink-900/90 p-2 shadow-lg lg:block">
              <div class="mb-1 flex items-center justify-between px-0.5">
                <p class="text-[10px] font-semibold text-cream-300/60">✋ Transform</p>
                <button @click="store.resetLayerTransform(store.activeLayer.id)" class="text-[9px] font-semibold text-red-300 hover:text-red-200" title="Đưa layer về mặc định">Reset all</button>
              </div>
              <div class="flex items-center gap-1.5">
                <span class="w-14 shrink-0 whitespace-nowrap text-[9px] text-cream-300/60">Opacity</span>
                <input type="range" min="0" max="1" step="0.05" :value="store.activeLayer.opacity" @input="store.updateLayerTransform(store.activeLayer.id, { opacity: Number($event.target.value) })" class="h-1.5 min-w-0 flex-1 accent-brand-500">
                <span class="w-9 shrink-0 whitespace-nowrap text-right text-[9px] text-cream-200">{{ Math.round(store.activeLayer.opacity * 100) }}%</span>
                <button @click="store.updateLayerTransform(store.activeLayer.id, { opacity: 1 })" class="grid h-4 w-4 shrink-0 place-items-center rounded text-cream-300 hover:bg-ink-700" title="Reset opacity">↺</button>
              </div>
              <div class="mt-1 flex items-center gap-1.5">
                <span class="w-14 shrink-0 whitespace-nowrap text-[9px] text-cream-300/60">Blend</span>
                <select :value="store.activeLayer.blend" @change="store.updateLayerTransform(store.activeLayer.id, { blend: $event.target.value })" class="min-w-0 flex-1 rounded bg-ink-800 px-1 py-0.5 text-[10px] text-cream-100">
                  <option value="normal">Normal</option>
                  <option value="multiply">Multiply</option>
                  <option value="screen">Screen</option>
                  <option value="overlay">Overlay</option>
                  <option value="darken">Darken</option>
                  <option value="lighten">Lighten</option>
                </select>
                <button @click="store.updateLayerTransform(store.activeLayer.id, { blend: 'normal' })" class="grid h-4 w-4 shrink-0 place-items-center rounded text-cream-300 hover:bg-ink-700" title="Reset blend">↺</button>
              </div>
              <div class="mt-1 flex items-center gap-1.5">
                <span class="w-14 shrink-0 whitespace-nowrap text-[9px] text-cream-300/60">Scale</span>
                <input type="range" min="0.2" max="3" step="0.05" :value="store.activeLayer.scale" @input="store.updateLayerTransform(store.activeLayer.id, { scale: Number($event.target.value) })" class="h-1.5 min-w-0 flex-1 accent-brand-500">
                <span class="w-9 shrink-0 whitespace-nowrap text-right text-[9px] text-cream-200">{{ Math.round(store.activeLayer.scale * 100) }}%</span>
                <button @click="store.updateLayerTransform(store.activeLayer.id, { scale: 1 })" class="grid h-4 w-4 shrink-0 place-items-center rounded text-cream-300 hover:bg-ink-700" title="Reset scale">↺</button>
              </div>
              <div class="mt-1 flex items-center gap-1.5">
                <span class="w-14 shrink-0 whitespace-nowrap text-[9px] text-cream-300/60">Xoay</span>
                <input type="range" min="-180" max="180" step="1" :value="store.activeLayer.rotation" @input="store.updateLayerTransform(store.activeLayer.id, { rotation: Number($event.target.value) })" class="h-1.5 min-w-0 flex-1 accent-brand-500">
                <span class="w-9 shrink-0 whitespace-nowrap text-right text-[9px] text-cream-200">{{ store.activeLayer.rotation }}°</span>
                <button @click="store.updateLayerTransform(store.activeLayer.id, { rotation: 0 })" class="grid h-4 w-4 shrink-0 place-items-center rounded text-cream-300 hover:bg-ink-700" title="Reset rotation">↺</button>
              </div>
              <div class="mt-1.5 grid grid-cols-3 gap-1.5 border-t border-ink-700/60 pt-1.5">
                <button @click="store.duplicateLayer(store.activeLayer.id)" class="rounded-lg bg-ink-800 px-1 py-1.5 text-[9px] font-semibold text-cream-200 hover:bg-ink-700" title="Nhân đôi layer (Ctrl+D)">📄 Nhân đôi</button>
                <button @click="store.bringLayerTo(store.activeLayer.id, 'front')" class="rounded-lg bg-ink-800 px-1 py-1.5 text-[9px] font-semibold text-cream-200 hover:bg-ink-700" title="Đưa lên trên cùng">⤒ Lên trên</button>
                <button @click="store.bringLayerTo(store.activeLayer.id, 'back')" class="rounded-lg bg-ink-800 px-1 py-1.5 text-[9px] font-semibold text-cream-200 hover:bg-ink-700" title="Đưa xuống dưới cùng">⤓ Xuống dưới</button>
              </div>
            </div>
            <div v-if="store.palette.length && store.step !== 3 && store.previewId" class="hidden rounded-2xl bg-ink-900/90 px-2.5 py-1.5 shadow-xl lg:block">
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
