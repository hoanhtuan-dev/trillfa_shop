<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useStudioStore } from '../store.js';
import BaseModal from './BaseModal.vue';
import CompareSlider from './CompareSlider.vue';
const store = useStudioStore();

const prompt = ref('');
const variants = ref(1);
const busy = ref(false);
const mode = ref('compose'); // 'compose' | 'tryon'
const open = ref(false);
const uploading = ref(false);
const uploaded = ref([]);   // [{ url, name }]
const poses = ref([]);      // pose presets từ Settings
const selected = ref([null, null, null]); // 3 slot cố định: image object hoặc null
const targetSlot = ref(0);  // slot đang chọn trong popup
const fileEl = ref(null);

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

const genImages = computed(() => store.generations
  .filter(g => g.media_url && g.type !== 'video' && g.status !== 'failed')
  .map(g => ({ key: 'gen-' + g.id, url: g.media_url, label: '#' + g.id })));

const poseImages = computed(() => poses.value
  .filter(p => p.image)
  .map(p => ({ key: 'pose-' + p.id, url: p.image, label: p.name, skeleton: p.skeleton || p.description || '' })));

const upImages = computed(() => uploaded.value.map((u, i) => ({ key: 'up-' + i + '-' + u.name, url: u.url, label: u.name })));

const selectedImgs = computed(() => selected.value.filter(Boolean));
const selectedCount = computed(() => selectedImgs.value.length);

function openSlot(i) {
  targetSlot.value = i;
  open.value = true;
}

// Bấm 1 ảnh trong popup → gán vào slot đang chọn
function pick(img) {
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
  ? ['👗 Trang phục', '🧍 Pose', '🖼 Bối cảnh (tùy chọn)']
  : mode.value === 'faceswap'
    ? ['🧍 Người mẫu', '👤 Khuôn mặt', 'Ảnh ghép (tùy chọn)']
    : ['Nền chính', 'Ảnh ghép', 'Ảnh ghép']);

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
function setCompose() {
  mode.value = 'compose';
}

function insertTag(tag) {
  prompt.value = (prompt.value ? prompt.value + ' ' : '') + tag + ' ';
}

onMounted(async () => {
  timer = setInterval(() => { now.value = Date.now(); }, 1000);
  try {
    const r = await fetch('/studio/swap-poses', { headers: { Accept: 'application/json' } });
    const d = await r.json();
    poses.value = d.items || [];
  } catch (e) { poses.value = []; }
});
onBeforeUnmount(() => { if (timer) clearInterval(timer); });

