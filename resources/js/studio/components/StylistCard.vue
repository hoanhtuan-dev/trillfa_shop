<script setup>
import { ref, onMounted } from 'vue';
import { useStudioStore } from '../store.js';
const store = useStudioStore();
const types = ref([]);
const loading = ref(false);
const selectedType = ref('');
onMounted(async () => {
  try { const r = await fetch('/studio/stylist/types', { headers: { Accept: 'application/json' } }); const d = await r.json(); types.value = d.items || d || []; } catch(e){}
});
async function generatePrompt() {
  if (!selectedType.value || loading.value) return;
  loading.value = true;
  try { const d = await store.api('/studio/stylist/prompt', { type: selectedType.value }); store.imagePromptEn = d.prompt_en; store.toast('Trợ lý thiết kế đã tạo prompt.'); }
  catch(e){ store.toast(e.message || 'Lỗi tạo prompt.', 'error'); }
  finally { loading.value = false; }
}
</script>
<template>
  <div class="card p-5" style="border:1px solid var(--color-brand-500); background: linear-gradient(160deg, rgba(74,122,144,.14), rgba(124,58,237,.06));">
    <h2 class="mb-1 font-display text-base font-semibold text-brand-300">✨ Trợ lý thiết kế</h2>
    <p class="text-[11px] text-ink-500">Chọn loại trang phục → AI viết prompt thiết kế.</p>
    <div class="mt-3 grid grid-cols-2 gap-1.5">
      <button v-for="t in types" :key="t.id" type="button" @click="selectedType = t.id" class="rounded-xl border p-2 text-left text-xs transition-colors" :class="selectedType === t.id ? 'border-brand-500 bg-brand-600/25 text-white' : 'border-ink-700 text-cream-200 hover:border-brand-400'">{{ t.name }}</button>
    </div>
    <button @click="generatePrompt" :disabled="loading || !selectedType" class="btn-brand mt-3 w-full whitespace-nowrap">{{ loading ? 'Đang tạo…' : '✨ Tạo prompt thiết kế' }}</button>
  </div>
</template>
