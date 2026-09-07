<script setup>
import { ref, onMounted, onBeforeUnmount, computed, watch, nextTick } from 'vue';
import { useStudioStore } from './store.js';
import SuggestCard from './components/SuggestCard.vue';
import ConceptCard from './components/ConceptCard.vue';
import StylistCard from './components/StylistCard.vue';
import UpscaleCard from './components/UpscaleCard.vue';
// [SWAP TẠM ẨN] import SwapCard from './components/SwapCard.vue';
import InpaintCard from './components/InpaintCard.vue';
// TẠM ẨN card "Ghép ảnh" (ComposeCard) để tập trung phát triển tính năng khác đã ổn định.
// import ComposeCard from './components/ComposeCard.vue';
import RefImageCard from './components/RefImageCard.vue';
import RegionTools from './components/RegionTools.vue';
import CanvasMaskTools from './components/CanvasMaskTools.vue';
import ContextToolbar from './components/ContextToolbar.vue';
import DirectorCard from './components/DirectorCard.vue';
import SourcePanel from './components/SourcePanel.vue';
import OutputModule from './components/OutputModule.vue';
import LibraryCard from './components/LibraryCard.vue';
import GalleryModal from './components/GalleryModal.vue';
import ProjectWorkspace from './components/ProjectWorkspace.vue';
import StudioIcon from './components/StudioIcon.vue';
import LayersPanel from './components/LayersPanel.vue';
import CanvasStatusBar from './components/CanvasStatusBar.vue';
const store = useStudioStore();
// CSRF token cho form Đăng xuất (Laravel route POST /dang-xuat).
const csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
const stepNav = [['1','Concept'],['2','Fitting Room'],['3','Director']];
const menuOpen = ref(false);
const outputOpen = ref(false);
const projectsOpen = ref(false);
const applyOpen = ref(false);
function openApplyPopover() {
  applyOpen.value = !applyOpen.value;
  if (applyOpen.value && !store.projectLoaded) store.loadProjects();
}
function onCanvasResize() { nextTick(() => { eraseTick.value++; drawTick.value++; }); }
onMounted(async () => { store.load(); store.loadPaletteFromImage(store.upscaleSrc); window.addEventListener('keydown', onCanvasKey); window.addEventListener('keydown', onLayerKeys); window.addEventListener('keydown', onHistoryKeys); window.addEventListener('resize', onCanvasResize); });
onBeforeUnmount(() => { window.removeEventListener('keydown', onCanvasKey); window.removeEventListener('keydown', onLayerKeys); window.removeEventListener('keydown', onHistoryKeys); window.removeEventListener('resize', onCanvasResize); });
// Palette bám ẢNH HIỆN TẠI (mọi nguồn: result/preview, ảnh tải lên, product, layer đang sửa…).
watch(() => store.upscaleSrc, (url) => { store.loadPaletteFromImage(url); });
// Template refs -> store: StudioApp owns the canvas DOM; the store needs the elements for crop geometry.
const cvImg = ref(null);
const canvasZoom = ref(null);
const eraseOverlay = ref(null);
const drawOverlay = ref(null);
watch([cvImg, canvasZoom], ([img, zoom]) => { store.setCanvasRefs(img, zoom); });
watch(eraseOverlay, (el) => store.attachEraseCanvas(el));
watch(drawOverlay, (el) => store.attachDrawCanvas(el));
// While crop mode is on: re-fit the box when the ratio changes, re-init when the image changes.
watch(() => store.reframeRatio, () => { if (store.cropMode) store.refitCropBox(); });
// Khi đổi layer, KHÔNG reset zoom/pan — giữ nguyên khung nhìn của người dùng.
watch(() => store.upscaleSrc, () => {
  if (store.cropMode) store.initCropBox();
});
function onCanvasKey(e) {
  const editing = store.cropMode || store.inpaintMaskMode !== 'none' || store.eraseMode || store.drawMode;
  if (!editing) return;
  const t = e.target;
  if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT' || t.isContentEditable)) return;
  if (e.key === 'Escape') {
    // Thoát an toàn theo ưu tiên: vùng chọn/mask → vẽ → xóa → crop.
    if (store.inpaintMaskMode !== 'none') store.clearInpaintMask();
    else if (store.drawMode) store.cancelDraw();
    else if (store.eraseMode) store.cancelErase();
    else if (store.cropMode) store.toggleCrop();
  } else if (e.key === 'Enter' && !(t && t.tagName === 'BUTTON')) {
    // Hoàn tất công cụ đang dùng (Enter = "Xong").
    if (store.cropMode) store.confirmCrop();
    else if (store.inpaintMaskMode !== 'none') store.confirmInpaintMask();
    else if (store.eraseMode) store.finishErase();
    else if (store.drawMode) store.finishDraw();
  }
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
const panel = computed(() => store.step === 1 ? [StylistCard, SuggestCard, ConceptCard] : store.step === 2 ? [RefImageCard, InpaintCard, UpscaleCard] : [DirectorCard]); // TẠM ẨN ComposeCard — bỏ khỏi step 2
// ContextToolbar chỉ hiện (floating) trên mobile khi có công cụ đang hoạt động — tối giản mobile.
const toolActive = computed(() => store.inpaintMaskMode !== 'none' || store.inpaintMaskDone || store.eraseMode || store.drawMode || store.reframeOpen || store.cropMode || store.filmOpen || store.looking);

// ── Layer editor (composite + transform) ──
const isolateActive = computed(() => store.cropMode || store.inpaintMaskMode !== 'none' || store.eraseMode || store.drawMode);
function layerStyle(l, i) {
  return {
    transform: store.layerTransformStyle(l),
    transformOrigin: 'center',
    opacity: l.opacity != null ? l.opacity : 1,
    mixBlendMode: (l.blend && l.blend !== 'normal') ? l.blend : 'normal',
    zIndex: i + 1,
  };
}
// Transform của layer active trong chế độ isolate — giữ ĐÚNG vị trí/scale/rotation như ở stack (không xê dịch).
const isolateLayerStyle = computed(() => {
  const l = store.activeLayer;
  if (!l) return {};
  return {
    transform: store.layerTransformStyle(l),
    transformOrigin: 'center',
    opacity: l.opacity != null ? l.opacity : 1,
    mixBlendMode: (l.blend && l.blend !== 'normal') ? l.blend : 'normal',
  };
});
// Overlay canvas xóa bám đúng vùng ảnh hiển thị (chịu zoom/pan) — khớp canvasMetrics.
const eraseTick = ref(0);
watch([() => store.zoom, () => store.pan.x, () => store.pan.y, () => store.upscaleSrc, () => store.imgTick], () => { nextTick(() => { eraseTick.value++; }); });
// Kích hoạt công cụ / ảnh isolate thay đổi → overlay chưa đo được vị trí ngay trong render đầu
// (img vừa được tạo). Bump tick SAU khi patch để khung vẽ/overlay hiện đúng vị trí từ giây đầu.
watch([() => store.cropMode, () => store.inpaintMaskMode, () => store.eraseMode, () => store.drawMode, () => store.cvImg, () => store.imgTick], () => {
  nextTick(() => { eraseTick.value++; drawTick.value++; });
});
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
// Overlay canvas vẽ (paint) bám đúng vùng ảnh — giống erase.
const drawTick = ref(0);
watch([() => store.zoom, () => store.pan.x, () => store.pan.y, () => store.upscaleSrc], () => { nextTick(() => { drawTick.value++; }); });
const drawOverlayStyle = computed(() => {
  void drawTick.value;
  const m = store.canvasMetrics();
  if (!m || !store.drawMode) return { display: 'none' };
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
    <!-- ══ Top account bar: thông tin người dùng + đăng nhập/đăng xuất + điều hướng quản trị ══ -->
    <div class="flex items-center justify-between gap-3 border-b border-ink-700 bg-ink-900/90 px-3 py-2.5 sm:px-4">
      <div class="flex min-w-0 items-center gap-2.5">
        <template v-if="store.user">
          <img :src="store.user.avatar || '/images/placeholder.svg'" class="h-8 w-8 shrink-0 rounded-full bg-ink-700 object-cover ring-2 ring-brand-500/40" @error="$event.target.src = '/images/placeholder.svg'" alt="Ảnh đại diện">
          <div class="min-w-0 leading-tight">
            <p class="truncate text-sm font-semibold text-cream-50">{{ store.user.name }}</p>
            <p class="truncate text-[11px] text-cream-300/60">{{ store.user.role_label || store.user.role }}<span class="hidden sm:inline"> · {{ store.user.email }}</span></p>
          </div>
        </template>
        <template v-else>
          <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-amber-500/20 text-amber-200"><StudioIcon name="lock" size="h-4 w-4" /></span>
          <div class="leading-tight">
            <p class="text-sm font-semibold text-cream-50">Chưa đăng nhập</p>
            <p class="text-[11px] text-cream-300/60">Đăng nhập để tạo ảnh/video &amp; lưu dữ liệu.</p>
          </div>
        </template>
      </div>
      <div class="flex shrink-0 items-center gap-2">
        <!-- Dự án + quick-apply gộp 1 tool-btn -->
        <div class="relative">
          <button @click="openApplyPopover" class="tool-btn" title="Dự án — áp dụng nhanh hoặc mở workspace quản lý"><StudioIcon name="kanban" size="h-3.5 w-3.5" /> <span class="hidden sm:inline">Dự án</span> <StudioIcon name="chevronDown" size="h-3 w-3" /></button>
          <div v-if="applyOpen" class="absolute left-0 top-full z-50 mt-1 w-72 rounded-xl border border-ink-700 bg-ink-900 shadow-xl">
            <div class="p-2.5">
              <p class="mb-2 text-[11px] font-semibold text-cream-200">Áp dụng dự án cho phiên tạo ảnh</p>
              <div v-if="store.projectScope !== 'own'" class="mb-2 rounded-lg bg-amber-500/10 px-2 py-1.5 text-[10px] text-amber-200">Đang xem dự án chờ duyệt. Mở workspace để xem dự án của bạn.</div>
              <div v-else class="max-h-64 overflow-y-auto">
                <!-- Tối đa 20 dự án gần nhất, cuộn được (trước giới hạn cứng 8) -->
                <div v-for="p in store.projects.slice(0, 20)" :key="p.id" @click="store.applyProject(p); applyOpen = false" class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 transition hover:bg-ink-800" :class="store.appliedProjectId() === p.id ? 'bg-brand-600/20 text-brand-100' : 'text-cream-200'">
                  <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ background: p.color }"></span>
                  <span class="min-w-0 flex-1 truncate text-xs">{{ p.name }}</span>
                  <span class="shrink-0 text-[9px] text-cream-300/50">{{ p.status }}</span>
                  <StudioIcon v-if="store.appliedProjectId() === p.id" name="pin" size="h-3 w-3" class="text-brand-400" />
                </div>
                <p v-if="!store.projects.length" class="px-2 py-1 text-[10px] text-cream-300/40">Chưa có dự án nào.</p>
              </div>
              <button v-if="store.appliedProject" @click="store.unapplyProject(); applyOpen = false" class="mt-2 flex w-full items-center justify-center gap-1 rounded-lg border border-red-500/30 bg-red-600/10 px-2 py-1.5 text-[10px] font-semibold text-red-200 transition hover:bg-red-600/20">
                <StudioIcon name="pinOff" size="h-3 w-3" /> Ngắt dự án hiện tại
              </button>
              <button @click="applyOpen = false; projectsOpen = true" class="mt-2 flex w-full items-center justify-center gap-1 rounded-lg border border-ink-700 bg-ink-800 px-2 py-1.5 text-[10px] font-semibold text-cream-200 transition hover:bg-ink-700">
                <StudioIcon name="kanban" size="h-3 w-3" /> Mở workspace quản lý dự án
              </button>
            </div>
          </div>
          <div v-if="applyOpen" class="fixed inset-0 z-40" @click="applyOpen = false"></div>
        </div>
        <span v-if="store.appliedProject" class="tool-btn is-active hidden max-w-[13rem] md:inline-flex" :title="'Dự án hiện tại: ' + store.appliedProject.name + ' — ảnh/video tạo mới sẽ tự gắn vào dự án này'">
          <button type="button" @click="projectsOpen = true" class="flex min-w-0 items-center gap-1.5 truncate hover:text-white"><StudioIcon name="pin" size="h-3.5 w-3.5" /><span class="truncate">{{ store.appliedProject.name }}</span></button>
          <button type="button" @click="store.unapplyProject()" class="shrink-0 text-brand-200/70 hover:text-white" aria-label="Ngắt dự án hiện tại"><StudioIcon name="x" size="h-3.5 w-3.5" /></button>
        </span>
        <span v-if="store.user" class="tool-btn hidden cursor-default md:inline-flex" title="Số credit còn lại"><StudioIcon name="coins" size="h-3.5 w-3.5" /> {{ store.creditsLeft }}</span>
        <a v-if="store.user && store.user.is_admin" href="/admin" class="tool-btn" title="Đi tới trang quản trị (Dashboard Manager)"><StudioIcon name="gear" size="h-3.5 w-3.5" /> <span class="hidden sm:inline">Quản trị</span></a>
        <form v-if="store.user" method="POST" action="/dang-xuat" class="m-0">
          <input type="hidden" name="_token" :value="csrfToken">
          <button type="submit" class="tool-btn" title="Đăng xuất khỏi tài khoản">Đăng xuất</button>
        </form>
        <a v-else href="/dang-nhap?redirect=/studio" class="rounded-full bg-amber-500 px-3 py-1 text-xs font-semibold text-black transition hover:bg-amber-400">Đăng nhập</a>
      </div>
    </div>
    <!-- toast (copy/status) -->
    <div v-if="store.flashMsg" class="pointer-events-none fixed left-1/2 bottom-5 z-[90] -translate-x-1/2 rounded-full px-4 py-2 text-xs font-semibold shadow-2xl" :class="store.flashType === 'error' ? 'bg-red-600 text-white' : 'bg-ink-800 text-cream-100 border border-brand-500/40'">{{ store.flashMsg }}</div>
    <!-- Mobile top bar -->
    <div class="flex items-center justify-between border-b border-ink-700 bg-ink-900/80 px-3 py-2 lg:hidden">
      <button @click="menuOpen = true" class="icon-btn !h-9 !w-9 border border-ink-700" title="Mở menu công cụ" aria-label="Mở menu công cụ"><StudioIcon name="menu" size="h-5 w-5" /></button>
      <span class="flex items-center gap-1.5 font-display text-sm font-semibold"><StudioIcon name="sparkles" size="h-4 w-4" class="text-brand-400" /> Studio</span>
      <div class="flex items-center gap-1.5">
        <button @click="projectsOpen = true" class="icon-btn relative !h-9 !w-9 border border-ink-700" :title="store.appliedProject ? 'Dự án hiện tại: ' + store.appliedProject.name : 'Dự án'" aria-label="Dự án"><StudioIcon name="kanban" size="h-4 w-4" /><span v-if="store.appliedProject" class="absolute right-1 top-1 h-1.5 w-1.5 rounded-full bg-brand-400"></span></button>
        <button @click="outputOpen = true" class="icon-btn relative !h-9 !w-9 border border-ink-700" title="Kết quả" aria-label="Kết quả"><StudioIcon name="grid" size="h-4 w-4" /><span v-if="store.generations.length" class="absolute -right-1 -top-1 grid h-4 min-w-4 place-items-center rounded-full bg-brand-600 px-1 text-[9px] font-bold leading-none text-white">{{ store.generations.length }}</span></button>
      </div>
    </div>
    <div class="flex flex-1 overflow-hidden">
      <!-- Left sidebar (desktop) -->
      <aside class="scrollbar-hide hidden w-80 shrink-0 flex-col overflow-y-auto border-r border-ink-700 bg-ink-900/70 p-3 lg:flex">
        <div class="mb-3 flex items-center justify-between"><span class="flex items-center gap-1.5 font-display text-sm font-semibold"><StudioIcon name="sparkles" size="h-4 w-4" class="text-brand-400" /> Studio</span><span class="text-[10px] text-cream-300/50">Credit {{ store.creditsLeft }}</span></div>
        <div class="mb-3 seg">
          <button v-for="s in stepNav" :key="s[0]" @click="store.step = Number(s[0])" class="seg-btn" :class="store.step === Number(s[0]) ? 'is-active' : ''">{{ s[1] }}</button>
        </div>
        <div class="scrollbar-hide space-y-3">
          <component :is="c" v-for="(c,i) in panel" :key="i" />
        </div>
      </aside>
      <!-- Center canvas -->
      <main class="relative flex-1 min-w-0 p-3">
        <div class="relative flex h-full flex-col overflow-hidden rounded-2xl border border-ink-700 bg-ink-900">
          <!-- ══ Toolbar dock (phía trên, full width) — desktop only ══ -->
          <div class="relative z-40 hidden min-h-12 shrink-0 items-center justify-center gap-2 overflow-x-auto overflow-y-hidden border-b border-ink-700/40 px-3 py-1.5 lg:flex">
            <ContextToolbar />
          </div>
          <!-- ══ Thân frame: vùng canvas + inspector Layers dock phải (desktop) ══ -->
          <div class="flex min-h-0 flex-1">
          <!-- ══ Vùng canvas (trái, flex-1) ══ -->
          <div class="relative flex-1 overflow-hidden" :class="bgClass">
          <!-- Floating tools (Crop/Select/Draw/Erase/Look) — mọi viewport; tự định vị theo màn hình -->
          <RegionTools />
          <!-- Inpaint mask overlay on canvas -->
          <CanvasMaskTools />
          <!-- active image source clear -->
          <div v-if="store.editSource" class="absolute left-3 top-3 z-30 flex items-center gap-1.5 rounded-full bg-ink-900/85 px-2.5 py-1.5 text-xs shadow-lg">
            <button @click="store.removeEditSource()" class="grid h-6 w-6 place-items-center rounded-full bg-ink-700 text-cream-200 hover:bg-red-600" title="Bỏ ảnh nguồn khỏi canvas" aria-label="Bỏ ảnh nguồn khỏi canvas"><StudioIcon name="x" size="h-3.5 w-3.5" /></button>
          </div>
          <div ref="canvasZoom" class="absolute inset-0 cursor-grab active:cursor-grabbing" style="touch-action:none" @wheel.prevent="store.wheelZoom($event)" @pointerdown="onCanvasBgDown($event)" @pointermove="store.panMove($event)" @pointerup="onCanvasBgUp($event)" @pointerleave="store.panEnd" @touchstart="onTouchStart($event)" @touchmove="onTouchMove($event)" @touchend="onTouchEnd($event)">
            <!-- Chế độ isolate (crop/inpaint/erase): layer active GIỮ ĐÚNG vị trí/scale như stack — không xê dịch -->
            <div v-if="isolateActive" class="absolute inset-0">
              <div v-if="store.upscaleSrc" class="absolute left-1/2 top-1/2" :style="{ transform: 'translate(-50%, -50%) translate(' + store.pan.x + 'px, ' + store.pan.y + 'px) scale(' + store.zoom + ')' }">
                <div class="absolute left-0 top-0" :style="isolateLayerStyle">
                  <img ref="cvImg" :src="store.upscaleSrc" class="block max-h-[512px] max-w-[512px] min-w-0 select-none" :class="store.activeLayerId === store.highlightLayerId ? 'outline-2 outline-dashed outline-red-500' : ''" draggable="false" @load="store.onCanvasImgLoad()" />
                </div>
              </div>
              <p v-else class="text-sm text-cream-300/60">Chọn/hiện một ảnh (Nguồn hoặc Kết quả) để làm việc.</p>
            </div>
            <!-- Chế độ stack: composite tất cả layer đang hiển thị -->
            <div v-else class="absolute inset-0">
              <div class="absolute left-1/2 top-1/2" :style="{ transform: 'translate(-50%, -50%) translate(' + store.pan.x + 'px, ' + store.pan.y + 'px) scale(' + store.zoom + ')' }">
                <div v-for="(l, i) in store.visibleLayers" :key="l.id" class="absolute left-0 top-0" :style="layerStyle(l, i)" @pointerdown.stop="onLayerPointerDown(l, $event)">
                  <img :src="l.image" class="relative block max-h-[512px] max-w-[512px] cursor-move select-none" :class="[l.id === store.activeLayerId ? 'ring-2 ring-brand-400' : '', l.id === store.highlightLayerId ? 'outline-2 outline-dashed outline-red-500' : '']" draggable="false" />
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
            <!-- Overlay canvas vẽ (paint): tô màu lên layer -->
            <canvas v-if="store.drawMode" ref="drawOverlay" class="absolute z-30 cursor-crosshair rounded" :style="drawOverlayStyle" @pointerdown.stop="store.beginDrawBrush($event)" @pointermove="store.drawBrushMove($event)" @pointerup="store.endDrawBrush()" @pointerleave="store.endDrawBrush()"></canvas>
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
          <!-- Mobile: strip chip layer mini (desktop dùng LayersPanel dock phải) -->
          <div v-if="store.canvasLayers.length && !store.inspectorOpen" class="absolute right-3 top-3 z-30 flex flex-col gap-1 lg:hidden">
            <button v-for="l in store.layersFrontFirst" :key="l.id" @click="store.selectLayer(l)" class="h-7 w-7 shrink-0 overflow-hidden rounded-md transition" :class="store.activeLayerId === l.id ? 'ring-2 ring-brand-400' : 'opacity-60 hover:opacity-100'" :title="l.name">
              <img :src="l.image" class="h-7 w-7 object-cover" />
            </button>
            <button @click="store.deleteLayer(store.activeLayer)" :disabled="!store.activeLayer" class="grid h-7 w-7 shrink-0 place-items-center rounded-md bg-ink-900/85 text-red-300 shadow hover:bg-red-600 hover:text-white disabled:opacity-30" title="Xóa layer khỏi canvas" aria-label="Xóa layer khỏi canvas"><StudioIcon name="trash" size="h-3.5 w-3.5" /></button>
          </div>
          <!-- variant slider (bottom, only when multiple variants) -->
          <div v-if="store.showBatch && store.activeBatch.length > 1" class="batch-slider absolute bottom-14 left-1/2 z-20 -translate-x-1/2 rounded-2xl bg-ink-900/90 px-2.5 py-1.5 shadow-xl">
            <div class="flex items-center gap-1.5">
              <span class="text-[10px] text-cream-300/60">{{ store.activeBatch.length }} biến thể</span>
              <button v-for="v in store.activeBatch" :key="v.id" @click="store.select(v)" class="relative h-12 w-12 overflow-hidden rounded-lg border-2 transition-all duration-300" :class="store.previewId === v.id ? 'border-brand-500 scale-105' : 'border-ink-700 hover:border-brand-400'">
                <template v-if="v.status === 'completed' && v.media_url">
                  <img :src="v.media_url" class="batch-thumb h-full w-full bg-ink-900 object-cover" loading="lazy">
                </template>
                <template v-else>
                  <div class="skeleton-shimmer absolute inset-0"></div>
                  <span class="absolute inset-0 grid place-items-center bg-black/40 text-[9px] font-semibold text-cream-200">
                    <span v-if="['pending','processing'].includes(v.status)" class="batch-dot"></span>
                    <span v-else-if="v.status === 'failed'"><StudioIcon name="alertTriangle" size="h-4 w-4" /></span>
                    <span v-else-if="v.status === 'cancelled'"><StudioIcon name="ban" size="h-4 w-4" /></span>
                  </span>
                </template>
              </button>
              <button @click="store.hideBatch()" class="ml-1 grid h-6 w-6 place-items-center rounded-full bg-ink-700 text-cream-200 transition-colors hover:bg-red-600" title="Ẩn biến thể" aria-label="Ẩn biến thể"><StudioIcon name="x" size="h-3.5 w-3.5" /></button>
            </div>
          </div>
          </div><!-- /vùng canvas -->
          <!-- ══ Inspector Layers: dock phải (desktop) · drawer đè canvas (mobile) ══ -->
          <div v-if="store.inspectorOpen" class="absolute inset-y-0 right-0 z-50 lg:static lg:z-auto">
            <LayersPanel />
          </div>
          </div><!-- /flex row: canvas + inspector -->
          <!-- ══ Toolbar ngữ cảnh floating trên mobile (12px trên status bar) ══ -->
          <div v-if="toolActive" class="absolute bottom-12 left-1/2 z-40 max-w-[calc(100%-1rem)] -translate-x-1/2 lg:hidden">
            <ContextToolbar />
          </div>
          <!-- ══ Status bar dock dưới khung canvas ══ -->
          <CanvasStatusBar />
        </div>
      </main>
      <!-- Right outputs (desktop) -->
      <aside class="scrollbar-hide hidden w-[115px] shrink-0 flex-col space-y-3 overflow-y-auto border-l border-ink-700 bg-ink-900/70 p-2 lg:flex">
        <SourcePanel />
        <OutputModule />
        <LibraryCard />
      </aside>
    </div>

    <!-- Mobile menu overlay -->
    <div v-if="menuOpen" class="fixed inset-0 z-50 lg:hidden" @click="menuOpen=false">
      <div class="absolute inset-0 bg-black/60"></div>
      <div class="absolute left-0 top-0 h-full w-80 scrollbar-hide overflow-y-auto bg-ink-900 p-3" @click.stop>
        <div class="panel-head -mx-3 mb-2 border-b border-ink-700 px-3"><span class="panel-title"><StudioIcon name="sparkles" size="h-4 w-4" class="text-brand-400" /> Studio</span><button @click="menuOpen=false" class="icon-btn !h-8 !w-8 bg-ink-800" title="Đóng menu" aria-label="Đóng menu"><StudioIcon name="x" size="h-4 w-4" /></button></div>
        <div class="mb-3 seg">
          <button v-for="s in stepNav" :key="s[0]" @click="store.step = Number(s[0]); menuOpen=false" class="seg-btn" :class="store.step === Number(s[0]) ? 'is-active' : ''">{{ s[1] }}</button>
        </div>
        <div class="space-y-3"><component :is="c" v-for="(c,i) in panel" :key="i" /></div>
      </div>
    </div>
    <!-- Mobile outputs overlay -->
    <div v-if="outputOpen" class="fixed inset-0 z-50 lg:hidden">
      <div class="absolute inset-0 bg-black/60"></div>
      <div class="absolute right-0 top-0 h-full w-80 scrollbar-hide overflow-y-auto bg-ink-900 p-3" @click.stop>
        <div class="panel-head -mx-3 mb-2 border-b border-ink-700 px-3"><span class="panel-title"><StudioIcon name="grid" size="h-4 w-4" class="text-brand-400" /> Outputs <span class="text-cream-300/50">({{ store.generations.length }})</span></span><button @click="outputOpen=false" class="icon-btn !h-8 !w-8 bg-ink-800" title="Đóng" aria-label="Đóng"><StudioIcon name="x" size="h-4 w-4" /></button></div>
        <SourcePanel />
        <OutputModule />
        <LibraryCard />
      </div>
    </div>
    <GalleryModal v-if="store.viewer" />
    <!-- ══ Bảng quản lý dự án thiết kế (Project Workspace) ══ -->
    <ProjectWorkspace v-model="projectsOpen" />
  </div>
</template>
<style scoped>
.cvs-checker { background: repeating-conic-gradient(#3a3a44 0 25%, #2a2a31 0 50%) 0 / 18px 18px; }

/* ── Batch slider (biến thể cùng lượt) ── */
.batch-slider { animation: batchSlideIn 0.35s cubic-bezier(0.22, 1, 0.36, 1); }
@keyframes batchSlideIn {
  from { opacity: 0; transform: translate(-50%, 8px); }
  to { opacity: 1; transform: translate(-50%, 0); }
}
.batch-thumb { animation: batchThumbIn 0.4s ease; }
@keyframes batchThumbIn {
  from { opacity: 0; transform: scale(0.85); }
  to { opacity: 1; transform: scale(1); }
}
/* Skeleton shimmer cho thumbnail đang chờ/xử lý */
.skeleton-shimmer {
  background: linear-gradient(100deg, #1c2333 20%, #2a3347 40%, #3a4560 60%, #2a3347 80%, #1c2333 100%);
  background-size: 200% 100%;
  animation: shimmerSweep 1.8s linear infinite;
}
@keyframes shimmerSweep {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
/* Dot nhấp nháy cho thumbnail pending */
.batch-dot {
  width: 6px;
  height: 6px;
  border-radius: 9999px;
  background: #f5c06a;
  animation: dotBlink 1.2s ease-in-out infinite;
}
@keyframes dotBlink {
  0%, 80%, 100% { opacity: 0.25; transform: scale(0.8); }
  40% { opacity: 1; transform: scale(1); }
}
</style>
