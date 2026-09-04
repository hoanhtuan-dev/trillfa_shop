<script setup>
import { computed, ref, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { useStudioStore } from '../store.js';
const store = useStudioStore();

// Hiển thị overlay khi: đang chỉnh (rect/brush) HOẶC đã lưu (done — vẫn xem được vùng đã chọn)
const visible = computed(() => store.upscaleSrc && (store.inpaintMaskMode !== 'none' || store.inpaintMaskDone));
const editing = computed(() => store.inpaintMaskMode !== 'none');

const maskBoxStyle = computed(() => {
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

    <!-- ══ Toolbar góc trên-trái ══ -->
    <div class="pointer-events-none absolute left-3 top-3 z-20 flex">
      <!-- Đang chỉnh: công cụ -->
      <div v-if="editing" class="pointer-events-auto flex items-center gap-1.5 rounded-full bg-ink-900/95 px-2 py-1 text-xs font-semibold shadow-xl ring-1 ring-brand-500/30">
        <template v-if="store.inpaintMaskMode === 'rect' || store.inpaintMaskMode === 'freehand'">
          <span class="px-1 text-[10px] font-medium text-cream-300/70">
            {{ store.inpaintMaskMode === 'freehand' ? '✏️ Vẽ tự do — kéo chuột để khoanh vùng' : (hasBox ? ('▭ ' + Math.round((store.inpaintMaskBox.w || 0) * 100) + '% × ' + Math.round((store.inpaintMaskBox.h || 0) * 100) + '%') : '▭ Kéo chọn vùng cần sửa') }}
          </span>
          <button @click.stop="store.confirmInpaintMask()" @pointerdown.stop class="rounded-full bg-brand-600 px-2.5 py-1 text-white transition-colors hover:bg-brand-700" :title="store.inpaintMaskSource === 'canvas' ? 'Thoát vùng chọn' : 'Áp dụng vùng chọn và thoát'">{{ store.inpaintMaskSource === 'canvas' ? '✓ Xong' : '✅ Xong' }}</button>
        </template>
        <template v-else>
          <button @click.stop="store.inpaintErase = false" @pointerdown.stop :class="!store.inpaintErase ? 'bg-brand-600 text-white' : 'bg-ink-800 text-cream-200 hover:bg-ink-700'" class="rounded-full px-2 py-0.5 transition-colors" title="Vẽ thêm vùng cần sửa">🖌 Vẽ</button>
          <button @click.stop="store.inpaintErase = true" @pointerdown.stop :class="store.inpaintErase ? 'bg-amber-600 text-white' : 'bg-ink-800 text-cream-200 hover:bg-ink-700'" class="rounded-full px-2 py-0.5 transition-colors" title="Tẩy nét đã vẽ">🧽 Tẩy</button>
          <button @click.stop="store.undoInpaintBrush()" @pointerdown.stop class="rounded-full bg-ink-800 px-2 py-0.5 text-cream-200 transition-colors hover:bg-ink-700" title="Hoàn tác nét vẽ (Ctrl+Z)">↩</button>
          <span class="mx-0.5 h-4 w-px bg-ink-600"></span>
          <span class="text-[10px] text-cream-300/70">Cọ</span>
          <button @click.stop="store.inpaintBrushSize = Math.max(2, (store.inpaintBrushSize||10) - 2)" @pointerdown.stop class="grid h-6 w-6 place-items-center rounded-full bg-ink-700 text-cream-200 hover:bg-ink-600" title="Cọ nhỏ hơn">−</button>
          <span class="min-w-5 text-center text-[11px] text-cream-100">{{ store.inpaintBrushSize || 10 }}</span>
          <button @click.stop="store.inpaintBrushSize = Math.min(48, (store.inpaintBrushSize||10) + 2)" @pointerdown.stop class="grid h-6 w-6 place-items-center rounded-full bg-ink-700 text-cream-200 hover:bg-ink-600" title="Cọ to hơn">+</button>
          <span class="mx-0.5 h-4 w-px bg-ink-600"></span>
          <button @click.stop="store.confirmInpaintMask()" @pointerdown.stop class="rounded-full bg-brand-600 px-2.5 py-1 text-white transition-colors hover:bg-brand-700" :title="store.inpaintMaskSource === 'canvas' ? 'Thoát vùng chọn' : 'Lưu vùng vẽ và thoát'">{{ store.inpaintMaskSource === 'canvas' ? '✓ Xong' : '✅ Xong' }}</button>
        </template>
        <span class="mx-0.5 h-4 w-px bg-ink-600"></span>
        <span class="text-[10px] text-cream-300/70">Feather</span>
        <input type="range" min="0" max="50" step="5" :value="store.inpaintFeather" @input="store.inpaintFeather = Number($event.target.value)" class="h-1.5 w-16 cursor-pointer accent-brand-500" @pointerdown.stop>
        <span class="min-w-5 text-center text-[11px] text-cream-100">{{ store.inpaintFeather }}</span>
        <template v-if="store.inpaintMaskMode === 'rect' || store.inpaintMaskMode === 'freehand'">
          <span class="mx-0.5 h-4 w-px bg-ink-600"></span>
          <button @click.stop="store.deleteSelectedRegion()" @pointerdown.stop class="rounded-full bg-red-600/30 px-2 py-0.5 text-cream-200 transition-colors hover:bg-red-600" title="Xóa nội dung trong vùng chọn">🧹 Xóa</button>
          <label class="relative inline-flex h-6 w-6 cursor-pointer overflow-hidden rounded-full ring-1 ring-white/20" title="Chọn màu tô">
            <span class="absolute inset-0" :style="{ background: store.inpaintFillColor }"></span>
            <input type="color" :value="store.inpaintFillColor" @input.stop="store.inpaintFillColor = $event.target.value" class="absolute inset-0 cursor-pointer opacity-0" @pointerdown.stop>
          </label>
          <button @click.stop="store.fillSelectedRegion()" @pointerdown.stop class="rounded-full bg-sky-600/30 px-2 py-0.5 text-cream-200 transition-colors hover:bg-sky-600" title="Tô màu vào vùng chọn">🎨 Tô</button>
        </template>
        <button @click.stop="store.clearInpaintMask()" @pointerdown.stop class="rounded-full bg-ink-700 px-2 py-0.5 text-cream-200 transition-colors hover:bg-red-600 hover:text-white" title="Bỏ mask hiện tại">✕</button>
      </div>
      <!-- Đã lưu: nút Chỉnh lại / Bỏ -->
      <div v-else class="pointer-events-auto flex items-center gap-1.5 rounded-full bg-ink-900/95 px-2 py-1 text-xs font-semibold shadow-xl ring-1 ring-emerald-500/40">
        <span class="px-1 text-[10px] font-medium text-emerald-200">✅ Đã lưu vùng</span>
        <button @click.stop="store.toggleInpaintMask(store._inpaintMaskKind)" @pointerdown.stop class="rounded-full bg-white/10 px-2 py-0.5 text-cream-100 transition-colors hover:bg-white/20" title="Mở lại để chỉnh sửa">✏️ Chỉnh lại</button>
        <button @click.stop="store.clearInpaintMask()" @pointerdown.stop class="rounded-full bg-ink-700 px-2 py-0.5 text-cream-200 transition-colors hover:bg-red-600 hover:text-white" title="Bỏ mask">✕</button>
      </div>
    </div>

    <!-- Hint dưới mép trái -->
    <div v-if="editing" class="pointer-events-none absolute bottom-3 left-3 z-20 flex">
      <div class="rounded-full bg-ink-900/85 px-2.5 py-0.5 text-[10px] font-medium text-cream-300/70">
        {{ store.inpaintMaskMode === 'rect' ? 'Kéo khung để di chuyển · kéo góc để chỉnh · Esc hủy' : (store.inpaintErase ? 'Tẩy nét — sửa chỗ vẽ lỡ' : (store.inpaintBrushData ? '✅ Đã vẽ · Ctrl+Z hoàn tác' : 'Vẽ lên vùng cần sửa · Esc hủy')) }}
      </div>
    </div>
  </div>
</template>

