<script setup>
import { computed, ref, watch, onMounted, onBeforeUnmount } from 'vue';
import { useStudioStore } from '../store.js';
import BaseModal from './BaseModal.vue';
const store = useStudioStore();
const showAdvanced = ref(false);
const promptLoading = ref(false);
const showHistory = ref(false);
const showTemplates = ref(false);
const showPresets = ref(false);
const presets = ref([]);
const presetsLoading = ref(false);
const presetTypes = ref([]);
const presetType = ref('');
const showEnrich = ref(false);
const history = ref([]);
const historyLoading = ref(false);
const enrichPreview = ref('');
const enrichLoading = ref(false);
const enrichError = ref('');

// ── 7. Character counter ──
const MAX_CHARS = 4000;
const charCount = computed(() => (store.imagePromptEn || '').length);
const charWarning = computed(() => charCount.value > MAX_CHARS * 0.85);
const charDanger = computed(() => charCount.value >= MAX_CHARS);

// ── 8. Undo/Redo ──
const undoStack = ref([]);
const redoStack = ref([]);
const MAX_UNDO = 30;
let ignoreNextInput = false;
function pushUndo(text) {
  if (ignoreNextInput) { ignoreNextInput = false; return; }
  const last = undoStack.value[undoStack.value.length - 1];
  if (last === text) return; // no change
  undoStack.value.push(text);
  if (undoStack.value.length > MAX_UNDO) undoStack.value.shift();
  redoStack.value = []; // clear redo on new input
}
function undo() {
  if (undoStack.value.length < 2) return;
  const current = undoStack.value.pop();
  redoStack.value.push(current);
  const prev = undoStack.value[undoStack.value.length - 1];
  ignoreNextInput = true;
  store.imagePromptEn = prev;
}
function redo() {
  if (!redoStack.value.length) return;
  const next = redoStack.value.pop();
  undoStack.value.push(next);
  ignoreNextInput = true;
  store.imagePromptEn = next;
}
function onPromptKeydown(e) {
  if ((e.ctrlKey || e.metaKey) && e.key === 'z' && !e.shiftKey) { e.preventDefault(); undo(); }
  if ((e.ctrlKey || e.metaKey) && (e.key === 'y' || (e.key === 'z' && e.shiftKey))) { e.preventDefault(); redo(); }
}
// Watch for text changes → push to undo stack (debounced)
let undoPushTimer = null;
watch(() => store.imagePromptEn, (val) => {
  if (undoPushTimer) clearTimeout(undoPushTimer);
  undoPushTimer = setTimeout(() => pushUndo(val || ''), 500);
});

// ── 12. Auto-save draft ──
const DRAFT_KEY = 'trillfa.prompt-draft';
function saveDraft() {
  try {
    const draft = {
      prompt: store.imagePromptEn || '',
      creativeLevel: store.creativeLevel,
      texture: store.texture,
      variantCount: store.variantCount,
      imageRatio: store.imageRatio,
      imageRes: store.imageRes,
      negativePrompt: store.negativePromptEn || '',
      timestamp: Date.now()
    };
    localStorage.setItem(DRAFT_KEY, JSON.stringify(draft));
  } catch (e) {}
}
function loadDraft() {
  try {
    const raw = localStorage.getItem(DRAFT_KEY);
    if (!raw) return false;
    const draft = JSON.parse(raw);
    if (!draft.prompt) return false;
    store.imagePromptEn = draft.prompt;
    if (draft.creativeLevel != null) { store.creativeLevel = draft.creativeLevel; localCreative.value = draft.creativeLevel; }
    if (draft.texture != null) { store.texture = draft.texture; localTexture.value = draft.texture; }
    if (draft.variantCount != null) { store.variantCount = draft.variantCount; localVariant.value = draft.variantCount; }
    if (draft.imageRatio) store.imageRatio = draft.imageRatio;
    if (draft.imageRes) store.imageRes = draft.imageRes;
    if (draft.negativePrompt) store.negativePromptEn = draft.negativePrompt;
    return true;
  } catch (e) { return false; }
}
function clearDraft() {
  try { localStorage.removeItem(DRAFT_KEY); } catch (e) {}
}
// Auto-save on changes (debounced)
let draftSaveTimer = null;
watch(() => [store.imagePromptEn, store.creativeLevel, store.texture, store.variantCount, store.imageRatio, store.imageRes, store.negativePromptEn], () => {
  if (draftSaveTimer) clearTimeout(draftSaveTimer);
  draftSaveTimer = setTimeout(saveDraft, 1000);
}, { deep: true });