async function uploadImage(e) {
  const file = e.target.files?.[0];
  if (!file) return;
  uploading.value = true;
  try {
    const fd = new FormData();
    fd.append('image', file);
    const res = await fetch('/studio/upload-ref', { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF(), Accept: 'application/json' }, body: fd });
    const d = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(d.message || 'Lỗi tải ảnh.');
    const img = { key: 'up-' + uploaded.value.length + '-' + d.name, url: d.url, label: file.name };
    uploaded.value.push({ url: d.url, name: d.name || file.name });
    selected.value[targetSlot.value] = img;
    open.value = false;
    store.toast('Đã tải ảnh vào ' + roleLabel(targetSlot.value) + '.');
  } catch (err) {
    store.toast(err.message || 'Lỗi tải ảnh.', 'error');
  } finally {
    uploading.value = false;
    if (fileEl.value) fileEl.value.value = '';
  }
}

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
  const items = await store.compose(urls, finalPrompt, variants.value, mode.value);
  if (items) lastIds.value = items.map(it => it.generation_id).filter(Boolean);
  busy.value = false;
}
</script>
<template>
  <div class="card p-5" style="border:1px solid var(--color-brand-500); background: linear-gradient(160deg, rgba(255,170,120,.13), rgba(74,122,144,.06));">
    <h2 class="mb-1 font-display text-base font-semibold text-brand-300">🧩 Ghép ảnh</h2>
    <p class="text-[11px] text-ink-500">Hòa trộn 2–3 ảnh thành 1.</p>

    <!-- Chip chế độ -->
    <div class="mt-2.5 flex gap-1.5">
      <button @click="setCompose()"
              :class="mode === 'compose' ? 'bg-brand-600 text-white' : 'bg-ink-800 text-cream-200 hover:bg-ink-700'"
              class="rounded-full px-2.5 py-1 text-[11px] font-semibold transition-colors">🧩 Ghép tự do</button>
      <button @click="setTryon()"
              :class="mode === 'tryon' ? 'bg-brand-600 text-white' : 'bg-ink-800 text-cream-200 hover:bg-ink-700'"
              class="rounded-full px-2.5 py-1 text-[11px] font-semibold transition-colors">🕺 Thử đồ ảo</button>
      <button @click="setFaceSwap()"
              :class="mode === 'faceswap' ? 'bg-brand-600 text-white' : 'bg-ink-800 text-cream-200 hover:bg-ink-700'"
              class="rounded-full px-2.5 py-1 text-[11px] font-semibold transition-colors">👤 Thay khuôn mặt</button>
    </div>
    <p v-if="mode === 'tryon'" class="mt-1.5 rounded-xl border border-brand-500/30 bg-brand-900/20 px-2.5 py-1.5 text-[10px] leading-relaxed text-brand-100">
      @image1 = 👗 trang phục · @image2 = 🧍 pose · @image3 = 🖼 bối cảnh (tùy chọn)
    </p>
    <p v-if="mode === 'faceswap'" class="mt-1.5 rounded-xl border border-brand-500/30 bg-brand-900/20 px-2.5 py-1.5 text-[10px] leading-relaxed text-brand-100">
      @image1 = 🧍 người mẫu · @image2 = 👤 khuôn mặt · @image3 = ảnh ghép (tùy chọn)
    </p>
    <!-- 3 slot ảnh: bấm để tải/chọn -->
    <div class="mt-3 grid grid-cols-3 gap-1.5">
      <button v-for="i in 3" :key="i" @click="openSlot(i - 1)"
              class="relative flex h-24 flex-col items-center justify-center overflow-hidden rounded-xl border transition"
              :class="selected[i-1] ? 'border-brand-500 bg-ink-900' : 'border-dashed border-ink-700 bg-ink-900/40 hover:border-brand-400'">
        <template v-if="selected[i-1]">
          <img :src="selected[i-1].url" class="h-full w-full object-cover">
          <span class="absolute left-1 top-1 rounded-full bg-brand-500 px-1.5 text-[9px] font-bold text-white">{{ i }}</span>
          <span class="absolute inset-x-0 bottom-0 bg-black/65 px-1 py-0.5 text-center text-[9px] font-semibold text-cream-100">{{ slotRoles[i-1] }}</span>
          <span @click.stop="removeSlot(i-1)" class="absolute right-1 top-1 grid h-5 w-5 place-items-center rounded-full bg-red-600/90 text-[9px] text-white">✕</span>
          <span v-if="i > 1" @click.stop="makeBase(i-1)" class="absolute bottom-6 right-1 grid h-5 w-5 place-items-center rounded-full bg-ink-800/90 text-[9px] text-white" title="Đưa lên làm @image1">⤴</span>
        </template>
        <template v-else>
          <span class="text-xl text-ink-600">{{ i === 1 ? '🖼' : '＋' }}</span>
          <span class="px-1 text-center text-[9px] font-medium text-cream-300/60">{{ slotRoles[i-1] }}</span>
          <span class="px-1 text-center text-[9px] text-cream-300/40">@image{{ i }}</span>
        </template>
      </button>
    </div>

    <label class="label mt-3">Mô tả ghép</label>
    <textarea v-model="prompt" rows="3" maxlength="1000" class="input !text-xs" placeholder="VD: giữ nguyên @image1, đặt cô gái trong @image2 vào nền studio…"></textarea>
    <div class="mt-1.5 flex flex-wrap items-center gap-1.5 text-[10px]">
      <span class="text-cream-300/50">Chèn nhanh:</span>
      <button v-for="n in 3" :key="n" @click="insertTag('@image' + n)"
              class="rounded-full bg-ink-800 px-2 py-0.5 font-semibold text-brand-300 transition hover:bg-brand-600 hover:text-white">@image{{ n }}</button>
    </div>

    <div class="mt-2 flex items-center gap-1.5 text-xs text-cream-200">
      <span class="mr-1">Số biến thể:</span>
      <button v-for="n in [1,2,3,4]" :key="n" @click="variants = n"
              :class="variants === n ? 'bg-brand-600 text-white' : 'bg-ink-800 text-cream-200 hover:bg-ink-700'"
              class="h-7 w-7 rounded-full font-semibold transition-colors">{{ n }}</button>
    </div>

    <button @click="run" :disabled="busy || selectedCount < 2 || !prompt.trim()" class="btn-brand mt-3 w-full whitespace-nowrap">
      {{ busy ? 'Đang ghép…' : '🧩 Ghép ảnh' }} <span v-if="!busy" class="opacity-70">· {{ store.imageCreditCost }} credit</span>
    </button>

    <!-- Tiến độ (giống Inpaint) -->
    <div v-if="running" class="mt-3 rounded-2xl border border-brand-500/30 bg-brand-900/30 p-3">
      <div class="flex items-center gap-2 text-xs text-brand-100">
        <span class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-brand-300 border-t-transparent"></span>
        <span class="font-semibold">{{ store.composeStage === 'send' ? 'Đang gửi yêu cầu tới AI…' : 'AI đang ghép ảnh…' }}</span>
      </div>
      <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-cream-200/80">
        <span>⏱ <b>{{ fmt(elapsedSec) }}</b></span>
        <span>{{ doneCount }}/{{ store.composeGenIds.length }} biến thể</span>
        <button @click="store.cancelCompose()" class="ml-auto rounded-full bg-red-600/25 px-2.5 py-1 font-semibold text-red-200 hover:bg-red-600">✕ Hủy</button>
      </div>
      <div class="mt-2 h-1 w-full overflow-hidden rounded-full bg-white/10"><div class="h-full animate-pulse rounded-full bg-brand-400" :style="{ width: (doneCount / Math.max(1, store.composeGenIds.length) * 100) + '%' }"></div></div>
    </div>

    <!-- Thành công -->
    <div v-if="store.composeStage === 'done'" class="mt-3 flex items-center gap-2 rounded-2xl border border-emerald-500/40 bg-emerald-900/25 p-3 text-xs text-emerald-200">
      ✅ Đã ghép xong — kết quả đã được chọn trong Outputs.
      <button @click="store.clearComposeStatus()" class="ml-auto rounded-full bg-white/10 px-2 py-0.5 hover:bg-white/20">Đóng</button>
    </div>

    <!-- Lỗi -->
    <div v-if="store.composeStage === 'error' && store.composeError" class="mt-3 rounded-2xl border border-red-500/40 bg-red-900/25 p-3 text-xs text-red-200">
      <p class="font-semibold">⚠️ Ghép ảnh thất bại</p>
      <p class="mt-1 whitespace-pre-line leading-relaxed">{{ store.composeError }}</p>
      <div class="mt-2 flex gap-2">
        <button @click="run" class="btn-brand btn-sm">🔄 Thử lại</button>
        <button @click="store.clearComposeStatus()" class="btn-ghost btn-sm">Đóng</button>
      </div>
    </div>

    <!-- Đã hủy -->
    <div v-if="store.composeStage === 'cancelled'" class="mt-3 flex items-center gap-2 rounded-2xl border border-white/15 bg-white/5 p-3 text-xs text-cream-200">
      🛑 Đã hủy yêu cầu ghép ảnh.
      <button @click="store.clearComposeStatus()" class="ml-auto rounded-full bg-white/10 px-2 py-0.5 hover:bg-white/20">Đóng</button>
    </div>

    <button v-if="baseUrl && afterUrl" @click="compareOpen = true" class="btn-outline mt-1.5 w-full whitespace-nowrap">🔍 So sánh Trước/Sau</button>

    <!-- Popup chọn/tải ảnh cho slot -->
    <BaseModal v-model="open" :title="'🖼 Tải ảnh cho ' + roleLabel(targetSlot)" wide>
      <div class="mb-3 rounded-2xl border border-brand-500/30 bg-brand-900/20 p-3 text-xs leading-relaxed text-brand-100">
        <p class="font-semibold">💡 @image{{ targetSlot + 1 }} = {{ slotRoles[targetSlot] }}</p>
      </div>

      <!-- Tải từ máy -->
      <label class="mb-3 flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-brand-500/40 bg-brand-900/20 px-3 py-2.5 text-xs font-semibold text-brand-200 transition hover:bg-brand-900/40">
        <span>{{ uploading ? '⏳ Đang tải lên…' : '📤 Tải ảnh từ máy' }}</span>
        <input ref="fileEl" type="file" accept="image/*" class="hidden" @change="uploadImage">
      </label>

      <!-- Vùng cuộn: pose + ảnh, lưới vuông -->
      <div class="max-h-[52vh] overflow-y-auto pr-1">
        <!-- Pose presets -->
        <template v-if="poseImages.length">
          <p class="mb-1.5 text-xs font-semibold text-cream-200">🕺 Pose (dáng)</p>
          <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
            <button v-for="p in poseImages" :key="p.key" @click="pick(p)"
                    class="relative aspect-square overflow-hidden rounded-xl border-2 border-ink-700 transition hover:border-brand-400">
              <img :src="p.url" class="h-full w-full bg-ink-900 object-cover" loading="lazy">
              <span class="absolute inset-x-0 bottom-0 truncate bg-black/60 px-1 py-0.5 text-[9px] text-cream-200">{{ p.label }}</span>
            </button>
          </div>
        </template>

        <!-- Ảnh của bạn (Outputs + đã tải lên) -->
        <template v-if="genImages.length || upImages.length">
          <p class="mb-1.5 mt-3 text-xs font-semibold text-cream-200">🖼 Ảnh của bạn</p>
          <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
            <button v-for="g in [...genImages, ...upImages]" :key="g.key" @click="pick(g)"
                    class="relative aspect-square overflow-hidden rounded-xl border-2 border-ink-700 transition hover:border-brand-400">
              <img :src="g.url" class="h-full w-full bg-ink-900 object-cover" loading="lazy">
              <span class="absolute inset-x-0 bottom-0 truncate bg-black/60 px-1 py-0.5 text-[9px] text-cream-200">{{ g.label }}</span>
            </button>
          </div>
        </template>
        <p v-if="!poseImages.length && !genImages.length && !upImages.length" class="rounded-2xl border border-dashed border-ink-600 p-4 text-center text-xs text-cream-300/60">Chưa có ảnh — hãy tải lên từ máy.</p>
      </div>
    </BaseModal>

    <!-- So sánh Trước/Sau -->
    <CompareSlider v-model="compareOpen" :before="baseUrl" :after="afterUrl" title="🧩 So sánh Trước/Sau khi ghép" />
  </div>
</template>
