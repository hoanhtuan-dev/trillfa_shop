<script setup>
import { useStudioStore } from '../store.js';
import StudioIcon from './StudioIcon.vue';
const store = useStudioStore();
</script>
<template>
  <!-- Thanh công cụ canvas: desktop = dải dọc giữa trái · mobile = dải ngang dưới đáy.
       (Trước đây ẩn hẳn trên mobile → không có lối vào công cụ Xóa/Vẽ/Vùng chọn.) -->
  <div v-if="store.upscaleSrc" class="pointer-events-none absolute inset-x-0 bottom-16 z-40 flex justify-center lg:inset-auto lg:left-2 lg:top-1/2 lg:bottom-auto lg:-translate-y-1/2 lg:justify-start">
    <div class="scrollbar-hide pointer-events-auto flex max-w-[calc(100vw-1.5rem)] items-center gap-1 overflow-x-auto rounded-2xl border border-ink-700 bg-ink-900/85 p-1.5 shadow-xl backdrop-blur lg:max-w-none lg:flex-col lg:items-center lg:overflow-visible">
      <button @click="store.finishDraw(); store.reframeOpen = !store.reframeOpen; store.filmOpen = false; store.exitErase(); store.clearInpaintMask()"
        :class="(store.reframeOpen || store.cropMode) ? 'bg-brand-600 text-white border-brand-400 shadow-brand-500/40' : 'text-cream-200 border-transparent hover:bg-ink-700'"
        class="grid h-9 w-9 shrink-0 place-items-center rounded-xl border transition-colors lg:h-11 lg:w-11" title="Reframe / Crop · Cắt khung theo tỷ lệ / chọn vùng" :aria-label="'Reframe / Crop · Cắt khung theo tỷ lệ / chọn vùng'">
        <StudioIcon name="crop" size="h-[20px] w-[20px] lg:h-[22px] lg:w-[22px]"/>
      </button>
      <button @click="store.finishDraw(); store.startCanvasSelect('rect'); store.reframeOpen = false; store.filmOpen = false; store.exitErase()"
        :class="(store.inpaintMaskMode === 'rect' && store.inpaintMaskSource === 'canvas') ? 'bg-brand-600 text-white border-brand-400 shadow-brand-500/40' : 'text-cream-200 border-transparent hover:bg-ink-700'"
        class="grid h-9 w-9 shrink-0 place-items-center rounded-xl border transition-colors lg:h-11 lg:w-11" title="Vẽ vùng chọn (chữ nhật) · Xóa / Tô màu / Feather" :aria-label="'Vẽ vùng chọn (chữ nhật) · Xóa / Tô màu / Feather'">
        <StudioIcon name="boxSelect" size="h-[20px] w-[20px] lg:h-[22px] lg:w-[22px]"/>
      </button>
      <button @click="store.finishDraw(); store.startCanvasSelect('freehand'); store.reframeOpen = false; store.filmOpen = false; store.exitErase()"
        :class="(store.inpaintMaskMode === 'freehand' && store.inpaintMaskSource === 'canvas') ? 'bg-brand-600 text-white border-brand-400 shadow-brand-500/40' : 'text-cream-200 border-transparent hover:bg-ink-700'"
        class="grid h-9 w-9 shrink-0 place-items-center rounded-xl border transition-colors lg:h-11 lg:w-11" title="Vẽ vùng chọn tự do (lasso) · Xóa / Tô màu / Feather" :aria-label="'Vẽ vùng chọn tự do (lasso) · Xóa / Tô màu / Feather'">
        <StudioIcon name="lasso" size="h-[20px] w-[20px] lg:h-[22px] lg:w-[22px]"/>
      </button>
      <button @click="store.finishDraw(); store.startCanvasSelect('path'); store.reframeOpen = false; store.filmOpen = false; store.exitErase()"
        :class="(store.inpaintMaskMode === 'path' && store.inpaintMaskSource === 'canvas') ? 'bg-brand-600 text-white border-brand-400 shadow-brand-500/40' : 'text-cream-200 border-transparent hover:bg-ink-700'"
        class="grid h-9 w-9 shrink-0 place-items-center rounded-xl border transition-colors lg:h-11 lg:w-11" title="Vùng chọn bằng đường cong (path/curve) · click thêm điểm · Đóng để tạo vùng" :aria-label="'Vùng chọn bằng đường cong (path/curve) · click thêm điểm · Đóng để tạo vùng'">
        <StudioIcon name="penTool" size="h-[20px] w-[20px] lg:h-[22px] lg:w-[22px]"/>
      </button>
      <button @click="store.finishDraw(); store.startCanvasSelect('magic'); store.reframeOpen = false; store.filmOpen = false; store.exitErase()"
        :class="(store.inpaintMaskMode === 'magic' && store.inpaintMaskSource === 'canvas') ? 'bg-brand-600 text-white border-brand-400 shadow-brand-500/40' : 'text-cream-200 border-transparent hover:bg-ink-700'"
        class="grid h-9 w-9 shrink-0 place-items-center rounded-xl border transition-colors lg:h-11 lg:w-11" title="Magic Wand · chọn vùng theo màu (click 1 điểm)" :aria-label="'Magic Wand · chọn vùng theo màu (click 1 điểm)'">
        <StudioIcon name="wand" size="h-[20px] w-[20px] lg:h-[22px] lg:w-[22px]"/>
      </button>
      <button @click="store.toggleDraw(); store.reframeOpen = false; store.filmOpen = false; store.clearInpaintMask(); store.exitErase()"
        :class="store.drawMode ? 'bg-brand-600 text-white border-brand-400 shadow-brand-500/40' : 'text-cream-200 border-transparent hover:bg-ink-700'"
        class="grid h-9 w-9 shrink-0 place-items-center rounded-xl border transition-colors lg:h-11 lg:w-11" title="Vẽ tự do (brush)" :aria-label="'Vẽ tự do (brush)'">
        <StudioIcon name="brush" size="h-[20px] w-[20px] lg:h-[22px] lg:w-[22px]"/>
      </button>
      <div class="h-px w-6 shrink-0 bg-ink-700 lg:h-6 lg:w-px"></div>
      <button @click="store.finishDraw(); store.toggleErase(); store.reframeOpen = false; store.filmOpen = false; store.clearInpaintMask()"
        :class="store.eraseMode ? 'bg-brand-600 text-white border-brand-400 shadow-brand-500/40' : 'text-cream-200 border-transparent hover:bg-ink-700'"
        class="grid h-9 w-9 shrink-0 place-items-center rounded-xl border transition-colors lg:h-11 lg:w-11" title="Xóa vùng (feather)" :aria-label="'Xóa vùng (feather)'">
        <StudioIcon name="eraser" size="h-[20px] w-[20px] lg:h-[22px] lg:w-[22px]"/>
      </button>
      <button @click="store.finishDraw(); store.filmOpen = !store.filmOpen; store.reframeOpen = false; store.exitErase(); store.clearInpaintMask()"
        :class="(store.filmOpen || store.looking) ? 'bg-brand-600 text-white border-brand-400 shadow-brand-500/40' : 'text-cream-200 border-transparent hover:bg-ink-700'"
        class="grid h-9 w-9 shrink-0 place-items-center rounded-xl border transition-colors lg:h-11 lg:w-11" title="Film Look · Gán tone màu phim" :aria-label="'Film Look · Gán tone màu phim'">
        <StudioIcon name="palette" size="h-[20px] w-[20px] lg:h-[22px] lg:w-[22px]"/>
      </button>
    </div>
  </div>
</template>
