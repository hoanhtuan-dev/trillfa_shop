<script setup>
import { computed } from 'vue';
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
</script>
<template>
  <div v-if="visible" class="absolute inset-0 z-31 cursor-crosshair" style="touch-action:none"
       @pointerdown="onPointerDown">

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
        <!-- Label trên: hướng dẫn -->
        <div class="pointer-events-none absolute left-1/2 -translate-x-1/2 -translate-y-full whitespace-nowrap rounded-full bg-ink-900/90 px-2 py-0.5 text-[10px] font-semibold text-brand-200"
             :style="{ left: '50%', top: (store.inpaintMaskBox.y * 100) + '%' }">
          Kéo trong khung để di chuyển · góc để chỉnh
        </div>
      </template>
      <!-- Hint khi chưa có box -->
      <div v-if="!hasBox" class="pointer-events-none absolute inset-0 grid place-items-center">
        <div class="rounded-full bg-ink-900/90 px-3 py-1 text-[11px] font-semibold text-brand-200">▭ Kéo chọn vùng cần sửa · Esc để hủy</div>
      </div>
      <!-- Label kích thước dưới -->
      <div v-if="hasBox" class="pointer-events-none absolute left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full bg-ink-900/90 px-2 py-0.5 text-[10px] font-semibold text-cream-100"
           :style="{ top: ((store.inpaintMaskBox.y + store.inpaintMaskBox.h) * 100) + '%', marginTop: '4px' }">
        {{ Math.round((store.inpaintMaskBox.w || 0) * 100) }}% × {{ Math.round((store.inpaintMaskBox.h || 0) * 100) }}%
      </div>
    </div>

    <!-- Brush mode hint -->
    <div v-if="store.inpaintMaskMode === 'brush'" class="pointer-events-none absolute inset-0 grid place-items-center">
      <div class="rounded-full bg-ink-900/90 px-3 py-1 text-xs font-semibold text-brand-200">
        <template v-if="!store.inpaintBrushData">🖌 Vẽ lên vùng cần sửa · Esc để hủy</template>
        <template v-else>✅ Đã vẽ mask</template>
      </div>
    </div>
  </div>
</template>
