<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useStudioStore } from '../store.js';
const store = useStudioStore();

const reframeOpen = ref(false);
const reframeRatios = ['1:1','3:4','4:5','9:16','16:9','2:3'];
const filmOpen = ref(false);
const looks = [['studio','Studio'],['warm','Ấm'],['cool','Lạnh'],['cinematic','Điện ảnh'],['dramatic','Dramatic'],['retro','Retro'],['mono','Mono']];
async function applyFilmLook() {
  if (!store.upscaleSrc || store.looking) return;
  store.looking = true;
  try { const d = await store.api('/studio/look', { image: store.upscaleSrc, look: store.lookPreset, level: Number(store.lookLevel)||5 }); store.addGen({ id:d.generation_id, type:'image', status:'completed', model:'look', provider:'look', media_url:d.media_url, error:null, credits_cost:0, created_at:'Vừa áp dụng' }); store.toast('Đã áp dụng Look ' + store.lookPreset + '.'); }
  catch(e){ store.toast(e.message || 'Lỗi áp dụng Look.', 'error'); }
  finally { store.looking = false; }
}
</script>
<template>
  <div class="absolute left-2 top-1/2 z-40 -translate-y-1/2" v-if="store.upscaleSrc">
    <div class="flex flex-col items-center gap-1 rounded-2xl border border-ink-700 bg-ink-900/85 p-1.5 shadow-xl backdrop-blur">
      <button @click="reframeOpen = !reframeOpen; filmOpen = false; store.exitErase(); store.clearInpaintMask()"
        :class="(reframeOpen || store.cropMode) ? 'bg-brand-600 text-white border-brand-400 shadow-brand-500/40' : 'text-cream-200 border-transparent hover:bg-ink-700'"
        class="grid h-11 w-11 place-items-center rounded-xl border transition-colors" title="Reframe / Crop · Cắt khung theo tỷ lệ / chọn vùng">
        <svg class="h-[22px] w-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2v14a2 2 0 0 0 2 2h14"/><path d="M18 22V8a2 2 0 0 0-2-2H2"/></svg>
      </button>
      <button @click="store.startCanvasSelect('rect'); reframeOpen = false; filmOpen = false; store.exitErase()"
        :class="(store.inpaintMaskMode === 'rect' && store.inpaintMaskSource === 'canvas') ? 'bg-brand-600 text-white border-brand-400 shadow-brand-500/40' : 'text-cream-200 border-transparent hover:bg-ink-700'"
        class="grid h-11 w-11 place-items-center rounded-xl border transition-colors" title="Vẽ vùng chọn (chữ nhật) · Xóa / Tô màu / Feather">
        <svg class="h-[22px] w-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3a2 2 0 0 0-2 2"/><path d="M19 3a2 2 0 0 1 2 2"/><path d="M21 19a2 2 0 0 1-2 2"/><path d="M5 21a2 2 0 0 1-2-2"/><path d="M9 3h1"/><path d="M14 3h1"/><path d="M9 21h1"/><path d="M14 21h1"/><path d="M3 9v1"/><path d="M3 14v1"/><path d="M21 9v1"/><path d="M21 14v1"/></svg>
      </button>
      <button @click="store.startCanvasSelect('freehand'); reframeOpen = false; filmOpen = false; store.exitErase()"
        :class="(store.inpaintMaskMode === 'freehand' && store.inpaintMaskSource === 'canvas') ? 'bg-brand-600 text-white border-brand-400 shadow-brand-500/40' : 'text-cream-200 border-transparent hover:bg-ink-700'"
        class="grid h-11 w-11 place-items-center rounded-xl border transition-colors" title="Vẽ vùng chọn tự do (lasso) · Xóa / Tô màu / Feather">
        <svg class="h-[22px] w-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 19c0-7 2-11 6-13 1.5-.7 3-1 5-1"/><path d="M11 5c3-2 6-1 8 2 2 3 2 8-2 11-2.5 1.9-5.5 2-8 1"/><path d="M8 13c-1.5 1.5-2 3.5-1 5.5"/><path d="M3 21l4-4"/></svg>
      </button>
      <div class="h-px w-6 bg-ink-700"></div>
      <button @click="store.toggleErase(); reframeOpen = false; filmOpen = false; store.clearInpaintMask()"
        :class="store.eraseMode ? 'bg-brand-600 text-white border-brand-400 shadow-brand-500/40' : 'text-cream-200 border-transparent hover:bg-ink-700'"
        class="grid h-11 w-11 place-items-center rounded-xl border transition-colors" title="Xóa vùng (feather)">
        <svg class="h-[22px] w-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7 21-4.3-4.3a1 1 0 0 1 0-1.4l9.7-9.7a1 1 0 0 1 1.4 0l5.7 5.7a1 1 0 0 1 0 1.4L12 19"/><path d="M22 21H7"/><path d="m5 11 9 9"/></svg>
      </button>
      <button @click="filmOpen = !filmOpen; reframeOpen = false; store.exitErase(); store.clearInpaintMask()"
        :class="(filmOpen || store.looking) ? 'bg-brand-600 text-white border-brand-400 shadow-brand-500/40' : 'text-cream-200 border-transparent hover:bg-ink-700'"
        class="grid h-11 w-11 place-items-center rounded-xl border transition-colors" title="Film Look · Gán tone màu phim">
        <svg class="h-[22px] w-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/></svg>
      </button>
    </div>
  </div>

  <div v-if="reframeOpen || store.cropMode" class="fixed top-16 left-1/2 z-[70] max-w-[94vw] -translate-x-1/2">
    <div class="flex flex-wrap items-center justify-center gap-2 rounded-full bg-ink-900/95 px-3 py-1.5 text-[11px] font-semibold shadow-xl ring-1 ring-brand-500/30 backdrop-blur">
      <span class="flex items-center gap-1 text-brand-300"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2v14a2 2 0 0 0 2 2h14"/><path d="M18 22V8a2 2 0 0 0-2-2H2"/></svg>Crop</span>
      <button v-for="r in reframeRatios" :key="r" type="button" @click="store.reframeRatio = r" class="rounded-full border px-2 py-0.5 transition-colors" :class="store.reframeRatio === r ? 'border-brand-600 bg-brand-600 font-semibold text-white' : 'border-ink-700 text-cream-200 hover:border-brand-400'">{{ r }}</button>
      <span class="h-4 w-px bg-ink-600"></span>
      <button @click="store.reframeCenter" :disabled="store.reframing || !store.upscaleSrc" class="rounded-full bg-ink-800 px-2.5 py-1 text-cream-100 hover:bg-ink-700 disabled:opacity-40">{{ store.reframing ? 'Đang cắt…' : 'Cắt giữa' }}</button>
      <button @click="store.toggleCrop" :disabled="store.reframing || !store.upscaleSrc" class="flex items-center gap-1 rounded-full px-2.5 py-1 transition-colors disabled:opacity-40" :class="store.cropMode ? 'bg-brand-600 text-white' : 'bg-ink-800 text-cream-100 hover:bg-ink-700'">
        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 3v1M15 3v1M9 20v1M15 20v1M3 9h1M3 15h1M20 9h1M20 15h1"/></svg>{{ store.cropMode ? 'Hủy chọn vùng' : 'Chọn vùng' }}
      </button>
      <button v-if="store.cropMode" @click="store.confirmCrop" :disabled="store.reframing || !store.upscaleSrc" class="flex items-center gap-1 rounded-full bg-brand-600 px-2.5 py-1 text-white hover:bg-brand-500 disabled:opacity-40">
        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>Áp dụng
      </button>
      <button @click="reframeOpen = false; if (store.cropMode) store.toggleCrop()" class="grid h-6 w-6 place-items-center rounded-full bg-ink-700 text-cream-200 hover:bg-red-600" title="Đóng"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
    </div>
  </div>

  <div v-if="filmOpen || store.looking" class="fixed top-16 left-1/2 z-[70] max-w-[94vw] -translate-x-1/2">
    <div class="flex flex-wrap items-center justify-center gap-2 rounded-full bg-ink-900/95 px-3 py-1.5 text-[11px] font-semibold shadow-xl ring-1 ring-brand-500/30 backdrop-blur">
      <span class="flex items-center gap-1 text-brand-300"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/></svg>Look</span>
      <button v-for="p in looks" :key="p[0]" type="button" @click="store.lookPreset = p[0]" class="rounded-full border px-2 py-0.5 transition-colors" :class="store.lookPreset === p[0] ? 'border-brand-600 bg-brand-600 font-semibold text-white' : 'border-ink-700 text-cream-200 hover:border-brand-400'">{{ p[1] }}</button>
      <span class="h-4 w-px bg-ink-600"></span>
      <span class="text-cream-300/70">Mức</span>
      <input type="range" min="0" max="10" step="1" v-model.number="store.lookLevel" class="h-1.5 w-24 cursor-pointer accent-brand-500">
      <span class="min-w-7 text-center text-cream-100">{{ store.lookLevel }}/10</span>
      <button @click="applyFilmLook" :disabled="store.looking || !store.upscaleSrc" class="rounded-full bg-brand-600 px-2.5 py-1 text-white hover:bg-brand-500 disabled:opacity-40">{{ store.looking ? 'Đang áp dụng…' : 'Áp dụng' }}</button>
      <button @click="filmOpen = false" class="grid h-6 w-6 place-items-center rounded-full bg-ink-700 text-cream-200 hover:bg-red-600" title="Đóng"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
    </div>
  </div>
</template>
