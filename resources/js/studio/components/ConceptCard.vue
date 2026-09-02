<script setup>
import { computed, ref, watch } from 'vue';
import { useStudioStore } from '../store.js';
import BaseModal from './BaseModal.vue';
const store = useStudioStore();
const showAdvanced = ref(false);
const promptLoading = ref(false);
const showHistory = ref(false);
const showTemplates = ref(false);
const history = ref([]);
const historyLoading = ref(false);
const enrichPreview = ref('');
const enrichLoading = ref(false);
const enrichError = ref('');

// ── Prompt templates ──
const templates = ref([
  { id: 'studio', name: 'Chụp studio', prompt: 'professional fashion photography, studio lighting, clean background' },
  { id: 'lookbook', name: 'Lookbook', prompt: 'fashion lookbook, outdoor natural light, editorial style, model walking' },
  { id: 'detail', name: 'Cận chi tiết', prompt: 'extreme close-up, fabric texture detail, macro fashion photography' },
  { id: 'minimal', name: 'Tối giản', prompt: 'minimalist fashion, clean aesthetic, soft natural light, simple composition' },
  { id: 'luxury', name: 'Luxury', prompt: 'luxury fashion editorial, high-end magazine, dramatic lighting, premium quality' },
  { id: 'street', name: 'Street style', prompt: 'street style fashion, urban background, candid shot, natural pose' },
]);

// Load saved templates from localStorage
function loadTemplates() {
  try {
    const saved = JSON.parse(localStorage.getItem('trillfa.prompt-templates') || '[]');
    if (Array.isArray(saved) && saved.length) {
      templates.value = [...templates.value, ...saved.map((t, i) => ({ ...t, id: 'custom-' + i }))];
    }
  } catch (e) {}
}
loadTemplates();

const promptPreview = computed(() => { const t = store.imagePromptEn || ''; return t.length > 54 ? t.slice(0, 54) + '…' : (t || 'Nhập/áp dụng prompt…'); });
const textureLabel = computed(() => {
  const t = store.texture;
  if (t <= 0) return 'Không';
  if (t <= 2) return 'Mịn phẳng';
  if (t <= 4) return 'Dệt nhẹ';
  if (t <= 6) return 'Rõ vừa';
  if (t <= 8) return 'Chi tiết cao';
  return 'Siêu chi tiết';
});
const creditEstimate = computed(() => {
  const base = 1;
  return base * store.variantCount;
});

// ── Debounce for sliders ──
const debounceTimers = {};
function debouncedSet(key, value, delay = 300) {
  if (debounceTimers[key]) clearTimeout(debounceTimers[key]);
  debounceTimers[key] = setTimeout(() => { store[key] = value; }, delay);
}
// Local reactive copies for sliders (instant UI feedback, debounced store write)
const localCreative = ref(store.creativeLevel);
const localTexture = ref(store.texture);
const localVariant = ref(store.variantCount);
watch(() => store.creativeLevel, (v) => { localCreative.value = v; });
watch(() => store.texture, (v) => { localTexture.value = v; });
watch(() => store.variantCount, (v) => { localVariant.value = v; });
watch(localCreative, (v) => { debouncedSet('creativeLevel', v); });
watch(localTexture, (v) => { debouncedSet('texture', v); });
watch(localVariant, (v) => { debouncedSet('variantCount', v); });

async function openPrompt() {
  if (!store.defaultsLoaded) {
    promptLoading.value = true;
    try { await store.loadDefaults(); } catch (e) {}
    finally { promptLoading.value = false; }
  }
  // Sync local copies
  localCreative.value = store.creativeLevel;
  localTexture.value = store.texture;
  localVariant.value = store.variantCount;
  enrichPreview.value = '';
  store.promptOpen = true;
}

function resetToDefaults() {
  store.applyDefaults();
  localCreative.value = store.creativeLevel;
  localTexture.value = store.texture;
  localVariant.value = store.variantCount;
  store.toast('Đã đặt lại về mặc định hệ thống.');
}

// ── Prompt History ──
async function loadHistory() {
  historyLoading.value = true;
  try {
    const res = await fetch('/studio/prompt-history', { headers: { Accept: 'application/json' } });
    const d = await res.json();
    history.value = d.items || [];
  } catch (e) { history.value = []; }
  finally { historyLoading.value = false; }
}
function applyHistory(item) {
  store.imagePromptEn = item.prompt;
  if (item.creative_level != null) { store.creativeLevel = Number(item.creative_level); localCreative.value = store.creativeLevel; }
  if (item.texture != null) { store.texture = Number(item.texture); localTexture.value = store.texture; }
  if (item.negative_prompt) store.negativePromptEn = item.negative_prompt;
  showHistory.value = false;
  store.toast('Đã áp dụng prompt từ lịch sử.');
}

