<script setup>
import { ref, onMounted, onBeforeUnmount, computed, watch, nextTick } from 'vue';
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
import ContextToolbar from './components/ContextToolbar.vue';
import DirectorCard from './components/DirectorCard.vue';
import SourcePanel from './components/SourcePanel.vue';
import OutputModule from './components/OutputModule.vue';
import GalleryModal from './components/GalleryModal.vue';
const store = useStudioStore();
function copyColor(c) {
  store.inpaintFillColor = c; // đồng bộ màu cho công cụ tô màu
  try { navigator.clipboard.writeText(c); store.toast('Đã chọn màu ' + c); } catch (e) { store.toast('Lỗi copy.', 'error'); }
}
const stepNav = [['1','Concept'],['2','Fitting Room'],['3','Director']];
const menuOpen = ref(false);
const outputOpen = ref(false);
// Layer rename (inline edit)
const renamingId = ref(null);
const renameValue = ref('');
function startRename(l) { renamingId.value = l.id; renameValue.value = l.name || ''; }
function commitRename() { if (!renamingId.value) return; store.renameLayer(renamingId.value, renameValue.value); renamingId.value = null; }
function cancelRename() { renamingId.value = null; }
onMounted(async () => { store.load(); store.loadPalette(store.previewId); window.addEventListener('keydown', onCanvasKey); window.addEventListener('keydown', onLayerKeys); window.addEventListener('keydown', onHistoryKeys); });
onBeforeUnmount(() => { window.removeEventListener('keydown', onCanvasKey); window.removeEventListener('keydown', onLayerKeys); window.removeEventListener('keydown', onHistoryKeys); });
watch(() => store.previewId, (id) => { store.loadPalette(id); });
// Template refs -> store: StudioApp owns the canvas DOM; the store needs the elements for crop geometry.
const cvImg = ref(null);
const canvasZoom = ref(null);
const eraseOverlay = ref(null);
watch([cvImg, canvasZoom], ([img, zoom]) => { store.setCanvasRefs(img, zoom); });
watch(eraseOverlay, (el) => store.attachEraseCanvas(el));
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
// Phím tắt undo/redo toàn cục (Ctrl+Z / Ctrl+Shift+Z / Ctrl+Y) — trừ khi đang vẽ mask brush.
function onHistoryKeys(e) {
  const t = e.target;
  if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT' || t.isContentEditable)) return;
  if (!(e.ctrlKey || e.metaKey)) return;
  if (store.inpaintMaskMode === 'brush') return; // brush dùng Ctrl+Z riêng cho undo nét vẽ
  if (e.key === 'z' || e.key === 'Z') { e.preventDefault(); if (e.shiftKey) store.redo(); else store.undo(); }
  else if (e.key === 'y' || e.key === 'Y') { e.preventDefault(); store.redo(); }
}
const bgClass = computed(() => ({ grid: 'cvs-checker', dark: 'bg-ink-950', white: 'bg-white', cream: 'bg-cream-100' }[store.canvasBg] || 'cvs-checker'));
const panel = computed(() => store.step === 1 ? [StylistCard, SuggestCard, ConceptCard] : store.step === 2 ? [ComposeCard, InpaintCard, UpscaleCard] : [DirectorCard]); // [SWAP TẠM ẨN: bỏ SwapCard]

