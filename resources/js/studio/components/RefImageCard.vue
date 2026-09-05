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

// ── Chip nhanh: thiết lập sẵn mô tả + độ giống — bấm để điền, bấm lại để bỏ. ──
const presets = [
  { id: 'bg-white',  label: 'Nền trắng studio',  similarity: 85, prompt: 'keep the product unchanged; replace the background with a clean pure-white seamless studio backdrop, even soft lighting, no harsh shadows' },
  { id: 'bg-dark',   label: 'Nền studio tối',    similarity: 80, prompt: 'keep the product unchanged; replace the background with a dark charcoal studio backdrop with a soft rim light and subtle gradient' },
  { id: 'outdoor',   label: 'Ngoài trời nắng',    similarity: 65, prompt: 'keep the product unchanged; place it in an outdoor sunny scene with natural soft daylight, slight lens flare, editorial fashion vibe' },
  { id: 'catalog',   label: 'Lookbook catalog',  similarity: 75, prompt: 'keep the product unchanged; minimal clean catalog look, soft diffuse top lighting, light gray seamless background, centered framing, faint drop shadow' },
  { id: 'editorial', label: 'Editorial cao cấp', similarity: 60, prompt: 'keep the product unchanged; high-fashion editorial mood, dramatic side light, rich contrast, magazine-grade styling, deep tones' },
  { id: 'dslr',      label: 'Đậm chất DSLR',      similarity: 78, prompt: 'keep the product unchanged; professional DSLR product photo, razor-sharp focus, shallow depth of field, softbox studio lighting, 50mm look' },
  { id: 'angle45',   label: 'Góc chếch 45°',     similarity: 55, prompt: 'keep the product unchanged; render from a 45-degree three-quarter side angle, same lighting and styling, slightly different camera height' },
  { id: 'detail',    label: 'Cận cảnh chi tiết', similarity: 50, prompt: 'keep the product unchanged; extreme close-up macro of the fabric texture, stitching and material, sharp studio macro lighting' },
  { id: 'vivid',     label: 'Màu sống động',     similarity: 82, prompt: 'keep the product unchanged; increase color vibrance and saturation slightly, punchy high-contrast look, crisp sharp detail' },
  { id: 'flatlay',   label: 'Flat-lay',          similarity: 58, prompt: 'keep the product unchanged; top-down flat-lay composition on a clean neutral surface, soft natural shadow, catalog styling' },
];
const activePreset = ref(null);
function applyPreset(p) {
  if (activePreset.value === p.id) {
    activePreset.value = null;          // bấm lại → bỏ chọn (không xoá mô tả để user giữ lại)
    return;
  }
  activePreset.value = p.id;
  prompt.value = p.prompt;
  similarity.value = p.similarity;
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
    <p class="mt-1 text-[11px] leading-relaxed text-cream-300/60">Dùng <b class="text-cream-100">{{ selectedModel?.model || 'qwen-image-3.0-pro' }}</b> để tạo bức ảnh <b class="text-cream-100">mới</b> giống ảnh tham chiếu — không sửa trên ảnh gốc (khác "Sửa ảnh").</p>

    <!-- Ảnh tham chiếu -->
    <div v-if="img" class="mt-3 flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 p-2.5">
      <img :src="img" class="h-14 w-14 rounded-xl bg-ink-900 object-cover">
      <div class="min-w-0 text-xs text-cream-200">
        <p class="truncate font-semibold">{{ imgName }}</p>
        <p class="text-cream-300/60">Ảnh tham chiếu — giữ chủ thể/phong cách/bố cục</p>
      </div>
    </div>
    <div v-else class="mt-3 rounded-2xl border border-dashed border-white/15 bg-white/5 p-3 text-xs text-cream-300/60">Chọn một ảnh trong <b>Outputs</b> để làm ảnh tham chiếu.</div>

    <!-- Chip nhanh -->
    <p class="label mt-4">Chip nhanh</p>
    <div class="flex flex-wrap gap-1.5">
      <button v-for="p in presets" :key="p.id" @click="applyPreset(p)"
              class="rounded-full border px-2.5 py-1 text-[10px] font-semibold transition-colors"
              :class="activePreset === p.id ? 'border-brand-400 bg-brand-600 text-white' : 'border-ink-700 bg-ink-800 text-cream-200 hover:border-brand-400 hover:bg-brand-600/20'">
        {{ p.label }}
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
