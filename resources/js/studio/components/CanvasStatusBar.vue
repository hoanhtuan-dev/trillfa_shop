<script setup>
// CanvasStatusBar — thanh trạng thái dock dưới khung canvas (SPEC: STUDIO_UI_REDESIGN.md §4.2).
// Không props — đọc/ghi trực tiếp useStudioStore(). Mọi icon qua <StudioIcon/>.
import { useStudioStore } from '../store.js';
import StudioIcon from './StudioIcon.vue';

const store = useStudioStore();

// Nút icon chuẩn (spec §4.2): h-7 hit-target, hover nền ink-700, disabled mờ 30%.
const BTN = 'grid h-7 w-7 place-items-center rounded-md text-cream-200 hover:bg-ink-700 disabled:opacity-30';

// Nền swatch — inline style y hệt pill cũ StudioApp (grid checker / dark / white / cream).
const bgSwatchStyle = (b) => ({
  background: b === 'grid'
    ? 'repeating-conic-gradient(#888 0 25%, #ccc 0 50%) 0 / 10px 10px'
    : b === 'dark'
      ? '#0a0a0f'
      : b === 'white'
        ? '#fff'
        : '#f5ead9',
});
</script>

<template>
  <div class="relative z-30 flex h-9 shrink-0 items-center gap-1 border-t border-ink-700 bg-ink-900/95 px-2">
    <!-- 1. Undo / Redo -->
    <button @click="store.undo()" :disabled="!store.undoStack.length" :class="BTN" title="Hoàn tác (Ctrl+Z)" aria-label="Hoàn tác (Ctrl+Z)"><StudioIcon name="undo" /></button>
    <button @click="store.redo()" :disabled="!store.redoStack.length" :class="BTN" title="Làm lại (Ctrl+Y)" aria-label="Làm lại (Ctrl+Y)"><StudioIcon name="redo" /></button>

    <!-- 2. Divider -->
    <div class="h-4 w-px bg-ink-700" aria-hidden="true"></div>

    <!-- 3. Zoom -->
    <button @click="store.zoomOut()" :class="BTN" title="Thu nhỏ" aria-label="Thu nhỏ"><StudioIcon name="zoomOut" /></button>
    <button @click="store.zoomFit()" class="min-w-12 rounded-md px-1 py-1 text-center text-[11px] tabular-nums text-cream-200 hover:bg-ink-700">{{ Math.round(store.zoom * 100) }}%</button>
    <button @click="store.zoomIn()" :class="BTN" title="Phóng to" aria-label="Phóng to"><StudioIcon name="zoomIn" /></button>
    <button @click="store.zoomFit()" :class="BTN" title="Vừa khung hình" aria-label="Vừa khung hình"><StudioIcon name="maximize" /></button>

    <!-- 4. Divider -->
    <div class="h-4 w-px bg-ink-700" aria-hidden="true"></div>

    <!-- 5. Nền canvas -->
    <button
      v-for="b in ['grid', 'dark', 'white', 'cream']"
      :key="b"
      @click="store.canvasBg = b"
      class="h-5 w-5 rounded-full border border-ink-600"
      :class="store.canvasBg === b ? 'ring-2 ring-brand-400' : ''"
      :style="bgSwatchStyle(b)"
      :title="'Nền: ' + b"
      :aria-label="'Nền: ' + b"
    ></button>

    <!-- 6. Spacer -->
    <div class="flex-1"></div>

    <!-- 7. Trạng thái (md+) -->
    <div class="hidden items-center gap-1.5 text-[10px] text-cream-300/60 md:flex">
      <StudioIcon name="layers" size="h-3.5 w-3.5" />
      <span>{{ store.canvasLayers.length }} lớp</span>
      <template v-if="store.activeLayer">
        <span class="max-w-32 truncate">· {{ store.activeLayer.name }}</span>
        <span>{{ Math.round((store.activeLayer.scale || 1) * 100) }}%</span>
      </template>
    </div>

    <!-- 8. Download -->
    <button
      @click="store.downloadActive()"
      :disabled="!store.upscaleSrc"
      class="grid h-7 w-7 place-items-center rounded-md bg-brand-600 text-white transition-colors hover:bg-brand-500 disabled:opacity-30"
      title="Tải ảnh đang chọn"
      aria-label="Tải ảnh đang chọn"
    ><StudioIcon name="download" /></button>

    <!-- 9. Toggle inspector (lg+) -->
    <button
      @click="store.toggleInspector()"
      class="grid h-7 w-7 place-items-center rounded-md text-cream-200 hover:bg-ink-700 disabled:opacity-30"
      :class="store.inspectorOpen ? 'bg-brand-600/20 text-brand-300' : ''"
      title="Bật/tắt panel Layers"
      aria-label="Bật/tắt panel Layers"
    ><StudioIcon name="panelRight" /></button>
  </div>
</template>
