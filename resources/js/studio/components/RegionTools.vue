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
      <button @click="reframeOpen = !reframeOpen; filmOpen = false"
        :class="(reframeOpen || store.cropMode) ? 'bg-brand-600 text-white border-brand-400 shadow-brand-500/40' : 'text-cream-200 border-transparent hover:bg-ink-700'"
        class="grid h-11 w-11 place-items-center rounded-xl border transition-colors" title="Reframe / Crop · Cắt khung theo tỷ lệ / chọn vùng">
        <svg class="h-[22px] w-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2v14a2 2 0 0 0 2 2h14"/><path d="M18 22V8a2 2 0 0 0-2-2H2"/></svg>
      </button>
      <div class="h-px w-6 bg-ink-700"></div>
      <button @click="filmOpen = !filmOpen; reframeOpen = false"
        :class="(filmOpen || store.looking) ? 'bg-brand-600 text-white border-brand-400 shadow-brand-500/40' : 'text-cream-200 border-transparent hover:bg-ink-700'"
        class="grid h-11 w-11 place-items-center rounded-xl border transition-colors" title="Film Look · Gán tone màu phim">
        <svg class="h-[22px] w-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/></svg>
      </button>
    </div>
  </div>

  <div v-if="reframeOpen || store.cropMode" class="scrollbar-hide absolute left-14 top-4 z-40 max-h-[calc(100%-2rem)] w-56 max-w-[76vw] overflow-y-auto rounded-xl border border-brand-500/30 bg-ink-900/95 p-3 text-[11px] shadow-2xl backdrop-blur">
    <div class="flex items-center justify-between gap-2">
      <p class="truncate text-xs font-semibold text-brand-300">📐 Reframe / Crop</p>
      <button @click="reframeOpen = false; if (store.cropMode) store.toggleCrop()" class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-ink-700 text-cream-200 hover:bg-red-600" title="Đóng">✕</button>
    </div>
    <p class="mt-0.5 text-[10px] leading-snug text-cream-200/70">Cắt lại khung theo tỷ lệ hoặc chọn vùng trên canvas.</p>
    <div class="mt-2 flex flex-wrap gap-1.5">
      <button v-for="r in reframeRatios" :key="r" type="button" @click="store.reframeRatio = r" class="rounded-full border px-2.5 py-1 text-xs transition-colors" :class="store.reframeRatio === r ? 'border-brand-600 bg-brand-600 font-semibold text-white' : 'border-ink-700 text-cream-200 hover:border-brand-400'">{{ r }}</button>
    </div>
    <button @click="store.reframeCenter" :disabled="store.reframing || !store.upscaleSrc" class="btn-outline btn-sm mt-3 w-full whitespace-nowrap">{{ store.reframing ? 'Đang cắt…' : '📐 Cắt giữa' }}</button>
    <button @click="store.toggleCrop" :disabled="store.reframing || !store.upscaleSrc" class="mt-1.5 w-full whitespace-nowrap rounded-2xl border py-2 text-sm font-semibold transition-colors" :class="store.cropMode ? 'border-brand-500 bg-brand-600 text-white' : 'border-ink-700 text-cream-200 hover:border-brand-400'">{{ store.cropMode ? '✂️ Đang chọn vùng… (Hủy)' : '✂️ Chọn vùng trên canvas' }}</button>
    <template v-if="store.cropMode">
      <button @click="store.confirmCrop" :disabled="store.reframing || !store.upscaleSrc" class="btn-brand mt-1.5 w-full whitespace-nowrap">✅ Áp dụng vùng đã chọn</button>
      <p class="mt-1.5 text-center text-[10px] text-cream-200/60">Kéo khung để di chuyển · kéo góc để đổi kích thước · đúp / Esc để hủy</p>
    </template>
  </div>

  <div v-if="filmOpen || store.looking" class="scrollbar-hide absolute left-14 top-4 z-40 max-h-[calc(100%-2rem)] w-56 max-w-[76vw] overflow-y-auto rounded-xl border border-brand-500/30 bg-ink-900/95 p-3 text-[11px] shadow-2xl backdrop-blur">
    <div class="flex items-center justify-between gap-2">
      <p class="truncate text-xs font-semibold text-brand-300">🎨 Film Look</p>
      <button @click="filmOpen = false" class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-ink-700 text-cream-200 hover:bg-red-600" title="Đóng">✕</button>
    </div>
    <p class="mt-1 text-[11px] text-cream-200/80">Gán tone màu phim cho ảnh đang chọn. Mức 1–4 nhẹ · 5–7 vừa · 8–10 đậm.</p>
    <div class="mt-2 flex flex-wrap gap-1.5">
      <button v-for="p in looks" :key="p[0]" type="button" @click="store.lookPreset = p[0]" class="rounded-full border px-2.5 py-1 text-xs transition-colors" :class="store.lookPreset === p[0] ? 'border-brand-600 bg-brand-600 font-semibold text-white' : 'border-ink-700 text-cream-200 hover:border-brand-400'">{{ p[1] }}</button>
    </div>
    <label class="label mt-2">Cường độ</label>
    <div class="flex items-center gap-3 rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs"><span class="shrink-0 font-medium text-cream-200">Mức</span><input type="range" min="0" max="10" step="1" v-model.number="store.lookLevel" class="h-2 w-full cursor-pointer accent-brand-500"><span class="shrink-0 font-semibold text-cream-50">{{ store.lookLevel }}/10</span></div>
    <button @click="applyFilmLook" :disabled="store.looking || !store.upscaleSrc" class="btn-brand mt-3 w-full whitespace-nowrap">{{ store.looking ? 'Đang áp dụng…' : '🎨 Áp dụng Look' }}</button>
  </div>
</template>
