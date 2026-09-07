<script setup>
import { computed } from 'vue';
import { useStudioStore } from '../store.js';
import StudioIcon from './StudioIcon.vue';
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
        <button @click="store.pathUndoPoint()" class="grid h-6 w-6 place-items-center rounded-full bg-ink-800 text-cream-200 transition-colors hover:bg-ink-700" title="Bỏ điểm neo cuối" aria-label="Bỏ điểm neo cuối">
          <StudioIcon name="undo" size="h-3.5 w-3.5" />
        </button>
      </template>
      <template v-if="store.inpaintMaskMode === 'magic'">
        <span class="text-[10px] text-cream-300/70">Ngưỡng</span>
        <input type="range" min="1" max="128" step="1" :value="store.magicTolerance" @input="store.magicTolerance = Number($event.target.value)" class="h-1.5 w-20 cursor-pointer accent-brand-500">
        <span class="min-w-7 text-center text-[11px] text-cream-100">{{ store.magicTolerance }}</span>
        <span class="mx-0.5 h-4 w-px bg-ink-600"></span>
        <span class="text-[10px] text-cream-300/70">Độ mịn</span>
        <input type="range" min="0" max="20" step="1" :value="store.magicFeather" @input="store.magicFeather = Number($event.target.value)" class="h-1.5 w-16 cursor-pointer accent-brand-500">
        <span class="min-w-5 text-center text-[11px] text-cream-100">{{ store.magicFeather }}</span>
      </template>
      <button @click="store.confirmInpaintMask()" class="flex items-center gap-1 rounded-full bg-brand-600 px-2.5 py-1 text-white transition-colors hover:bg-brand-700" :title="store.inpaintMaskSource === 'canvas' ? 'Thoát vùng chọn' : 'Áp dụng vùng chọn và thoát'">
        <StudioIcon name="check" size="h-3.5 w-3.5" />Xong
      </button>
    </template>
    <template v-else>
      <button @click="store.inpaintErase = false" :class="!store.inpaintErase ? 'bg-brand-600 text-white' : 'bg-ink-800 text-cream-200 hover:bg-ink-700'" class="flex items-center gap-1 rounded-full px-2 py-0.5 transition-colors" title="Vẽ thêm vùng cần sửa">
        <StudioIcon name="brush" size="h-3.5 w-3.5" />Vẽ
      </button>
      <button @click="store.inpaintErase = true" :class="store.inpaintErase ? 'bg-amber-600 text-white' : 'bg-ink-800 text-cream-200 hover:bg-ink-700'" class="flex items-center gap-1 rounded-full px-2 py-0.5 transition-colors" title="Tẩy nét đã vẽ">
        <StudioIcon name="eraser" size="h-3.5 w-3.5" />Tẩy
      </button>
      <button @click="store.undoInpaintBrush()" class="grid h-6 w-6 place-items-center rounded-full bg-ink-800 text-cream-200 transition-colors hover:bg-ink-700" title="Hoàn tác nét vẽ (Ctrl+Z)" aria-label="Hoàn tác nét vẽ (Ctrl+Z)">
        <StudioIcon name="undo" size="h-3.5 w-3.5" />
      </button>
      <span class="mx-0.5 h-4 w-px bg-ink-600"></span>
      <span class="text-[10px] text-cream-300/70">Cọ</span>
      <button @click="store.inpaintBrushSize = Math.max(2, (store.inpaintBrushSize||10) - 2)" class="grid h-6 w-6 place-items-center rounded-full bg-ink-700 text-cream-200 hover:bg-ink-600" title="Cọ nhỏ hơn" aria-label="Cọ nhỏ hơn"><StudioIcon name="minus" size="h-3.5 w-3.5" /></button>
      <span class="min-w-5 text-center text-[11px] text-cream-100">{{ store.inpaintBrushSize || 10 }}</span>
      <button @click="store.inpaintBrushSize = Math.min(48, (store.inpaintBrushSize||10) + 2)" class="grid h-6 w-6 place-items-center rounded-full bg-ink-700 text-cream-200 hover:bg-ink-600" title="Cọ to hơn" aria-label="Cọ to hơn"><StudioIcon name="plus" size="h-3.5 w-3.5" /></button>
      <span class="mx-0.5 h-4 w-px bg-ink-600"></span>
      <button @click="store.confirmInpaintMask()" class="flex items-center gap-1 rounded-full bg-brand-600 px-2.5 py-1 text-white transition-colors hover:bg-brand-700" :title="store.inpaintMaskSource === 'canvas' ? 'Thoát vùng chọn' : 'Lưu vùng vẽ và thoát'">
        <StudioIcon name="check" size="h-3.5 w-3.5" />Xong
      </button>
    </template>
    <span class="mx-0.5 h-4 w-px bg-ink-600"></span>
    <span class="text-[10px] text-cream-300/70">Feather</span>
    <input type="range" min="0" max="50" step="1" :value="store.inpaintFeather" @input="store.inpaintFeather = Number($event.target.value)" class="h-1.5 w-16 cursor-pointer accent-brand-500">
    <span class="min-w-5 text-center text-[11px] text-cream-100">{{ store.inpaintFeather }}</span>
    <template v-if="store.inpaintMaskMode === 'rect' || store.inpaintMaskMode === 'freehand' || store.inpaintMaskMode === 'path' || store.inpaintMaskMode === 'magic'">
      <span class="mx-0.5 h-4 w-px bg-ink-600"></span>
      <template v-if="store.inpaintMaskMode === 'freehand' || store.inpaintMaskMode === 'path' || store.inpaintMaskMode === 'magic'">
        <button @click="store.setInpaintSelectMode('add')" :class="store.inpaintSelectMode === 'add' ? 'bg-brand-600 text-white' : 'bg-ink-800 text-cream-200 hover:bg-ink-700'" class="grid h-6 w-6 place-items-center rounded-full transition-colors" title="Cộng vào vùng chọn" aria-label="Cộng vào vùng chọn">
          <StudioIcon name="boxSelect" size="h-3.5 w-3.5" />
        </button>
        <button @click="store.setInpaintSelectMode('subtract')" :class="store.inpaintSelectMode === 'subtract' ? 'bg-brand-600 text-white' : 'bg-ink-800 text-cream-200 hover:bg-ink-700'" class="grid h-6 w-6 place-items-center rounded-full transition-colors" title="Trừ khỏi vùng chọn" aria-label="Trừ khỏi vùng chọn">
          <StudioIcon name="boxSelect" size="h-3.5 w-3.5" />
        </button>
      </template>
      <button @click="store.invertSelection()" class="flex items-center gap-1 rounded-full bg-ink-800 px-2.5 py-1 text-cream-200 transition-colors hover:bg-ink-700" title="Đảo ngược vùng chọn (chọn phần bên ngoài)">
        <StudioIcon name="swapHorizontal" size="h-3.5 w-3.5" />Đảo
      </button>
      <button @click="store.floatSelectedRegion()" class="flex items-center gap-1 rounded-full bg-amber-600/30 px-2 py-0.5 text-cream-200 transition-colors hover:bg-amber-600" title="Nâng (cắt) vùng chọn thành layer mới để di chuyển">
        <StudioIcon name="scissors" size="h-3.5 w-3.5" />Nâng
      </button>
      <button @click="store.duplicateSelectedRegion()" class="flex items-center gap-1 rounded-full bg-emerald-600/30 px-2 py-0.5 text-cream-200 transition-colors hover:bg-emerald-600" title="Nhân đôi vùng chọn thành layer mới">
        <StudioIcon name="copy" size="h-3.5 w-3.5" />Nhân đôi
      </button>
      <button @click="store.deleteSelectedRegion()" class="flex items-center gap-1 rounded-full bg-red-600/30 px-2 py-0.5 text-cream-200 transition-colors hover:bg-red-600" title="Xóa nội dung trong vùng chọn">
        <StudioIcon name="trash" size="h-3.5 w-3.5" />Xóa
      </button>
      <label class="relative inline-flex h-6 w-6 cursor-pointer overflow-hidden rounded-full ring-1 ring-white/20" title="Chọn màu tô">
        <span class="absolute inset-0" :style="{ background: store.inpaintFillColor }"></span>
        <input type="color" :value="store.inpaintFillColor" @input="store.inpaintFillColor = $event.target.value" class="absolute inset-0 cursor-pointer opacity-0">
      </label>
      <button @click="store.fillSelectedRegion()" class="flex items-center gap-1 rounded-full bg-sky-600/30 px-2 py-0.5 text-cream-200 transition-colors hover:bg-sky-600" title="Tô màu vào vùng chọn">
        <StudioIcon name="paintBucket" size="h-4 w-4" />Tô
      </button>
    </template>
    <button @click="store.clearInpaintMask()" class="grid h-6 w-6 place-items-center rounded-full bg-ink-700 text-cream-200 transition-colors hover:bg-red-600 hover:text-white" title="Bỏ mask hiện tại" aria-label="Bỏ mask hiện tại">
      <StudioIcon name="x" size="h-3.5 w-3.5" />
    </button>
  </div>

  <!-- ══ Đã lưu vùng ══ -->
  <div v-else-if="store.inpaintMaskDone" class="flex items-center gap-2 rounded-full bg-ink-900/95 px-3 py-1.5 text-xs font-semibold shadow-xl ring-1 ring-emerald-500/40">
    <span class="flex items-center gap-1 px-1 text-[10px] font-medium text-emerald-200">
      <StudioIcon name="check" size="h-3.5 w-3.5" />Đã lưu vùng
    </span>
    <button @click="store.toggleInpaintMask(store._inpaintMaskKind)" class="flex items-center gap-1 rounded-full bg-white/10 px-2 py-0.5 text-cream-100 transition-colors hover:bg-white/20" title="Mở lại để chỉnh sửa">
      <StudioIcon name="pencil" size="h-3.5 w-3.5" />Chỉnh lại
    </button>
    <button @click="store.clearInpaintMask()" class="grid h-6 w-6 place-items-center rounded-full bg-ink-700 text-cream-200 transition-colors hover:bg-red-600 hover:text-white" title="Bỏ mask" aria-label="Bỏ mask">
      <StudioIcon name="x" size="h-3.5 w-3.5" />
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
      <StudioIcon name="trash" size="h-3.5 w-3.5" />Xóa
    </button>
    <button @click="store.finishErase()" class="flex items-center gap-1 rounded-full bg-brand-600 px-3 py-1 text-white hover:bg-brand-500">
      <StudioIcon name="check" size="h-3.5 w-3.5" />Xong
    </button>
    <button @click="store.cancelErase()" class="grid h-6 w-6 place-items-center rounded-full bg-ink-700 text-cream-200 hover:bg-ink-600" title="Hủy" aria-label="Hủy">
      <StudioIcon name="x" size="h-3.5 w-3.5" />
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
    <button @click="store.applyDrawNow()" class="flex items-center gap-1 rounded-full bg-emerald-600/80 px-3 py-1 text-white hover:bg-emerald-500" title="Áp dụng nét vẽ và vẽ tiếp"><StudioIcon name="brush" size="h-3.5 w-3.5" />Vẽ</button>
    <button @click="store.finishDraw()" class="flex items-center gap-1 rounded-full bg-brand-600 px-3 py-1 text-white hover:bg-brand-500"><StudioIcon name="check" size="h-3.5 w-3.5" />Xong</button>
    <button @click="store.cancelDraw()" class="grid h-6 w-6 place-items-center rounded-full bg-ink-700 text-cream-200 hover:bg-ink-600" title="Hủy" aria-label="Hủy"><StudioIcon name="x" size="h-3.5 w-3.5" /></button>
  </div>

  <!-- ══ Crop ══ -->
  <div v-else-if="store.reframeOpen || store.cropMode" class="flex flex-wrap items-center justify-center gap-2 rounded-full bg-ink-900/95 px-3 py-1.5 text-xs font-semibold shadow-xl ring-1 ring-brand-500/30">
    <span class="flex items-center gap-1 text-brand-300">
      <StudioIcon name="crop" size="h-4 w-4" />Crop
    </span>
    <button v-for="r in reframeRatios" :key="r" type="button" @click="store.reframeRatio = r" class="rounded-full border px-2 py-0.5 transition-colors" :class="store.reframeRatio === r ? 'border-brand-600 bg-brand-600 font-semibold text-white' : 'border-ink-700 text-cream-200 hover:border-brand-400'">{{ r }}</button>
    <span class="h-4 w-px bg-ink-600"></span>
    <button @click="store.reframeCenter()" :disabled="store.reframing || !store.upscaleSrc" class="rounded-full bg-ink-800 px-2.5 py-1 text-cream-100 hover:bg-ink-700 disabled:opacity-40">{{ store.reframing ? 'Đang cắt…' : 'Cắt giữa' }}</button>
    <button @click="store.toggleCrop()" :disabled="store.reframing || !store.upscaleSrc" class="flex items-center gap-1 rounded-full px-2.5 py-1 transition-colors disabled:opacity-40" :class="store.cropMode ? 'bg-brand-600 text-white' : 'bg-ink-800 text-cream-100 hover:bg-ink-700'">
      <StudioIcon name="boxSelect" size="h-3.5 w-3.5" />{{ store.cropMode ? 'Hủy chọn vùng' : 'Chọn vùng' }}
    </button>
    <button v-if="store.cropMode" @click="store.confirmCrop()" :disabled="store.reframing || !store.upscaleSrc" class="flex items-center gap-1 rounded-full bg-brand-600 px-2.5 py-1 text-white hover:bg-brand-500 disabled:opacity-40">
      <StudioIcon name="check" size="h-3.5 w-3.5" />Áp dụng
    </button>
    <button @click="store.reframeOpen = false; if (store.cropMode) store.toggleCrop()" class="grid h-6 w-6 place-items-center rounded-full bg-ink-700 text-cream-200 hover:bg-red-600" title="Đóng" aria-label="Đóng">
      <StudioIcon name="x" size="h-3.5 w-3.5" />
    </button>
  </div>

  <!-- ══ Film Look ══ -->
  <div v-else-if="store.filmOpen || store.looking" class="flex flex-wrap items-center justify-center gap-2 rounded-full bg-ink-900/95 px-3 py-1.5 text-xs font-semibold shadow-xl ring-1 ring-brand-500/30">
    <span class="flex items-center gap-1 text-brand-300">
      <StudioIcon name="palette" size="h-4 w-4" />Look
    </span>
    <button v-for="p in looks" :key="p[0]" type="button" @click="store.lookPreset = p[0]" class="rounded-full border px-2 py-0.5 transition-colors" :class="store.lookPreset === p[0] ? 'border-brand-600 bg-brand-600 font-semibold text-white' : 'border-ink-700 text-cream-200 hover:border-brand-400'">{{ p[1] }}</button>
    <span class="h-4 w-px bg-ink-600"></span>
    <span class="text-cream-300/70">Mức</span>
    <input type="range" min="0" max="10" step="1" v-model.number="store.lookLevel" class="h-1.5 w-24 cursor-pointer accent-brand-500">
    <span class="min-w-7 text-center text-cream-100">{{ store.lookLevel }}/10</span>
    <button @click="store.applyFilmLook()" :disabled="store.looking || !store.upscaleSrc" class="rounded-full bg-brand-600 px-2.5 py-1 text-white hover:bg-brand-500 disabled:opacity-40">{{ store.looking ? 'Đang áp dụng…' : 'Áp dụng' }}</button>
    <button @click="store.filmOpen = false" class="grid h-6 w-6 place-items-center rounded-full bg-ink-700 text-cream-200 hover:bg-red-600" title="Đóng" aria-label="Đóng">
      <StudioIcon name="x" size="h-3.5 w-3.5" />
    </button>
  </div>

  <!-- ══ Placeholder ══ -->
  <span v-else class="text-[11px] font-medium text-cream-300/50">Chọn công cụ từ thanh công cụ cạnh canvas (Esc = hủy · Enter = xong)</span>
</template>