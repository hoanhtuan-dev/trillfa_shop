<script setup>
import { ref, computed } from 'vue';
import { useStudioStore } from '../store.js';
import BaseModal from './BaseModal.vue';
import CompareSlider from './CompareSlider.vue';
const store = useStudioStore();

const baseUrl = ref('');
const lastIds = ref([]);
const compareOpen = ref(false);
const afterUrl = computed(() => store.generations.find(g => lastIds.value.includes(g.id) && g.status === 'completed')?.media_url || '');

const prompt = ref('');
const variants = ref(1);
const busy = ref(false);
const open = ref(false);
const uploading = ref(false);
const uploaded = ref([]); // [{ url, name }]
const selected = ref([]); // array of key
const fileEl = ref(null);

const CSRF = () => (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

const genImages = computed(() => store.generations
  .filter(g => g.media_url && g.type !== 'video' && g.status !== 'failed')
  .map(g => ({ key: 'gen-' + g.id, url: g.media_url, label: '#' + g.id })));

const upImages = computed(() => uploaded.value.map((u, i) => ({ key: 'up-' + i + '-' + u.name, url: u.url, label: 'Tải lên' })));

const images = computed(() => [...genImages.value, ...upImages.value]);
const selectedImgs = computed(() => selected.value.map(k => images.value.find(img => img.key === k)).filter(Boolean));

function toggle(key) {
  const i = selected.value.indexOf(key);
  if (i >= 0) {
    selected.value.splice(i, 1);
  } else if (selected.value.length < 3) {
    selected.value.push(key);
  } else {
    store.toast('Tối đa 3 ảnh.', 'error');
  }
}

function remove(key) {
  const i = selected.value.indexOf(key);
  if (i >= 0) selected.value.splice(i, 1);
}

function makeBase(key) {
  const i = selected.value.indexOf(key);
  if (i <= 0) return;
  selected.value.splice(i, 1);
  selected.value.unshift(key);
}

function roleLabel(i) { return '@image' + (i + 1); }

function insertTag(tag) {
  prompt.value = (prompt.value ? prompt.value + ' ' : '') + tag + ' ';
}

// Chip nhanh: mẫu prompt 1-click dùng định danh @imageN
const quickTemplates = [
  { icon: '👗', label: 'Thay trang phục', prompt: 'thay trang phục của @image1 bằng trang phục trong @image2' },
  { icon: '👤', label: 'Thay nhân vật', prompt: 'xóa nhân vật trong @image1, thay bằng nhân vật trong @image2' },
  { icon: '🌅', label: 'Đổi nền', prompt: 'giữ nguyên @image1, đổi nền theo @image2' },
  { icon: '🎨', label: 'Phối style', prompt: 'áp dụng phong cách của @image2 vào @image1' },
];
function applyTemplate(t) { prompt.value = t.prompt; }

const step = computed(() => {
  if (!selected.value.length) return 1;
  if (!prompt.value.trim()) return 2;
  return 3;
});

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
    uploaded.value.push({ url: d.url, name: d.name || file.name });
    const key = 'up-' + (uploaded.value.length - 1) + '-' + d.name;
    if (selected.value.length < 3) selected.value.push(key);
    store.toast('Đã tải ảnh lên.');
  } catch (err) {
    store.toast(err.message || 'Lỗi tải ảnh.', 'error');
  } finally {
    uploading.value = false;
    if (fileEl.value) fileEl.value.value = '';
  }
}