// ── Layer editor (composite + transform) ──
const isolateActive = computed(() => store.cropMode || store.inpaintMaskMode !== 'none' || store.eraseMode || store.regionSelectMode);
function layerStyle(l, i) {
  return {
    transform: 'translate(-50%, -50%) translate(' + (l.x || 0) + 'px, ' + (l.y || 0) + 'px) rotate(' + (l.rotation || 0) + 'deg) scale(' + ((l.scale || 1) * (l.flipX ? -1 : 1)) + ', ' + ((l.scale || 1) * (l.flipY ? -1 : 1)) + ')',
    transformOrigin: 'center',
    opacity: l.opacity != null ? l.opacity : 1,
    mixBlendMode: (l.blend && l.blend !== 'normal') ? l.blend : 'normal',
    zIndex: i + 1,
  };
}
function regionBoxStyle() {
  const b = store.regionBox || { x: 0.25, y: 0.25, w: 0.5, h: 0.5 };
  return { left: (b.x * 100) + '%', top: (b.y * 100) + '%', width: (b.w * 100) + '%', height: (b.h * 100) + '%' };
}
// Transform của layer active trong chế độ isolate — giữ ĐÚNG vị trí/scale/rotation như ở stack (không xê dịch).
const isolateLayerStyle = computed(() => {
  const l = store.activeLayer;
  if (!l) return {};
  return {
    transform: 'translate(-50%, -50%) translate(' + (l.x || 0) + 'px, ' + (l.y || 0) + 'px) rotate(' + (l.rotation || 0) + 'deg) scale(' + ((l.scale || 1) * (l.flipX ? -1 : 1)) + ', ' + ((l.scale || 1) * (l.flipY ? -1 : 1)) + ')',
    transformOrigin: 'center',
    opacity: l.opacity != null ? l.opacity : 1,
    mixBlendMode: (l.blend && l.blend !== 'normal') ? l.blend : 'normal',
  };
});
// Overlay canvas xóa bám đúng vùng ảnh hiển thị (chịu zoom/pan) — khớp canvasMetrics.
const eraseTick = ref(0);
watch([() => store.zoom, () => store.pan.x, () => store.pan.y, () => store.upscaleSrc], () => { nextTick(() => { eraseTick.value++; }); });
const eraseOverlayStyle = computed(() => {
  void eraseTick.value;
  const m = store.canvasMetrics();
  if (!m || !store.eraseMode) return { display: 'none' };
  return {
    left: (m.vx / m.crW * 100) + '%',
    top: (m.vy / m.crH * 100) + '%',
    width: (m.vw / m.crW * 100) + '%',
    height: (m.vh / m.crH * 100) + '%',
    touchAction: 'none',
  };
});
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
function onTouchStart(e) {
  if (e.touches.length === 2) store.beginPinch(e.touches[0], e.touches[1]);
}
function onTouchMove(e) {
  if (e.touches.length === 2) {
    if (e.cancelable) e.preventDefault();
    store.pinchMove(e.touches[0], e.touches[1]);
  }
}
function onTouchEnd(e) {
  if (e.touches.length < 2) store.endPinch();
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
    <div v-if="store.flashMsg" class="pointer-events-none fixed left-1/2 bottom-5 z-[90] -translate-x-1/2 rounded-full px-4 py-2 text-xs font-semibold shadow-2xl" :class="store.flashType === 'error' ? 'bg-red-600 text-white' : 'bg-ink-800 text-cream-100 border border-brand-500/40'">{{ store.flashMsg }}</div>
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
        <div class="relative flex h-full flex-col overflow-hidden rounded-2xl border border-ink-700" :class="bgClass">
          <!-- ══ Toolbar dock (phía trên, full width) ══ -->
          <div class="relative z-40 flex min-h-12 shrink-0 items-center justify-center gap-2 overflow-x-auto overflow-y-hidden border-b border-ink-700/40 px-3 py-1.5">
            <ContextToolbar />
          </div>
          <!-- ══ Vùng canvas (dưới dock) ══ -->
          <div class="relative flex-1 overflow-hidden">
          <!-- Floating tools (Reframe + Film Look) — desktop only -->
          <div class="hidden lg:block">
            <RegionTools />
          </div>
          <!-- Inpaint mask overlay on canvas -->
          <CanvasMaskTools />
          <!-- active image source clear -->
          <div v-if="store.editSource" class="absolute left-3 top-3 z-30 flex items-center gap-1.5 rounded-full bg-ink-900/85 px-2.5 py-1.5 text-xs shadow-lg">
            <button @click="store.clearSource()" class="grid h-6 w-6 place-items-center rounded-full bg-ink-700 text-cream-200 hover:bg-red-600" title="Bỏ ảnh nguồn khỏi canvas">✕</button>
          </div>
          <div ref="canvasZoom" class="absolute inset-0 cursor-grab active:cursor-grabbing" style="touch-action:none" @wheel.prevent="store.wheelZoom($event)" @pointerdown="onCanvasBgDown($event)" @pointermove="store.panMove($event)" @pointerup="onCanvasBgUp($event)" @pointerleave="store.panEnd" @touchstart="onTouchStart($event)" @touchmove="onTouchMove($event)" @touchend="onTouchEnd($event)">
            <!-- Chế độ isolate (crop/inpaint/erase): layer active GIỮ ĐÚNG vị trí/scale như stack — không xê dịch -->
            <div v-if="isolateActive" class="absolute inset-0">
              <div v-if="store.upscaleSrc" class="absolute left-1/2 top-1/2" :style="{ transform: 'translate(-50%, -50%) translate(' + store.pan.x + 'px, ' + store.pan.y + 'px) scale(' + store.zoom + ')' }">
                <div class="absolute left-0 top-0" :style="isolateLayerStyle">
                  <img ref="cvImg" :src="store.upscaleSrc" class="block max-h-[512px] max-w-[512px] min-w-0 select-none" draggable="false" @load="store.onCanvasImgLoad()" />
                </div>
              </div>
              <p v-else class="text-sm text-cream-300/60">Chọn/hiện một ảnh (Nguồn hoặc Kết quả) để làm việc.</p>
            </div>
            <!-- Chế độ stack: composite tất cả layer đang hiển thị -->
            <div v-else class="absolute inset-0">
              <div class="absolute left-1/2 top-1/2" :style="{ transform: 'translate(-50%, -50%) translate(' + store.pan.x + 'px, ' + store.pan.y + 'px) scale(' + store.zoom + ')' }">
                <div v-for="(l, i) in store.visibleLayers" :key="l.id" class="absolute left-0 top-0" :style="layerStyle(l, i)" @pointerdown.stop="onLayerPointerDown(l, $event)">
                  <img :src="l.image" class="relative block max-h-[512px] max-w-[512px] cursor-move select-none" :class="l.id === store.activeLayerId ? 'ring-2 ring-brand-400' : ''" draggable="false" />
                  <template v-if="l.id === store.activeLayerId && !l.locked">
                    <div class="absolute -bottom-3 -right-3 h-4 w-4 cursor-nwse-resize rounded-sm border-2 border-white bg-brand-400 shadow" @pointerdown.stop="onScalePointerDown(l, $event)" title="Kéo để phóng to/thu nhỏ"></div>
                    <div class="absolute -top-7 left-1/2 h-4 w-4 -translate-x-1/2 cursor-grab rounded-full border-2 border-white bg-brand-400 shadow" @pointerdown.stop="onRotatePointerDown(l, $event)" title="Kéo để xoay"></div>
                  </template>
                </div>
              </div>
              <p v-if="!store.visibleLayers.length" class="absolute inset-0 grid place-items-center text-sm text-cream-300/60">Chọn/hiện một ảnh (Nguồn hoặc Kết quả) để làm việc.</p>
            </div>
            <!-- Overlay canvas xóa: bám đúng vùng ảnh hiển thị (chịu zoom/pan) -->
            <canvas v-if="store.eraseMode" ref="eraseOverlay" class="absolute z-30 cursor-crosshair rounded bg-red-500/10" :style="eraseOverlayStyle" @pointerdown.stop="store.beginEraseBrush($event)" @pointermove="store.eraseBrushMove($event)" @pointerup="store.endEraseBrush()" @pointerleave="store.endEraseBrush()"></canvas>
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
          <!-- Canvas toolbar: undo/redo + zoom -->
          <div class="absolute bottom-3 left-3 z-32 flex items-center gap-1 rounded-full bg-ink-900/85 px-2 py-1.5 shadow-lg">
            <button @click="store.undo()" :disabled="!store.undoStack.length" class="grid h-8 w-8 place-items-center rounded-full text-cream-200 hover:bg-ink-700 disabled:opacity-30" title="Hoàn tác (Ctrl+Z)"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"/></svg></button>
            <button @click="store.redo()" :disabled="!store.redoStack.length" class="grid h-8 w-8 place-items-center rounded-full text-cream-200 hover:bg-ink-700 disabled:opacity-30" title="Làm lại (Ctrl+Y)"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 7v6h-6"/><path d="M3 17a9 9 0 0 1 9-9 9 9 0 0 1 6 2.3L21 13"/></svg></button>
            <span class="h-4 w-px bg-ink-700"></span>
            <button @click="store.zoomOut()" class="grid h-8 w-8 place-items-center rounded-full text-cream-200 hover:bg-ink-700">−</button>
            <button @click="store.zoomFit()" class="rounded-full px-2 py-1 text-xs text-cream-200 hover:bg-ink-700">Vừa</button>
            <button @click="store.zoomIn()" class="grid h-8 w-8 place-items-center rounded-full text-cream-200 hover:bg-ink-700">+</button>
            <span class="px-1 text-xs text-cream-200">{{ Math.round(store.zoom * 100) }}%</span>
          </div>
          <!-- right column: layers thumbnails (minimal) + transform/palette -->
          <div class="absolute right-3 top-3 z-32 flex flex-col items-end gap-1.5">
            <!-- Luôn hiển thị: thêm layer + tô màu toàn bộ layer -->
            <div class="flex items-center gap-1.5 rounded-full bg-ink-900/85 p-1.5 shadow-lg">
              <button @click="store.addBlankLayer()" class="grid h-7 w-7 place-items-center rounded-full bg-brand-600 text-white transition-colors hover:bg-brand-500" title="Thêm layer vẽ trống (trùng vị trí layer đang chọn)"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5v14"/></svg></button>
              <button @click="store.fillActiveLayer()" :disabled="!store.activeLayer" class="grid h-7 w-7 place-items-center rounded-full bg-ink-800 text-cream-200 transition-colors hover:bg-ink-700 disabled:opacity-40" title="Tô màu toàn bộ layer đang chọn"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m19 11-8-8-8.5 8.5a2.12 2.12 0 0 0 0 3L12 24l8.5-8.5a2.12 2.12 0 0 0 0-3z"/><path d="M12 2v3"/></svg></button>
            </div>
            <div v-if="store.canvasLayers.length" class="flex flex-col gap-1 lg:hidden">
              <button v-for="l in store.canvasLayers" :key="l.id" @click="store.selectLayer(l)" class="h-6 w-6 shrink-0 overflow-hidden rounded-sm transition" :class="store.activeLayerId === l.id ? 'ring-2 ring-brand-400' : 'opacity-60 hover:opacity-100'" :title="l.name">
                <img :src="l.image" class="h-6 w-6 object-cover" />
              </button>
              <button @click="store.deleteLayer(store.activeLayer)" :disabled="!store.activeLayer" class="grid h-6 w-6 shrink-0 place-items-center rounded-sm text-red-300 hover:bg-red-600 hover:text-white disabled:opacity-30" title="Xóa layer khỏi canvas"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
            </div>
            <div v-if="store.canvasLayers.length" class="hidden w-64 flex-col gap-1.5 rounded-2xl bg-ink-900/85 p-2 shadow-lg lg:flex">
              <div class="flex items-center justify-between px-0.5">
                <p class="text-[10px] font-semibold text-cream-300/60">Layers ({{ store.canvasLayers.length }})</p>
                <button @click="store.cleanCanvas()" class="text-[9px] font-semibold text-red-300 hover:text-red-200" title="Dọn canvas — bỏ hết ảnh trên canvas (không xóa kết quả)">Dọn canvas</button>
              </div>
              <div class="scrollbar-hide flex max-h-44 flex-col gap-1.5 overflow-y-auto">
                <div v-for="(l, i) in store.canvasLayers" :key="l.id" class="group relative flex items-center gap-1 rounded-lg border p-1" :class="[store.activeLayerId === l.id ? 'border-brand-500 bg-brand-600/20' : 'border-ink-700/60', l.visible ? '' : 'opacity-40']">
                  <button @click="store.toggleLayerVisible(l.id)" class="grid h-5 w-5 shrink-0 place-items-center rounded text-cream-200 hover:bg-ink-700" :title="l.visible ? 'Ẩn layer' : 'Hiện layer'"><span v-if="l.visible"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg></span><span v-else><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" y1="2" x2="22" y2="22"/></svg></span></button>
                  <button @click="store.selectLayer(l)" class="flex min-w-0 flex-1 items-center gap-1.5 text-left">
                    <img :src="l.image" class="h-7 w-7 shrink-0 rounded bg-ink-900 object-cover">
                    <span v-if="renamingId !== l.id" class="truncate text-[10px] text-cream-100">{{ l.name }}</span>
                    <input v-else v-model="renameValue" class="w-full min-w-0 rounded bg-ink-900 px-1 py-0.5 text-[10px] text-cream-100 outline-none ring-1 ring-brand-500" @keyup.enter="commitRename()" @keyup.esc="cancelRename()" @blur="commitRename()" @click.stop>
                  </button>
                  <div class="flex shrink-0 items-center gap-0.5 opacity-0 transition-opacity group-hover:opacity-100">
                    <button @click="store.toggleLayerLock(l.id)" class="grid h-5 w-5 place-items-center rounded" :class="l.locked ? 'bg-amber-500/20 text-amber-300' : 'text-cream-300 hover:bg-ink-700'" :title="l.locked ? 'Mở khóa' : 'Khóa layer'"><span v-if="l.locked"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span><span v-else><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg></span></button>
                    <button @click="store.moveLayer(l.id, 'up')" :disabled="i === 0 || l.locked" class="grid h-5 w-4 place-items-center rounded text-cream-300 hover:bg-ink-700 disabled:opacity-30" title="Lên trên"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"/></svg></button>
                    <button @click="store.moveLayer(l.id, 'down')" :disabled="i === store.canvasLayers.length - 1 || l.locked" class="grid h-5 w-4 place-items-center rounded text-cream-300 hover:bg-ink-700 disabled:opacity-30" title="Xuống dưới"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></button>
                    <button v-if="renamingId !== l.id" @click="startRename(l)" :disabled="l.locked" class="grid h-5 w-5 place-items-center rounded text-cream-300 hover:bg-ink-700 disabled:opacity-30" title="Đổi tên"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5z"/></svg></button>
                    <button @click="store.deleteLayer(l)" :disabled="l.locked" class="grid h-5 w-5 place-items-center rounded bg-red-600/25 text-red-200 hover:bg-red-600 disabled:opacity-30" :title="l.locked ? 'Đang khóa' : 'Gỡ khỏi canvas (không xóa kết quả)'"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                  </div>
                </div>
              </div>
              <div class="mt-1 grid grid-cols-2 gap-1.5 border-t border-ink-700/60 pt-1.5">
                <button @click="store.exportComposite()" :disabled="!store.visibleLayers.length" class="flex items-center justify-center gap-1 rounded-lg bg-ink-800 px-2 py-1.5 text-[9px] font-semibold text-cream-200 hover:bg-ink-700 disabled:opacity-40" title="Gộp tất cả layer đang hiển thị và tải xuống PNG"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg><span>Xuất</span></button>
                <button @click="store.flattenToLayer()" :disabled="!store.visibleLayers.length" class="flex items-center justify-center gap-1 rounded-lg bg-brand-600 px-2 py-1.5 text-[9px] font-semibold text-white hover:bg-brand-500 disabled:opacity-40" title="Gộp tất cả layer đang hiển thị thành 1 layer mới"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/></svg><span>Gộp</span></button>
              </div>
            </div>
            <p v-if="!store.canvasLayers.length" class="rounded-lg bg-ink-900/50 px-2 py-1.5 text-center text-[10px] text-cream-300/40">Chưa có layer.</p>
            <div v-if="store.activeLayer" class="hidden w-64 rounded-2xl bg-ink-900/90 p-2 shadow-lg lg:block">
              <div class="mb-1 flex items-center justify-between px-0.5">
                <p class="flex items-center gap-1 text-[10px] font-semibold text-cream-300/60"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 9l-3 3 3 3"/><path d="M9 5l3-3 3 3"/><path d="M15 19l-3 3-3-3"/><path d="M19 9l3 3-3 3"/><path d="M2 12h20"/><path d="M12 2v20"/></svg><span>Transform</span></p>
                <button @click="store.resetLayerTransform(store.activeLayer.id)" class="text-[9px] font-semibold text-red-300 hover:text-red-200" title="Đưa layer về mặc định">Reset all</button>
              </div>
              <div class="flex items-center gap-1.5">
                <span class="w-14 shrink-0 whitespace-nowrap text-[9px] text-cream-300/60">Opacity</span>
                <input type="range" min="0" max="1" step="0.05" :value="store.activeLayer.opacity" @input="store.updateLayerTransform(store.activeLayer.id, { opacity: Number($event.target.value) })" class="h-1.5 min-w-0 flex-1 accent-brand-500">
                <span class="w-9 shrink-0 whitespace-nowrap text-right text-[9px] text-cream-200">{{ Math.round(store.activeLayer.opacity * 100) }}%</span>
                <button @click="store.updateLayerTransform(store.activeLayer.id, { opacity: 1 })" class="grid h-4 w-4 shrink-0 place-items-center rounded text-cream-300 hover:bg-ink-700" title="Reset opacity"><svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg></button>
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
                <button @click="store.updateLayerTransform(store.activeLayer.id, { blend: 'normal' })" class="grid h-4 w-4 shrink-0 place-items-center rounded text-cream-300 hover:bg-ink-700" title="Reset blend"><svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg></button>
              </div>
              <div class="mt-1 flex items-center gap-1.5">
                <span class="w-14 shrink-0 whitespace-nowrap text-[9px] text-cream-300/60">Scale</span>
                <input type="range" min="0.2" max="3" step="0.05" :value="store.activeLayer.scale" @input="store.updateLayerTransform(store.activeLayer.id, { scale: Number($event.target.value) })" class="h-1.5 min-w-0 flex-1 accent-brand-500">
                <span class="w-9 shrink-0 whitespace-nowrap text-right text-[9px] text-cream-200">{{ Math.round(store.activeLayer.scale * 100) }}%</span>
                <button @click="store.updateLayerTransform(store.activeLayer.id, { scale: 1 })" class="grid h-4 w-4 shrink-0 place-items-center rounded text-cream-300 hover:bg-ink-700" title="Reset scale"><svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg></button>
              </div>
              <div class="mt-1 flex items-center gap-1.5">
                <span class="w-14 shrink-0 whitespace-nowrap text-[9px] text-cream-300/60">Xoay</span>
                <input type="range" min="-180" max="180" step="1" :value="store.activeLayer.rotation" @input="store.updateLayerTransform(store.activeLayer.id, { rotation: Number($event.target.value) })" class="h-1.5 min-w-0 flex-1 accent-brand-500">
                <span class="w-9 shrink-0 whitespace-nowrap text-right text-[9px] text-cream-200">{{ store.activeLayer.rotation }}°</span>
                <button @click="store.updateLayerTransform(store.activeLayer.id, { rotation: 0 })" class="grid h-4 w-4 shrink-0 place-items-center rounded text-cream-300 hover:bg-ink-700" title="Reset rotation"><svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg></button>
              </div>
              <div class="mt-1.5 grid grid-cols-3 gap-1.5 border-t border-ink-700/60 pt-1.5">
                <button @click="store.duplicateLayer(store.activeLayer.id)" class="flex items-center justify-center gap-1 rounded-lg bg-ink-800 px-1 py-1.5 text-[9px] font-semibold text-cream-200 hover:bg-ink-700" title="Nhân đôi layer (Ctrl+D)"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg><span>Nhân đôi</span></button>
                <button @click="store.bringLayerTo(store.activeLayer.id, 'front')" class="flex items-center justify-center gap-1 rounded-lg bg-ink-800 px-1 py-1.5 text-[9px] font-semibold text-cream-200 hover:bg-ink-700" title="Đưa lên trên cùng"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3h14"/><path d="m18 13-6-6-6 6"/><path d="M12 7v14"/></svg><span>Lên</span></button>
                <button @click="store.bringLayerTo(store.activeLayer.id, 'back')" class="flex items-center justify-center gap-1 rounded-lg bg-ink-800 px-1 py-1.5 text-[9px] font-semibold text-cream-200 hover:bg-ink-700" title="Đưa xuống dưới cùng"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 21h14"/><path d="m18 11-6 6-6-6"/><path d="M12 17V3"/></svg><span>Xuống</span></button>
              </div>
              <div class="mt-1.5 grid grid-cols-2 gap-1.5">
                <button @click="store.toggleFlipX(store.activeLayer.id)" class="flex items-center justify-center gap-1 rounded-lg bg-ink-800 px-1 py-1.5 text-[9px] font-semibold text-cream-200 hover:bg-ink-700" title="Lật ngang layer"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v14c0 1.1.9 2 2 2h3"/><path d="M16 3h3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-3"/><path d="M12 20v2"/><path d="M12 14v2"/><path d="M12 8v2"/><path d="M12 2v2"/></svg><span>Ngang</span></button>
                <button @click="store.toggleFlipY(store.activeLayer.id)" class="flex items-center justify-center gap-1 rounded-lg bg-ink-800 px-1 py-1.5 text-[9px] font-semibold text-cream-200 hover:bg-ink-700" title="Lật dọc layer"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v3"/><path d="M21 16v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-3"/><path d="M4 12H2"/><path d="M10 12H8"/><path d="M16 12h-2"/><path d="M22 12h-2"/></svg><span>Dọc</span></button>
              </div>
            </div>
            <div v-if="store.palette.length && store.step !== 3 && store.previewId" class="hidden w-64 rounded-2xl bg-ink-900/90 px-2.5 py-1.5 shadow-xl lg:block">
              <div class="mb-1 flex items-center gap-1 text-[10px] font-semibold text-cream-300/60"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/></svg><span>Palette</span></div>
              <div class="grid grid-cols-3 gap-1.5">
                <button v-for="c in store.palette.slice(0, 8)" :key="c" @click="copyColor(c)" class="h-7 w-full rounded-md border border-ink-700 transition hover:scale-105" :style="{ background: c }" :title="'Nhấn để copy ' + c"></button>
              </div>
            </div>
          </div>
          <!-- canvas bg + crop toggles -->
          <div class="absolute right-3 bottom-3 z-32 flex items-center gap-2 rounded-full bg-ink-900/85 px-2.5 py-1.5 shadow-lg">
            <button @click="store.downloadActive()" :disabled="!store.upscaleSrc" class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-brand-600 text-white shadow-brand-500/40 transition-colors hover:bg-brand-500 disabled:opacity-40" title="Tải ảnh xuống"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></button>
            <span class="h-4 w-px bg-ink-700"></span>
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
        </div>
      </main>
      <!-- Right outputs (desktop) -->
      <aside class="scrollbar-hide hidden w-48 shrink-0 flex-col space-y-3 overflow-y-auto border-l border-ink-700 bg-ink-900/70 p-2 lg:flex">
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
