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
       @pointerdown="onPointerDown" @contextmenu.prevent @dragstart.prevent>
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

    <!-- ══ Toolbar ngữ cảnh (phía trên, căn giữa) ══ -->
    <div class="pointer-events-none absolute top-3 left-1/2 z-20 flex max-w-[94%] -translate-x-1/2">
      <!-- Đang chỉnh: công cụ -->
      <div v-if="editing" class="pointer-events-auto flex flex-wrap items-center justify-center gap-2 rounded-full bg-ink-900/95 px-3 py-1.5 text-xs font-semibold shadow-xl ring-1 ring-brand-500/30">
        <template v-if="store.inpaintMaskMode === 'rect' || store.inpaintMaskMode === 'freehand'">
          <button @click.stop="store.confirmInpaintMask()" @pointerdown.stop class="flex items-center gap-1 rounded-full bg-brand-600 px-2.5 py-1 text-white transition-colors hover:bg-brand-700" :title="store.inpaintMaskSource === 'canvas' ? 'Thoát vùng chọn' : 'Áp dụng vùng chọn và thoát'"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>Xong</button>
        </template>
        <template v-else>
          <button @click.stop="store.inpaintErase = false" @pointerdown.stop :class="!store.inpaintErase ? 'bg-brand-600 text-white' : 'bg-ink-800 text-cream-200 hover:bg-ink-700'" class="flex items-center gap-1 rounded-full px-2 py-0.5 transition-colors" title="Vẽ thêm vùng cần sửa"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9.06 11.9 8.07-8.06a2.85 2.85 0 1 1 4.03 4.03l-8.06 8.08"/><path d="M7.07 14.94c-1.66 0-3 1.35-3 3.02 0 1.33-2.5 1.52-2 2.02 1.08 1.1 2.49 2.02 4 2.02 2.2 0 4-1.8 4-4.04a3.01 3.01 0 0 0-3-3.02z"/></svg>Vẽ</button>
          <button @click.stop="store.inpaintErase = true" @pointerdown.stop :class="store.inpaintErase ? 'bg-amber-600 text-white' : 'bg-ink-800 text-cream-200 hover:bg-ink-700'" class="flex items-center gap-1 rounded-full px-2 py-0.5 transition-colors" title="Tẩy nét đã vẽ"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7 21-4.3-4.3a1 1 0 0 1 0-1.4l9.7-9.7a1 1 0 0 1 1.4 0l5.7 5.7a1 1 0 0 1 0 1.4L12 19"/><path d="M22 21H7"/><path d="m5 11 9 9"/></svg>Tẩy</button>
          <button @click.stop="store.undoInpaintBrush()" @pointerdown.stop class="rounded-full bg-ink-800 px-2 py-0.5 text-cream-200 transition-colors hover:bg-ink-700" title="Hoàn tác nét vẽ (Ctrl+Z)"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"/></svg></button>
          <span class="mx-0.5 h-4 w-px bg-ink-600"></span>
          <span class="text-[10px] text-cream-300/70">Cọ</span>
          <button @click.stop="store.inpaintBrushSize = Math.max(2, (store.inpaintBrushSize||10) - 2)" @pointerdown.stop class="grid h-6 w-6 place-items-center rounded-full bg-ink-700 text-cream-200 hover:bg-ink-600" title="Cọ nhỏ hơn">−</button>
          <span class="min-w-5 text-center text-[11px] text-cream-100">{{ store.inpaintBrushSize || 10 }}</span>
          <button @click.stop="store.inpaintBrushSize = Math.min(48, (store.inpaintBrushSize||10) + 2)" @pointerdown.stop class="grid h-6 w-6 place-items-center rounded-full bg-ink-700 text-cream-200 hover:bg-ink-600" title="Cọ to hơn">+</button>
          <span class="mx-0.5 h-4 w-px bg-ink-600"></span>
          <button @click.stop="store.confirmInpaintMask()" @pointerdown.stop class="flex items-center gap-1 rounded-full bg-brand-600 px-2.5 py-1 text-white transition-colors hover:bg-brand-700" :title="store.inpaintMaskSource === 'canvas' ? 'Thoát vùng chọn' : 'Lưu vùng vẽ và thoát'"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>Xong</button>
        </template>
        <span class="mx-0.5 h-4 w-px bg-ink-600"></span>
        <span class="text-[10px] text-cream-300/70">Feather</span>
        <input type="range" min="0" max="50" step="5" :value="store.inpaintFeather" @input="store.inpaintFeather = Number($event.target.value)" class="h-1.5 w-16 cursor-pointer accent-brand-500" @pointerdown.stop>
        <span class="min-w-5 text-center text-[11px] text-cream-100">{{ store.inpaintFeather }}</span>
        <template v-if="store.inpaintMaskMode === 'rect' || store.inpaintMaskMode === 'freehand'">
          <span class="mx-0.5 h-4 w-px bg-ink-600"></span>
          <button @click.stop="store.duplicateSelectedRegion()" @pointerdown.stop class="flex items-center gap-1 rounded-full bg-emerald-600/30 px-2 py-0.5 text-cream-200 transition-colors hover:bg-emerald-600" title="Nhân đôi vùng chọn thành layer mới"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>Nhân đôi</button>
          <button @click.stop="store.deleteSelectedRegion()" @pointerdown.stop class="flex items-center gap-1 rounded-full bg-red-600/30 px-2 py-0.5 text-cream-200 transition-colors hover:bg-red-600" title="Xóa nội dung trong vùng chọn"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>Xóa</button>
          <label class="relative inline-flex h-6 w-6 cursor-pointer overflow-hidden rounded-full ring-1 ring-white/20" title="Chọn màu tô">
            <span class="absolute inset-0" :style="{ background: store.inpaintFillColor }"></span>
            <input type="color" :value="store.inpaintFillColor" @input.stop="store.inpaintFillColor = $event.target.value" class="absolute inset-0 cursor-pointer opacity-0" @pointerdown.stop>
          </label>
          <button @click.stop="store.fillSelectedRegion()" @pointerdown.stop class="flex items-center gap-1 rounded-full bg-sky-600/30 px-2 py-0.5 text-cream-200 transition-colors hover:bg-sky-600" title="Tô màu vào vùng chọn"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m19 11-8-8-8.5 8.5a2.12 2.12 0 0 0 0 3L12 24l8.5-8.5a2.12 2.12 0 0 0 0-3z"/></svg>Tô</button>
        </template>
        <button @click.stop="store.clearInpaintMask()" @pointerdown.stop class="rounded-full bg-ink-700 px-2 py-0.5 text-cream-200 transition-colors hover:bg-red-600 hover:text-white" title="Bỏ mask hiện tại"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
      </div>
      <!-- Đã lưu: nút Chỉnh lại / Bỏ -->
      <div v-else class="pointer-events-auto flex items-center gap-2 rounded-full bg-ink-900/95 px-3 py-1.5 text-xs font-semibold shadow-xl ring-1 ring-emerald-500/40">
        <span class="flex items-center gap-1 px-1 text-[10px] font-medium text-emerald-200"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>Đã lưu vùng</span>
        <button @click.stop="store.toggleInpaintMask(store._inpaintMaskKind)" @pointerdown.stop class="flex items-center gap-1 rounded-full bg-white/10 px-2 py-0.5 text-cream-100 transition-colors hover:bg-white/20" title="Mở lại để chỉnh sửa"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5z"/></svg>Chỉnh lại</button>
        <button @click.stop="store.clearInpaintMask()" @pointerdown.stop class="rounded-full bg-ink-700 px-2 py-0.5 text-cream-200 transition-colors hover:bg-red-600 hover:text-white" title="Bỏ mask"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
      </div>
    </div>

    <!-- Hint phía dưới, căn giữa -->
    <div v-if="editing" class="pointer-events-none absolute bottom-3 left-1/2 z-20 flex -translate-x-1/2">
      <div class="rounded-full bg-ink-900/85 px-2.5 py-0.5 text-[10px] font-medium text-cream-300/70">
        {{ store.inpaintMaskMode === 'rect' ? 'Kéo khung để di chuyển · kéo góc để chỉnh · Esc hủy' : (store.inpaintErase ? 'Tẩy nét — sửa chỗ vẽ lỡ' : (store.inpaintBrushData ? '✅ Đã vẽ · Ctrl+Z hoàn tác' : 'Vẽ lên vùng cần sửa · Esc hủy')) }}
      </div>
    </div>
  </div>
</template>

