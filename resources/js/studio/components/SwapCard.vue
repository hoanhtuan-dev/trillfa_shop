<script setup>
import { ref, onMounted } from 'vue';
import { useStudioStore } from '../store.js';
const store = useStudioStore();
const models = ref([]), poses = ref([]);
onMounted(async () => {
  try { const r = await fetch('/studio/swap-models', { headers: { Accept: 'application/json' } }); const d = await r.json(); models.value = d.items || d.models || []; } catch(e){}
  try { const r = await fetch('/studio/swap-poses', { headers: { Accept: 'application/json' } }); const d = await r.json(); poses.value = d.items || d.poses || []; } catch(e){}
});
function toggle(list, id) { const i = list.indexOf(id); if (i >= 0) list.splice(i,1); else list.push(id); }
</script>
<template>
  <div class="card p-5" style="border:1px solid var(--color-brand-500); background: linear-gradient(160deg, rgba(232,87,125,.12), rgba(74,122,144,.06));">
    <h2 class="mb-1 font-display text-base font-semibold text-brand-300">🪄 Thay Đổi Người Mẫu</h2>
    <p class="text-[11px] text-ink-500">Chọn khuôn mặt + dáng → thử trang phục (giữ nguyên 100%).</p>
    <label class="label mt-3">Khuôn mặt</label>
    <div class="flex flex-wrap gap-1.5">
      <button v-for="m in models" :key="m.id" type="button" @click="toggle(store.swapModelIds, m.id)" class="rounded-full border px-3 py-1.5 text-xs" :class="store.swapModelIds.includes(m.id) ? 'border-brand-600 bg-brand-600 font-semibold text-white' : 'border-ink-700 text-cream-200 hover:border-brand-400'">{{ m.name || m.id }}</button>
    </div>
    <label class="label mt-3">Dáng</label>
    <div class="flex flex-wrap gap-1.5">
      <button v-for="p in poses" :key="p.id" type="button" @click="toggle(store.swapPoseIds, p.id)" class="rounded-full border px-3 py-1.5 text-xs" :class="store.swapPoseIds.includes(p.id) ? 'border-brand-600 bg-brand-600 font-semibold text-white' : 'border-ink-700 text-cream-200 hover:border-brand-400'">{{ p.name || p.id }}</button>
    </div>
    <button @click="store.runSwap()" :disabled="store.swapLoading || !store.swapModelIds.length" class="btn-brand mt-3 w-full whitespace-nowrap">{{ store.swapLoading ? 'Đang ghép…' : '🔄 Áp Dụng Thay Đổi Người Mẫu' }}</button>
  </div>
</template>
