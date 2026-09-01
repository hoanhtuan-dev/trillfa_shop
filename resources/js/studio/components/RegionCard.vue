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
</script>
<template>
  <div class="card p-5" style="border:1px solid var(--color-brand-500); background: linear-gradient(160deg, rgba(200,150,90,.13), rgba(74,122,144,.06));">
    <h2 class="mb-1 font-display text-base font-semibold text-brand-300">🧹 Vùng chọn (Xóa / Thay)</h2>
    <p class="text-[11px] text-ink-500">Chọn một vùng ngay trên canvas rồi xóa hoặc thay nội dung trong vùng đó.</p>

    <!-- Chọn thao tác -->
    <div class="mt-3 flex gap-2">
      <button v-for="(op, key) in store.regionOps" :key="key" @click="store.startRegionSelect(key)" class="flex-1 rounded-2xl border py-2 text-xs font-semibold transition-colors" :class="store.regionMode === key ? 'border-brand-500 bg-brand-600 text-white' : 'border-ink-700 text-cream-200 hover:border-brand-400'">{{ op.label }}</button>
    </div>
    <p v-if="store.regionMode" class="mt-1.5 text-[11px] text-brand-200">👉 {{ activeMeta?.hint }} — kéo chọn vùng trên canvas (Esc để hủy).</p>

    <!-- Vùng đã chọn + nút áp dụng -->
    <div v-if="store.regionMode && hasRegion" class="mt-3 rounded-2xl border border-white/10 bg-white/5 p-3">
      <p class="text-[11px] text-cream-200">Vùng chọn: <b>{{ Math.round((store.regionBox.w || 0) * 100) }}%</b> × <b>{{ Math.round((store.regionBox.h || 0) * 100) }}%</b> của ảnh</p>
      <textarea v-if="activeMeta?.needsPrompt" v-model="store.regionPrompt" rows="2" maxlength="2000" class="input mt-2 !text-xs" placeholder="VD: thay bằng túi xách da đen, bỏ người đi…"></textarea>
      <div class="mt-2 flex gap-2">
        <button @click="store.applyRegion()" :disabled="running" class="btn-brand btn-sm whitespace-nowrap">{{ activeOp === 'erase' ? '🗑 Xóa vùng chọn' : '🪄 Thay vùng chọn' }}</button>
        <button @click="store.stopRegionSelect()" class="btn-ghost btn-sm">Hủy chọn</button>
      </div>
    </div>

    <!-- Tiến độ -->
    <div v-if="running" class="mt-3 rounded-2xl border border-brand-500/30 bg-brand-900/30 p-3">
      <div class="flex items-center gap-2 text-xs text-brand-100">
        <span class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-brand-300 border-t-transparent"></span>
        <span class="font-semibold">{{ store.regionStage === 'send' ? 'Đang gửi yêu cầu…' : 'AI đang xử lý vùng…' }}</span>
      </div>
      <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-cream-200/80">
        <span>⏱ <b>{{ fmt(elapsedSec) }}</b></span>
        <span>Nhiệm vụ #{{ store.regionGenId }}</span>
        <span v-if="activeGen?.model">Model: {{ activeGen.model }}</span>
        <button @click="store.cancelRegion()" class="ml-auto rounded-full bg-red-600/25 px-2.5 py-1 font-semibold text-red-200 hover:bg-red-600">✕ Hủy</button>
      </div>
      <div class="mt-2 h-1 w-full overflow-hidden rounded-full bg-white/10"><div class="h-full animate-pulse rounded-full bg-brand-400" style="width:60%"></div></div>
    </div>

    <!-- Thành công -->
    <div v-if="store.regionStage === 'done'" class="mt-3 flex items-center gap-2 rounded-2xl border border-emerald-500/40 bg-emerald-900/25 p-3 text-xs text-emerald-200">
      ✅ Đã xong — ảnh mới đã được chọn trong Outputs.
      <button @click="store.clearRegionStatus()" class="ml-auto rounded-full bg-white/10 px-2 py-0.5 hover:bg-white/20">Đóng</button>
    </div>

    <!-- Lỗi -->
    <div v-if="store.regionStage === 'error' && store.regionError" class="mt-3 rounded-2xl border border-red-500/40 bg-red-900/25 p-3 text-xs text-red-200">
      <p class="font-semibold">⚠️ Thất bại</p>
      <p class="mt-1 whitespace-pre-line leading-relaxed">{{ store.regionError }}</p>
      <div class="mt-2 flex gap-2">
        <button @click="store.applyRegion()" class="btn-brand btn-sm">🔄 Thử lại</button>
        <button @click="store.clearRegionStatus()" class="btn-ghost btn-sm">Đóng</button>
      </div>
    </div>

    <!-- Đã hủy -->
    <div v-if="store.regionStage === 'cancelled'" class="mt-3 flex items-center gap-2 rounded-2xl border border-white/15 bg-white/5 p-3 text-xs text-cream-200">
      🛑 Đã hủy thao tác vùng.
      <button @click="store.clearRegionStatus()" class="ml-auto rounded-full bg-white/10 px-2 py-0.5 hover:bg-white/20">Đóng</button>
    </div>
  </div>
</template>
