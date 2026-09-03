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

// Chỉ cần pointerdown — việc kéo (move/resize/draw/brush) do store đăng ký
// window listeners nên không bao giờ bị mất khi chuột ra ngoài.
function onPointerDown(e) { store.inpaintMaskStart(e); }
</script>
<template>
  <div v-if="visible" class="absolute inset-0 z-31 cursor-crosshair" style="touch-action:none"
       @pointerdown="onPointerDown">

    <!-- Rect mask: khung dashed + handles (handles KHÔNG .stop → pointerdown rơi xuống container, hit-test tự nhận diện góc/di chuyển) -->
    <div v-if="store.inpaintMaskMode === 'rect'" class="pointer-events-none absolute inset-0">
      <div class="absolute border-2 border-dashed border-brand-300" :style="maskBoxStyle"
           style="box-shadow: 0 0 0 9999px rgba(0,0,0,0.55);">
        <template v-if="hasBox">
          <div class="absolute -left-2.5 -top-2.5 h-5 w-5 cursor-nwse-resize rounded-sm border-2 border-white bg-brand-400 shadow"
               style="pointer-events:auto; touch-action:none" title="Kéo góc"></div>
          <div class="absolute -right-2.5 -top-2.5 h-5 w-5 cursor-nesw-resize rounded-sm border-2 border-white bg-brand-400 shadow"
               style="pointer-events:auto; touch-action:none" title="Kéo góc"></div>
          <div class="absolute -bottom-2.5 -left-2.5 h-5 w-5 cursor-nesw-resize rounded-sm border-2 border-white bg-brand-400 shadow"
               style="pointer-events:auto; touch-action:none" title="Kéo góc"></div>
          <div class="absolute -bottom-2.5 -right-2.5 h-5 w-5 cursor-nwse-resize rounded-sm border-2 border-white bg-brand-400 shadow"
               style="pointer-events:auto; touch-action:none" title="Kéo góc"></div>
        </template>
      </div>
      <div v-if="hasBox" class="absolute left-1/2 top-0 -translate-x-1/2 -translate-y-full whitespace-nowrap rounded-full bg-ink-900/90 px-2 py-0.5 text-[10px] font-semibold text-brand-200">
        Kéo viền/góc để chỉnh · trong khung để di chuyển
      </div>
      <div class="absolute left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full bg-ink-900/90 px-2 py-0.5 text-[10px] font-semibold text-brand-200"
           :class="hasBox ? '-bottom-7' : 'top-1/2 -translate-y-1/2'">
        {{ hasBox ? Math.round((store.inpaintMaskBox.w || 0) * 100) + '% × ' + Math.round((store.inpaintMaskBox.h || 0) * 100) + '%' : 'Kéo chọn vùng cần sửa · Esc để hủy' }}
      </div>
    </div>

    <!-- Brush mode hint -->
    <div v-if="store.inpaintMaskMode === 'brush'" class="pointer-events-none absolute inset-0 grid place-items-center">
      <div class="rounded-full bg-ink-900/90 px-3 py-1 text-xs font-semibold text-brand-200">
        <template v-if="!store.inpaintBrushData">🖌 Vẽ lên vùng cần sửa · Esc để hủy</template>
        <template v-else>✅ Đã vẽ mask · {{ Math.round((store.inpaintMaskBox.w || 0) * 100) }}% × {{ Math.round((store.inpaintMaskBox.h || 0) * 100) }}%</template>
      </div>
    </div>
  </div>
</template>
