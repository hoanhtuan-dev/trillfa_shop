<script setup>
import { ref, computed } from 'vue';
import { useStudioStore } from '../store.js';
import StudioIcon from './StudioIcon.vue';

const store = useStudioStore();

// Card "Ảnh mới từ ảnh mẫu" (i2i): model SINH ẢNH (qwen-image-3.0-pro) nhận ảnh tham chiếu
// làm base và tạo 1 bức ảnh HOÀN TOÀN MỚI giống ảnh mẫu theo % tương đồng — KHÔNG phải edit.

const img = computed(() => store.upscaleSrc || store.preview?.media_url || '');
const imgName = computed(() => store.upscaleName || (store.preview ? 'Ảnh kết quả #' + store.preview.id : 'Ảnh đang chọn'));

const prompt = ref('');
const similarity = ref(70);
const variants = ref(1);
const busy = ref(false);

// Model sinh ảnh mặc định = qwen-image-3.0-pro; cho phép chọn từ các model edit-capable
// (defaults.inpaint_models). Sử dụng .slice() để KHÔNG đột biến state store khi chèn default.
const options = computed(() => {
  const list = Array.isArray(store.inpaintModels) ? store.inpaintModels.slice() : [];
  const hasDefault = list.some(o => o.provider === 'qwen' && o.model === 'qwen-image-3.0-pro');
  if (!hasDefault) {
    list.unshift({ provider: 'qwen', model: 'qwen-image-3.0-pro', label: 'qwen-image-3.0-pro (mặc định)', default: false });
  }
  return list;
});
const modelKey = ref('qwen:qwen-image-3.0-pro');
const selectedModel = computed(() => {
  const [provider, model] = (modelKey.value || '').split(':');
  return provider && model ? { provider, model } : null;
});

// Cho phép tạo ngay cả khi chưa nhập mô tả — backend tự dựng prompt "create a fresh variation".
const canSubmit = computed(() => !!img.value && !busy.value);

// ── Nền Studio: 8 màu nền studio thông dụng trong chụp ảnh thời trang. Bấm để điền
// mô tả + độ giống; bấm lại để bỏ. Mỗi chip có 1 swatch màu đại diện cho nền.
const presets = [
  { id: 'studio-white',  label: 'Trắng thuần',  color: '#ffffff', similarity: 82, prompt: 'keep the subject unchanged; replace the background with a pure-white seamless studio backdrop, even soft diffused lighting, no harsh shadows, clean editorial fashion look' },
  { id: 'studio-lgray',  label: 'Xám nhạt',     color: '#e8e8e8', similarity: 80, prompt: 'keep the subject unchanged; replace the background with a light neutral gray seamless studio backdrop, soft top-down diffused lighting, subtle gradient' },
  { id: 'studio-mgray',  label: 'Xám trung',    color: '#9a9a9a', similarity: 78, prompt: 'keep the subject unchanged; replace the background with an 18% medium gray seamless studio backdrop, professional softbox lighting, balanced contrast' },
  { id: 'studio-storm',  label: 'Xám đậm',      color: '#4a4a4a', similarity: 76, prompt: 'keep the subject unchanged; replace the background with a dark storm-gray studio backdrop with a soft rim light and subtle gradient, dramatic fashion mood' },
  { id: 'studio-black',  label: 'Đen tuyền',    color: '#0a0a0a', similarity: 75, prompt: 'keep the subject unchanged; replace the background with a jet-black seamless studio backdrop, deep shadow, single soft key light, high-contrast editorial mood' },
  { id: 'studio-cream',  label: 'Kem',          color: '#f2e7d5', similarity: 80, prompt: 'keep the subject unchanged; replace the background with a warm cream/beige seamless studio backdrop, soft warm diffuse lighting, clean catalog look' },
  { id: 'studio-blue',   label: 'Xanh phấn',   color: '#c9d8e0', similarity: 78, prompt: 'keep the subject unchanged; replace the background with a soft powder-blue seamless studio backdrop, cool soft lighting, calm editorial mood' },
  { id: 'studio-blush',  label: 'Hồng phấn',   color: '#ead3d5', similarity: 78, prompt: 'keep the subject unchanged; replace the background with a dusty blush-pink seamless studio backdrop, soft warm diffuse lighting, fashion lookbook mood' },
];
const activePreset = ref(null);
function applyPreset(p) {
  if (activePreset.value === p.id) {
    activePreset.value = null;          // bấm lại → bỏ chọn (không xoá mô tả để user giữ lại)
    return;
  }
  activePreset.value = p.id;
  activeAngle.value = null;             // nhóm góc chụp và nền studio loại trừ nhau
  prompt.value = p.prompt;
  similarity.value = p.similarity;
}

