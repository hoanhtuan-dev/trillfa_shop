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
// Phải bám metricsTick để re-eval khi zoom/pan → vị trí overlay KHÔNG bị trôi.
const fullImageStyle = computed(() => {
  void metricsTick.value;
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

// Làm mờ vùng NGOÀI vùng chọn: invert mask rồi multiply → vùng chọn sáng rõ, phần đảo (ngoài) tối đi.
// mask quy ước: đen=vùng chọn, trắng=ngoài → invert(1) đổi thành trắng=vùng chọn(giữ sáng), đen=ngoài(tối).
const showDimOverlay = computed(() => {
  if (!store.inpaintBrushData) return false;
  const mode = store.inpaintMaskMode;
  return mode === 'freehand' || mode === 'path' || mode === 'magic' || mode === 'rect';
});
const invertedRect = computed(() => store.inpaintMaskMode === 'rect' && !!store.inpaintBrushData);

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
  if (store.inpaintMaskMode === 'path') {
    store.pathAddPoint(e);
    return;
  }
  if (store.inpaintMaskMode === 'magic') {
    store.magicWand(e);
    return;
  }
  store.inpaintMaskStart(e);
}

// SVG path freehand (GIMP-style): điểm normalized → pixel trong container
const pathPixels = (pts) => {
  const m = store.canvasMetrics();
  if (!m) return '';
  return pts.map(p => Math.round(m.vx + p.nx * m.vw) + ',' + Math.round(m.vy + p.ny * m.vh)).join(' ');
};
const freehandPoints = computed(() => { void metricsTick.value; return pathPixels(store.inpaintFreehandPoints); });
const freehandPaths = computed(() => { void metricsTick.value; return store.inpaintFreehandPaths.map((pts) => pathPixels(pts)); });
// Đường cong path/curve: lấy mẫu Catmull-Rom (đóng kín) qua các điểm neo.
const pathSmooth = (pts) => {
  const m = store.canvasMetrics();
  if (!m || pts.length < 2) return '';
  const P = pts.map((p) => ({ x: p.nx, y: p.ny }));
  const n = P.length, SAMPLES = 24, out = [];
  for (let i = 0; i < n; i++) {
    const p0 = P[(i - 1 + n) % n], p1 = P[i], p2 = P[(i + 1) % n], p3 = P[(i + 2) % n];
    const cx1 = p1.x + (p2.x - p0.x) / 6, cy1 = p1.y + (p2.y - p0.y) / 6;
    const cx2 = p2.x - (p3.x - p1.x) / 6, cy2 = p2.y - (p3.y - p1.y) / 6;
    for (let s = 0; s < SAMPLES; s++) {
      const t = s / SAMPLES, a = 1 - t, b = t;
      const x = a * a * a * p1.x + 3 * a * a * b * cx1 + 3 * a * b * b * cx2 + b * b * b * p2.x;
      const y = a * a * a * p1.y + 3 * a * a * b * cy1 + 3 * a * b * b * cy2 + b * b * b * p2.y;
      out.push(Math.round(m.vx + x * m.vw) + ',' + Math.round(m.vy + y * m.vh));
    }
  }
  return out.join(' ');
};
const pathSmoothPixels = computed(() => { void metricsTick.value; return pathSmooth(store.inpaintPathPoints); });
const pathRegionsPixels = computed(() => { void metricsTick.value; return store.inpaintPathRegions.map((pts) => pathSmooth(pts)); });

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
// Bám TỪNG thành phần pan.x/pan.y — panMove đổi trực tiếp pan.x/pan.y (không gán lại object)
// nên nếu watch cả `store.pan` (tham chiếu object) sẽ KHÔNG chạy khi kéo pan → overlay trôi.
watch([() => store.zoom, () => store.pan.x, () => store.pan.y, () => store.imgTick], () => { nextTick(() => { metricsTick.value++; }); });

function onKeyDown(e) {
  if ((e.ctrlKey || e.metaKey) && (e.key === 'z' || e.key === 'Z')) {
    if (store.inpaintMaskMode === 'brush') { e.preventDefault(); store.undoInpaintBrush(); }
  }
}
function onResize() { nextTick(() => { metricsTick.value++; }); }
onMounted(() => { window.addEventListener('keydown', onKeyDown); window.addEventListener('resize', onResize); });
onBeforeUnmount(() => { store.attachBrushCanvas(null); attachedEl = null; window.removeEventListener('keydown', onKeyDown); window.removeEventListener('resize', onResize); });
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

    <!-- Làm mờ vùng ĐẢO NGƯỢC (ngoài vùng chọn) — magic/freehand/path + rect đã đảo -->
    <img v-if="showDimOverlay" :src="'data:image/png;base64,' + store.inpaintBrushData"
         class="pointer-events-none absolute z-10 rounded-lg mix-blend-multiply"
         :style="{ ...fullImageStyle, filter: 'invert(1)', opacity: 0.55 }" alt="Vùng chọn" />

    <!-- Freehand (lasso): hiển thị TẤT CẢ nét đã hoàn thành + nét đang kéo (GIMP-style) -->
    <svg v-if="store.inpaintMaskMode === 'freehand' && (store.inpaintFreehandPaths.length || store.inpaintFreehandPoints.length > 1)"
         class="pointer-events-none absolute inset-0 z-10 h-full w-full">
      <template v-for="(path, idx) in freehandPaths" :key="idx">
        <polyline :points="path" fill="none" stroke="#f43f5e" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />
        <polygon :points="path" fill="rgba(244,63,94,0.12)" stroke="none" />
      </template>
      <polyline v-if="store.inpaintFreehandPoints.length > 1" :points="freehandPoints" fill="none" stroke="#f43f5e" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />
    </svg>

    <!-- Path (curve) select: hiển thị TẤT CẢ vùng đã đóng + path đang vẽ -->
    <svg v-if="store.inpaintMaskMode === 'path' && (store.inpaintPathRegions.length || store.inpaintPathPoints.length > 1)"
         class="pointer-events-none absolute inset-0 z-10 h-full w-full">
      <template v-for="(reg, idx) in pathRegionsPixels" :key="'r'+idx">
        <polyline :points="reg" fill="none" stroke="#a78bfa" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />
        <polygon :points="reg" fill="rgba(167,139,250,0.12)" stroke="none" />
      </template>
      <polyline v-if="store.inpaintPathPoints.length > 1" :points="pathSmoothPixels" fill="none" stroke="#a78bfa" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />
      <polygon v-if="store.inpaintPathPoints.length > 2" :points="pathSmoothPixels" fill="rgba(167,139,250,0.12)" stroke="none" />
      <template v-for="(p, i) in store.inpaintPathPoints" :key="'p'+i">
        <circle :cx="Math.round(store.canvasMetrics().vx + p.nx * store.canvasMetrics().vw)" :cy="Math.round(store.canvasMetrics().vy + p.ny * store.canvasMetrics().vh)" r="3" fill="#a78bfa" />
      </template>
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
          <div v-if="!invertedRect" class="pointer-events-none absolute -inset-px" style="box-shadow: 0 0 0 9999px rgba(0,0,0,0.55);"></div>
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
