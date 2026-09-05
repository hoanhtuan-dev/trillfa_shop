<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useStudioStore } from '../store.js';
import SourceLibraryPicker from './SourceLibraryPicker.vue';
import CompareSlider from './CompareSlider.vue';
import StudioIcon from './StudioIcon.vue';
const store = useStudioStore();

const prompt = ref('');
const variants = ref(1);
const creativeLevel = ref(6);   // mức độ sáng tạo (1–10) — dùng cho chế độ Ghép Trang Phục
const style = ref('');          // phong cách thiết kế (nhập tự do) — dùng cho chế độ Ghép Trang Phục
const ornamentLevel = ref(3);   // mức độ trang trí (0–10; 0 = tối giản, 10 = cầu kỳ) — dùng cho chế độ Ghép Trang Phục
const busy = ref(false);
const previewOpen = ref(false);
const previewPrompt = ref('');
const previewLoading = ref(false);
const previewDirty = ref(false);
const mode = ref('compose'); // 'compose' | 'tryon' | 'faceswap' | 'outfit'
const open = ref(false);
const selected = ref([null, null, null]); // 3 slot cố định: image object hoặc null
const targetSlot = ref(0);  // slot đang chọn trong popup

const baseUrl = ref('');
const lastIds = ref([]);
const compareOpen = ref(false);
const afterUrl = computed(() => store.generations.find(g => lastIds.value.includes(g.id) && g.status === 'completed')?.media_url || '');

// Tiến trình (giống Inpaint)
const now = ref(Date.now());
let timer = null;
const elapsedSec = computed(() => store.composeStartTs ? Math.max(0, Math.floor((now.value - store.composeStartTs) / 1000)) : 0);
const fmt = (s) => String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
const running = computed(() => store.composeStage === 'send' || store.composeStage === 'processing');
const doneCount = computed(() => store.composeGenIds.filter(id => store.generations.find(g => g.id === Number(id))?.status === 'completed').length);