// ── Góc chụp: 4 hướng camera cơ bản. Mỗi chip có icon SVG camera xoay theo hướng đó.
// Bấm để điền prompt góc; bấm lại để bỏ. Nhóm này độc lập (loại trừ) với Nền Studio.
const anglePresets = [
  {
    id: 'angle-front',
    label: 'Chính diện',
    similarity: 70,
    prompt: 'keep the subject, garment and styling unchanged; shoot from a straight-on front view, eye-level camera, symmetrical framing, flat even studio lighting',
    // camera nhìn thẳng: body + lens giữa + chấm nhỏ trên (flash/viewfinder phía trước)
    svg: '<path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/><circle cx="17" cy="10" r="0.6"/>',
  },
  {
    id: 'angle-back',
    label: 'Mặt sau',
    similarity: 65,
    prompt: 'keep the subject, garment and styling unchanged; shoot from directly behind the subject (back view), eye-level camera, same lighting, full back of garment visible',
    // camera quay về phía sau: body + lens có 2 nét chéo qua (X) → "back side"
    svg: '<path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/><path d="M10 11l4 4"/><path d="M14 11l-4 4"/>',
  },
  {
    id: 'angle-left45',
    label: 'Nghiêng 45° trái',
    similarity: 62,
    prompt: 'keep the subject, garment and styling unchanged; shoot from a 45-degree three-quarter front-left angle, camera slightly to the left and slightly above eye level, same lighting and framing',
    // camera xoay chéo trái: body + lens + dấu mũi tên chéo sang trái
    svg: '<path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/><path d="M6 6l4 4"/><path d="M6 10h4V6"/>',
  },
  {
    id: 'angle-right45',
    label: 'Nghiêng 45° phải',
    similarity: 62,
    prompt: 'keep the subject, garment and styling unchanged; shoot from a 45-degree three-quarter front-right angle, camera slightly to the right and slightly above eye level, same lighting and framing',
    // camera xoay chéo phải: body + lens + dấu mũi tên chéo sang phải
    svg: '<path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/><path d="M18 6l-4 4"/><path d="M18 10h-4V6"/>',
  },
];
const activeAngle = ref(null);
function applyAngle(a) {
  if (activeAngle.value === a.id) {
    activeAngle.value = null;
    return;
  }
  activeAngle.value = a.id;
  activePreset.value = null;            // nhóm góc chụp và nền studio loại trừ nhau
  prompt.value = a.prompt;
  similarity.value = a.similarity;
}

async function runRefgen() {
  if (!canSubmit.value) return;
  busy.value = true;
  // Ảnh tham chiếu có thể là data:URL (canvas flattened) → backend downscaleSource xử lý.
  const items = await store.refgen(img.value, prompt.value.trim(), similarity.value, variants.value, selectedModel.value);
  busy.value = false;
  if (items && items.length) {
    store.toast('Đã gửi ' + items.length + ' ảnh mới — đang tạo…');
  }
}
</script>