async function run() {
  if (selected.value.length < 2 || busy.value) return;
  const urls = selectedImgs.value.map(g => g.url).filter(Boolean);
  baseUrl.value = urls[0] || '';
  busy.value = true;
  const items = await store.compose(urls, prompt.value, variants.value);
  if (items) lastIds.value = items.map(it => it.generation_id).filter(Boolean);
  busy.value = false;
}
</script>
<template>
  <div class="card p-5" style="border:1px solid var(--color-brand-500); background: linear-gradient(160deg, rgba(255,170,120,.13), rgba(74,122,144,.06));">
    <h2 class="mb-1 font-display text-base font-semibold text-brand-300">🧩 Ghép ảnh</h2>
    <p class="text-[11px] text-ink-500">Hòa trộn 2–3 ảnh thành 1.</p>

    <!-- Chip nhanh (template 1-click) -->
    <div class="mt-2.5 flex flex-wrap gap-1.5">
      <button v-for="t in quickTemplates" :key="t.label" @click="applyTemplate(t)"
              class="rounded-full border border-ink-700 bg-ink-800/60 px-2.5 py-1 text-[11px] font-medium text-cream-200 transition hover:border-brand-400 hover:bg-brand-600/20">
        {{ t.icon }} {{ t.label }}
      </button>
    </div>

    <!-- Ảnh đã chọn (3 slot) -->
    <div class="mt-3 grid grid-cols-3 gap-1.5">
      <div v-for="i in 3" :key="i"
           class="relative flex h-24 flex-col items-center justify-center overflow-hidden rounded-xl border"
           :class="selectedImgs[i-1] ? 'border-brand-500 bg-ink-900' : 'border-dashed border-ink-700 bg-ink-900/40'">
        <template v-if="selectedImgs[i-1]">
          <img :src="selectedImgs[i-1].url" class="h-full w-full object-cover">
          <span class="absolute left-1 top-1 rounded-full bg-brand-500 px-1.5 text-[9px] font-bold text-white">{{ i }}</span>
          <span class="absolute inset-x-0 bottom-0 bg-black/65 px-1 py-0.5 text-center text-[9px] font-semibold text-cream-100">{{ roleLabel(i-1) }}</span>
          <button @click="remove(selectedImgs[i-1].key)" class="absolute right-1 top-1 grid h-5 w-5 place-items-center rounded-full bg-red-600/90 text-[9px] text-white">✕</button>
          <button v-if="i > 1" @click="makeBase(selectedImgs[i-1].key)" class="absolute bottom-6 right-1 grid h-5 w-5 place-items-center rounded-full bg-ink-800/90 text-[9px] text-white" title="Đưa lên làm nền chính">⤴</button>
        </template>
        <template v-else>
          <span class="text-2xl text-ink-600">{{ i === 1 ? '🖼' : '＋' }}</span>
          <span class="px-1 text-center text-[9px] text-cream-300/50">@image{{ i }}</span>
        </template>
      </div>
    </div>

    <button @click="open = true" class="btn-outline mt-2 w-full">
      {{ selected.length ? '🖼 Chọn lại ảnh' : '🖼 Chọn ảnh (2–3)' }} <span v-if="selected.length" class="text-cream-300/60">· {{ selected.length }}/3</span>
    </button>
    <p v-if="selected.length < 2" class="mt-1 text-center text-[10px] text-cream-300/50">Mẹo: bấm ảnh muốn làm <b class="text-brand-300">@image1 (nền chính)</b> trước.</p>

    <label class="label mt-3">Mô tả ghép</label>
    <textarea v-model="prompt" rows="3" maxlength="1000" class="input !text-xs" placeholder="VD: giữ nguyên @image1, đặt cô gái trong @image2 vào nền studio…"></textarea>
    <div class="mt-1.5 flex flex-wrap items-center gap-1.5 text-[10px]">
      <span class="text-cream-300/50">Chèn nhanh:</span>
      <button v-for="n in (selected.length || 0)" :key="n" @click="insertTag('@image' + n)"
              class="rounded-full bg-ink-800 px-2 py-0.5 font-semibold text-brand-300 transition hover:bg-brand-600 hover:text-white">@image{{ n }}</button>
    </div>

    <div class="mt-2 flex items-center gap-1.5 text-xs text-cream-200">
      <span class="mr-1">Số biến thể:</span>
      <button v-for="n in [1,2,3,4]" :key="n" @click="variants = n"
              :class="variants === n ? 'bg-brand-600 text-white' : 'bg-ink-800 text-cream-200 hover:bg-ink-700'"
              class="h-7 w-7 rounded-full font-semibold transition-colors">{{ n }}</button>
    </div>

    <button @click="run" :disabled="busy || selected.length < 2 || !prompt.trim()" class="btn-brand mt-3 w-full whitespace-nowrap">
      {{ busy ? 'Đang ghép…' : '🧩 Ghép ảnh' }} <span v-if="!busy" class="opacity-70">· {{ store.imageCreditCost }} credit</span>
    </button>

    <button v-if="baseUrl && afterUrl" @click="compareOpen = true" class="btn-outline mt-1.5 w-full whitespace-nowrap">🔍 So sánh Trước/Sau</button>

    <!-- Popup chọn ảnh -->
    <BaseModal v-model="open" title="🖼 Chọn 2–3 ảnh để ghép" wide>
      <div class="mb-3 rounded-2xl border border-brand-500/30 bg-brand-900/20 p-3 text-xs leading-relaxed text-brand-100">
        <p class="font-semibold">💡 Cách chọn & mô tả:</p>
        <p class="mt-1 text-brand-100/80">• Bấm ảnh làm <b>@image1</b> (nền chính / bố cục) trước.<br>• Bấm thêm 1–2 ảnh làm <b>@image2</b>, <b>@image3</b>.<br>• Trong ô mô tả, dùng <b>@image1/@image2/@image3</b> để chỉ từng ảnh — vd: "giữ nguyên @image1, đặt cô gái trong @image2…".</p>
      </div>

      <!-- Nút tải ảnh lên -->
      <label class="mb-3 flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-brand-500/40 bg-brand-900/20 px-3 py-2.5 text-xs font-semibold text-brand-200 transition hover:bg-brand-900/40">
        <span>{{ uploading ? '⏳ Đang tải lên…' : '📤 Tải ảnh lên từ máy' }}</span>
        <input ref="fileEl" type="file" accept="image/*" class="hidden" @change="uploadImage">
      </label>

      <div v-if="images.length" class="grid grid-cols-3 gap-2 sm:grid-cols-4">
        <button v-for="g in images" :key="g.key" @click="toggle(g.key)"
                class="relative overflow-hidden rounded-xl border-2 transition"
                :class="selected.includes(g.key) ? 'border-brand-500 ring-2 ring-brand-500/40' : 'border-ink-700 hover:border-ink-500'">
          <img :src="g.url" class="h-24 w-full bg-ink-900 object-cover" loading="lazy">
          <span v-if="selected.includes(g.key)"
                class="absolute left-1.5 top-1.5 grid h-6 w-6 place-items-center rounded-full bg-brand-500 text-xs font-bold text-white shadow">
            {{ selected.indexOf(g.key) + 1 }}
          </span>
          <span class="absolute inset-x-0 bottom-0 truncate bg-black/60 px-1 py-0.5 text-[9px] text-cream-200">{{ g.label }}</span>
        </button>
      </div>
      <p v-else class="rounded-2xl border border-dashed border-ink-600 p-4 text-center text-xs text-cream-300/60">Chưa có ảnh — hãy tạo ảnh trong <b>Concept</b> hoặc tải lên từ máy.</p>

      <div class="mt-4 flex items-center justify-between">
        <span class="text-xs text-cream-300/70">Đã chọn: <b class="text-brand-300">{{ selected.length }}/3</b></span>
        <button @click="open = false" class="btn-brand">✅ Xong</button>
      </div>
    </BaseModal>

    <!-- So sánh Trước/Sau -->
    <CompareSlider v-model="compareOpen" :before="baseUrl" :after="afterUrl" title="🧩 So sánh Trước/Sau khi ghép" />
  </div>
</template>
