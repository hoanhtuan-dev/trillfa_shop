<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useStudioStore } from '../store.js';
const store = useStudioStore();

// Hiển thị khi Inpaint đang active mask
const visible = computed(() => store.inpaintMaskMode !== 'none' && store.upscaleSrc);

const maskLabel = computed(() => {
  if (store.inpaintMaskMode === 'rect') return '▭ Kéo chọn vùng cần sửa';
  if (store.inpaintMaskMode === 'brush') return '🖌 Vẽ mask lên vùng cần sửa';
  return '';
});

const hasBox = computed(() => (store.inpaintMaskBox.w || 0) >= 0.02 && (store.inpaintMaskBox.h || 0) >= 0.02);

function onPointerDown(e) { store.inpaintMaskStart(e, store.cvImg); }
function onPointerMove(e) { store.inpaintMaskMove(e, store.cvImg); }
function onPointerUp() { store.inpaintMaskStop(); }
</script>
<template>
  <!-- Mask overlay trên canvas chính (khi Inpaint active mask) -->
  <div v-if="visible" class="absolute inset-0 z-31 cursor-crosshair"
       @pointerdown="onPointerDown"
       @pointermove="onPointerMove"
       @pointerup="onPointerUp"
       @pointercancel="onPointerUp"
       @pointerleave="onPointerUp">
    
    <!-- Rect mask: khung dashed + handles -->
    <div v-if="store.inpaintMaskMode === 'rect'" class="pointer-events-none absolute inset-0">
      <div class="absolute border-2 border-dashed border-brand-300"
           :style="{
             left: (store.inpaintMaskBox.x || 0) * 100 + '%',
             top: (store.inpaintMaskBox.y || 0) * 100 + '%',
             width: (store.inpaintMaskBox.w || 0) * 100 + '%',
             height: (store.inpaintMaskBox.h || 0) * 100 + '%',
             boxShadow: '0 0 0 9999px rgba(0,0,0,0.55)',
           }">
        <template v-if="hasBox">
          <div class="absolute -left-2 -top-2 h-4 w-4 cursor-nwse-resize rounded-sm border-2 border-white bg-brand-400 shadow"
               style="pointer-events:auto; touch-action:none"
               @pointerdown.stop="store._inpaintHandle='nw'; store._inpaintDrag={x:0,y:0,box:{...store.inpaintMaskBox}}"></div>
          <div class="absolute -right-2 -top-2 h-4 w-4 cursor-nesw-resize rounded-sm border-2 border-white bg-brand-400 shadow"
               style="pointer-events:auto; touch-action:none"
               @pointerdown.stop="store._inpaintHandle='ne'; store._inpaintDrag={x:0,y:0,box:{...store.inpaintMaskBox}}"></div>
          <div class="absolute -bottom-2 -left-2 h-4 w-4 cursor-nesw-resize rounded-sm border-2 border-white bg-brand-400 shadow"
               style="pointer-events:auto; touch-action:none"
               @pointerdown.stop="store._inpaintHandle='sw'; store._inpaintDrag={x:0,y:0,box:{...store.inpaintMaskBox}}"></div>
          <div class="absolute -bottom-2 -right-2 h-4 w-4 cursor-nwse-resize rounded-sm border-2 border-white bg-brand-400 shadow"
               style="pointer-events:auto; touch-action:none"
               @pointerdown.stop="store._inpaintHandle='se'; store._inpaintDrag={x:0,y:0,box:{...store.inpaintMaskBox}}"></div>
        </template>
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
