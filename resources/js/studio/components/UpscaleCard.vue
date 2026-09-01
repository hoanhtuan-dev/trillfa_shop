<script setup>
import { useStudioStore } from '../store.js';
const store = useStudioStore();
async function runUpscale() {
  if (!store.upscaleSrc || store.upscaling) return;
  store.upscaling = true;
  try {
    const d = await store.api('/studio/upscale', { image: store.upscaleSrc, scale: Number(store.upscaleScale)||2, refine: Number(store.upscaleRefine)||0, photoreal: Number(store.studioPhotoreal)||0, skin_detail: Number(store.skinDetail)||0, light_shadow: Number(store.lightShadow)||0, fabric_detail: Number(store.fabricDetail)||0 });
    store.addGen({ id: d.generation_id, type:'image', status:'completed', model:'upscale', provider:'upscale', media_url:d.media_url, error:null, credits_cost:0, created_at:'Vừa nâng cấp' });
    store.toast('Đã nâng cấp ảnh (' + store.upscaleScale + 'x).');
  } catch(e){ store.toast(e.message || 'Lỗi nâng cấp ảnh.', 'error'); }
  finally { store.upscaling = false; }
}
</script>
<template>
  <div class="card p-5" style="border:1px solid var(--color-brand-500); background: linear-gradient(160deg, rgba(232,150,120,.13), rgba(74,122,144,.06));">
    <h2 class="mb-1 font-display text-base font-semibold text-brand-300">🔍 Tinh chỉnh & Nâng cấp ảnh</h2>
    <p class="text-[11px] text-ink-500">Phóng to độ phân giải + làm nét/chi tiết ảnh đang chọn.</p>
    <template v-if="store.upscaleSrc"><div class="mt-3 flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 p-2.5"><img :src="store.upscaleSrc" class="h-16 w-16 rounded-xl bg-ink-900 object-cover"><div class="min-w-0 text-xs text-cream-200"><p class="truncate font-semibold">{{ store.upscaleName }}</p></div></div></template>
    <div v-else class="mt-3 text-xs text-cream-300/60">Chọn ảnh (kết quả / chỉnh sửa) để nâng cấp.</div>
    <label class="label mt-3">Độ phóng to (Upscale)</label>
    <div class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-3 py-2.5 text-xs"><span class="shrink-0 font-medium text-cream-200">Độ phóng</span><input type="range" min="1" max="4" step="1" v-model.number="store.upscaleScale" class="h-2 w-full cursor-pointer accent-brand-500"><span class="shrink-0 font-semibold text-cream-50">{{ store.upscaleScale }}x</span></div>
    <label class="label mt-3">Tinh chỉnh AI (nét & chi tiết)</label>
    <div class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-3 py-2.5 text-xs"><span class="shrink-0 font-medium text-cream-200">Tinh chỉnh</span><input type="range" min="0" max="10" step="1" v-model.number="store.upscaleRefine" class="h-2 w-full cursor-pointer accent-brand-500"><span class="shrink-0 font-semibold text-cream-50">{{ store.upscaleRefine }}/10</span></div>
    <label class="label mt-3">🎞 Studio Chân thực (như ảnh chụp chuyên nghiệp)</label>
    <div class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-3 py-2.5 text-xs"><span class="shrink-0 font-medium text-cream-200">Chân thực</span><input type="range" min="0" max="10" step="1" v-model.number="store.studioPhotoreal" class="h-2 w-full cursor-pointer accent-brand-500"><span class="shrink-0 font-semibold text-cream-50">{{ store.studioPhotoreal }}/10</span></div>
    <label class="label mt-3">👩 Da: lỗ chân lông · vết nám (có kiểm soát)</label>
    <div class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-3 py-2.5 text-xs"><span class="shrink-0 font-medium text-cream-200">Da</span><input type="range" min="0" max="10" step="1" v-model.number="store.skinDetail" class="h-2 w-full cursor-pointer accent-brand-500"><span class="shrink-0 font-semibold text-cream-50">{{ store.skinDetail }}/10</span></div>
    <label class="label mt-3">💡 Ánh sáng & Bóng đổ (chiều sáng + sâu bóng)</label>
    <div class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-3 py-2.5 text-xs"><span class="shrink-0 font-medium text-cream-200">Ánh sáng</span><input type="range" min="0" max="10" step="1" v-model.number="store.lightShadow" class="h-2 w-full cursor-pointer accent-brand-500"><span class="shrink-0 font-semibold text-cream-50">{{ store.lightShadow }}/10</span></div>
    <label class="label mt-3">🧵 Vải (độ sần sùi & vân)</label>
    <div class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-3 py-2.5 text-xs"><span class="shrink-0 font-medium text-cream-200">Vải</span><input type="range" min="0" max="10" step="1" v-model.number="store.fabricDetail" class="h-2 w-full cursor-pointer accent-brand-500"><span class="shrink-0 font-semibold text-cream-50">{{ store.fabricDetail }}/10</span></div>
    <button @click="runUpscale" :disabled="store.upscaling || !store.upscaleSrc" class="btn-brand mt-3 w-full whitespace-nowrap">{{ store.upscaling ? 'Đang nâng cấp…' : '🔍 Nâng cấp Ảnh' }}</button>
  </div>
</template>
