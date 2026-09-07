<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useStudioStore } from '../store.js';
import CompareSlider from './CompareSlider.vue';
import StudioIcon from './StudioIcon.vue';
import LoadingSpinner from './LoadingSpinner.vue';
const store = useStudioStore();

const beforeUrl = ref('');   // ảnh gốc trước khi sửa (để so sánh)
const compareOpen = ref(false);
function submitInpaint() {
  beforeUrl.value = store.upscaleSrc || '';
  store.inpaint(store.inpaintPrompt);
}

// Ảnh ĐANG CHỌN TRÊN CANVAS — nhận MỌI nguồn: upload / sản phẩm / kết quả / đã chỉnh sửa.
const activeImg = computed(() => store.upscaleSrc || '');

const now = ref(Date.now());
let timer = null;
onMounted(() => { timer = setInterval(() => { now.value = Date.now(); }, 1000); });
onBeforeUnmount(() => { if (timer) clearInterval(timer); });

const elapsedSec = computed(() => store.inpaintStartTs ? Math.max(0, Math.floor((now.value - store.inpaintStartTs) / 1000)) : 0);
const fmt = (s) => String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');

const activeGen = computed(() => store.inpaintGenId ? store.generations.find(g => g.id === Number(store.inpaintGenId)) : null);
const canSubmit = computed(() => !!activeImg.value && !store.inpainting && !!store.inpaintPrompt.trim());
const running = computed(() => store.inpaintStage === 'send' || store.inpaintStage === 'processing');
const maskActive = computed(() => store.inpaintMaskMode !== 'none');
</script>
<template>
  <div class="card p-5" style="background: linear-gradient(160deg, rgba(124,200,90,.13), rgba(74,122,144,.06));">
    <h2 class="flex items-center gap-2 font-display text-base font-semibold text-brand-300"><StudioIcon name="pencil" /> Sửa ảnh</h2>

    <!-- Ảnh đang chọn (mọi nguồn: upload / sản phẩm / kết quả / đã chỉnh sửa) -->
    <div v-if="activeImg" class="mt-3 flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 p-2.5">
      <img :src="activeImg" class="h-14 w-14 rounded-xl bg-ink-900 object-cover">
      <div class="min-w-0 text-xs text-cream-200">
        <p class="truncate font-semibold">{{ store.upscaleName || 'Ảnh đang chọn' }}</p>
        <p class="text-cream-300/60">Sẽ sửa trực tiếp trên ảnh này</p>
      </div>
    </div>
    <div v-else class="mt-3 rounded-2xl border border-dashed border-white/15 bg-white/5 p-3 text-xs text-cream-300/60">Chọn một ảnh trên <b>canvas</b> (Nguồn / Kết quả / sản phẩm) để sửa.</div>

    <!-- Vẽ Mask: DUY NHẤT 1 công cụ = vùng chọn bằng đường cong (Bezier). Vẽ xong & đóng → tự lấy làm mask. -->
    <div v-if="activeImg" class="mt-3 flex flex-wrap items-center gap-1.5">
      <button @click="store.toggleInpaintMask('path')" title="Vẽ vùng cần sửa bằng đường cong — đóng kín để làm mask"
              :class="store.inpaintMaskMode === 'path' ? 'is-active' : ''"
              class="seg-btn">
        <StudioIcon name="penTool" size="h-3.5 w-3.5" /> Vẽ mask
      </button>
      <button v-if="maskActive || store.inpaintMaskDone" @click="store.clearInpaintMask()" title="Bỏ mask hiện tại"
              class="rounded-full bg-red-600/25 px-2 py-1 text-[10px] font-semibold text-red-200 hover:bg-red-600">
        Bỏ mask
      </button>
    </div>
    <div v-if="maskActive" class="mt-1.5 rounded-xl border border-brand-500/30 bg-brand-900/20 px-2.5 py-1.5 text-[10px] text-brand-200">
      Vẽ đường cong quanh vùng cần sửa — quay lại điểm đầu để đóng kín, vùng chọn tự thành mask.
    </div>
    <!-- Trạng thái mask ĐÃ LƯU (bấm Xong, overlay tắt): hiển thị vùng sẽ xử lý + thumbnail -->
    <div v-else-if="store.inpaintMaskDone" class="mt-1.5 rounded-xl border border-emerald-500/30 bg-emerald-900/20 px-2.5 py-2 text-[10px] text-emerald-200">
      <div class="flex items-center gap-2.5">
        <!-- Thumbnail vùng đã chọn: rect (ô đen trên nền trắng) / brush (mask đen-trắng) -->
        <div v-if="store._inpaintMaskKind === 'rect'" class="relative h-12 w-12 shrink-0 overflow-hidden rounded-lg border border-white/20 bg-white">
          <div class="absolute bg-black/85" :style="{ left: (store.inpaintMaskBox.x || 0) * 100 + '%', top: (store.inpaintMaskBox.y || 0) * 100 + '%', width: (store.inpaintMaskBox.w || 0) * 100 + '%', height: (store.inpaintMaskBox.h || 0) * 100 + '%' }"></div>
        </div>
        <img v-else-if="store.inpaintBrushData" :src="'data:image/png;base64,' + store.inpaintBrushData" class="h-12 w-12 shrink-0 rounded-lg border border-white/20 bg-white object-contain" alt="Mask" />
        <div class="min-w-0 flex-1">
          <p class="font-semibold">{{ store._inpaintMaskKind === 'rect' ? 'Đã chọn vùng ' + Math.round((store.inpaintMaskBox.w || 0) * 100) + '% × ' + Math.round((store.inpaintMaskBox.h || 0) * 100) + '%' : 'Đã vẽ mask' }}</p>
          <p class="mt-0.5 text-emerald-200/70">AI sẽ chỉ sửa trong vùng tô đen bên cạnh.</p>
        </div>
        <button @click="store.toggleInpaintMask('path')" class="shrink-0 rounded-full bg-white/10 px-2 py-0.5 font-semibold hover:bg-white/20">Chỉnh lại</button>
      </div>
    </div>

    <label class="label mt-4">Mô tả chỉnh sửa</label>
    <textarea v-model="store.inpaintPrompt" rows="3" maxlength="1000" class="input !text-xs" placeholder="VD: đổi màu áo thành đỏ, ngắn tay hơn, thêm túi trước…"></textarea>
    <p class="mt-1 text-right text-[10px] text-cream-300/50">{{ store.inpaintPrompt.length }}/1000</p>

    <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-cream-200">
      <label class="flex cursor-pointer items-center gap-1.5"><input type="checkbox" v-model="store.inpaintPreserveFace" class="h-3.5 w-3.5 accent-brand-500"> Giữ nguyên khuôn mặt & dáng</label>
      <label class="flex cursor-pointer items-center gap-1.5"><input type="checkbox" v-model="store.inpaintPreserveBg" class="h-3.5 w-3.5 accent-brand-500"> Giữ nguyên nền</label>
    </div>

    <button @click="submitInpaint" :disabled="!canSubmit" class="btn-brand mt-3 w-full whitespace-nowrap">
      <span v-if="store.inpainting && store.inpaintStage === 'send'">Đang gửi yêu cầu…</span>
      <span v-else-if="store.inpainting">AI đang chỉnh sửa…</span>
      <span v-else>Sửa ảnh <span class="opacity-70">· {{ store.imageCreditCost }} credit</span></span>
    </button>

    <!-- Tiến độ -->
    <div v-if="running" class="mt-3 rounded-2xl border border-brand-500/30 bg-brand-900/30 p-3">
      <LoadingSpinner
        :text="store.inpaintStage === 'send' ? 'Đang gửi yêu cầu tới AI…' : 'AI đang chỉnh sửa ảnh…'"
        :subtext="fmt(elapsedSec) + ' · Nhiệm vụ #' + store.inpaintGenId + (activeGen?.model ? ' · Model: ' + activeGen.model : '')" />
      <div class="mt-2 flex justify-end">
        <button @click="store.cancelInpaint()" class="rounded-full bg-red-600/25 px-2.5 py-1 text-[10px] font-semibold text-red-200 hover:bg-red-600">Hủy</button>
      </div>
    </div>

    <!-- Thành công -->
    <div v-if="store.inpaintStage === 'done'" class="mt-3 flex items-center gap-2 rounded-2xl border border-emerald-500/40 bg-emerald-900/25 p-3 text-xs text-emerald-200">
      Đã sửa xong — ảnh mới đã được chọn trong Outputs.
      <button v-if="beforeUrl && activeGen?.media_url" @click="compareOpen = true" class="rounded-full bg-white/10 px-2 py-0.5 font-semibold hover:bg-white/20">So sánh Trước/Sau</button>
      <button @click="store.clearInpaintStatus()" class="ml-auto rounded-full bg-white/10 px-2 py-0.5 hover:bg-white/20">Đóng</button>
    </div>

    <!-- Lỗi -->
    <div v-if="store.inpaintStage === 'error' && store.inpaintError" class="mt-3 rounded-2xl border border-red-500/40 bg-red-900/25 p-3 text-xs text-red-200">
      <p class="font-semibold">Sửa ảnh thất bại</p>
      <p class="mt-1 whitespace-pre-line leading-relaxed">{{ store.inpaintError }}</p>
      <div class="mt-2 flex gap-2">
        <button @click="store.inpaint(store.inpaintPrompt)" class="btn-brand btn-sm">Thử lại</button>
        <button @click="store.clearInpaintStatus()" class="btn-ghost btn-sm">Đóng</button>
      </div>
    </div>

    <!-- Đã hủy -->
    <div v-if="store.inpaintStage === 'cancelled'" class="mt-3 flex items-center gap-2 rounded-2xl border border-white/15 bg-white/5 p-3 text-xs text-cream-200">
      Đã hủy yêu cầu sửa ảnh.
      <button @click="store.clearInpaintStatus()" class="ml-auto rounded-full bg-white/10 px-2 py-0.5 hover:bg-white/20">Đóng</button>
    </div>

    <!-- So sánh Trước/Sau -->
    <CompareSlider v-model="compareOpen" :before="beforeUrl" :after="activeGen?.media_url || ''" />
  </div>
</template>