// Save draft on generate
const origGenerate = store.generateImage.bind(store);
store.generateImage = function() {
  clearDraft();
  return origGenerate();
};

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

// ── Draft state ──
const showDraftNotice = ref(false);
const draftTime = ref('');

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
  
  // Check for saved draft
  try {
    const raw = localStorage.getItem(DRAFT_KEY);
    if (raw) {
      const draft = JSON.parse(raw);
      if (draft.prompt) {
        showDraftNotice.value = true;
        const d = new Date(draft.timestamp);
        draftTime.value = d.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }) + ' ' + d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit' });
      }
    }
  } catch (e) {}
  
  store.promptOpen = true;
}

function restoreDraft() {
  loadDraft();
  showDraftNotice.value = false;
  undoStack.value = [store.imagePromptEn || ''];
  redoStack.value = [];
  store.toast('Đã khôi phục bản nháp.');
}

function dismissDraft() {
  showDraftNotice.value = false;
  clearDraft();
}

function resetToDefaults() {
  store.applyDefaults();
  localCreative.value = store.creativeLevel;
  localTexture.value = store.texture;
  localVariant.value = store.variantCount;
  clearDraft();
  undoStack.value = [];
  redoStack.value = [];
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

// ── Preset (danh sách loại trang phục từ Trợ lý thiết kế) ──
async function loadPresets() {
  presetsLoading.value = true;
  try {
    const [tRes, pRes] = await Promise.all([
      fetch('/studio/stylist/types', { headers: { Accept: 'application/json' } }),
      fetch('/studio/stylist/presets', { headers: { Accept: 'application/json' } }),
    ]);
    const tD = await tRes.json();
    const pD = await pRes.json();
    presetTypes.value = tD.types || tD.items || [];
    presets.value = pD.presets || pD.items || [];
  } catch (e) { presets.value = []; presetTypes.value = []; }
  finally { presetsLoading.value = false; }
}
const filteredPresets = computed(() => {
  if (!presetType.value) return presets.value;
  return presets.value.filter((p) => p.type === presetType.value);
});
function applyPreset(p) {
  if (p.prompt) store.imagePromptEn = p.prompt;
  showPresets.value = false;
  store.toast('Đã áp dụng preset: ' + (p.name || p.id));
}

// ── Live Enrich Preview ──
let enrichDebounce = null;
let enrichAbort = null;

// Open the enrich preview popup — triggers immediately
async function openEnrichPreview() {
  if (!store.imagePromptEn?.trim()) {
    store.toast('Nhập prompt trước khi preview enrich.', 'error');
    return;
  }
  showEnrich.value = true;
  // Immediately fetch enrich preview
  await doEnrichPreview();
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
          <button @click="showHistory = !showHistory; if (showHistory) { loadHistory(); showTemplates = false; showPresets = false }" class="rounded-lg border px-3 py-1.5 text-xs font-semibold transition" :class="showHistory ? 'border-brand-500 bg-brand-600 text-white' : 'border-ink-600 bg-ink-800 text-cream-200 hover:border-brand-400'">📋 Lịch sử</button>
          <button @click="showTemplates = !showTemplates; if (showTemplates) { showHistory = false; showPresets = false }" class="rounded-lg border px-3 py-1.5 text-xs font-semibold transition" :class="showTemplates ? 'border-brand-500 bg-brand-600 text-white' : 'border-ink-600 bg-ink-800 text-cream-200 hover:border-brand-400'">📁 Templates</button>
          <button @click="showPresets = !showPresets; if (showPresets) { showHistory = false; showTemplates = false; loadPresets() }" class="rounded-lg border px-3 py-1.5 text-xs font-semibold transition" :class="showPresets ? 'border-brand-500 bg-brand-600 text-white' : 'border-ink-600 bg-ink-800 text-cream-200 hover:border-brand-400'">✨ Preset</button>
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

        <!-- Preset popup (danh sách prompt đã lưu từ Trợ lý thiết kế) -->
        <div v-if="showPresets" class="fixed inset-0 z-[80] flex items-center justify-center bg-black/70 p-4" @click.self="showPresets = false">
          <div class="max-h-[80vh] w-full max-w-lg overflow-y-auto rounded-3xl border border-brand-500/40 bg-ink-900 p-5 shadow-2xl" @click.stop>
            <div class="mb-3 flex items-center justify-between">
              <span class="text-sm font-semibold text-brand-300">✨ Preset — Prompt đã lưu</span>
              <button @click="showPresets = false" class="grid h-8 w-8 place-items-center rounded-full bg-ink-700 text-cream-200 hover:text-white">✕</button>
            </div>
            <!-- Lọc theo loại trang phục -->
            <div class="mb-3 flex flex-wrap gap-1.5">
              <button @click="presetType = ''" :class="presetType === '' ? 'bg-brand-600 text-white' : 'bg-ink-700 text-cream-200 hover:bg-ink-600'" class="rounded-full px-3 py-1 text-xs font-semibold transition">Tất cả</button>
              <button v-for="t in presetTypes" :key="t.id" @click="presetType = t.id" :class="presetType === t.id ? 'bg-brand-600 text-white' : 'bg-ink-700 text-cream-200 hover:bg-ink-600'" class="rounded-full px-3 py-1 text-xs font-semibold transition">{{ t.emoji }} {{ t.name }}</button>
            </div>
            <p v-if="presetsLoading" class="py-6 text-center text-xs text-cream-300/60">⏳ Đang tải preset…</p>
            <div v-else-if="!filteredPresets.length" class="py-6 text-center text-xs text-cream-300/50">Chưa có preset cho lựa chọn này — dùng ✨ Trợ lý thiết kế để tạo prompt, nó sẽ tự lưu tại đây.</div>
            <div v-else class="space-y-2">
              <button v-for="p in filteredPresets" :key="p.id" @click="applyPreset(p)" class="w-full rounded-xl border border-white/10 bg-white/5 p-3 text-left transition hover:border-brand-400 hover:bg-brand-600/10">
                <span class="block text-sm font-semibold text-cream-100">{{ p.name }}</span>
                <span class="mt-1 block text-[11px] leading-snug text-cream-300/50 line-clamp-2">{{ p.prompt }}</span>
              </button>
            </div>
            <p class="mt-3 text-[10px] text-cream-300/40">Prompt tạo bằng ✨ Trợ lý thiết kế được lưu tự động theo loại trang phục; chọn 1 preset để điền vào ô prompt.</p>
          </div>
        </div>

        <!-- ── Prompt textarea + char counter + undo/redo ── -->
        <div class="relative">
          <textarea v-model="store.imagePromptEn" @keydown="onPromptKeydown" rows="6" class="input !text-sm !py-3 !pr-16" placeholder="Nhập ý tưởng / prompt (EN hoặc VI). Mô tả trang phục, phong cách, bối cảnh, ánh sáng…"></textarea>
          <!-- Char counter + undo/redo -->
          <div class="absolute bottom-2 right-2 flex items-center gap-1">
            <button @click="undo" :disabled="undoStack.length < 2" class="grid h-6 w-6 place-items-center rounded bg-ink-700 text-[10px] text-cream-200 hover:bg-ink-600 disabled:opacity-30" title="Undo (Ctrl+Z)">↩</button>
            <button @click="redo" :disabled="!redoStack.length" class="grid h-6 w-6 place-items-center rounded bg-ink-700 text-[10px] text-cream-200 hover:bg-ink-600 disabled:opacity-30" title="Redo (Ctrl+Y)">↪</button>
            <span class="text-[10px] font-semibold" :class="charDanger ? 'text-red-400' : charWarning ? 'text-amber-400' : 'text-cream-300/50'">{{ charCount }}/{{ MAX_CHARS }}</span>
          </div>
        </div>

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

        <!-- ── Nâng cao + Preview Enrich: nút bấm rõ ràng ── -->
        <div class="my-3 flex gap-2">
          <button @click="showAdvanced = !showAdvanced" class="flex-1 rounded-xl border px-3 py-2 text-xs font-semibold transition" :class="showAdvanced ? 'border-brand-500 bg-brand-600/20 text-brand-200' : 'border-ink-600 bg-ink-800 text-cream-200 hover:border-brand-400'">
            {{ showAdvanced ? '▾' : '▸' }} Nâng cao (negative prompt)
          </button>
          <button @click="openEnrichPreview" class="flex-1 rounded-xl border px-3 py-2 text-xs font-semibold transition" :class="showEnrich ? 'border-brand-500 bg-brand-600/20 text-brand-200' : 'border-ink-600 bg-ink-800 text-cream-200 hover:border-brand-400'">
            ✨ Preview Enrich
          </button>
        </div>

        <!-- Advanced: Negative prompt -->
        <div v-if="showAdvanced" class="my-3 rounded-2xl border border-ink-700 bg-ink-800 p-3">
          <label class="label text-sm">Negative prompt (điều model KHÔNG nên tạo)</label>
          <textarea v-model="store.negativePromptEn" rows="2" class="input !text-sm !py-2" placeholder="blurry, low quality, distorted proportions, extra limbs, deformed hands, watermark, text, logo..."></textarea>
          <p class="mt-1 text-[10px] text-cream-300/50">Để trống để dùng negative prompt mặc định từ Cài đặt.</p>
        </div>

        <!-- Enrich Preview popup -->
        <div v-if="showEnrich" class="fixed inset-0 z-[80] flex items-center justify-center bg-black/70 p-4" @click.self="showEnrich = false">
          <div class="max-h-[85vh] w-full max-w-2xl overflow-y-auto rounded-3xl border border-emerald-500/40 bg-ink-900 p-5 shadow-2xl" @click.stop>
            <div class="mb-3 flex items-center justify-between">
              <span class="text-sm font-semibold text-emerald-300">✨ Prompt Enrich Preview</span>
              <div class="flex items-center gap-2">
                <button @click="doEnrichPreview" :disabled="enrichLoading" class="rounded-full bg-ink-700 px-3 py-1 text-[10px] text-cream-200 hover:bg-brand-600">{{ enrichLoading ? '⏳ Đang xử lý…' : '🔄 Làm mới' }}</button>
                <button @click="showEnrich = false" class="grid h-8 w-8 place-items-center rounded-full bg-ink-700 text-cream-200 hover:text-white">✕</button>
              </div>
            </div>
            <!-- Original prompt -->
            <div class="mb-3 rounded-xl border border-ink-600 bg-ink-800 p-3">
              <p class="mb-1 text-[10px] text-cream-300/40">📝 Prompt gốc:</p>
              <p class="text-xs leading-relaxed text-cream-200 whitespace-pre-wrap">{{ store.imagePromptEn || '(chưa nhập prompt)' }}</p>
            </div>
            <!-- Enriched prompt -->
            <div class="rounded-xl border border-emerald-500/30 bg-emerald-900/20 p-3">
              <p class="mb-1 text-[10px] text-emerald-300/60">✨ Prompt sau khi enrich (gửi lên model):</p>
              <div v-if="enrichLoading" class="py-4 text-center text-xs text-brand-300/60">⏳ Đang xử lý…</div>
              <div v-else-if="enrichError" class="text-xs text-red-300/60">{{ enrichError }}</div>
              <p v-else-if="enrichPreview" class="max-h-60 overflow-y-auto text-xs leading-relaxed text-emerald-100 whitespace-pre-wrap">{{ enrichPreview }}</p>
              <p v-else class="text-xs text-cream-300/40">Bấm "🔄 Làm mới" để xem prompt đã enrich.</p>
            </div>
            <!-- Settings summary -->
            <div class="mt-3 grid grid-cols-4 gap-2 text-[10px] text-cream-300/40">
              <span>Sáng tạo: {{ store.creativeLevel }}/10</span>
              <span>Texture: {{ textureLabel }}</span>
              <span>Ratio: {{ store.imageRatio }}</span>
              <span>Res: {{ store.imageRes }}</span>
            </div>
          </div>
        </div>

        <!-- ── Draft restore notice ── -->
        <div v-if="showDraftNotice" class="my-3 rounded-xl border border-amber-500/30 bg-amber-900/20 p-2.5">
          <div class="flex items-center justify-between">
            <p class="text-[11px] text-amber-300/80">📝 Có bản nháp chưa gửi từ lúc {{ draftTime }}</p>
            <div class="flex gap-1.5">
              <button @click="restoreDraft" class="rounded-lg bg-amber-600 px-2 py-1 text-[10px] font-semibold text-white hover:bg-amber-500">Khôi phục</button>
              <button @click="dismissDraft" class="rounded-lg bg-ink-700 px-2 py-1 text-[10px] text-cream-200 hover:bg-red-600">Bỏ qua</button>
            </div>
          </div>
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