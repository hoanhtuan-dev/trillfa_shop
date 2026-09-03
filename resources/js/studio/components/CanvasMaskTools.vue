<script setup>
import { computed, ref, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { useStudioStore } from '../store.js';
const store = useStudioStore();

const visible = computed(() => store.inpaintMaskMode !== 'none' && store.upscaleSrc);

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

const hasBox = computed(() => (store.inpaintMaskBox.w || 0) >= 0.02 && (store.inpaintMaskBox.h || 0) >= 0.02);

// Bấm ngoài box (container) → tạo vùng mới; brush → vẽ mask.
function onPointerDown(e) { store.inpaintMaskStart(e); }

// ── Brush: canvas overlay THẬT phủ đúng vùng ảnh hiển thị (nét vẽ hiện ngay, đỏ 70%) ──
// canvasMetrics đọc DOM rect → không tự reactive theo zoom/pan. metricsTick là "cái neo"
// reactive: mỗi lần zoom/pan/đổi ảnh, sau khi Vue render xong ta bump → computed chạy lại
// với rect mới → overlay luôn bám đúng vùng ảnh hiển thị.
const metricsTick = ref(0);
const brushCanvas = ref(null);
const brushOverlayStyle = computed(() => {
  void metricsTick.value; // neo reactive
  const m = store.canvasMetrics();
  if (!m || store.inpaintMaskMode !== 'brush') return { display: 'none' };
  return {
    left: (m.vx / m.crW * 100) + '%',
    top: (m.vy / m.crH * 100) + '%',
    width: (m.vw / m.crW * 100) + '%',
    height: (m.vh / m.crH * 100) + '%',
  };
});

let attachedEl = null; // canvas hiện đang gắn — tránh clearRect xoá nét đã vẽ khi re-attach
function attachBrush(forceClear = false) {
  const el = store.inpaintMaskMode === 'brush' ? (brushCanvas.value || null) : null;
  if (el && el === attachedEl && !forceClear) return; // cùng canvas → giữ nét đã vẽ
  store.attachBrushCanvas(el);
  attachedEl = el; // attachBrushCanvas luôn clearRect trên element mới
}

// Bật/tắt brush mode → gắn / gỡ canvas
watch(() => store.inpaintMaskMode, () => { metricsTick.value++; nextTick(() => attachBrush()); });
// Đổi ảnh nguồn → canvas giữ element nhưng phải XOÁ nét cũ (mask cũ không còn hợp lệ)
watch(() => store.upscaleSrc, () => {
  if (store.inpaintMaskMode !== 'brush') return;
  store.attachBrushCanvas(null); attachedEl = null;
  store.inpaintBrushData = '';
  nextTick(() => { metricsTick.value++; attachBrush(true); });
});
// Zoom/pan → chỉ cập nhật vị trí overlay (KHÔNG clear nét)
watch([() => store.zoom, () => store.pan], () => { nextTick(() => { metricsTick.value++; }); });
// Ctrl/Cmd+Z: hoàn tác nét vẽ khi đang ở chế độ brush
function onKeyDown(e) {
  if ((e.ctrlKey || e.metaKey) && (e.key === 'z' || e.key === 'Z')) {
    if (store.inpaintMaskMode === 'brush') { e.preventDefault(); store.undoInpaintBrush(); }
  }
}
onMounted(() => { window.addEventListener('keydown', onKeyDown); });
onBeforeUnmount(() => { store.attachBrushCanvas(null); attachedEl = null; window.removeEventListener('keydown', onKeyDown); });
</script>
<template>
  <div v-if="visible" class="absolute inset-0 z-31 cursor-crosshair" style="touch-action:none"
       @pointerdown="onPointerDown">
    <!-- Brush overlay canvas: hiển thị nét vẽ mask (đỏ 70%) ngay trên ảnh -->
    <canvas v-if="store.inpaintMaskMode === 'brush' && store.upscaleSrc" ref="brushCanvas"
            :width="512" :height="512"
            class="pointer-events-none absolute z-10 rounded-lg"
            :style="brushOverlayStyle"></canvas>

    <!-- Rect: box + handles tự bắt pointerdown với KEY TƯỜNG MINH (.stop) — giống crop,
         kéo/di chuyển không bao giờ nhầm lẫn hay mất vùng -->
    <div v-if="store.inpaintMaskMode === 'rect'" class="pointer-events-none absolute inset-0">
      <template v-if="hasBox">
        <!-- Vùng sáng (interior): kéo để DI CHUYỂN box -->
        <div class="absolute cursor-move select-none" style="pointer-events:auto; touch-action:none"
             :style="maskBoxStyle"
             @pointerdown.stop="store.beginInpaintDrag('move', $event)"
             @dblclick.stop="store.resetInpaintMaskBox()"
             title="Kéo để di chuyển · đúp chuột để vẽ lại vùng">
          <!-- Viền dashed (con của box, pointer-events none → không chặn move) -->
          <div class="pointer-events-none absolute -inset-px border-2 border-dashed border-brand-300"></div>
          <!-- Shadow tối bên ngoài -->
          <div class="pointer-events-none absolute -inset-px" style="box-shadow: 0 0 0 9999px rgba(0,0,0,0.55);"></div>
          <!-- 4 góc resize -->
          <div class="absolute -left-3 -top-3 h-6 w-6 cursor-nwse-resize rounded-sm border-2 border-white bg-brand-400 shadow"
               style="pointer-events:auto; touch-action:none"
               @pointerdown.stop="store.beginInpaintDrag('nw', $event)" title="Kéo góc"></div>
          <div class="absolute -right-3 -top-3 h-6 w-6 cursor-nesw-resize rounded-sm border-2 border-white bg-brand-400 shadow"
               style="pointer-events:auto; touch-action:none"
               @pointerdown.stop="store.beginInpaintDrag('ne', $event)" title="Kéo góc"></div>
          <div class="absolute -bottom-3 -left-3 h-6 w-6 cursor-nesw-resize rounded-sm border-2 border-white bg-brand-400 shadow"
               style="pointer-events:auto; touch-action:none"
               @pointerdown.stop="store.beginInpaintDrag('sw', $event)" title="Kéo góc"></div>
          <div class="absolute -bottom-3 -right-3 h-6 w-6 cursor-nwse-resize rounded-sm border-2 border-white bg-brand-400 shadow"
               style="pointer-events:auto; touch-action:none"
               @pointerdown.stop="store.beginInpaintDrag('se', $event)" title="Kéo góc"></div>
        </div>
      </template>
    </div>

    <!-- ══ Toolbar chung: gắn mép TRÊN (không che giữa ảnh) — chứa nút Xong/Hủy ══ -->
    <div class="pointer-events-none absolute inset-x-0 top-3 z-20 flex justify-center">
      <div class="pointer-events-auto flex items-center gap-1.5 rounded-full bg-ink-900/95 px-2 py-1 text-xs font-semibold shadow-xl ring-1 ring-brand-500/30">
        <!-- Rect: hướng dẫn + kích thước -->
        <template v-if="store.inpaintMaskMode === 'rect'">
          <span class="px-1 text-[10px] font-medium text-cream-300/70">
            {{ hasBox ? ('▭ ' + Math.round((store.inpaintMaskBox.w || 0) * 100) + '% × ' + Math.round((store.inpaintMaskBox.h || 0) * 100) + '%') : '▭ Kéo chọn vùng cần sửa' }}
          </span>
          <button @click.stop="store.confirmInpaintMask()" @pointerdown.stop class="rounded-full bg-brand-600 px-2.5 py-1 text-white transition-colors hover:bg-brand-700" title="Áp dụng vùng chọn và thoát">✅ Xong</button>
        </template>
        <!-- Brush: nút Vẽ/Tẩy/Hoàn tác + cỡ cọ + Xong -->
        <template v-else>
          <button @click.stop="store.inpaintErase = false" @pointerdown.stop :class="!store.inpaintErase ? 'bg-brand-600 text-white' : 'bg-ink-800 text-cream-200 hover:bg-ink-700'"
                  class="rounded-full px-2 py-0.5 transition-colors" title="Vẽ thêm vùng cần sửa">🖌 Vẽ</button>
          <button @click.stop="store.inpaintErase = true" @pointerdown.stop :class="store.inpaintErase ? 'bg-amber-600 text-white' : 'bg-ink-800 text-cream-200 hover:bg-ink-700'"
                  class="rounded-full px-2 py-0.5 transition-colors" title="Tẩy nét đã vẽ (sửa khi lỡ tay)">🧽 Tẩy</button>
          <button @click.stop="store.undoInpaintBrush()" @pointerdown.stop class="rounded-full bg-ink-800 px-2 py-0.5 text-cream-200 transition-colors hover:bg-ink-700" title="Hoàn tác nét vẽ (Ctrl+Z)">↩</button>
          <span class="mx-0.5 h-4 w-px bg-ink-600"></span>
          <span class="text-[10px] text-cream-300/70">Cọ</span>
          <button @click.stop="store.inpaintBrushSize = Math.max(2, (store.inpaintBrushSize||10) - 2)" @pointerdown.stop class="grid h-6 w-6 place-items-center rounded-full bg-ink-700 text-cream-200 hover:bg-ink-600" title="Cọ nhỏ hơn">−</button>
          <span class="min-w-5 text-center text-[11px] text-cream-100">{{ store.inpaintBrushSize || 10 }}</span>
          <button @click.stop="store.inpaintBrushSize = Math.min(48, (store.inpaintBrushSize||10) + 2)" @pointerdown.stop class="grid h-6 w-6 place-items-center rounded-full bg-ink-700 text-cream-200 hover:bg-ink-600" title="Cọ to hơn">+</button>
          <span class="mx-0.5 h-4 w-px bg-ink-600"></span>
          <button @click.stop="store.confirmInpaintMask()" @pointerdown.stop class="rounded-full bg-brand-600 px-2.5 py-1 text-white transition-colors hover:bg-brand-700" title="Lưu vùng vẽ và thoát">✅ Xong</button>
        </template>
        <button @click.stop="store.clearInpaintMask()" @pointerdown.stop class="rounded-full bg-ink-700 px-2 py-0.5 text-cream-200 transition-colors hover:bg-red-600 hover:text-white" title="Bỏ mask hiện tại">✕</button>
      </div>
    </div>

    <!-- Hint nhỏ dưới mép (không che vùng vẽ) -->
    <div class="pointer-events-none absolute inset-x-0 bottom-3 z-20 flex justify-center">
      <div class="rounded-full bg-ink-900/85 px-2.5 py-0.5 text-[10px] font-medium text-cream-300/70">
        {{ store.inpaintMaskMode === 'rect' ? 'Kéo khung để di chuyển · kéo góc để chỉnh · Esc hủy' : (store.inpaintErase ? 'Tẩy nét — sửa chỗ vẽ lỡ' : (store.inpaintBrushData ? '✅ Đã vẽ · Ctrl+Z hoàn tác' : 'Vẽ lên vùng cần sửa · Esc hủy')) }}
      </div>
    </div>
  </div>
</template>