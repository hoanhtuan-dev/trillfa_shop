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
let enrichAbort = null;
function scheduleEnrichPreview() {
  if (enrichDebounce) clearTimeout(enrichDebounce);
  enrichDebounce = setTimeout(doEnrichPreview, 800);
}
async function doEnrichPreview() {
  const p = store.imagePromptEn?.trim();
  if (!p) { enrichPreview.value = ''; enrichLoading.value = false; return; }
  // Cancel any in-flight request
  if (enrichAbort) { enrichAbort.abort(); enrichAbort = null; }
  const controller = new AbortController();
  enrichAbort = controller;
  enrichLoading.value = true;
  enrichError.value = '';
  try {
    const res = await fetch('/studio/preview-enrich', {
      method: 'POST',
      signal: controller.signal,
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
    // Only update if this request wasn't superseded
    if (enrichAbort === controller) {
      enrichPreview.value = d.enriched || '';
      if (d.negative_prompt) enrichPreview.value += '\n\n— Negative: ' + d.negative_prompt;
      enrichLoading.value = false;
      enrichAbort = null;
    }
  } catch (e) {
    if (e.name === 'AbortError') return; // silently ignore aborted requests
    if (enrichAbort === controller) {
      enrichError.value = e.message;
      enrichLoading.value = false;
      enrichAbort = null;
    }
  }
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
        <!-- ── Top bar: History + Templates + Credit ── -->
        <div class="mb-3 flex items-center gap-2">
          <button @click="showHistory = !showHistory; if (showHistory) { loadHistory(); showTemplates = false }" class="rounded-lg border px-3 py-1.5 text-xs font-semibold transition" :class="showHistory ? 'border-brand-500 bg-brand-600 text-white' : 'border-ink-600 bg-ink-800 text-cream-200 hover:border-brand-400'">📋 Lịch sử</button>
          <button @click="showTemplates = !showTemplates; if (showTemplates) showHistory = false" class="rounded-lg border px-3 py-1.5 text-xs font-semibold transition" :class="showTemplates ? 'border-brand-500 bg-brand-600 text-white' : 'border-ink-600 bg-ink-800 text-cream-200 hover:border-brand-400'">📁 Templates</button>
          <span class="ml-auto text-xs text-cream-300/40">💰 ~{{ creditEstimate }} credit</span>
        </div>

        <!-- History panel (inline expand) -->
        <div v-if="showHistory" class="mb-3 max-h-44 overflow-y-auto rounded-xl border border-brand-500/30 bg-ink-800 p-2">
          <div v-if="historyLoading" class="py-3 text-center text-xs text-cream-300/50">Đang tải lịch sử…</div>
          <div v-else-if="!history.length" class="py-3 text-center text-xs text-cream-300/50">Chưa có prompt nào. Tạo ảnh đầu tiên để lưu lịch sử.</div>
          <button v-for="h in history" :key="h.id" @click="applyHistory(h)" class="mb-1 w-full rounded-lg border border-white/5 bg-white/5 p-2 text-left text-[11px] transition hover:border-brand-400">
            <p class="truncate text-cream-100">{{ h.prompt }}</p>
            <p class="mt-0.5 text-cream-300/40">{{ h.created_at }} · Sáng tạo {{ h.creative_level || '—' }}/10</p>
          </button>
        </div>

        <!-- Templates popup -->
        <div v-if="showTemplates" class="fixed inset-0 z-[80] flex items-center justify-center bg-black/70 p-4" @click.self="showTemplates = false">
          <div class="max-h-[80vh] w-full max-w-md overflow-y-auto rounded-3xl border border-brand-500/40 bg-ink-900 p-5 shadow-2xl" @click.stop>
            <div class="mb-3 flex items-center justify-between">
              <span class="text-sm font-semibold text-brand-300">📁 Prompt Templates</span>
              <button @click="showTemplates = false" class="grid h-8 w-8 place-items-center rounded-full bg-ink-700 text-cream-200 hover:text-white">✕</button>
            </div>
            <div class="grid grid-cols-2 gap-2">
              <button v-for="t in templates" :key="t.id" @click="applyTemplate(t)" class="rounded-xl border border-white/10 bg-white/5 p-3 text-left transition hover:border-brand-400 hover:bg-brand-600/10">
                <span class="block text-sm font-semibold text-cream-100">{{ t.name }}</span>
                <span class="mt-1 block text-[11px] leading-snug text-cream-300/50 line-clamp-2">{{ t.prompt }}</span>
              </button>
            </div>
            <p class="mt-3 text-[10px] text-cream-300/30">Template giúp bạn bắt đầu nhanh. Chọn 1 template để đưa vào ô prompt.</p>
          </div>
        </div>

        <!-- ── Prompt textarea (cao hơn 30%: rows 4→6) ── -->
        <textarea v-model="store.imagePromptEn" @input="scheduleEnrichPreview" rows="6" class="input !text-sm !py-3" placeholder="Nhập ý tưởng / prompt (EN hoặc VI). Mô tả trang phục, phong cách, bối cảnh, ánh sáng…"></textarea>

        <!-- Live enrich preview -->
        <div v-if="enrichPreview || enrichLoading" class="my-3 rounded-xl border border-emerald-500/30 bg-emerald-900/20 p-2.5">
          <div class="flex items-center justify-between mb-1">
            <p class="text-[11px] text-emerald-300/60">✨ Prompt sau khi enrich (gửi lên model):</p>
            <button @click="doEnrichPreview" class="rounded-full bg-ink-700 px-2 py-0.5 text-[10px] text-cream-200 hover:bg-brand-600" :disabled="enrichLoading">{{ enrichLoading ? '⏳' : '🔄 Làm mới' }}</button>
          </div>
          <p v-if="enrichPreview" class="max-h-24 overflow-y-auto text-[11px] leading-relaxed text-emerald-100 whitespace-pre-wrap">{{ enrichPreview }}</p>
          <p v-else class="text-[11px] text-brand-300/60">⏳ Đang phân tích prompt…</p>
        </div>
        <div v-if="enrichError" class="my-1 text-[11px] text-red-300/60">{{ enrichError }}</div>

        <!-- ── Sáng tạo + Biến thể: chung 1 hàng 2 cột ── -->
        <div class="my-3 grid grid-cols-2 gap-3">
          <div class="rounded-2xl border border-ink-700 bg-ink-800 px-3 py-2.5">
            <p class="mb-1 flex items-center justify-between text-xs"><span class="font-medium text-cream-200">🎚 Sáng tạo</span><span class="font-semibold text-cream-50">{{ localCreative }}/10</span></p>
            <input type="range" min="1" max="10" v-model.number="localCreative" class="w-full cursor-pointer accent-brand-500">
          </div>
          <div class="rounded-2xl border border-ink-700 bg-ink-800 px-3 py-2.5">
            <p class="mb-1 flex items-center justify-between text-xs"><span class="font-medium text-cream-200">📦 Biến thể</span><span class="font-semibold text-cream-50">{{ localVariant }}</span></p>
            <input type="range" min="1" max="4" step="1" v-model.number="localVariant" class="w-full cursor-pointer accent-brand-500">
          </div>
        </div>

        <!-- ── Ratio + Resolution ── -->
        <div class="my-3 grid grid-cols-2 gap-3">
          <select v-model="store.imageRatio" class="input !py-2.5 !text-sm"><option v-for="r in ['1:1','4:3','3:4','9:16','16:9','4:5','21:9']" :key="r" :value="r">{{ r }}</option></select>
          <select v-model="store.imageRes" class="input !py-2.5 !text-sm"><option value="1K">1K</option><option value="2K">2K</option></select>
        </div>

        <!-- ── Texture ── -->
        <div class="my-3 rounded-2xl border border-ink-700 bg-ink-800 px-3 py-2.5">
          <p class="mb-1 flex items-center justify-between text-xs"><span class="font-medium text-cream-200">🧵 Texture</span><span class="font-semibold text-brand-300">{{ textureLabel }}</span></p>
          <input type="range" min="0" max="10" step="1" v-model.number="localTexture" class="w-full cursor-pointer accent-brand-500">
        </div>

        <!-- ── Nâng cao + Templates: nút bấm rõ ràng ── -->
        <div class="my-3 flex gap-2">
          <button @click="showAdvanced = !showAdvanced" class="flex-1 rounded-xl border px-3 py-2 text-xs font-semibold transition" :class="showAdvanced ? 'border-brand-500 bg-brand-600/20 text-brand-200' : 'border-ink-600 bg-ink-800 text-cream-200 hover:border-brand-400'">
            {{ showAdvanced ? '▾' : '▸' }} Nâng cao (negative prompt)
          </button>
          <button @click="showTemplates = !showTemplates" class="flex-1 rounded-xl border px-3 py-2 text-xs font-semibold transition" :class="showTemplates ? 'border-brand-500 bg-brand-600/20 text-brand-200' : 'border-ink-600 bg-ink-800 text-cream-200 hover:border-brand-400'">
            📁 Templates
          </button>
        </div>

        <!-- Advanced: Negative prompt -->
        <div v-if="showAdvanced" class="my-3 rounded-2xl border border-ink-700 bg-ink-800 p-3">
          <label class="label text-sm">Negative prompt (điều model KHÔNG nên tạo)</label>
          <textarea v-model="store.negativePromptEn" @input="scheduleEnrichPreview" rows="2" class="input !text-sm !py-2" placeholder="blurry, low quality, distorted proportions, extra limbs, deformed hands, watermark, text, logo..."></textarea>
          <p class="mt-1 text-[10px] text-cream-300/50">Để trống để dùng negative prompt mặc định từ Cài đặt.</p>
        </div>

        <!-- ── Reset + Generate ── -->
        <div class="my-3 flex items-center justify-between">
          <button @click="resetToDefaults" class="text-xs text-cream-300/50 underline hover:text-brand-300">↺ Đặt lại mặc định</button>
        </div>
        <button @click="store.promptOpen = false; store.generateImage()" :disabled="store.generating || !store.imagePromptEn" class="btn-brand mt-1 w-full whitespace-nowrap !py-3 !text-sm">{{ store.generating ? 'Đang gửi…' : '🎨 Tạo Ảnh 2D' }}</button>
      </template>
    </BaseModal>
  </div>
</template>