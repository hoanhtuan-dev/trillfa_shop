<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useStudioStore } from '../store.js';
const store = useStudioStore();

// Live elapsed timer while an inpaint job is running.
const now = ref(Date.now());
let timer = null;
onMounted(() => { timer = setInterval(() => { now.value = Date.now(); }, 1000); });
onBeforeUnmount(() => { if (timer) clearInterval(timer); });

const elapsedSec = computed(() => store.inpaintStartTs ? Math.max(0, Math.floor((now.value - store.inpaintStartTs) / 1000)) : 0);
const fmt = (s) => String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');

const activeGen = computed(() => store.inpaintGenId ? store.generations.find(g => g.id === Number(store.inpaintGenId)) : null);
const canSubmit = computed(() => !!store.previewId && !!store.preview?.media_url && !store.inpainting && !!store.inpaintPrompt.trim());
const running = computed(() => store.inpaintStage === 'send' || store.inpaintStage === 'processing');
</script>
<template>
  <div class="card p-5" style="border:1px solid var(--color-brand-500); background: linear-gradient(160deg, rgba(124,200,90,.13), rgba(74,122,144,.06));">
    <h2 class="mb-1 font-display text-base font-semibold text-brand-300">✏️ Sửa ảnh (Inpaint)</h2>
    <p class="text-[11px] text-ink-500">Chỉnh sửa vùng/phần tử trên ảnh đang chọn — AI sửa đúng ảnh, giữ phần còn lại.</p>

    <!-- Ảnh sẽ được sửa -->
    <div v-if="store.preview?.media_url" class="mt-3 flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 p-2.5">
      <img :src="store.preview.media_url" class="h-14 w-14 rounded-xl bg-ink-900 object-cover">
      <div class="min-w-0 text-xs text-cream-200">
        <p class="truncate font-semibold">Ảnh kết quả #{{ store.preview.id }}</p>
        <p class="text-cream-300/60">Sẽ sửa trực tiếp trên ảnh này</p>
      </div>
    </div>
    <div v-else class="mt-3 rounded-2xl border border-dashed border-white/15 bg-white/5 p-3 text-xs text-cream-300/60">Chọn một ảnh kết quả trong <b>Outputs</b> để sửa.</div>

    <label class="label mt-3">Mô tả chỉnh sửa</label>
    <textarea v-model="store.inpaintPrompt" rows="3" maxlength="1000" class="input !text-xs" placeholder="VD: đổi màu áo thành đỏ, ngắn tay hơn, thêm túi trước…"></textarea>
    <p class="mt-1 text-right text-[10px] text-cream-300/50">{{ store.inpaintPrompt.length }}/1000</p>

    <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-cream-200">
      <label class="flex cursor-pointer items-center gap-1.5"><input type="checkbox" v-model="store.inpaintPreserveFace" class="h-3.5 w-3.5 accent-brand-500"> Giữ nguyên khuôn mặt & dáng</label>
      <label class="flex cursor-pointer items-center gap-1.5"><input type="checkbox" v-model="store.inpaintPreserveBg" class="h-3.5 w-3.5 accent-brand-500"> Giữ nguyên nền</label>
    </div>

    <button @click="store.inpaint(store.inpaintPrompt)" :disabled="!canSubmit" class="btn-brand mt-3 w-full whitespace-nowrap">
      <span v-if="store.inpainting && store.inpaintStage === 'send'">Đang gửi yêu cầu…</span>
      <span v-else-if="store.inpainting">AI đang chỉnh sửa…</span>
      <span v-else>✏️ Sửa ảnh</span>
    </button>

    <!-- Tiến độ -->
    <div v-if="running" class="mt-3 rounded-2xl border border-brand-500/30 bg-brand-900/30 p-3">
      <div class="flex items-center gap-2 text-xs text-brand-100">
        <span class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-brand-300 border-t-transparent"></span>
        <span class="font-semibold">{{ store.inpaintStage === 'send' ? 'Đang gửi yêu cầu tới AI…' : 'AI đang chỉnh sửa ảnh…' }}</span>
      </div>
      <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-cream-200/80">
        <span>⏱ <b>{{ fmt(elapsedSec) }}</b></span>
        <span>Nhiệm vụ #{{ store.inpaintGenId }}</span>
        <span v-if="activeGen?.model">Model: {{ activeGen.model }}</span>
        <button @click="store.cancelInpaint()" class="ml-auto rounded-full bg-red-600/25 px-2.5 py-1 font-semibold text-red-200 hover:bg-red-600">✕ Hủy</button>
      </div>
      <div class="mt-2 h-1 w-full overflow-hidden rounded-full bg-white/10"><div class="h-full animate-pulse rounded-full bg-brand-400" style="width:60%"></div></div>
    </div>

    <!-- Thành công -->
    <div v-if="store.inpaintStage === 'done'" class="mt-3 flex items-center gap-2 rounded-2xl border border-emerald-500/40 bg-emerald-900/25 p-3 text-xs text-emerald-200">
      ✅ Đã sửa xong — ảnh mới đã được chọn trong Outputs.
      <button @click="store.clearInpaintStatus()" class="ml-auto rounded-full bg-white/10 px-2 py-0.5 hover:bg-white/20">Đóng</button>
    </div>

    <!-- Lỗi -->
    <div v-if="store.inpaintStage === 'error' && store.inpaintError" class="mt-3 rounded-2xl border border-red-500/40 bg-red-900/25 p-3 text-xs text-red-200">
      <p class="font-semibold">⚠️ Sửa ảnh thất bại</p>
      <p class="mt-1 whitespace-pre-line leading-relaxed">{{ store.inpaintError }}</p>
      <div class="mt-2 flex gap-2">
        <button @click="store.inpaint(store.inpaintPrompt)" class="btn-brand btn-sm">🔄 Thử lại</button>
        <button @click="store.clearInpaintStatus()" class="btn-ghost btn-sm">Đóng</button>
      </div>
    </div>

    <!-- Đã hủy -->
    <div v-if="store.inpaintStage === 'cancelled'" class="mt-3 flex items-center gap-2 rounded-2xl border border-white/15 bg-white/5 p-3 text-xs text-cream-200">
      🛑 Đã hủy yêu cầu sửa ảnh.
      <button @click="store.clearInpaintStatus()" class="ml-auto rounded-full bg-white/10 px-2 py-0.5 hover:bg-white/20">Đóng</button>
    </div>
  </div>
</template>
