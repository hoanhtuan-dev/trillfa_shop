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

// Chip chỉnh nhanh: mẫu mô tả phổ biến → bấm điền thẳng vào ô "Mô tả chỉnh sửa".
const quickChips = [
  { icon: 'palette', label: 'Đổi màu', prompt: 'đổi màu quần áo trong vùng chọn sang màu khác, giữ nguyên chi tiết, chất liệu và phong cách.' },
  { icon: 'trash', label: 'Xóa tạp chất', prompt: 'xóa các chi tiết, đốm, vật/người thừa trong vùng chọn, lấp đầy bằng nền phù hợp.' },
  { icon: 'sparkles', label: 'Làm sạch', prompt: 'tăng độ sắc nét, làm sạch vùng chọn, bỏ nhiễu, hạt và đốm mờ.' },
  { icon: 'shirt', label: 'Chỉnh dáng', prompt: 'điều chỉnh dáng và độ ôm của trang phục trong vùng chọn cho vừa vặn, đẹp hơn.' },
  { icon: 'feather', label: 'Chất liệu', prompt: 'tăng độ chi tiết, độ mịn của chất liệu vải/dệt trong vùng chọn.' },
  { icon: 'wand', label: 'Thay nền', prompt: 'thay đổi phông nền trong vùng chọn theo mô tả, giữ nguyên chủ thể.' },
];
function applyChip(c) { store.inpaintPrompt = c.prompt; store.toast('Đã điền nhanh: ' + c.label); }
</script>
<template>
  <div class="card p-5" style="background: linear-gradient(160deg, rgba(124,200,90,.13), rgba(74,122,144,.06));">
    <h2 class="flex items-center gap-2 font-display text-base font-semibold text-brand-300">
      <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-brand-600/20 text-brand-300"><StudioIcon name="pencil" size="h-4 w-4" /></span>
      Sửa ảnh
      <span class="rounded-full bg-brand-600/30 px-1.5 py-0.5 text-[9px] font-semibold text-brand-200">AI</span>
    </h2>

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
    <div v-if="activeImg" class="mt-3 space-y-2">
      <button @click="store.toggleInpaintMask('path')" title="Vẽ vùng cần sửa bằng đường cong — đóng kín để làm mask"
              class="group flex w-full items-center justify-center gap-2.5 rounded-2xl border px-4 py-3 text-sm font-semibold transition-all duration-200"
              :class="store.inpaintMaskMode === 'path'
                ? 'border-emerald-400 bg-gradient-to-r from-emerald-500 to-teal-500 text-white shadow-lg shadow-emerald-900/30'
                : 'border-emerald-400/40 bg-emerald-500/10 text-emerald-200 hover:border-emerald-400/80 hover:bg-emerald-500/20 hover:shadow-md hover:shadow-emerald-900/20 active:scale-[.98]'">
        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl transition-colors"
              :class="store.inpaintMaskMode === 'path' ? 'bg-white/20' : 'bg-emerald-500/20 group-hover:bg-emerald-500/30'">
          <StudioIcon name="penTool" size="h-4 w-4" />
        </span>
        <span class="flex flex-col items-start text-left leading-tight">
          <span class="text-[13px] font-bold">{{ store.inpaintMaskMode === 'path' ? 'Đang vẽ mask — đóng kín để hoàn tất' : 'Vẽ mask' }}</span>
          <span class="text-[10px] opacity-80">Vùng chọn bằng đường cong (Bezier)</span>
        </span>
      </button>
      <div v-if="maskActive || store.inpaintMaskDone" class="flex justify-center">
        <button @click="store.clearInpaintMask()" title="Bỏ mask hiện tại"
                class="rounded-full bg-red-600/25 px-3 py-1 text-[10px] font-semibold text-red-200 transition-colors hover:bg-red-600 hover:text-white">
          Bỏ mask
        </button>
      </div>
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

    <label class="label mt-4"><StudioIcon name="zap" size="h-3.5 w-3.5" class="-mt-0.5 mr-1 inline text-brand-300" /> Chỉnh nhanh</label>
    <div class="mt-1 grid grid-cols-2 gap-1.5">
      <button v-for="c in quickChips" :key="c.label" @click="applyChip(c)"
              class="flex items-center gap-2 rounded-xl border px-2 py-1.5 text-left text-[10px] font-semibold transition-all"
              :class="store.inpaintPrompt === c.prompt ? 'border-brand-400 bg-brand-600/25 text-cream-50 shadow-brand-500/20' : 'border-ink-700 bg-ink-800 text-cream-200 hover:border-brand-400/50 hover:bg-ink-700'">
        <StudioIcon :name="c.icon" size="h-3.5 w-3.5" class="shrink-0 text-brand-300" />
        <span class="truncate">{{ c.label }}</span>
      </button>
    </div>

    <label class="label mt-4">Mô tả chỉnh sửa</label>
    <textarea v-model="store.inpaintPrompt" rows="3" maxlength="1000" class="input !text-xs" placeholder="VD: đổi màu áo thành đỏ, ngắn tay hơn, thêm túi trước…"></textarea>
    <p class="mt-1 text-right text-[10px] text-cream-300/50">{{ store.inpaintPrompt.length }}/1000</p>

    <div class="mt-2 flex flex-wrap gap-1.5">
      <button @click="store.inpaintPreserveFace = !store.inpaintPreserveFace"
              :class="store.inpaintPreserveFace ? 'border-brand-600 bg-brand-600 font-semibold text-white' : 'border-ink-700 text-cream-200 hover:border-brand-400'"
              class="rounded-full border px-3 py-1.5 text-[11px] font-semibold transition">
        <StudioIcon name="user" size="h-3 w-3" class="-mt-0.5 mr-1 inline" /> Giữ khuôn mặt & dáng
      </button>
      <button @click="store.inpaintPreserveBg = !store.inpaintPreserveBg"
              :class="store.inpaintPreserveBg ? 'border-brand-600 bg-brand-600 font-semibold text-white' : 'border-ink-700 text-cream-200 hover:border-brand-400'"
              class="rounded-full border px-3 py-1.5 text-[11px] font-semibold transition">
        <StudioIcon name="background" size="h-3 w-3" class="-mt-0.5 mr-1 inline" /> Giữ nền
      </button>
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