const CSRF = () => (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

const selectedImgs = computed(() => selected.value.filter(Boolean));
const selectedCount = computed(() => selectedImgs.value.length);

function openSlot(i) {
  targetSlot.value = i;
  open.value = true;
}

// Bấm 1 ảnh trong popup → gán vào slot đang chọn
function onPick(img) {
  selected.value[targetSlot.value] = img;
  open.value = false;
}

function removeSlot(i) {
  selected.value[i] = null;
}

// Đưa slot i lên làm @image1 (nền chính)
function makeBase(i) {
  if (i <= 0) return;
  const s = [...selected.value];
  const img = s[i];
  s.splice(i, 1);
  s.unshift(img);
  s.push(null); // giữ luôn 3 slot
  s.length = 3;
  selected.value = s;
}

function roleLabel(i) { return '@image' + (i + 1); }

// Vai trò slot theo chế độ
const slotRoles = computed(() => mode.value === 'tryon'
  ? ['Trang phục', 'Pose', 'Bối cảnh (tùy chọn)']
  : mode.value === 'faceswap'
    ? ['Người mẫu', 'Khuôn mặt', 'Ảnh ghép (tùy chọn)']
    : mode.value === 'outfit'
      ? ['Trang phục 1', 'Trang phục 2', 'Bối cảnh (tùy chọn)']
      : ['Nền chính', 'Ảnh ghép', 'Ảnh ghép']);

const promptPlaceholder = computed(() => mode.value === 'outfit'
  ? 'VD: lai tạo trang phục từ phom dáng của @image1 và màu sắc của @image2…'
  : mode.value === 'tryon'
    ? 'VD: mặc @image1 lên người mẫu theo dáng @image2…'
    : mode.value === 'faceswap'
      ? 'VD: thay khuôn mặt @image2 vào người mẫu @image1…'
      : 'VD: giữ nguyên @image1, đặt cô gái trong @image2 vào nền studio…');

function setTryon() {
  mode.value = 'tryon';
  prompt.value = 'mặc trang phục @image1 lên người mẫu theo dáng @image2, giữ đúng dáng và tỉ lệ cơ thể';
  store.toast('Thử đồ ảo: @image1 = trang phục, @image2 = pose, @image3 = bối cảnh (tùy chọn).');
}
function setFaceSwap() {
  mode.value = 'faceswap';
  prompt.value = 'thay khuôn mặt của @image1 bằng khuôn mặt trong @image2, giữ nguyên dáng, trang phục, bối cảnh';
  store.toast('Thay khuôn mặt: @image1 = người mẫu, @image2 = khuôn mặt, @image3 = ảnh ghép (tùy chọn).');
}
function setOutfit() {
  mode.value = 'outfit';
  prompt.value = 'lai tạo trang phục mới từ @image1 và @image2: hòa trộn các đặc điểm nổi bật của cả hai (phom dáng, chất liệu, màu sắc, chi tiết) thành biến thể thời trang mới, đúng chuẩn thiết kế thời trang chuyên nghiệp';
  store.toast('Ghép Trang Phục: @image1 + @image2 = trang phục, @image3 = bối cảnh (tùy chọn).');
}
function setCompose() {
  mode.value = 'compose';
}

function insertTag(tag) {
  prompt.value = (prompt.value ? prompt.value + ' ' : '') + tag + ' ';
}

onMounted(() => {
  timer = setInterval(() => { now.value = Date.now(); }, 1000);
});
onBeforeUnmount(() => { if (timer) clearInterval(timer); });

async function run() {
  if (selectedCount.value < 2 || busy.value) return;
  const urls = selectedImgs.value.map(g => g.url).filter(Boolean);
  baseUrl.value = urls[0] || '';
  // Thử đồ ảo: tự chèn mô tả tư thế (skeleton) của pose để model hiểu dáng chính xác
  let finalPrompt = prompt.value;
  if (mode.value === 'tryon' && selected.value[1]?.skeleton) {
    finalPrompt += '. Pose detail: ' + selected.value[1].skeleton;
  }
  busy.value = true;
  const override = previewDirty.value ? previewPrompt.value : '';
  const items = await store.compose(urls, finalPrompt, variants.value, mode.value, creativeLevel.value, style.value, ornamentLevel.value, override);
  if (items) lastIds.value = items.map(it => it.generation_id).filter(Boolean);
  busy.value = false;
}

// ── Xem trước / chỉnh tay prompt ──
function togglePreview() {
  previewOpen.value = !previewOpen.value;
  if (previewOpen.value) loadPreview();
}

async function loadPreview() {
  if (selectedCount.value < 2) { store.toast('Chọn ít nhất 2 ảnh để xem trước prompt.', 'error'); return; }
  previewLoading.value = true;
  try {
    const urls = selectedImgs.value.map(g => g.url).filter(Boolean);
    const res = await fetch('/studio/compose/preview', {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': CSRF(), 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ images: urls, prompt: prompt.value, mode: mode.value, creative_level: creativeLevel.value, style: style.value, ornament_level: ornamentLevel.value }),
    });
    const d = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(d.message || 'Không tải được bản xem trước prompt.');
    previewPrompt.value = d.prompt || '';
    previewDirty.value = false;
  } catch (e) {
    store.toast(e.message || 'Lỗi tải bản xem trước prompt.', 'error');
  } finally {
    previewLoading.value = false;
  }
}

function onPreviewEdit() { previewDirty.value = true; }
</script>
<template>
  <div class="card p-5" style="border:1px solid var(--color-brand-500); background: linear-gradient(160deg, rgba(255,170,120,.13), rgba(74,122,144,.06));">
    <h2 class="flex items-center gap-2 font-display text-base font-semibold text-brand-300"><StudioIcon name="puzzle" /> Ghép ảnh</h2>

    <!-- Chip chế độ -->
    <div class="mt-3 grid grid-cols-2 gap-2">
      <button @click="setCompose()" title="Ghép tự do: hòa trộn nhiều ảnh"
              :class="mode === 'compose' ? 'bg-brand-600 text-white' : 'bg-ink-800 text-cream-200 hover:bg-ink-700'"
              class="flex flex-1 flex-col items-center justify-center gap-1.5 rounded-2xl px-2 py-2 text-[11px] font-semibold transition-colors"><StudioIcon name="layers" size="h-5 w-5" /> Ghép tự do</button>
      <button @click="setTryon()" title="Thử đồ ảo: mặc trang phục lên người mẫu"
              :class="mode === 'tryon' ? 'bg-brand-600 text-white' : 'bg-ink-800 text-cream-200 hover:bg-ink-700'"
              class="flex flex-1 flex-col items-center justify-center gap-1.5 rounded-2xl px-2 py-2 text-[11px] font-semibold transition-colors"><StudioIcon name="pose" size="h-5 w-5" /> Thử đồ ảo</button>
      <button @click="setFaceSwap()" title="Thay khuôn mặt người mẫu"
              :class="mode === 'faceswap' ? 'bg-brand-600 text-white' : 'bg-ink-800 text-cream-200 hover:bg-ink-700'"
              class="flex flex-1 flex-col items-center justify-center gap-1.5 rounded-2xl px-2 py-2 text-[11px] font-semibold transition-colors"><StudioIcon name="user" size="h-5 w-5" /> Thay khuôn mặt</button>
      <button @click="setOutfit()" title="Ghép Trang Phục: lai tạo biến thể trang phục mới từ 2 trang phục"
              :class="mode === 'outfit' ? 'bg-brand-600 text-white' : 'bg-ink-800 text-cream-200 hover:bg-ink-700'"
              class="flex flex-1 flex-col items-center justify-center gap-1.5 rounded-2xl px-2 py-2 text-[11px] font-semibold transition-colors"><StudioIcon name="shirt" size="h-5 w-5" /> Ghép Trang Phục</button>
    </div>
    <p v-if="mode === 'tryon'" class="mt-1.5 rounded-xl border border-brand-500/30 bg-brand-900/20 px-2.5 py-1.5 text-[10px] leading-relaxed text-brand-100">
      @image1 = trang phục · @image2 = pose · @image3 = bối cảnh (tùy chọn)
    </p>
    <p v-if="mode === 'faceswap'" class="mt-1.5 rounded-xl border border-brand-500/30 bg-brand-900/20 px-2.5 py-1.5 text-[10px] leading-relaxed text-brand-100">
      @image1 = người mẫu · @image2 = khuôn mặt · @image3 = ảnh ghép (tùy chọn)
    </p>
    <p v-if="mode === 'outfit'" class="mt-1.5 rounded-xl border border-brand-500/30 bg-brand-900/20 px-2.5 py-1.5 text-[10px] leading-relaxed text-brand-100">
      @image1 + @image2 = trang phục nguồn · @image3 = bối cảnh (tùy chọn) — lai tạo biến thể mới
    </p>
    <!-- 3 slot ảnh: bấm để tải/chọn -->
    <div class="mt-4 grid grid-cols-3 gap-2">
      <button v-for="i in 3" :key="i" @click="openSlot(i - 1)" title="Bấm để tải/chọn ảnh"
              class="relative flex h-24 flex-col items-center justify-center overflow-hidden rounded-xl border transition"
              :class="selected[i-1] ? 'border-brand-500 bg-ink-900' : 'border-dashed border-ink-700 bg-ink-900/40 hover:border-brand-400'">
        <template v-if="selected[i-1]">
          <img :src="selected[i-1].url" class="h-full w-full object-cover">
          <span class="absolute left-1 top-1 rounded-full bg-brand-500 px-1.5 text-[9px] font-bold text-white">{{ i }}</span>
          <span class="absolute inset-x-0 bottom-0 bg-black/65 px-1 py-0.5 text-center text-[9px] font-semibold text-cream-100">{{ slotRoles[i-1] }}</span>
          <span @click.stop="removeSlot(i-1)" title="Bỏ ảnh khỏi slot" class="absolute right-1 top-1 grid h-5 w-5 place-items-center rounded-full bg-red-600/90 text-[9px] text-white"><StudioIcon name="x" size="h-3 w-3" /></span>
          <span v-if="i > 1" @click.stop="makeBase(i-1)" class="absolute bottom-6 right-1 grid h-5 w-5 place-items-center rounded-full bg-ink-800/90 text-[9px] text-white" title="Đưa lên làm @image1">⤴</span>
        </template>
        <template v-else>
          <span class="grid h-6 w-6 place-items-center text-ink-600"><StudioIcon name="image" size="h-5 w-5" v-if="i === 1" /><span v-else>＋</span></span>
          <span class="px-1 text-center text-[9px] font-medium text-cream-300/60">{{ slotRoles[i-1] }}</span>
          <span class="px-1 text-center text-[9px] text-cream-300/40">@image{{ i }}</span>
        </template>
      </button>
    </div>

    <label class="label mt-4">Mô tả ghép</label>
    <textarea v-model="prompt" rows="3" maxlength="1000" class="input !text-xs" :placeholder="promptPlaceholder"></textarea>
    <div class="mt-1.5 flex flex-wrap items-center gap-1.5 text-[10px]">
      <button v-for="n in 3" :key="n" @click="insertTag('@image' + n)"
              class="rounded-full bg-ink-800 px-2 py-0.5 font-semibold text-brand-300 transition hover:bg-brand-600 hover:text-white">@image{{ n }}</button>
    </div>

    <!-- Phong cách + trang trí + mức sáng tạo (chỉ cho chế độ Ghép Trang Phục) -->
    <div v-if="mode === 'outfit'" class="mt-3">
      <label class="label">Phong cách</label>
      <input v-model="style" type="text" maxlength="200" class="input !text-xs" placeholder="VD: tối giản hiện đại, công sở thanh lịch, streetwear, boho, cổ điển…">
      <p class="mt-1 text-[10px] text-cream-300/50">Phong cách là hướng sáng tạo CHỦ ĐẠO — kết quả sẽ bám theo phong cách này.</p>
    </div>
    <div v-if="mode === 'outfit'" class="mt-3 flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-3 py-2.5 text-xs">
      <span class="shrink-0 font-medium text-cream-200">Trang trí</span>
      <input type="range" min="0" max="10" v-model.number="ornamentLevel" class="h-2 w-full cursor-pointer accent-brand-500">
      <span class="shrink-0 font-semibold text-cream-50">{{ ornamentLevel }}</span><span class="shrink-0 text-cream-300/60">/10</span>
    </div>
    <p v-if="mode === 'outfit'" class="mt-1 text-[10px] leading-relaxed text-cream-300/50">0 = tối giản, không họa tiết/đính đá · 10 = cầu kỳ, đính đá & họa tiết đậm.</p>
    <div v-if="mode === 'outfit'" class="mt-3 flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-3 py-2.5 text-xs">
      <span class="shrink-0 font-medium text-cream-200">Sáng tạo</span>
      <input type="range" min="1" max="10" v-model.number="creativeLevel" class="h-2 w-full cursor-pointer accent-brand-500">
      <span class="shrink-0 font-semibold text-cream-50">{{ creativeLevel }}</span><span class="shrink-0 text-cream-300/60">/10</span>
    </div>
    <p v-if="mode === 'outfit'" class="mt-1 text-[10px] leading-relaxed text-cream-300/50">Thấp = bám sát 2 trang phục gốc · Cao = tự do lai tạo, editorial.</p>

    <div class="mt-3 flex items-center gap-1.5 text-xs text-cream-200">
      <span class="mr-1">Số biến thể:</span>
      <button v-for="n in [1,2,3,4]" :key="n" @click="variants = n"
              :class="variants === n ? 'bg-brand-600 text-white' : 'bg-ink-800 text-cream-200 hover:bg-ink-700'"
              class="h-7 w-7 rounded-full font-semibold transition-colors">{{ n }}</button>
    </div>

    <!-- Xem trước / chỉnh tay prompt -->
    <button @click="togglePreview" type="button" class="btn-outline mt-3 w-full whitespace-nowrap">
      {{ previewOpen ? 'Ẩn xem trước prompt' : '👁 Xem trước prompt' }}
    </button>
    <div v-if="previewOpen" class="mt-2 rounded-2xl border border-brand-500/30 bg-brand-900/20 p-3">
      <div class="mb-1.5 flex items-center justify-between gap-2">
        <span class="text-[11px] font-semibold text-brand-200">Prompt sẽ gửi cho AI (chỉnh được)</span>
        <button @click="loadPreview" :disabled="previewLoading" class="btn-ghost btn-sm shrink-0">{{ previewLoading ? 'Đang tải…' : 'Làm mới' }}</button>
      </div>
      <textarea v-model="previewPrompt" @input="onPreviewEdit" rows="6" class="input w-full !text-[11px] leading-relaxed" placeholder="Bấm Làm mới để lấy prompt hiện tại…"></textarea>
      <p class="mt-1 text-[10px] leading-relaxed" :class="previewDirty ? 'text-amber-300' : 'text-cream-300/50'">
        <span v-if="previewDirty">✓ Sẽ gửi bản prompt đã chỉnh này.</span>
        <span v-else>Chưa chỉnh sửa — hệ thống tự dựng prompt từ các tùy chọn. Đổi tùy chọn/ảnh xong bấm "Làm mới".</span>
      </p>
    </div>

    <button @click="run" :disabled="busy || selectedCount < 2 || !prompt.trim()" class="btn-brand mt-3 w-full whitespace-nowrap">
      {{ busy ? 'Đang ghép…' : 'Ghép ảnh' }} <span v-if="!busy" class="opacity-70">· {{ store.imageCreditCost }} credit</span>
    </button>

    <!-- Tiến độ (giống Inpaint) -->
    <div v-if="running" class="mt-3 rounded-2xl border border-brand-500/30 bg-brand-900/30 p-3">
      <div class="flex items-center gap-2 text-xs text-brand-100">
        <span class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-brand-300 border-t-transparent"></span>
        <span class="font-semibold">{{ store.composeStage === 'send' ? 'Đang gửi yêu cầu tới AI…' : 'AI đang ghép ảnh…' }}</span>
      </div>
      <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-cream-200/80">
        <span><b>{{ fmt(elapsedSec) }}</b></span>
        <span>{{ doneCount }}/{{ store.composeGenIds.length }} biến thể</span>
        <button @click="store.cancelCompose()" class="ml-auto rounded-full bg-red-600/25 px-2.5 py-1 font-semibold text-red-200 hover:bg-red-600">Hủy</button>
      </div>
      <div class="mt-2 h-1 w-full overflow-hidden rounded-full bg-white/10"><div class="h-full animate-pulse rounded-full bg-brand-400" :style="{ width: (doneCount / Math.max(1, store.composeGenIds.length) * 100) + '%' }"></div></div>
    </div>

    <!-- Thành công -->
    <div v-if="store.composeStage === 'done'" class="mt-3 flex items-center gap-2 rounded-2xl border border-emerald-500/40 bg-emerald-900/25 p-3 text-xs text-emerald-200">
      Đã ghép xong — kết quả đã được chọn trong Outputs.
      <button @click="store.clearComposeStatus()" class="ml-auto rounded-full bg-white/10 px-2 py-0.5 hover:bg-white/20">Đóng</button>
    </div>

    <!-- Lỗi -->
    <div v-if="store.composeStage === 'error' && store.composeError" class="mt-3 rounded-2xl border border-red-500/40 bg-red-900/25 p-3 text-xs text-red-200">
      <p class="font-semibold">Ghép ảnh thất bại</p>
      <p class="mt-1 whitespace-pre-line leading-relaxed">{{ store.composeError }}</p>
      <div class="mt-2 flex gap-2">
        <button @click="run" class="btn-brand btn-sm">Thử lại</button>
        <button @click="store.clearComposeStatus()" class="btn-ghost btn-sm">Đóng</button>
      </div>
    </div>

    <!-- Đã hủy -->
    <div v-if="store.composeStage === 'cancelled'" class="mt-3 flex items-center gap-2 rounded-2xl border border-white/15 bg-white/5 p-3 text-xs text-cream-200">
      Đã hủy yêu cầu ghép ảnh.
      <button @click="store.clearComposeStatus()" class="ml-auto rounded-full bg-white/10 px-2 py-0.5 hover:bg-white/20">Đóng</button>
    </div>

    <button v-if="baseUrl && afterUrl" @click="compareOpen = true" class="btn-outline mt-1.5 w-full whitespace-nowrap">So sánh Trước/Sau</button>

    <!-- Popup chọn/tải ảnh cho slot (dùng chung Thư viện ảnh nguồn) -->
    <SourceLibraryPicker
      v-model="open"
      :title="'Tải ảnh cho ' + roleLabel(targetSlot) + ' · ' + slotRoles[targetSlot]"
      mode="pick"
      :include-poses="mode === 'tryon'"
      @pick="onPick" />

    <!-- So sánh Trước/Sau -->
    <CompareSlider v-model="compareOpen" :before="baseUrl" :after="afterUrl" title="So sánh Trước/Sau khi ghép" />
  </div>
</template>
