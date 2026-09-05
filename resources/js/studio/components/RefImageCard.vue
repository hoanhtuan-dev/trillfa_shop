<script setup>
import { ref, computed } from 'vue';
import { useStudioStore } from '../store.js';
import StudioIcon from './StudioIcon.vue';
const store = useStudioStore();

// Card "Tạo ảnh mới từ ảnh mẫu" (i2i): model SINH ẢNH (qwen-image-3.0-pro) nhận ảnh tham chiếu
// làm base và tạo 1 bức ảnh HOÀN TOÀN MỚI giống ảnh mẫu theo % tương đồng — KHÔNG phải edit.

const img = computed(() => store.upscaleSrc || store.preview?.media_url || '');
const imgName = computed(() => store.upscaleName || (store.preview ? 'Ảnh kết quả #' + store.preview.id : 'Ảnh đang chọn'));

const prompt = ref('');
const similarity = ref(70);
const variants = ref(1);
const busy = ref(false);

// Model sinh ảnh mặc định = qwen-image-3.0-pro; cho phép chọn từ các model image edit-capable
// (defaults.inpaint_models) — card này dùng model ảnh chứ KHÔNG dùng model Qwen Edit để sửa.
const defaultModel = { provider: 'qwen', model: 'qwen-image-3.0-pro' };
const options = computed(() => {
  const list = store.inpaintModels || [];
  const hasDefault = list.some(o => o.provider === 'qwen' && o.model === 'qwen-image-3.0-pro');
  if (!hasDefault) list.unshift({ provider: 'qwen', model: 'qwen-image-3.0-pro', label: 'qwen-image-3.0-pro (mặc định)', default: false });
  return list;
});
const modelKey = ref('qwen:qwen-image-3.0-pro');
const selectedModel = computed(() => {
  const [provider, model] = (modelKey.value || '').split(':');
  return provider && model ? { provider, model } : null;
});

const canSubmit = computed(() => !!img.value && !busy.value && !!prompt.value.trim());

async function runRefgen() {
  if (!canSubmit.value) { store.toast('Chọn ảnh tham chiếu + nhập mô tả.', 'error'); return; }
  busy.value = true;
  const items = await store.refgen(img.value, prompt.value.trim(), similarity.value, variants.value, selectedModel.value);
  busy.value = false;
  if (items && items.length) {
    store.toast('Đã tạo ' + items.length + ' ảnh mới từ ảnh mẫu.');
  }
}
</script>
<template>
  <div class="card p-5" style="border:1px solid var(--color-brand-500); background: linear-gradient(160deg, rgba(124,200,90,.13), rgba(74,122,144,.06));">
    <h2 class="flex items-center gap-2 font-display text-base font-semibold text-brand-300">
      <StudioIcon name="image" /> Ảnh mới từ ảnh mẫu
      <span class="rounded-full bg-brand-600/30 px-1.5 py-0.5 text-[9px] font-semibold text-brand-200">i2i</span>
    </h2>
    <p class="mt-1 text-[11px] leading-relaxed text-cream-300/60">Dùng <b class="text-cream-100">{{ selectedModel?.model || 'qwen-image-3.0-pro' }}</b> để tạo một bức ảnh <b class="text-cream-100">mới</b> giống ảnh tham chiếu — không sửa trên ảnh gốc (khác với "Sửa ảnh").</p>

    <!-- Ảnh tham chiếu -->
    <div v-if="img" class="mt-3 flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 p-2.5">
      <img :src="img" class="h-14 w-14 rounded-xl bg-ink-900 object-cover">
      <div class="min-w-0 text-xs text-cream-200">
        <p class="truncate font-semibold">{{ imgName }}</p>
        <p class="text-cream-300/60">Ảnh tham chiếu — sẽ giữ chủ thể/phong cách/bố cục</p>
      </div>
    </div>
    <div v-else class="mt-3 rounded-2xl border border-dashed border-white/15 bg-white/5 p-3 text-xs text-cream-300/60">Chọn một ảnh trong <b>Outputs</b> để làm ảnh tham chiếu.</div>

    <!-- Model -->
    <label class="label mt-4">Model</label>
    <select v-model="modelKey" class="input !py-2 !text-xs" title="Model sinh ảnh nhận ảnh tham chiếu — mặc định qwen-image-3.0-pro">
      <option v-for="o in options" :key="o.provider + ':' + o.model" :value="o.provider + ':' + o.model">{{ o.label }}</option>
    </select>

    <!-- Mô tả -->
    <label class="label mt-4">Mô tả ảnh mới</label>
    <textarea v-model="prompt" rows="3" maxlength="1000" class="input !text-xs" placeholder="VD: giữ nguyên chủ thể và phong cách nhưng đổi sang phông nền studio tối, góc máy chếch…"></textarea>
    <p class="mt-1 text-right text-[10px] text-cream-300/50">{{ prompt.length }}/1000</p>

    <!-- Độ giống ảnh mẫu -->
    <label class="label mt-4">Độ giống ảnh mẫu</label>
    <div class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-3 py-2.5 text-xs">
      <span class="shrink-0 font-medium text-cream-200">Giống</span>
      <input type="range" min="0" max="100" step="5" v-model.number="similarity" class="h-2 w-full cursor-pointer accent-brand-500">
      <span class="shrink-0 font-semibold text-cream-50">{{ similarity }}%</span>
    </div>

    <!-- Số biến thể -->
    <label class="label mt-4">Số ảnh</label>
    <select v-model.number="variants" class="input !py-2 !text-xs">
      <option :value="1">1 ảnh</option>
      <option :value="2">2 ảnh</option>
      <option :value="3">3 ảnh</option>
      <option :value="4">4 ảnh</option>
    </select>

    <button @click="runRefgen" :disabled="!canSubmit" class="btn-brand mt-4 w-full whitespace-nowrap">
      <span v-if="busy">Đang tạo {{ variants }} ảnh…</span>
      <span v-else>Tạo {{ variants }} ảnh mới <span class="opacity-70">· {{ store.imageCreditCost * variants }} credit</span></span>
    </button>
    <p class="mt-2 text-[10px] leading-relaxed text-cream-300/40">Kết quả xuất hiện trong <b>Outputs</b> — chọn ảnh nào cũng được để xem lớn / làm ảnh gốc tiếp theo.</p>
  </div>
</template>