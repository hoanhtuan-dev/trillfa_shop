<script setup>
import { ref, computed } from 'vue';
import { useStudioStore } from '../store.js';
const store = useStudioStore();

const prompt = ref('');
const busy = ref(false);
const selected = ref([]); // array of generation ids, thứ tự chọn = thứ tự (ảnh đầu làm base)

// Các ảnh có thể ghép: mọi generation có media_url và không phải video
const images = computed(() => store.generations.filter(g => g.media_url && g.type !== 'video' && g.status !== 'failed'));

function toggle(g) {
  const i = selected.value.indexOf(g.id);
  if (i >= 0) {
    selected.value.splice(i, 1);
  } else if (selected.value.length < 3) {
    selected.value.push(g.id);
  } else {
    store.toast('Tối đa 3 ảnh.', 'error');
  }
}

function roleLabel(idx) {
  return idx === 0 ? 'Nền chính' : ('Ghép ' + (idx + 1));
}

async function run() {
  if (selected.value.length < 2 || busy.value) return;
  const urls = selected.value
    .map(id => store.generations.find(g => g.id === id)?.media_url)
    .filter(Boolean);
  busy.value = true;
  await store.compose(urls, prompt.value);
  busy.value = false;
}
</script>
<template>
  <div class="card p-5" style="border:1px solid var(--color-brand-500); background: linear-gradient(160deg, rgba(255,170,120,.13), rgba(74,122,144,.06));">
    <h2 class="mb-1 font-display text-base font-semibold text-brand-300">🧩 Ghép ảnh (Compose)</h2>
    <p class="text-[11px] text-ink-500">Chọn 2–3 ảnh — ảnh đầu là nền chính, AI hòa trộn thành 1 ảnh thương mại.</p>

    <!-- Chọn ảnh -->
    <div v-if="images.length" class="mt-3 grid grid-cols-4 gap-1.5">
      <button v-for="(g, i) in images" :key="g.id" @click="toggle(g)"
              class="relative overflow-hidden rounded-lg border-2 transition"
              :class="selected.includes(g.id) ? 'border-brand-500' : 'border-ink-700 hover:border-ink-500'">
        <img :src="g.media_url" class="h-16 w-full bg-ink-900 object-cover">
        <span v-if="selected.includes(g.id)"
              class="absolute left-1 top-1 grid h-5 w-5 place-items-center rounded-full bg-brand-500 text-[10px] font-bold text-white">
          {{ selected.indexOf(g.id) + 1 }}
        </span>
      </button>
    </div>
    <div v-else class="mt-3 rounded-2xl border border-dashed border-white/15 bg-white/5 p-3 text-xs text-cream-300/60">Chưa có ảnh — hãy tạo ảnh trước trong <b>Concept</b> hoặc <b>Outputs</b>.</div>

    <!-- Danh sách vai trò đã chọn -->
    <div v-if="selected.length" class="mt-2 flex flex-wrap gap-1.5">
      <span v-for="(id, i) in selected" :key="id" class="rounded-full bg-ink-800 px-2 py-0.5 text-[10px] text-cream-200">
        {{ i + 1 }}. {{ roleLabel(i) }}
      </span>
    </div>

    <label class="label mt-3">Mô tả ghép</label>
    <textarea v-model="prompt" rows="3" maxlength="1000" class="input !text-xs" placeholder="VD: đặt sản phẩm lên bàn studio, hòa ánh sáng tự nhiên…"></textarea>

    <button @click="run" :disabled="busy || selected.length < 2 || !prompt.trim()" class="btn-brand mt-3 w-full whitespace-nowrap">
      {{ busy ? 'Đang ghép…' : '🧩 Ghép ảnh' }}
    </button>
  </div>
</template>
