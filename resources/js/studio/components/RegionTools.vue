<script setup>
import { useStudioStore } from '../store.js';
const store = useStudioStore();
</script>
<template>
  <div class="absolute left-2 top-1/2 z-40 -translate-y-1/2" v-if="store.upscaleSrc">
    <div class="flex flex-col items-center gap-1 rounded-2xl border border-ink-700 bg-ink-900/85 p-1.5 shadow-xl backdrop-blur">
      <button @click="store.finishDraw(); store.reframeOpen = !store.reframeOpen; store.filmOpen = false; store.exitErase(); store.clearInpaintMask()"
        :class="(store.reframeOpen || store.cropMode) ? 'bg-brand-600 text-white border-brand-400 shadow-brand-500/40' : 'text-cream-200 border-transparent hover:bg-ink-700'"
        class="grid h-11 w-11 place-items-center rounded-xl border transition-colors" title="Reframe / Crop · Cắt khung theo tỷ lệ / chọn vùng">
        <svg class="h-[22px] w-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2v14a2 2 0 0 0 2 2h14"/><path d="M18 22V8a2 2 0 0 0-2-2H2"/></svg>
      </button>
      <button @click="store.finishDraw(); store.startCanvasSelect('rect'); store.reframeOpen = false; store.filmOpen = false; store.exitErase()"
        :class="(store.inpaintMaskMode === 'rect' && store.inpaintMaskSource === 'canvas') ? 'bg-brand-600 text-white border-brand-400 shadow-brand-500/40' : 'text-cream-200 border-transparent hover:bg-ink-700'"
        class="grid h-11 w-11 place-items-center rounded-xl border transition-colors" title="Vẽ vùng chọn (chữ nhật) · Xóa / Tô màu / Feather">
        <svg class="h-[22px] w-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3a2 2 0 0 0-2 2"/><path d="M19 3a2 2 0 0 1 2 2"/><path d="M21 19a2 2 0 0 1-2 2"/><path d="M5 21a2 2 0 0 1-2-2"/><path d="M9 3h1"/><path d="M14 3h1"/><path d="M9 21h1"/><path d="M14 21h1"/><path d="M3 9v1"/><path d="M3 14v1"/><path d="M21 9v1"/><path d="M21 14v1"/></svg>
      </button>
      <button @click="store.finishDraw(); store.startCanvasSelect('freehand'); store.reframeOpen = false; store.filmOpen = false; store.exitErase()"
        :class="(store.inpaintMaskMode === 'freehand' && store.inpaintMaskSource === 'canvas') ? 'bg-brand-600 text-white border-brand-400 shadow-brand-500/40' : 'text-cream-200 border-transparent hover:bg-ink-700'"
        class="grid h-11 w-11 place-items-center rounded-xl border transition-colors" title="Vẽ vùng chọn tự do (lasso) · Xóa / Tô màu / Feather">
        <svg class="h-[22px] w-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 19c0-7 2-11 6-13 1.5-.7 3-1 5-1"/><path d="M11 5c3-2 6-1 8 2 2 3 2 8-2 11-2.5 1.9-5.5 2-8 1"/><path d="M8 13c-1.5 1.5-2 3.5-1 5.5"/><path d="M3 21l4-4"/></svg>
      </button>
      <button @click="store.finishDraw(); store.startCanvasSelect('path'); store.reframeOpen = false; store.filmOpen = false; store.exitErase()"
        :class="(store.inpaintMaskMode === 'path' && store.inpaintMaskSource === 'canvas') ? 'bg-brand-600 text-white border-brand-400 shadow-brand-500/40' : 'text-cream-200 border-transparent hover:bg-ink-700'"
        class="grid h-11 w-11 place-items-center rounded-xl border transition-colors" title="Vùng chọn bằng đường cong (path/curve) · click thêm điểm · Đóng để tạo vùng">
        <svg class="h-[22px] w-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15.707 21.293a1 1 0 0 1-1.414 0l-1.586-1.586a1 1 0 0 1 0-1.414l5.586-5.586a1 1 0 0 1 1.414 0l1.586 1.586a1 1 0 0 1 0 1.414z"/><path d="m18 13-1.375-6.874a1 1 0 0 0-.746-.776L3.235 2.028a1 1 0 0 0-1.207 1.207L5.35 15.879a1 1 0 0 0 .776.746L13 18"/><path d="m2.3 2.3 7.286 7.286"/><circle cx="11" cy="11" r="2"/></svg>
      </button>
      <button @click="store.finishDraw(); store.startCanvasSelect('magic'); store.reframeOpen = false; store.filmOpen = false; store.exitErase()"
        :class="(store.inpaintMaskMode === 'magic' && store.inpaintMaskSource === 'canvas') ? 'bg-brand-600 text-white border-brand-400 shadow-brand-500/40' : 'text-cream-200 border-transparent hover:bg-ink-700'"
        class="grid h-11 w-11 place-items-center rounded-xl border transition-colors" title="Magic Wand · chọn vùng theo màu (click 1 điểm)">
        <svg class="h-[22px] w-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21 2-2 2"/><path d="M15 6.6 3.3 18.3a2 2 0 0 0 0 2.8l2.6 2.6a2 2 0 0 0 2.8 0L20.4 12a2 2 0 0 0 0-2.8z"/><path d="m11 12 2 2"/></svg>
      </button>
      <button @click="store.toggleDraw(); store.reframeOpen = false; store.filmOpen = false; store.clearInpaintMask(); store.exitErase()"
        :class="store.drawMode ? 'bg-brand-600 text-white border-brand-400 shadow-brand-500/40' : 'text-cream-200 border-transparent hover:bg-ink-700'"
        class="grid h-11 w-11 place-items-center rounded-xl border transition-colors" title="Vẽ tự do (brush)">
        <svg class="h-[22px] w-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9.06 11.9 8.07-8.06a2.85 2.85 0 1 1 4.03 4.03l-8.06 8.08"/><path d="M7.07 14.94c-1.66 0-3 1.35-3 3.02 0 1.33-2.5 1.52-2 2.02 1.08 1.1 2.49 2.02 4 2.02 2.2 0 4-1.8 4-4.04a3.01 3.01 0 0 0-3-3.02z"/></svg>
      </button>
      <div class="h-px w-6 bg-ink-700"></div>
      <button @click="store.finishDraw(); store.toggleErase(); store.reframeOpen = false; store.filmOpen = false; store.clearInpaintMask()"
        :class="store.eraseMode ? 'bg-brand-600 text-white border-brand-400 shadow-brand-500/40' : 'text-cream-200 border-transparent hover:bg-ink-700'"
        class="grid h-11 w-11 place-items-center rounded-xl border transition-colors" title="Xóa vùng (feather)">
        <svg class="h-[22px] w-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7 21-4.3-4.3a1 1 0 0 1 0-1.4l9.7-9.7a1 1 0 0 1 1.4 0l5.7 5.7a1 1 0 0 1 0 1.4L12 19"/><path d="M22 21H7"/><path d="m5 11 9 9"/></svg>
      </button>
      <button @click="store.finishDraw(); store.filmOpen = !store.filmOpen; store.reframeOpen = false; store.exitErase(); store.clearInpaintMask()"
        :class="(store.filmOpen || store.looking) ? 'bg-brand-600 text-white border-brand-400 shadow-brand-500/40' : 'text-cream-200 border-transparent hover:bg-ink-700'"
        class="grid h-11 w-11 place-items-center rounded-xl border transition-colors" title="Film Look · Gán tone màu phim">
        <svg class="h-[22px] w-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/></svg>
      </button>
    </div>
  </div>
</template>
