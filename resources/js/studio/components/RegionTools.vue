<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useStudioStore } from '../store.js';
const store = useStudioStore();

const now = ref(Date.now());
let timer = null;
onMounted(() => { timer = setInterval(() => { now.value = Date.now(); }, 1000); });
onBeforeUnmount(() => { if (timer) clearInterval(timer); });

const activeOp = computed(() => store.regionOp || store.regionMode);
const activeMeta = computed(() => (activeOp.value && store.regionOps[activeOp.value]) || null);
const running = computed(() => store.regionStage === 'send' || store.regionStage === 'processing');
const elapsedSec = computed(() => store.regionStartTs ? Math.max(0, Math.floor((now.value - store.regionStartTs) / 1000)) : 0);
const fmt = (s) => String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
const activeGen = computed(() => store.regionGenId ? store.generations.find(g => g.id === Number(store.regionGenId)) : null);
const hasRegion = computed(() => store.regionBox && (store.regionBox.w || 0) >= 0.02 && (store.regionBox.h || 0) >= 0.02);
const panelOpen = computed(() => !!store.regionMode || ['send', 'processing', 'done', 'error', 'cancelled'].includes(store.regionStage));
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
  <!-- Toolbar nổi: gom 3 công cụ vào 1 card (icon SVG chuẩn ngành, nút +10%) -->
  <div class="absolute left-2 top-1/2 z-40 -translate-y-1/2" v-if="store.upscaleSrc">
    <div class="flex flex-col items-center gap-1 rounded-2xl border border-ink-700 bg-ink-900/85 p-1.5 shadow-xl backdrop-blur">
      <button v-for="(op, key) in store.regionOps" :key="key" @click="store.startRegionSelect(key); reframeOpen = false; filmOpen = false"
        :class="store.regionMode === key ? 'bg-brand-600 text-white border-brand-400 shadow-brand-500/40' : 'text-cream-200 border-transparent hover:bg-ink-700'"
        class="grid h-11 w-11 place-items-center rounded-xl border transition-colors" :title="op.label + ' · ' + op.hint">
        <!-- Xóa vùng (eraser) -->
        <svg v-if="key === 'erase'" class="h-[22px] w-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7 21-4.3-4.3c-1-1-1-2.5 0-3.4l9.6-9.6c1-1 2.5-1 3.4 0l5.6 5.6c1 1 1 2.5 0 3.4L13 21"/><path d="M22 21H7"/><path d="m5 11 9 9"/></svg>
        <!-- Thay vùng (wand-sparkles) -->
        <svg v-else class="h-[22px] w-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.64 3.64-1.28-1.28a1.21 1.21 0 0 0-1.72 0L2.36 18.64a1.21 1.21 0 0 0 0 1.72l1.28 1.28a1.2 1.2 0 0 0 1.72 0L21.64 5.36a1.2 1.2 0 0 0 0-1.72"/><path d="m14 7 3 3"/><path d="M5 6v4"/><path d="M19 14v4"/><path d="M10 2v2"/><path d="M7 8H3"/><path d="M21 16h-4"/><path d="M11 3H9"/></svg>
      </button>
      <div class="h-px w-6 bg-ink-700"></div>
      <button @click="reframeOpen = !reframeOpen; store.stopRegionSelect(); filmOpen = false"
        :class="(reframeOpen || store.cropMode) ? 'bg-brand-600 text-white border-brand-400 shadow-brand-500/40' : 'text-cream-200 border-transparent hover:bg-ink-700'"
        class="grid h-11 w-11 place-items-center rounded-xl border transition-colors" title="Reframe / Crop · Cắt khung theo tỷ lệ / chọn vùng">
        <!-- Reframe / Crop (crop) -->
        <svg class="h-[22px] w-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2v14a2 2 0 0 0 2 2h14"/><path d="M18 22V8a2 2 0 0 0-2-2H2"/></svg>
      </button>
      <div class="h-px w-6 bg-ink-700"></div>
      <button @click="filmOpen = !filmOpen; store.stopRegionSelect(); reframeOpen = false"
        :class="(filmOpen || store.looking) ? 'bg-brand-600 text-white border-brand-400 shadow-brand-500/40' : 'text-cream-200 border-transparent hover:bg-ink-700'"
        class="grid h-11 w-11 place-items-center rounded-xl border transition-colors" title="Film Look · Gán tone màu phim">
        <!-- Film Look (palette) -->
        <svg class="h-[22px] w-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/></svg>
      </button>
    </div>
  </div>

  <!-- Panel nổi trong vùng canvas (khi chọn công cụ / đang chạy) -->
  <div v-if="panelOpen" class="absolute left-14 top-1/2 z-40 w-72 max-w-[82vw] -translate-y-1/2 rounded-2xl border border-brand-500/30 bg-ink-900/95 p-4 shadow-2xl backdrop-blur">
    <div class="flex items-center justify-between gap-2">
      <p class="text-sm font-semibold text-brand-300">{{ activeMeta?.icon }} {{ activeMeta?.label }}</p>
      <button @click="store.clearRegionStatus(); store.stopRegionSelect()" class="grid h-6 w-6 place-items-center rounded-full bg-ink-700 text-cream-200 hover:bg-red-600" title="Đóng">✕</button>
    </div>
    <p class="mt-1 text-[11px] text-cream-200/80">{{ activeMeta?.hint }}</p>

    <!-- Vùng đã chọn + nút áp dụng -->
    <div v-if="store.regionMode && hasRegion" class="mt-2 rounded-xl border border-white/10 bg-white/5 p-2 text-[11px] text-cream-200">
      Vùng: <b>{{ Math.round((store.regionBox.w || 0) * 100) }}%</b> × <b>{{ Math.round((store.regionBox.h || 0) * 100) }}%</b> của ảnh
      <textarea v-if="activeMeta?.needsPrompt" v-model="store.regionPrompt" rows="2" maxlength="2000" class="input mt-2 !text-xs" placeholder="VD: thay bằng túi xách da đen…"></textarea>
      <div class="mt-2 flex gap-2">
        <button @click="store.applyRegion()" :disabled="running" class="btn-brand btn-sm whitespace-nowrap">{{ activeOp === 'erase' ? '🗑 Xóa vùng chọn' : '🪄 Thay vùng chọn' }}</button>
        <button @click="store.stopRegionSelect()" class="btn-ghost btn-sm">Hủy chọn</button>
      </div>
    </div>
    <p v-else-if="store.regionMode" class="mt-2 text-[11px] text-brand-200">👉 Kéo chọn vùng trên canvas (Esc để hủy).</p>

    <!-- Tiến độ -->
    <div v-if="running" class="mt-3 rounded-xl border border-brand-500/30 bg-brand-900/30 p-2.5">
      <div class="flex items-center gap-2 text-xs text-brand-100">
        <span class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-brand-300 border-t-transparent"></span>
        <span class="font-semibold">{{ store.regionStage === 'send' ? 'Đang gửi yêu cầu…' : 'AI đang xử lý vùng…' }}</span>
      </div>
      <div class="mt-1.5 flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-cream-200/80">
        <span>⏱ <b>{{ fmt(elapsedSec) }}</b></span>
        <span v-if="activeGen?.model">Model: {{ activeGen.model }}</span>
        <button @click="store.cancelRegion()" class="ml-auto rounded-full bg-red-600/25 px-2 py-0.5 font-semibold text-red-200 hover:bg-red-600">✕ Hủy</button>
      </div>
      <div class="mt-1.5 h-1 w-full overflow-hidden rounded-full bg-white/10"><div class="h-full animate-pulse rounded-full bg-brand-400" style="width:60%"></div></div>
    </div>

    <!-- Thành công -->
    <div v-if="store.regionStage === 'done'" class="mt-3 flex items-center gap-2 rounded-xl border border-emerald-500/40 bg-emerald-900/25 p-2.5 text-xs text-emerald-200">✅ Đã xong — ảnh mới đã chọn.<button @click="store.clearRegionStatus()" class="ml-auto rounded-full bg-white/10 px-2 py-0.5 hover:bg-white/20">Đóng</button></div>

    <!-- Lỗi -->
    <div v-if="store.regionStage === 'error' && store.regionError" class="mt-3 rounded-xl border border-red-500/40 bg-red-900/25 p-2.5 text-xs text-red-200">
      <p class="font-semibold">⚠️ Thất bại</p>
      <p class="mt-1 whitespace-pre-line leading-relaxed">{{ store.regionError }}</p>
      <div class="mt-2 flex gap-2"><button @click="store.applyRegion()" class="btn-brand btn-sm">🔄 Thử lại</button><button @click="store.clearRegionStatus()" class="btn-ghost btn-sm">Đóng</button></div>
    </div>

    <!-- Đã hủy -->
    <div v-if="store.regionStage === 'cancelled'" class="mt-3 flex items-center gap-2 rounded-xl border border-white/15 bg-white/5 p-2.5 text-xs text-cream-200">🛑 Đã hủy.<button @click="store.clearRegionStatus()" class="ml-auto rounded-full bg-white/10 px-2 py-0.5 hover:bg-white/20">Đóng</button></div>
  </div>

  <!-- Panel Reframe / Crop nổi (giống 2 lệnh xóa/thay) -->
  <div v-if="reframeOpen || store.cropMode" class="absolute left-14 top-1/2 z-40 w-64 max-w-[80vw] -translate-y-1/2 rounded-2xl border border-brand-500/30 bg-ink-900/95 p-4 shadow-2xl backdrop-blur">
    <div class="flex items-center justify-between gap-2">
      <p class="text-sm font-semibold text-brand-300">📐 Reframe / Crop</p>
      <button @click="reframeOpen = false; if (store.cropMode) store.toggleCrop()" class="grid h-6 w-6 place-items-center rounded-full bg-ink-700 text-cream-200 hover:bg-red-600" title="Đóng">✕</button>
    </div>
    <p class="mt-1 text-[11px] text-cream-200/80">Cắt lại khung theo tỷ lệ hoặc chọn vùng trên canvas.</p>
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

  <!-- Panel Film Look nổi -->
  <div v-if="filmOpen || store.looking" class="absolute left-14 top-1/2 z-40 w-64 max-w-[80vw] -translate-y-1/2 rounded-2xl border border-brand-500/30 bg-ink-900/95 p-4 shadow-2xl backdrop-blur">
    <div class="flex items-center justify-between gap-2">
      <p class="text-sm font-semibold text-brand-300">🎨 Film Look</p>
      <button @click="filmOpen = false" class="grid h-6 w-6 place-items-center rounded-full bg-ink-700 text-cream-200 hover:bg-red-600" title="Đóng">✕</button>
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
