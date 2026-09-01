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
</script>
<template>
  <!-- 2 icon công cụ nổi mép trái canvas -->
  <div class="absolute left-2 top-1/2 z-40 flex -translate-y-1/2 flex-col items-center gap-1.5" v-if="store.upscaleSrc">
    <button v-for="(op, key) in store.regionOps" :key="key" @click="store.startRegionSelect(key)"
      :class="store.regionMode === key ? 'bg-brand-600 text-white border-brand-400 shadow-brand-500/40' : 'bg-ink-900/85 text-cream-200 border-ink-700 hover:bg-ink-700'"
      class="grid h-10 w-10 place-items-center rounded-full border text-lg shadow-lg transition-colors" :title="op.label + ' · ' + op.hint">
      {{ op.icon }}
    </button>
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
</template>
