<script setup>
import { useStudioStore } from '../store.js';
const store = useStudioStore();
const looks = [['studio','Studio'],['warm','Ấm'],['cool','Lạnh'],['cinematic','Điện ảnh'],['dramatic','Dramatic'],['retro','Retro'],['mono','Mono']];
async function applyLook() {
  if (!store.upscaleSrc || store.looking) return;
  store.looking = true;
  try { const d = await store.api('/studio/look', { image: store.upscaleSrc, look: store.lookPreset, level: Number(store.lookLevel)||5 }); store.addGen({ id:d.generation_id, type:'image', status:'completed', model:'look', provider:'look', media_url:d.media_url, error:null, credits_cost:0, created_at:'Vừa áp dụng' }); store.toast('Đã áp dụng Look ' + store.lookPreset + '.'); }
  catch(e){ store.toast(e.message || 'Lỗi áp dụng Look.', 'error'); }
  finally { store.looking = false; }
}
</script>
<template>
  <div class="card p-5" style="border:1px solid var(--color-brand-500); background: linear-gradient(160deg, rgba(180,120,180,.13), rgba(74,122,144,.06));">
    <h2 class="mb-1 font-display text-base font-semibold text-brand-300">🎨 Film Look</h2>
    <p class="text-[11px] text-ink-500">Gán tone màu phim cho ảnh đang chọn. Mức 1–4 nhẹ · 5–7 vừa · 8–10 đậm.</p>
    <div class="mt-3 flex flex-wrap gap-1.5">
      <button v-for="p in looks" :key="p[0]" type="button" @click="store.lookPreset = p[0]" class="rounded-full border px-3 py-1.5 text-xs transition-colors" :class="store.lookPreset === p[0] ? 'border-brand-600 bg-brand-600 font-semibold text-white' : 'border-ink-700 text-cream-200 hover:border-brand-400'">{{ p[1] }}</button>
    </div>
    <label class="label mt-3">Cường độ</label>
    <div class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-3 py-2.5 text-xs"><span class="shrink-0 font-medium text-cream-200">Mức</span><input type="range" min="0" max="10" step="1" v-model.number="store.lookLevel" class="h-2 w-full cursor-pointer accent-brand-500"><span class="shrink-0 font-semibold text-cream-50">{{ store.lookLevel }}/10</span></div>
    <button @click="applyLook" :disabled="store.looking || !store.upscaleSrc" class="btn-brand mt-3 w-full whitespace-nowrap">{{ store.looking ? 'Đang áp dụng…' : '🎨 Áp dụng Look' }}</button>
  </div>
</template>
