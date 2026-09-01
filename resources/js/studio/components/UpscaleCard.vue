<script setup>
import { ref, watch } from 'vue';
import { useStudioStore } from '../store.js';
const store = useStudioStore();
const popupOpen = ref(false), presetName = ref('');
watch(() => store.upscaleCfg(), () => store.saveUpscaleMemory(), { deep: true });
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
function setv(field, val) { store[field] = Number(val); store.saveUpscaleMemory(); }
</script>
<template>
  <div class="card p-5" style="border:1px solid var(--color-brand-500); background: linear-gradient(160deg, rgba(232,150,120,.13), rgba(74,122,144,.06));">
    <div class="flex items-center justify-between">
      <h2 class="font-display text-base font-semibold text-brand-300">🔍 Tinh chỉnh & Nâng cấp ảnh</h2>
      <button @click="popupOpen = true" class="btn-outline btn-sm whitespace-nowrap">⚙️ Cài đặt & presets</button>
    </div>
    <template v-if="store.upscaleSrc"><div class="mt-3 flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 p-2.5"><img :src="store.upscaleSrc" class="h-16 w-16 rounded-xl bg-ink-900 object-cover"><div class="min-w-0 text-xs text-cream-200"><p class="truncate font-semibold">{{ store.upscaleName }}</p><p class="text-cream-300/60">{{ store.upscaleScale }}x</p></div></div></template>
    <div v-else class="mt-3 text-xs text-cream-300/60">Chọn ảnh (kết quả / chỉnh sửa / nguồn) để nâng cấp.</div>
    <label class="label mt-3">Độ phóng to (Upscale)</label>
    <div class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-3 py-2.5 text-xs"><span class="shrink-0 font-medium text-cream-200">Độ phóng</span><input type="range" min="1" max="4" step="1" :value="store.upscaleScale" @input="setv('upscaleScale', $event.target.value)" class="h-2 w-full cursor-pointer accent-brand-500"><span class="shrink-0 font-semibold text-cream-50">{{ store.upscaleScale }}x</span></div>
    <button @click="runUpscale" :disabled="store.upscaling || !store.upscaleSrc" class="btn-brand mt-3 w-full whitespace-nowrap">{{ store.upscaling ? 'Đang nâng cấp…' : '🔍 Nâng cấp Ảnh' }}</button>
    <!-- Settings + presets popup -->
    <div v-if="popupOpen" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/70 p-4" @click.self="popupOpen=false">
      <div class="scrollbar-hide max-h-[92vh] w-full max-w-md overflow-y-auto rounded-3xl border border-brand-500/30 bg-ink-900 p-5" @click.stop>
        <div class="mb-3 flex items-center justify-between"><span class="text-sm font-semibold text-brand-300">🔍 Cài đặt & presets</span><button @click="popupOpen=false" class="grid h-8 w-8 place-items-center rounded-full bg-ink-700 text-cream-200">✕</button></div>
        <div class="space-y-2.5">
          <div v-for="(f,i) in [['upscaleRefine','Tinh chỉnh AI'],['studioPhotoreal','Studio Chân thực'],['skinDetail','Da (lỗ chân lông/nám)'],['lightShadow','Ánh sáng & Bóng đổ'],['fabricDetail','Vải (độ sần sùi)']]" :key="f[0]">
            <div class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-3 py-2 text-xs"><span class="w-40 shrink-0 font-medium text-cream-200">{{ f[1] }}</span><input type="range" min="0" max="10" step="1" :value="store[f[0]]" @input="setv(f[0], $event.target.value)" class="h-2 w-full cursor-pointer accent-brand-500"><span class="shrink-0 font-semibold text-cream-50">{{ store[f[0]] }}</span></div>
          </div>
        </div>
        <!-- Save preset -->
        <div class="mt-4 flex gap-1.5">
          <input v-model="presetName" placeholder="Tên preset…" class="input !py-1.5">
          <button @click="store.savePreset(presetName); presetName=''" class="btn-brand btn-sm whitespace-nowrap">💾 Lưu preset</button>
        </div>
        <!-- Preset list -->
        <div v-if="store.upscalePresets.length" class="mt-3 space-y-1.5">
          <p class="text-xs font-semibold text-cream-200">Presets</p>
          <div v-for="p in store.upscalePresets" :key="p.name" class="flex items-center gap-2 rounded-xl border border-ink-700 bg-ink-800 p-2 text-xs">
            <button @click="store.applyPreset(p)" class="flex-1 text-left text-cream-100 hover:text-brand-300">{{ p.name }} <span class="text-cream-300/50">· {{ p.scale }}x · Da {{ p.skin }}</span></button>
            <button @click="store.deletePreset(p.name)" class="grid h-6 w-6 place-items-center rounded-full bg-red-600/20 text-red-200 hover:bg-red-600">🗑</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