<template>
  <div class="card p-5" style="border:1px solid var(--color-brand-500); background: linear-gradient(160deg, rgba(124,200,90,.13), rgba(74,122,144,.06));">
    <h2 class="flex items-center gap-2 font-display text-base font-semibold text-brand-300">
      <StudioIcon name="image" /> Ảnh mới từ ảnh mẫu
      <span class="rounded-full bg-brand-600/30 px-1.5 py-0.5 text-[9px] font-semibold text-brand-200">i2i</span>
    </h2>

    <!-- Ảnh tham chiếu -->
    <div v-if="img" class="mt-3 flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 p-2.5">
      <img :src="img" class="h-14 w-14 rounded-xl bg-ink-900 object-cover">
      <div class="min-w-0 text-xs text-cream-200">
        <p class="truncate font-semibold">{{ imgName }}</p>
        <p class="text-cream-300/60">Ảnh tham chiếu — giữ chủ thể/phong cách/bố cục</p>
      </div>
    </div>
    <div v-else class="mt-3 rounded-2xl border border-dashed border-white/15 bg-white/5 p-3 text-xs text-cream-300/60">Chọn một ảnh trong <b>Outputs</b> để làm ảnh tham chiếu.</div>

    <!-- Nền Studio -->
    <div class="mt-4 flex items-center justify-between">
      <p class="label">Nền Studio</p>
      <span class="text-[9px] font-medium text-cream-300/40">{{ presets.length }} nền</span>
    </div>
    <div class="mt-1 grid grid-cols-2 gap-1.5">
      <button v-for="p in presets" :key="p.id" @click="applyPreset(p)"
              class="flex items-center gap-2 rounded-xl border px-2 py-1.5 text-left text-[10px] font-semibold transition-all"
              :class="activePreset === p.id ? 'border-brand-400 bg-brand-600/25 text-cream-50 shadow-brand-500/20' : 'border-ink-700 bg-ink-800 text-cream-200 hover:border-brand-400/50 hover:bg-ink-700'">
        <span class="h-4 w-4 shrink-0 rounded-full border border-white/25 shadow-inner ring-1 ring-black/30" :style="{ background: p.color }" :title="'Mã màu ' + p.color"></span>
        <span class="truncate">{{ p.label }}</span>
      </button>
    </div>

    <!-- Góc chụp -->
    <div class="mt-4 flex items-center justify-between">
      <p class="label">Góc chụp</p>
      <span class="text-[9px] font-medium text-cream-300/40">{{ anglePresets.length }} góc</span>
    </div>
    <div class="mt-1 grid grid-cols-2 gap-1.5">
      <button v-for="a in anglePresets" :key="a.id" @click="applyAngle(a)"
              class="flex items-center gap-2 rounded-xl border px-2 py-1.5 text-left text-[10px] font-semibold transition-all"
              :class="activeAngle === a.id ? 'border-brand-400 bg-brand-600/25 text-cream-50 shadow-brand-500/20' : 'border-ink-700 bg-ink-800 text-cream-200 hover:border-brand-400/50 hover:bg-ink-700'">
        <svg class="h-4 w-4 shrink-0 text-brand-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" v-html="a.svg"></svg>
        <span class="truncate">{{ a.label }}</span>
      </button>
    </div>

    <!-- Model -->
    <label class="label mt-4">Model</label>
    <select v-model="modelKey" class="input !py-2 !text-xs" title="Model sinh ảnh nhận ảnh tham chiếu — mặc định qwen-image-3.0-pro">
      <option v-for="o in options" :key="o.provider + ':' + o.model" :value="o.provider + ':' + o.model">{{ o.label }}</option>
    </select>

    <!-- Mô tả -->
    <label class="label mt-4">Mô tả ảnh mới <span class="text-cream-300/40">(để trống = tạo biến thể giống ảnh mẫu)</span></label>
    <textarea v-model="prompt" rows="3" maxlength="1000" class="input !text-xs" placeholder="VD: giữ chủ thể, đổi sang nền studio tối, góc máy chếch…"></textarea>
    <p class="mt-1 text-right text-[10px] text-cream-300/50">{{ prompt.length }}/1000</p>

    <!-- Độ giống ảnh mẫu -->
    <label class="label mt-3">Độ giống ảnh mẫu</label>
    <div class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-3 py-2.5 text-xs">
      <span class="shrink-0 font-medium text-cream-200">Giống</span>
      <input type="range" min="0" max="100" step="5" v-model.number="similarity" class="h-2 w-full cursor-pointer accent-brand-500">
      <span class="shrink-0 font-semibold text-cream-50">{{ similarity }}%</span>
    </div>

    <!-- Số ảnh -->
    <label class="label mt-3">Số ảnh</label>
    <div class="flex gap-1.5">
      <button v-for="n in [1,2,3,4]" :key="n" @click="variants = n"
              class="flex-1 rounded-xl border py-2 text-xs font-semibold transition-colors"
              :class="variants === n ? 'border-brand-400 bg-brand-600 text-white' : 'border-ink-700 bg-ink-800 text-cream-200 hover:bg-ink-700'">{{ n }}</button>
    </div>

    <button @click="runRefgen" :disabled="!canSubmit" class="btn-brand mt-4 w-full whitespace-nowrap">
      <span v-if="busy">Đang tạo {{ variants }} ảnh…</span>
      <span v-else>Tạo {{ variants }} ảnh mới <span class="opacity-70">· {{ store.imageCreditCost * variants }} credit</span></span>
    </button>
    <p class="mt-2 text-[10px] leading-relaxed text-cream-300/40">Kết quả xuất hiện trong <b>Outputs</b> — chọn ảnh nào cũng được để xem lớn / làm ảnh gốc tiếp theo.</p>
  </div>
</template>
