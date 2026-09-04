<script setup>
import { computed } from 'vue';
import { useStudioStore } from '../store.js';
const store = useStudioStore();

const reframeRatios = ['1:1','3:4','4:5','9:16','16:9','2:3'];
const looks = [['studio','Studio'],['warm','Ấm'],['cool','Lạnh'],['cinematic','Điện ảnh'],['dramatic','Dramatic'],['retro','Retro'],['mono','Mono']];
const hasBox = computed(() => (store.inpaintMaskBox.w || 0) >= 0.02 && (store.inpaintMaskBox.h || 0) >= 0.02);
</script>
<template>
  <!-- ══ Vùng chọn (rect/freehand/brush) ══ -->
  <div v-if="store.inpaintMaskMode !== 'none'" class="flex flex-wrap items-center justify-center gap-2 rounded-full bg-ink-900/95 px-3 py-1.5 text-xs font-semibold shadow-xl ring-1 ring-brand-500/30">
    <template v-if="store.inpaintMaskMode === 'rect' || store.inpaintMaskMode === 'freehand' || store.inpaintMaskMode === 'path' || store.inpaintMaskMode === 'magic'">
      <template v-if="store.inpaintMaskMode === 'path'">
        <button @click="store.pathClose()" class="flex items-center gap-1 rounded-full bg-violet-600 px-2.5 py-1 text-white transition-colors hover:bg-violet-500" title="Đóng vùng chọn từ đường cong">Đóng</button>
        <button @click="store.pathUndoPoint()" class="grid h-6 w-6 place-items-center rounded-full bg-ink-800 text-cream-200 transition-colors hover:bg-ink-700" title="Bỏ điểm neo cuối">
          <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"/></svg>
        </button>
      </template>
      <template v-if="store.inpaintMaskMode === 'magic'">
        <span class="text-[10px] text-cream-300/70">Ngưỡng</span>
        <input type="range" min="2" max="128" step="2" :value="store.magicTolerance" @input="store.magicTolerance = Number($event.target.value)" class="h-1.5 w-20 cursor-pointer accent-brand-500">
        <span class="min-w-7 text-center text-[11px] text-cream-100">{{ store.magicTolerance }}</span>
      </template>
      <button @click="store.confirmInpaintMask()" class="flex items-center gap-1 rounded-full bg-brand-600 px-2.5 py-1 text-white transition-colors hover:bg-brand-700" :title="store.inpaintMaskSource === 'canvas' ? 'Thoát vùng chọn' : 'Áp dụng vùng chọn và thoát'">
        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>Xong
      </button>
    </template>
    <template v-else>
      <button @click="store.inpaintErase = false" :class="!store.inpaintErase ? 'bg-brand-600 text-white' : 'bg-ink-800 text-cream-200 hover:bg-ink-700'" class="flex items-center gap-1 rounded-full px-2 py-0.5 transition-colors" title="Vẽ thêm vùng cần sửa">
        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9.06 11.9 8.07-8.06a2.85 2.85 0 1 1 4.03 4.03l-8.06 8.08"/><path d="M7.07 14.94c-1.66 0-3 1.35-3 3.02 0 1.33-2.5 1.52-2 2.02 1.08 1.1 2.49 2.02 4 2.02 2.2 0 4-1.8 4-4.04a3.01 3.01 0 0 0-3-3.02z"/></svg>Vẽ
      </button>
      <button @click="store.inpaintErase = true" :class="store.inpaintErase ? 'bg-amber-600 text-white' : 'bg-ink-800 text-cream-200 hover:bg-ink-700'" class="flex items-center gap-1 rounded-full px-2 py-0.5 transition-colors" title="Tẩy nét đã vẽ">
        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7 21-4.3-4.3a1 1 0 0 1 0-1.4l9.7-9.7a1 1 0 0 1 1.4 0l5.7 5.7a1 1 0 0 1 0 1.4L12 19"/><path d="M22 21H7"/><path d="m5 11 9 9"/></svg>Tẩy
      </button>
      <button @click="store.undoInpaintBrush()" class="grid h-6 w-6 place-items-center rounded-full bg-ink-800 text-cream-200 transition-colors hover:bg-ink-700" title="Hoàn tác nét vẽ (Ctrl+Z)">
        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"/></svg>
      </button>
      <span class="mx-0.5 h-4 w-px bg-ink-600"></span>
      <span class="text-[10px] text-cream-300/70">Cọ</span>
      <button @click="store.inpaintBrushSize = Math.max(2, (store.inpaintBrushSize||10) - 2)" class="grid h-6 w-6 place-items-center rounded-full bg-ink-700 text-cream-200 hover:bg-ink-600" title="Cọ nhỏ hơn">−</button>
      <span class="min-w-5 text-center text-[11px] text-cream-100">{{ store.inpaintBrushSize || 10 }}</span>
      <button @click="store.inpaintBrushSize = Math.min(48, (store.inpaintBrushSize||10) + 2)" class="grid h-6 w-6 place-items-center rounded-full bg-ink-700 text-cream-200 hover:bg-ink-600" title="Cọ to hơn">+</button>
      <span class="mx-0.5 h-4 w-px bg-ink-600"></span>
      <button @click="store.confirmInpaintMask()" class="flex items-center gap-1 rounded-full bg-brand-600 px-2.5 py-1 text-white transition-colors hover:bg-brand-700" :title="store.inpaintMaskSource === 'canvas' ? 'Thoát vùng chọn' : 'Lưu vùng vẽ và thoát'">
        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>Xong
      </button>
    </template>
    <span class="mx-0.5 h-4 w-px bg-ink-600"></span>
    <span class="text-[10px] text-cream-300/70">Feather</span>
    <input type="range" min="0" max="50" step="5" :value="store.inpaintFeather" @input="store.inpaintFeather = Number($event.target.value)" class="h-1.5 w-16 cursor-pointer accent-brand-500">
    <span class="min-w-5 text-center text-[11px] text-cream-100">{{ store.inpaintFeather }}</span>
    <template v-if="store.inpaintMaskMode === 'rect' || store.inpaintMaskMode === 'freehand' || store.inpaintMaskMode === 'path' || store.inpaintMaskMode === 'magic'">
      <span class="mx-0.5 h-4 w-px bg-ink-600"></span>
      <template v-if="store.inpaintMaskMode === 'freehand' || store.inpaintMaskMode === 'path' || store.inpaintMaskMode === 'magic'">
        <button @click="store.setInpaintSelectMode('add')" :class="store.inpaintSelectMode === 'add' ? 'bg-brand-600 text-white' : 'bg-ink-800 text-cream-200 hover:bg-ink-700'" class="grid h-6 w-6 place-items-center rounded-full transition-colors" title="Cộng vào vùng chọn">
          <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3a2 2 0 0 0-2 2"/><path d="M19 3a2 2 0 0 1 2 2"/><path d="M21 19a2 2 0 0 1-2 2"/><path d="M5 21a2 2 0 0 1-2-2"/><path d="M12 8v8M8 12h8"/></svg>
        </button>
        <button @click="store.setInpaintSelectMode('subtract')" :class="store.inpaintSelectMode === 'subtract' ? 'bg-brand-600 text-white' : 'bg-ink-800 text-cream-200 hover:bg-ink-700'" class="grid h-6 w-6 place-items-center rounded-full transition-colors" title="Trừ khỏi vùng chọn">
          <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3a2 2 0 0 0-2 2"/><path d="M19 3a2 2 0 0 1 2 2"/><path d="M21 19a2 2 0 0 1-2 2"/><path d="M5 21a2 2 0 0 1-2-2"/><path d="M8 12h8"/></svg>
        </button>
      </template>
      <button @click="store.invertSelection()" class="flex items-center gap-1 rounded-full bg-ink-800 px-2.5 py-1 text-cream-200 transition-colors hover:bg-ink-700" title="Đảo ngược vùng chọn (chọn phần bên ngoài)">
        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3 4 7l4 4"/><path d="M4 7h16"/><path d="m16 21 4-4-4-4"/><path d="M20 17H4"/></svg>Đảo
      </button>
      <button @click="store.floatSelectedRegion()" class="flex items-center gap-1 rounded-full bg-amber-600/30 px-2 py-0.5 text-cream-200 transition-colors hover:bg-amber-600" title="Nâng (cắt) vùng chọn thành layer mới để di chuyển">
        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="6" r="3"/><path d="M8.12 8.12 12 12"/><path d="M20 4 8.12 15.88"/><circle cx="6" cy="18" r="3"/><path d="M14.8 14.8 20 20"/></svg>Nâng
      </button>
      <button @click="store.duplicateSelectedRegion()" class="flex items-center gap-1 rounded-full bg-emerald-600/30 px-2 py-0.5 text-cream-200 transition-colors hover:bg-emerald-600" title="Nhân đôi vùng chọn thành layer mới">
        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>Nhân đôi
      </button>
      <button @click="store.deleteSelectedRegion()" class="flex items-center gap-1 rounded-full bg-red-600/30 px-2 py-0.5 text-cream-200 transition-colors hover:bg-red-600" title="Xóa nội dung trong vùng chọn">
        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>Xóa
      </button>
      <label class="relative inline-flex h-6 w-6 cursor-pointer overflow-hidden rounded-full ring-1 ring-white/20" title="Chọn màu tô">
        <span class="absolute inset-0" :style="{ background: store.inpaintFillColor }"></span>
        <input type="color" :value="store.inpaintFillColor" @input="store.inpaintFillColor = $event.target.value" class="absolute inset-0 cursor-pointer opacity-0">
      </label>
      <button @click="store.fillSelectedRegion()" class="flex items-center gap-1 rounded-full bg-sky-600/30 px-2 py-0.5 text-cream-200 transition-colors hover:bg-sky-600" title="Tô màu vào vùng chọn">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 8h14l-1.5 11.5a2 2 0 0 1-2 1.9H8.5a2 2 0 0 1-2-1.9z"/><path d="M9 8V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v3"/><path d="M12 1v2"/></svg>Tô
      </button>
    </template>
    <button @click="store.clearInpaintMask()" class="grid h-6 w-6 place-items-center rounded-full bg-ink-700 text-cream-200 transition-colors hover:bg-red-600 hover:text-white" title="Bỏ mask hiện tại">
      <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
    </button>
  </div>

  <!-- ══ Đã lưu vùng ══ -->
  <div v-else-if="store.inpaintMaskDone" class="flex items-center gap-2 rounded-full bg-ink-900/95 px-3 py-1.5 text-xs font-semibold shadow-xl ring-1 ring-emerald-500/40">
    <span class="flex items-center gap-1 px-1 text-[10px] font-medium text-emerald-200">
      <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>Đã lưu vùng
    </span>
    <button @click="store.toggleInpaintMask(store._inpaintMaskKind)" class="flex items-center gap-1 rounded-full bg-white/10 px-2 py-0.5 text-cream-100 transition-colors hover:bg-white/20" title="Mở lại để chỉnh sửa">
      <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5z"/></svg>Chỉnh lại
    </button>
    <button @click="store.clearInpaintMask()" class="grid h-6 w-6 place-items-center rounded-full bg-ink-700 text-cream-200 transition-colors hover:bg-red-600 hover:text-white" title="Bỏ mask">
      <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
    </button>
  </div>

  <!-- ══ Xóa vùng ══ -->
  <div v-else-if="store.eraseMode" class="flex flex-wrap items-center justify-center gap-2 rounded-full bg-ink-900/95 px-3 py-1.5 text-xs font-semibold shadow-xl ring-1 ring-red-500/30">
    <span class="text-[10px] text-cream-200">Cọ</span>
    <input type="range" min="3" max="150" step="1" :value="store.eraseBrushSize" @input="store.eraseBrushSize = Number($event.target.value)" class="h-1.5 w-24 cursor-pointer accent-brand-500">
    <span class="w-8 text-right text-[10px] text-cream-200">{{ store.eraseBrushSize }}px</span>
    <span class="mx-0.5 h-4 w-px bg-ink-600"></span>
    <span class="text-[10px] text-cream-200">Feather</span>
    <input type="range" min="0" max="60" step="1" :value="store.eraseFeather" @input="store.eraseFeather = Number($event.target.value)" class="h-1.5 w-24 cursor-pointer accent-brand-500">
    <span class="w-6 text-right text-[10px] text-cream-200">{{ store.eraseFeather }}</span>
    <span class="mx-0.5 h-4 w-px bg-ink-600"></span>
    <button @click="store.applyEraseNow()" class="flex items-center gap-1 rounded-full bg-red-600/80 px-3 py-1 text-white hover:bg-red-500" title="Áp dụng nét đã xóa và vẽ tiếp">
      <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>Xóa
    </button>
    <button @click="store.finishErase()" class="flex items-center gap-1 rounded-full bg-brand-600 px-3 py-1 text-white hover:bg-brand-500">
      <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>Xong
    </button>
    <button @click="store.cancelErase()" class="grid h-6 w-6 place-items-center rounded-full bg-ink-700 text-cream-200 hover:bg-ink-600" title="Hủy">
      <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
    </button>
  </div>

  <!-- ══ Vẽ tự do (paint brush) ══ -->
  <div v-else-if="store.drawMode" class="flex flex-wrap items-center justify-center gap-2 rounded-full bg-ink-900/95 px-3 py-1.5 text-xs font-semibold shadow-xl ring-1 ring-emerald-500/30">
    <span class="text-[10px] text-cream-200">Cọ</span>
    <input type="range" min="3" max="150" step="1" :value="store.drawBrushSize" @input="store.drawBrushSize = Number($event.target.value)" class="h-1.5 w-24 cursor-pointer accent-brand-500">
    <span class="w-8 text-right text-[10px] text-cream-200">{{ store.drawBrushSize }}px</span>
    <span class="mx-0.5 h-4 w-px bg-ink-600"></span>
    <span class="text-[10px] text-cream-200">Đậm</span>
    <input type="range" min="0.05" max="1" step="0.05" :value="store.drawOpacity" @input="store.drawOpacity = Number($event.target.value)" class="h-1.5 w-20 cursor-pointer accent-brand-500">
    <span class="w-8 text-right text-[10px] text-cream-200">{{ Math.round(store.drawOpacity * 100) }}%</span>
    <span class="mx-0.5 h-4 w-px bg-ink-600"></span>
    <span class="text-[10px] text-cream-200">Mềm</span>
    <input type="range" min="0" max="60" step="1" :value="store.drawSoftness" @input="store.drawSoftness = Number($event.target.value)" class="h-1.5 w-20 cursor-pointer accent-brand-500">
    <span class="w-6 text-right text-[10px] text-cream-200">{{ store.drawSoftness }}</span>
    <span class="mx-0.5 h-4 w-px bg-ink-600"></span>
    <label class="relative inline-flex h-6 w-6 cursor-pointer overflow-hidden rounded-full ring-1 ring-white/20" title="Chọn màu vẽ">
      <span class="absolute inset-0" :style="{ background: store.inpaintFillColor }"></span>
      <input type="color" :value="store.inpaintFillColor" @input="store.inpaintFillColor = $event.target.value" class="absolute inset-0 cursor-pointer opacity-0">
    </label>
    <button @click="store.applyDrawNow()" class="flex items-center gap-1 rounded-full bg-emerald-600/80 px-3 py-1 text-white hover:bg-emerald-500" title="Áp dụng nét vẽ và vẽ tiếp"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9.06 11.9 8.07-8.06a2.85 2.85 0 1 1 4.03 4.03l-8.06 8.08"/><path d="M7.07 14.94c-1.66 0-3 1.35-3 3.02 0 1.33-2.5 1.52-2 2.02 1.08 1.1 2.49 2.02 4 2.02 2.2 0 4-1.8 4-4.04a3.01 3.01 0 0 0-3-3.02z"/></svg>Vẽ</button>
    <button @click="store.finishDraw()" class="flex items-center gap-1 rounded-full bg-brand-600 px-3 py-1 text-white hover:bg-brand-500"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>Xong</button>
    <button @click="store.cancelDraw()" class="grid h-6 w-6 place-items-center rounded-full bg-ink-700 text-cream-200 hover:bg-ink-600" title="Hủy"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
  </div>

  <!-- ══ Crop ══ -->
  <div v-else-if="store.reframeOpen || store.cropMode" class="flex flex-wrap items-center justify-center gap-2 rounded-full bg-ink-900/95 px-3 py-1.5 text-xs font-semibold shadow-xl ring-1 ring-brand-500/30">
    <span class="flex items-center gap-1 text-brand-300">
      <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2v14a2 2 0 0 0 2 2h14"/><path d="M18 22V8a2 2 0 0 0-2-2H2"/></svg>Crop
    </span>
    <button v-for="r in reframeRatios" :key="r" type="button" @click="store.reframeRatio = r" class="rounded-full border px-2 py-0.5 transition-colors" :class="store.reframeRatio === r ? 'border-brand-600 bg-brand-600 font-semibold text-white' : 'border-ink-700 text-cream-200 hover:border-brand-400'">{{ r }}</button>
    <span class="h-4 w-px bg-ink-600"></span>
    <button @click="store.reframeCenter()" :disabled="store.reframing || !store.upscaleSrc" class="rounded-full bg-ink-800 px-2.5 py-1 text-cream-100 hover:bg-ink-700 disabled:opacity-40">{{ store.reframing ? 'Đang cắt…' : 'Cắt giữa' }}</button>
    <button @click="store.toggleCrop()" :disabled="store.reframing || !store.upscaleSrc" class="flex items-center gap-1 rounded-full px-2.5 py-1 transition-colors disabled:opacity-40" :class="store.cropMode ? 'bg-brand-600 text-white' : 'bg-ink-800 text-cream-100 hover:bg-ink-700'">
      <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 3v1M15 3v1M9 20v1M15 20v1M3 9h1M3 15h1M20 9h1M20 15h1"/></svg>{{ store.cropMode ? 'Hủy chọn vùng' : 'Chọn vùng' }}
    </button>
    <button v-if="store.cropMode" @click="store.confirmCrop()" :disabled="store.reframing || !store.upscaleSrc" class="flex items-center gap-1 rounded-full bg-brand-600 px-2.5 py-1 text-white hover:bg-brand-500 disabled:opacity-40">
      <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>Áp dụng
    </button>
    <button @click="store.reframeOpen = false; if (store.cropMode) store.toggleCrop()" class="grid h-6 w-6 place-items-center rounded-full bg-ink-700 text-cream-200 hover:bg-red-600" title="Đóng">
      <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
    </button>
  </div>

  <!-- ══ Film Look ══ -->
  <div v-else-if="store.filmOpen || store.looking" class="flex flex-wrap items-center justify-center gap-2 rounded-full bg-ink-900/95 px-3 py-1.5 text-xs font-semibold shadow-xl ring-1 ring-brand-500/30">
    <span class="flex items-center gap-1 text-brand-300">
      <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/></svg>Look
    </span>
    <button v-for="p in looks" :key="p[0]" type="button" @click="store.lookPreset = p[0]" class="rounded-full border px-2 py-0.5 transition-colors" :class="store.lookPreset === p[0] ? 'border-brand-600 bg-brand-600 font-semibold text-white' : 'border-ink-700 text-cream-200 hover:border-brand-400'">{{ p[1] }}</button>
    <span class="h-4 w-px bg-ink-600"></span>
    <span class="text-cream-300/70">Mức</span>
    <input type="range" min="0" max="10" step="1" v-model.number="store.lookLevel" class="h-1.5 w-24 cursor-pointer accent-brand-500">
    <span class="min-w-7 text-center text-cream-100">{{ store.lookLevel }}/10</span>
    <button @click="store.applyFilmLook()" :disabled="store.looking || !store.upscaleSrc" class="rounded-full bg-brand-600 px-2.5 py-1 text-white hover:bg-brand-500 disabled:opacity-40">{{ store.looking ? 'Đang áp dụng…' : 'Áp dụng' }}</button>
    <button @click="store.filmOpen = false" class="grid h-6 w-6 place-items-center rounded-full bg-ink-700 text-cream-200 hover:bg-red-600" title="Đóng">
      <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
    </button>
  </div>

  <!-- ══ Placeholder ══ -->
  <span v-else class="text-[11px] font-medium text-cream-300/50">Chọn công cụ từ thanh dọc bên trái</span>
</template>