// ── Prompt Templates ──
function applyTemplate(tpl) {
  store.imagePromptEn = tpl.prompt;
  showTemplates.value = false;
  store.toast('Đã áp dụng template: ' + tpl.name);
}

// ── Live Enrich Preview ──
let enrichDebounce = null;
function scheduleEnrichPreview() {
  if (enrichDebounce) clearTimeout(enrichDebounce);
  enrichDebounce = setTimeout(doEnrichPreview, 400);
}
async function doEnrichPreview() {
  const p = store.imagePromptEn?.trim();
  if (!p) { enrichPreview.value = ''; return; }
  enrichLoading.value = true;
  enrichError.value = '';
  try {
    const res = await fetch('/studio/preview-enrich', {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || '',
        'Content-Type': 'application/json',
        Accept: 'application/json'
      },
      body: JSON.stringify({
        prompt: p,
        creative_level: store.creativeLevel,
        texture: store.texture,
        negative_prompt: store.negativePromptEn || null
      })
    });
    if (!res.ok) throw new Error('Lỗi preview');
    const d = await res.json();
    enrichPreview.value = d.enriched || '';
    if (d.negative_prompt) enrichPreview.value += '\n\n— Negative: ' + d.negative_prompt;
  } catch (e) {
    enrichError.value = e.message;
    enrichPreview.value = '';
  }
  finally { enrichLoading.value = false; }
}
</script>
<template>
  <div class="card p-5" style="border:1px solid var(--color-brand-500); background: linear-gradient(160deg, rgba(124,58,237,.12), rgba(74,122,144,.06));">
    <button @click="openPrompt" class="flex w-full items-center justify-between rounded-2xl border border-white/10 bg-white/5 p-3 text-left transition hover:border-brand-400">
      <span class="min-w-0 flex-1 overflow-hidden"><span class="block truncate text-sm font-semibold text-brand-300">🎛 Prompt Tạo Ảnh</span><span class="mt-0.5 block w-full truncate text-[11px] text-ink-500">{{ promptPreview }}</span></span><span class="ml-1 shrink-0 text-lg text-cream-200">›</span>
    </button>
    <!-- Prompt popup (shared for AI generation) -->
    <BaseModal :model-value="store.promptOpen" @update:model-value="store.promptOpen = $event" title="🎛 Prompt Tạo Ảnh" wide>
      <div v-if="promptLoading" class="py-8 text-center text-sm text-cream-300/60">⏳ Đang tải cài đặt mặc định…</div>
      <template v-else>
        <!-- Prompt history + templates bar -->
        <div class="mb-2 flex items-center gap-1.5">
          <button @click="showHistory = !showHistory; if (showHistory) { loadHistory(); showTemplates = false }" class="rounded-full px-2.5 py-1 text-[10px] font-semibold transition" :class="showHistory ? 'bg-brand-600 text-white' : 'bg-ink-700 text-cream-200 hover:bg-ink-600'">📋 Lịch sử</button>
          <button @click="showTemplates = !showTemplates; if (showTemplates) showHistory = false" class="rounded-full px-2.5 py-1 text-[10px] font-semibold transition" :class="showTemplates ? 'bg-brand-600 text-white' : 'bg-ink-700 text-cream-200 hover:bg-ink-600'">📁 Templates</button>
          <span class="ml-auto text-[10px] text-cream-300/50">💰 ~{{ creditEstimate }} credit</span>
        </div>

        <!-- History panel -->
        <div v-if="showHistory" class="mb-2 max-h-40 overflow-y-auto rounded-xl border border-brand-500/30 bg-ink-800 p-2">
          <div v-if="historyLoading" class="py-3 text-center text-[10px] text-cream-300/50">Đang tải lịch sử…</div>
          <div v-else-if="!history.length" class="py-3 text-center text-[10px] text-cream-300/50">Chưa có prompt nào. Tạo ảnh đầu tiên để lưu lịch sử.</div>
          <button v-for="h in history" :key="h.id" @click="applyHistory(h)" class="mb-1 w-full rounded-lg border border-white/5 bg-white/5 p-2 text-left text-[10px] transition hover:border-brand-400">
            <p class="truncate text-cream-100">{{ h.prompt }}</p>
            <p class="mt-0.5 text-cream-300/40">{{ h.created_at }} · Sáng tạo {{ h.creative_level || '—' }}/10</p>
          </button>
        </div>

        <!-- Templates panel -->
        <div v-if="showTemplates" class="mb-2 max-h-40 overflow-y-auto rounded-xl border border-brand-500/30 bg-ink-800 p-2">
          <div class="grid grid-cols-2 gap-1">
            <button v-for="t in templates" :key="t.id" @click="applyTemplate(t)" class="rounded-lg border border-white/5 bg-white/5 p-2 text-left text-[10px] transition hover:border-brand-400">
              <span class="font-semibold text-cream-100">{{ t.name }}</span>
              <span class="mt-0.5 block truncate text-cream-300/40">{{ t.prompt }}</span>
            </button>
          </div>
        </div>

        <textarea v-model="store.imagePromptEn" @input="scheduleEnrichPreview" rows="4" class="input !text-xs" placeholder="Nhập ý tưởng / prompt (EN hoặc VI)."></textarea>

        <!-- Live enrich preview -->
        <div v-if="enrichLoading" class="mt-1 text-[10px] text-brand-300/60">⏳ Đang preview prompt đã enrich…</div>
        <div v-else-if="enrichPreview" class="mt-2 rounded-xl border border-emerald-500/30 bg-emerald-900/20 p-2.5">
          <p class="text-[10px] text-emerald-300/60 mb-1">✨ Prompt sau khi enrich (gửi lên model):</p>
          <p class="max-h-24 overflow-y-auto text-[10px] leading-relaxed text-emerald-100 whitespace-pre-wrap">{{ enrichPreview }}</p>
        </div>
        <div v-else-if="enrichError" class="mt-1 text-[10px] text-red-300/60">{{ enrichError }}</div>

        <div class="mt-3 rounded-2xl border border-ink-700 bg-ink-800 px-3 py-2 text-xs">
          <p class="mb-1 font-medium text-cream-200">Sáng tạo <span class="float-right font-semibold text-cream-50">{{ localCreative }}/10</span></p>
          <input type="range" min="1" max="10" v-model.number="localCreative" class="w-full cursor-pointer accent-brand-500">
        </div>
        <div class="mt-2 grid grid-cols-2 gap-2">
          <select v-model="store.imageRatio" class="input !py-2"><option v-for="r in ['1:1','4:3','3:4','9:16','16:9','4:5','21:9']" :key="r" :value="r">{{ r }}</option></select>
          <select v-model="store.imageRes" class="input !py-2"><option value="1K">1K</option><option value="2K">2K</option></select>
        </div>
        <label class="label mt-2">Số biến thể / lần tạo</label>
        <div class="flex items-center gap-3 rounded-2xl border border-ink-700 bg-ink-800 px-3 py-2 text-xs"><span class="shrink-0 font-medium text-cream-200">Biến thể</span><input type="range" min="1" max="4" step="1" v-model.number="localVariant" class="h-2 w-full cursor-pointer accent-brand-500"><span class="shrink-0 font-semibold text-cream-50">{{ localVariant }}</span></div>
        <label class="label mt-2">🧵 Texture: <span class="font-semibold text-brand-300">{{ textureLabel }}</span></label>
        <div class="flex items-center gap-3 rounded-2xl border border-ink-700 bg-ink-800 px-3 py-2 text-xs"><span class="shrink-0 font-medium text-cream-200">Texture</span><input type="range" min="0" max="10" step="1" v-model.number="localTexture" class="h-2 w-full cursor-pointer accent-brand-500"><span class="shrink-0 font-semibold text-cream-50">{{ localTexture }}</span></div>
        <!-- Advanced: Negative prompt -->
        <button @click="showAdvanced = !showAdvanced" class="mt-2 flex w-full items-center gap-1 text-xs text-cream-300/60 hover:text-cream-200">
          <span>{{ showAdvanced ? '▾' : '▸' }}</span> Nâng cao (negative prompt)
        </button>
        <div v-if="showAdvanced" class="mt-2 rounded-2xl border border-ink-700 bg-ink-800 p-3">
          <label class="label">Negative prompt (điều model KHÔNG nên tạo)</label>
          <textarea v-model="store.negativePromptEn" @input="scheduleEnrichPreview" rows="2" class="input !text-xs" placeholder="blurry, low quality, distorted proportions, extra limbs, deformed hands, watermark, text, logo..."></textarea>
          <p class="mt-1 text-[10px] text-cream-300/50">Để trống để dùng negative prompt mặc định từ Cài đặt.</p>
        </div>
        <!-- Credit info + reset -->
        <div class="mt-3 flex items-center justify-between text-[10px] text-cream-300/50">
          <button @click="resetToDefaults" class="underline hover:text-brand-300">↺ Đặt lại mặc định</button>
        </div>
        <button @click="store.promptOpen = false; store.generateImage()" :disabled="store.generating || !store.imagePromptEn" class="btn-brand mt-2 w-full whitespace-nowrap">{{ store.generating ? 'Đang gửi…' : '🎨 Tạo Ảnh 2D' }}</button>
      </template>
    </BaseModal>
  </div>
</template>