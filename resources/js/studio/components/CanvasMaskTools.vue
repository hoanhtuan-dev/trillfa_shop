<script setup>
import { computed, ref, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { useStudioStore } from '../store.js';
const store = useStudioStore();

// Hiển thị overlay khi: đang chỉnh (rect/brush) HOẶC đã lưu (done — vẫn xem được vùng đã chọn)
const visible = computed(() => store.upscaleSrc && (store.inpaintMaskMode !== 'none' || store.inpaintMaskDone));
const editing = computed(() => store.inpaintMaskMode !== 'none');

const maskBoxStyle = computed(() => {
  void metricsTick.value;
  const m = store.canvasMetrics();
  if (!m) return { display: 'none' };
  const b = store.inpaintMaskBox || { x: 0, y: 0, w: 0, h: 0 };
  return {
    left: ((m.vx + b.x * m.vw) / m.crW * 100) + '%',
    top: ((m.vy + b.y * m.vh) / m.crH * 100) + '%',
    width: (b.w * m.vw / m.crW * 100) + '%',
    height: (b.h * m.vh / m.crH * 100) + '%',
  };
});

// Toàn bộ vùng ảnh hiển thị — dùng cho brush mask preview (đã lưu) phủ đúng chỗ.
const fullImageStyle = computed(() => {
  const m = store.canvasMetrics();
  if (!m) return { display: 'none' };
  return {
    left: (m.vx / m.crW * 100) + '%',
    top: (m.vy / m.crH * 100) + '%',
    width: (m.vw / m.crW * 100) + '%',
    height: (m.vh / m.crH * 100) + '%',
  };
});

const hasBox = computed(() => (store.inpaintMaskBox.w || 0) >= 0.02 && (store.inpaintMaskBox.h || 0) >= 0.02);

// Kích thước canvas brush theo TỈ LỆ ẢNH GỐC (natural iw:ih), scale về cạnh dài 512.
// Quan trọng: KHÔNG vuông cứng 512×512 — nếu ảnh dọc/ngang thì mask bị ép méo và lệch vị trí.
const brushCanvasSize = computed(() => {
  const m = store.canvasMetrics();
  const base = 512;
  if (m && m.iw && m.ih) {
    const ia = m.iw / m.ih;
    if (ia >= 1) return { width: base, height: Math.max(1, Math.round(base / ia)) };
    return { width: Math.max(1, Math.round(base * ia)), height: base };
  }
  return { width: base, height: base };
});

// Bấm ngoài box (container) → tạo vùng mới; brush → vẽ mask; freehand → vẽ tự do. Chỉ khi ĐANG chỉnh.
function onFhMove(e) { store.freehandMove(e); }
function onFhUp() { window.removeEventListener('pointermove', onFhMove); window.removeEventListener('pointerup', onFhUp); store.freehandStop(); }
function onPointerDown(e) {
  if (!editing.value) return;
  e.preventDefault();
  if (store.inpaintMaskMode === 'freehand') {
    store.freehandStart(e);
    window.addEventListener('pointermove', onFhMove);
    window.addEventListener('pointerup', onFhUp);
    return;
  }
  store.inpaintMaskStart(e);
}

// SVG path freehand (GIMP-style): điểm normalized → pixel trong container
const freehandPoints = computed(() => {
  void metricsTick.value;
  const m = store.canvasMetrics();
  if (!m) return '';
  return store.inpaintFreehandPoints.map(p => Math.round(m.vx + p.nx * m.vw) + ',' + Math.round(m.vy + p.ny * m.vh)).join(' ');
});

// ── Brush: canvas overlay THẬT phủ đúng vùng ảnh hiển thị (nét vẽ hiện ngay, đỏ 60%) ──
const metricsTick = ref(0);
const brushCanvas = ref(null);
const brushOverlayStyle = computed(() => {
  void metricsTick.value;
  const m = store.canvasMetrics();
  if (!m || store.inpaintMaskMode !== 'brush') return { display: 'none' };
  return {
    left: (m.vx / m.crW * 100) + '%',
    top: (m.vy / m.crH * 100) + '%',
    width: (m.vw / m.crW * 100) + '%',
    height: (m.vh / m.crH * 100) + '%',
  };
});

let attachedEl = null;
function attachBrush(forceClear = false) {
  const el = store.inpaintMaskMode === 'brush' ? (brushCanvas.value || null) : null;
  if (el && el === attachedEl && !forceClear) return;
  store.attachBrushCanvas(el);
  attachedEl = el;
}

watch(() => store.inpaintMaskMode, () => { metricsTick.value++; nextTick(() => attachBrush()); });
watch(() => store.upscaleSrc, () => {
  if (store.inpaintMaskMode !== 'brush') return;
  store.attachBrushCanvas(null); attachedEl = null;
  store.inpaintBrushData = '';
  nextTick(() => { metricsTick.value++; attachBrush(true); });
});
// Khi kích thước canvas brush đổi (ảnh load xong có naturalWidth/Height) → re-attach đúng tỉ lệ
watch(brushCanvasSize, () => {
  if (store.inpaintMaskMode !== 'brush') return;
  nextTick(() => attachBrush(true));
});
watch([() => store.zoom, () => store.pan], () => { nextTick(() => { metricsTick.value++; }); });

function onKeyDown(e) {
  if ((e.ctrlKey || e.metaKey) && (e.key === 'z' || e.key === 'Z')) {
    if (store.inpaintMaskMode === 'brush') { e.preventDefault(); store.undoInpaintBrush(); }
  }
}
onMounted(() => { window.addEventListener('keydown', onKeyDown); });
onBeforeUnmount(() => { store.attachBrushCanvas(null); attachedEl = null; window.removeEventListener('keydown', onKeyDown); });
</script>
<template>
  <div v-if="visible" class="absolute inset-0 z-31 select-none" :class="editing ? 'cursor-crosshair' : 'cursor-default'"
       style="touch-action:none; -webkit-user-select:none; user-select:none; -webkit-touch-callout:none;"
       @pointerdown="onPointerDown" @wheel.prevent="store.wheelZoom($event)" @contextmenu.prevent @dragstart.prevent>
    <!-- Brush overlay canvas: hiển thị nét vẽ mask (đỏ 60%) ngay trên ảnh khi ĐANG vẽ -->
    <canvas v-if="store.inpaintMaskMode === 'brush' && store.upscaleSrc" ref="brushCanvas"
            :width="brushCanvasSize.width" :height="brushCanvasSize.height"
            class="pointer-events-none absolute z-10 rounded-lg"
            :style="brushOverlayStyle"></canvas>

    <!-- Freehand (lasso): hiển thị đường vẽ đang kéo (GIMP-style) -->
    <svg v-if="store.inpaintMaskMode === 'freehand' && store.inpaintFreehandPoints.length > 1"
         class="pointer-events-none absolute inset-0 z-10 h-full w-full">
      <polyline :points="freehandPoints" fill="none" stroke="#f43f5e" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />
      <polygon v-if="store.inpaintMaskMode === 'freehand' && !store._inpaintFreehandActive && store.inpaintFreehandPoints.length"
               :points="freehandPoints" fill="rgba(244,63,94,0.12)" stroke="none" />
    </svg>

    <!-- Rect: box + handles (chỉ khi ĐANG chỉnh) -->
    <div v-if="store.inpaintMaskMode === 'rect'" class="pointer-events-none absolute inset-0">
      <template v-if="hasBox">
        <div class="absolute cursor-move select-none" style="pointer-events:auto; touch-action:none"
             :style="maskBoxStyle"
             @pointerdown.stop="store.beginInpaintDrag('move', $event)"
             @dblclick.stop="store.resetInpaintMaskBox()"
             title="Kéo để di chuyển · đúp chuột để vẽ lại vùng">
          <div class="pointer-events-none absolute -inset-px border-2 border-dashed border-brand-300"></div>
          <div class="pointer-events-none absolute -inset-px" style="box-shadow: 0 0 0 9999px rgba(0,0,0,0.55);"></div>
          <div class="absolute -left-3 -top-3 h-6 w-6 cursor-nwse-resize rounded-sm border-2 border-white bg-brand-400 shadow" style="pointer-events:auto; touch-action:none" @pointerdown.stop="store.beginInpaintDrag('nw', $event)" title="Kéo góc"></div>
          <div class="absolute -right-3 -top-3 h-6 w-6 cursor-nesw-resize rounded-sm border-2 border-white bg-brand-400 shadow" style="pointer-events:auto; touch-action:none" @pointerdown.stop="store.beginInpaintDrag('ne', $event)" title="Kéo góc"></div>
          <div class="absolute -bottom-3 -left-3 h-6 w-6 cursor-nesw-resize rounded-sm border-2 border-white bg-brand-400 shadow" style="pointer-events:auto; touch-action:none" @pointerdown.stop="store.beginInpaintDrag('sw', $event)" title="Kéo góc"></div>
          <div class="absolute -bottom-3 -right-3 h-6 w-6 cursor-nwse-resize rounded-sm border-2 border-white bg-brand-400 shadow" style="pointer-events:auto; touch-action:none" @pointerdown.stop="store.beginInpaintDrag('se', $event)" title="Kéo góc"></div>
        </div>
      </template>
    </div>

    <!-- ══ Preview vùng ĐÃ LƯU (bấm Xong): hiển thị kết quả ngay trên ảnh, không chỉnh được ══ -->
    <div v-if="!editing && store.inpaintMaskDone" class="pointer-events-none absolute inset-0 z-10">
      <!-- Rect đã lưu: viền xanh lá -->
      <div v-if="store._inpaintMaskKind === 'rect'" class="absolute" :style="maskBoxStyle">
        <div class="pointer-events-none absolute -inset-px border-2 border-dashed border-emerald-400"></div>
        <div class="pointer-events-none absolute -inset-px" style="box-shadow: 0 0 0 9999px rgba(0,0,0,0.45);"></div>
      </div>
      <!-- Brush đã lưu: mask đen-trắng phủ đúng vùng ảnh, opacity cao để thấy rõ -->
      <img v-else-if="store.inpaintBrushData" :src="'data:image/png;base64,' + store.inpaintBrushData"
           class="pointer-events-none absolute rounded-lg mix-blend-multiply"
           :style="{ ...fullImageStyle, opacity: 0.65 }" alt="Vùng đã vẽ" />
    </div>
  </div>
</template>
