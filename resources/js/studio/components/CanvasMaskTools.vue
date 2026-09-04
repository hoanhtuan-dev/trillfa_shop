<script setup>
import { computed, ref, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { useStudioStore } from '../store.js';
const store = useStudioStore();

// Hiển thị overlay khi: đang chỉnh (rect/brush) HOẶC đã lưu (done — vẫn xem được vùng đã chọn)
const visible = computed(() => store.upscaleSrc && (store.inpaintMaskMode !== 'none' || store.inpaintMaskDone));
const editing = computed(() => store.inpaintMaskMode !== 'none');

// ── Overlay được NEO VÀO KHUNG LAYER (cùng CSS transform với <img>) ──
// Toàn bộ visual vẽ ở toạ độ KHUNG ẢNH (px layout 0..baseW/H) bên trong 1 wrapper lặp lại đúng
// chuỗi transform của <img> isolate (pan/zoom + layerTransformStyle). CSS tự scale/mirror mọi thứ
// nên preview KHÔNG BAO GIỜ trôi dù zoom/pan/transform đổi — không phụ thuộc getBoundingClientRect.
const frame = computed(() => store.frameLayout); // {w,h} layout px của <img>
const anchor = computed(() => {
  const l = store.activeLayer;
  const f = frame.value;
  if (!l || !f || !store.upscaleSrc) return null;
  return {
    outer: 'translate(' + (store.pan.x || 0) + 'px, ' + (store.pan.y || 0) + 'px) scale(' + (store.zoom || 1) + ')',
    inner: store.layerTransformStyle(l),
    w: f.w, h: f.h,
  };
});
// Nghịch đảo tỉ lệ hiển thị (zoom × layer scale): giữ nét vẽ/điểm neo có độ lớn ổn định trên màn hình.
const invScale = computed(() => {
  const l = store.activeLayer;
  const sc = Math.max(0.05, Math.min(8, Number(l && l.scale) || 1)) * Math.max(0.05, store.zoom || 1);
  return 1 / sc;
});
// Bán kính điểm neo (path/curve) giữ ~3px màn hình bất kể zoom/scale.
const circleR = computed(() => Math.max(0.5, 3 * invScale.value));

// Style vùng chữ nhật (mask box / crop-like) theo % KHUNG ẢNH — không cần metrics container.
function boxFrameStyle() {
  const b = store.inpaintMaskBox || { x: 0, y: 0, w: 0, h: 0 };
  return { left: (b.x * 100) + '%', top: (b.y * 100) + '%', width: (b.w * 100) + '%', height: (b.h * 100) + '%' };
}
const hasBox = computed(() => (store.inpaintMaskBox.w || 0) >= 0.02 && (store.inpaintMaskBox.h || 0) >= 0.02);

// Làm mờ vùng NGOÀI vùng chọn: invert mask rồi multiply → vùng chọn sáng rõ, phần đảo (ngoài) tối đi.
// mask quy ước: đen=vùng chọn, trắng=ngoài → invert(1) đổi thành trắng=vùng chọn(giữ sáng), đen=ngoài(tối).
const showDimOverlay = computed(() => {
  if (!store.inpaintBrushData) return false;
  const mode = store.inpaintMaskMode;
  return mode === 'freehand' || mode === 'path' || mode === 'magic' || mode === 'rect';
});
const invertedRect = computed(() => store.inpaintMaskMode === 'rect' && !!store.inpaintBrushData);

// Kích thước buffer canvas brush theo TỈ LỆ ẢNH (cạnh dài 512), không vuông cứng để không méo.
const brushCanvasSize = computed(() => {
  const base = 512;
  const img = store.cvImg, f = frame.value;
  let ia = 1;
  if (img && img.naturalWidth > 1 && img.naturalHeight > 1) ia = img.naturalWidth / img.naturalHeight;
  else if (f) ia = f.w / f.h;
  if (ia >= 1) return { width: base, height: Math.max(1, Math.round(base / ia)) };
  return { width: Math.max(1, Math.round(base * ia)), height: base };
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

// ── Toạ độ SVG trong KHUNG ẢNH (px layout) — normalized (0..1) × baseW/baseH ──
// Toạ độ giữ SỐ THẬP PHÂN (0.1px layout) — nếu làm tròn về px layout nguyên thì khi phóng to
// mỗi bước 1px layout = nhiều px màn hình → đường gãy/răng cưa.
const fmt1 = (v) => Math.round(v * 10) / 10;
const pt = (p) => {
  const f = frame.value;
  if (!f) return '';
  return fmt1(p.nx * f.w) + ',' + fmt1(p.ny * f.h);
};
const pathPixels = (pts) => (pts || []).map(pt).join(' ');
const freehandPoints = computed(() => pathPixels(store.inpaintFreehandPoints));
const freehandPaths = computed(() => store.inpaintFreehandPaths.map((pts) => pathPixels(pts)));
// Đường cong path/curve: lấy mẫu Catmull-Rom (đóng kín) qua các điểm neo — sample theo khung ảnh.
const pathSmooth = (pts) => {
  const f = frame.value;
  if (!f || !pts || pts.length < 2) return '';
  const P = pts.map((p) => ({ x: p.nx * f.w, y: p.ny * f.h }));
  const n = P.length, SAMPLES = 24, out = [];
  for (let i = 0; i < n; i++) {
    const p0 = P[(i - 1 + n) % n], p1 = P[i], p2 = P[(i + 1) % n], p3 = P[(i + 2) % n];
    const cx1 = p1.x + (p2.x - p0.x) / 6, cy1 = p1.y + (p2.y - p0.y) / 6;
    const cx2 = p2.x - (p3.x - p1.x) / 6, cy2 = p2.y - (p3.y - p1.y) / 6;
    for (let s = 0; s < SAMPLES; s++) {
      const t = s / SAMPLES, a = 1 - t, b = t;
      const x = a * a * a * p1.x + 3 * a * a * b * cx1 + 3 * a * b * b * cx2 + b * b * b * p2.x;
      const y = a * a * a * p1.y + 3 * a * a * b * cy1 + 3 * a * b * b * cy2 + b * b * b * p2.y;
      out.push(fmt1(x) + ',' + fmt1(y));
    }
  }
  return out.join(' ');
};
const pathSmoothPixels = computed(() => pathSmooth(store.inpaintPathPoints));
const pathRegionsPixels = computed(() => store.inpaintPathRegions.map((pts) => pathSmooth(pts)));

// ── Brush: canvas overlay nét vẽ mask (đỏ 60%) — nằm TRONG khung layer, co giãn đúng theo ảnh ──
const metricsTick = ref(0);
const brushCanvas = ref(null);
let attachedEl = null;
function attachBrush(forceClear = false) {
  const el = store.inpaintMaskMode === 'brush' ? (brushCanvas.value || null) : null;
  if (el && el === attachedEl && !forceClear) return;
  store.attachBrushCanvas(el);
  attachedEl = el;
}

watch(() => store.inpaintMaskMode, () => { nextTick(() => attachBrush()); });
watch(() => store.upscaleSrc, () => {
  if (store.inpaintMaskMode !== 'brush') return;
  store.attachBrushCanvas(null); attachedEl = null;
  store.inpaintBrushData = '';
  nextTick(() => { metricsTick.value++; attachBrush(true); });
});
// Khi tỉ lệ canvas brush đổi (ảnh load xong) → re-attach đúng tỉ lệ
watch(brushCanvasSize, () => {
  if (store.inpaintMaskMode !== 'brush') return;
  nextTick(() => attachBrush(true));
});

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

    <!-- ══ Neo theo khung <img> isolate: wrapper lặp đúng transform pan/zoom + transform layer ══ -->
    <div v-if="anchor" class="pointer-events-none absolute left-1/2 top-1/2" :style="{ width: '0px', height: '0px', transform: anchor.outer, transformOrigin: 'center' }">
      <div class="absolute left-0 top-0" :style="{ width: anchor.w + 'px', height: anchor.h + 'px', transform: anchor.inner, transformOrigin: 'center' }">

        <!-- Brush overlay canvas: hiển thị nét vẽ mask (đỏ 60%) ngay trên ảnh khi ĐANG vẽ -->
        <canvas v-if="store.inpaintMaskMode === 'brush' && store.upscaleSrc" ref="brushCanvas"
                :width="brushCanvasSize.width" :height="brushCanvasSize.height"
                class="pointer-events-none absolute left-0 top-0 h-full w-full rounded-lg"></canvas>

        <!-- Làm mờ vùng ĐẢO NGƯỢC (ngoài vùng chọn) — magic/freehand/path + rect đã đảo -->
        <img v-if="showDimOverlay" :src="'data:image/png;base64,' + store.inpaintBrushData"
             class="pointer-events-none absolute left-0 top-0 h-full w-full rounded-lg mix-blend-multiply"
             :style="{ filter: 'invert(1)', opacity: 0.55 }" alt="Vùng chọn" />

        <!-- Freehand (lasso): hiển thị TẤT CẢ nét đã hoàn thành + nét đang kéo (GIMP-style) -->
        <svg v-if="store.inpaintMaskMode === 'freehand' && (store.inpaintFreehandPaths.length || store.inpaintFreehandPoints.length > 1)"
             class="pointer-events-none absolute left-0 top-0 h-full w-full">
          <template v-for="(path, idx) in freehandPaths" :key="idx">
            <polyline :points="path" fill="none" stroke="#f43f5e" :stroke-width="2 * invScale" stroke-linejoin="round" stroke-linecap="round" />
            <polygon :points="path" fill="rgba(244,63,94,0.12)" stroke="none" />
          </template>
          <polyline v-if="store.inpaintFreehandPoints.length > 1" :points="freehandPoints" fill="none" stroke="#f43f5e" :stroke-width="2 * invScale" stroke-linejoin="round" stroke-linecap="round" />
        </svg>

        <!-- Path (curve) select: hiển thị TẤT CẢ vùng đã đóng + path đang vẽ -->
        <svg v-if="store.inpaintMaskMode === 'path' && (store.inpaintPathRegions.length || store.inpaintPathPoints.length > 1)"
             class="pointer-events-none absolute left-0 top-0 h-full w-full">
          <template v-for="(reg, idx) in pathRegionsPixels" :key="'r'+idx">
            <polyline :points="reg" fill="none" stroke="#a78bfa" :stroke-width="2 * invScale" stroke-linejoin="round" stroke-linecap="round" />
            <polygon :points="reg" fill="rgba(167,139,250,0.12)" stroke="none" />
          </template>
          <polyline v-if="store.inpaintPathPoints.length > 1" :points="pathSmoothPixels" fill="none" stroke="#a78bfa" :stroke-width="2 * invScale" stroke-linejoin="round" stroke-linecap="round" />
          <polygon v-if="store.inpaintPathPoints.length > 2" :points="pathSmoothPixels" fill="rgba(167,139,250,0.12)" stroke="none" />
          <template v-for="(p, i) in store.inpaintPathPoints" :key="'p'+i">
            <circle :cx="p.nx * (anchor.w)" :cy="p.ny * (anchor.h)" :r="circleR" fill="#a78bfa" />
          </template>
        </svg>

        <!-- Rect: box + handles (chỉ khi ĐANG chỉnh) -->
        <div v-if="store.inpaintMaskMode === 'rect'" class="pointer-events-none absolute inset-0">
          <template v-if="hasBox">
            <div class="absolute cursor-move select-none" style="pointer-events:auto; touch-action:none"
                 :style="boxFrameStyle()"
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
        <template v-if="!editing && store.inpaintMaskDone">
          <!-- Rect đã lưu: viền xanh lá -->
          <div v-if="store._inpaintMaskKind === 'rect'" class="pointer-events-none absolute" :style="boxFrameStyle()">
            <div class="pointer-events-none absolute -inset-px border-2 border-dashed border-emerald-400"></div>
            <div class="pointer-events-none absolute -inset-px" style="box-shadow: 0 0 0 9999px rgba(0,0,0,0.45);"></div>
          </div>
          <!-- Brush đã lưu: mask đen-trắng phủ đúng vùng ảnh, opacity cao để thấy rõ -->
          <img v-else-if="store.inpaintBrushData" :src="'data:image/png;base64,' + store.inpaintBrushData"
               class="pointer-events-none absolute left-0 top-0 h-full w-full rounded-lg mix-blend-multiply"
               style="opacity:0.65" alt="Vùng đã vẽ" />
        </template>
      </div>
    </div>
  </div>
</template>
