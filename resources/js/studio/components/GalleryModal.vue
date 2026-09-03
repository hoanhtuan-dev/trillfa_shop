<script setup>
import { computed, ref, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { useStudioStore } from '../store.js';

const store = useStudioStore();

const items = computed(() => store.generations.filter(g => g.media_url));
const idx = computed(() => items.value.findIndex(g => g.id === store.viewer?.id));
const current = computed(() => items.value[idx.value] || store.viewer);
const isVideo = computed(() => current.value?.type === 'video');
const imgError = ref(false);

function nav(d) {
  const n = items.value[(idx.value + d + items.value.length) % items.value.length];
  if (n) { store.viewer = n; }
}
function close() { store.viewer = null; }

// Đổi ảnh (mọi cách: nav / click thumbnail / xóa) → reset zoom + hủy confirm + scroll strip theo
watch(() => current.value?.id, () => { resetZoom(); resetConfirm(); imgError.value = false; nextTick(scrollStripToActive); });

// ── Xóa an toàn: xác nhận 2 bước, tự reset sau 3.5s ──
const confirming = ref(false);
let confirmTimer = null;
function startConfirm() { confirming.value = true; clearTimeout(confirmTimer); confirmTimer = setTimeout(() => { confirming.value = false; }, 3500); }
function resetConfirm() { confirming.value = false; clearTimeout(confirmTimer); }
async function doDelete() {
  const g = current.value; if (!g) return;
  const at = idx.value; // vị trí trước khi xóa
  const ok = await store.deleteGen(g);
  resetConfirm();
  if (!ok) return; // xóa thất bại → giữ modal
  const left = items.value;
  if (!left.length) { close(); return; }
  const next = left[Math.min(Math.max(at, 0), left.length - 1)];
  store.viewer = next;
  resetZoom();
}

// ── Zoom / pan (local, không đụng zoom của canvas) ──
const viewerZoom = ref(1);
const viewerPan = ref({ x: 0, y: 0 });
let drag = null;
function resetZoom() { viewerZoom.value = 1; viewerPan.value = { x: 0, y: 0 }; }
function onWheel(e) { const f = e.deltaY > 0 ? 1 / 1.15 : 1.15; viewerZoom.value = Math.max(0.25, Math.min(4, viewerZoom.value * f)); }
function zoomIn() { viewerZoom.value = Math.min(4, viewerZoom.value * 1.25); }
function zoomOut() { viewerZoom.value = Math.max(0.25, viewerZoom.value / 1.25); }
function panStart(e) { drag = { x: e.clientX, y: e.clientY, px: viewerPan.value.x, py: viewerPan.value.y }; }
function panMove(e) { if (drag) viewerPan.value = { x: drag.px + (e.clientX - drag.x), y: drag.py + (e.clientY - drag.y) }; }
function panEnd() { drag = null; }
function toggleZoom() { viewerZoom.value = viewerZoom.value <= 1 ? 2 : 1; }

// ── Dải thumbnail: wheel + kéo để cuộn ngang ──
const stripEl = ref(null);
let stripDrag = null;
function stripWheel(e) {
  const el = e.currentTarget;
  el.scrollLeft += e.deltaY + e.deltaX;
}
function stripDown(e) {
  stripDrag = { x: e.clientX, left: e.currentTarget.scrollLeft };
  if (e.currentTarget.setPointerCapture) { try { e.currentTarget.setPointerCapture(e.pointerId); } catch (err) {} }
}
function stripMove(e) {
  if (!stripDrag) return;
  e.currentTarget.scrollLeft = stripDrag.left - (e.clientX - stripDrag.x);
}
function stripUp() { stripDrag = null; }
function scrollStripToActive() {
  const el = stripEl.value;
  if (!el) return;
  const active = el.querySelector('[data-active="true"]');
  if (active) active.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
}

// ── Copy prompt ──
function copyPrompt() {
  const p = current.value?.prompt || current.value?.image_prompt_en || '';
  if (!p) { store.toast('Không có prompt để sao chép.', 'error'); return; }
  try { navigator.clipboard.writeText(p); store.toast('Đã sao chép prompt.'); } catch (e) { store.toast('Lỗi sao chép.', 'error'); }
}

// ── Badge trạng thái ──
const statusMeta = computed(() => {
  const s = current.value?.status;
  return {
    pending:    { label: 'Đang chờ',      cls: 'border-amber-500/40 bg-amber-500/15 text-amber-200' },
    processing: { label: 'Đang xử lý',    cls: 'border-amber-500/40 bg-amber-500/15 text-amber-200' },
    completed:  { label: 'Hoàn tất',      cls: 'border-emerald-500/40 bg-emerald-500/15 text-emerald-200' },
    failed:     { label: 'Lỗi',           cls: 'border-red-500/40 bg-red-500/15 text-red-200' },
    cancelled:  { label: 'Đã hủy',        cls: 'border-ink-600 bg-ink-800 text-cream-300/70' },
  }[s] || { label: s || '—', cls: 'border-ink-600 bg-ink-800 text-cream-300/70' };
});

const fields = [
  { k: 'model', l: 'Model' }, { k: 'provider', l: 'Provider' },
  { k: 'ratio', l: 'Tỷ lệ' }, { k: 'resolution', l: 'Độ phân giải' },
  { k: 'duration', l: 'Thời lượng' }, { k: 'created_at', l: 'Ngày' },
];

// ── Keyboard: Esc đóng · ←/→ chuyển ảnh (capture để ưu tiên khi modal mở) ──
function onKey(e) {
  const t = e.target;
  if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable)) return;
  e.stopImmediatePropagation();
  if (e.key === 'Escape') {
    e.preventDefault();
    if (confirming.value) { resetConfirm(); return; } // Esc ưu tiên hủy xác nhận xóa, không đóng modal
    close();
  }
  else if (e.key === 'ArrowLeft') { e.preventDefault(); nav(-1); }
  else if (e.key === 'ArrowRight') { e.preventDefault(); nav(1); }
}
onMounted(() => {
  window.addEventListener('keydown', onKey, true);
  document.body.style.overflow = 'hidden'; // khóa scroll nền khi modal mở
  nextTick(scrollStripToActive);
});
onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKey, true);
  document.body.style.overflow = ''; // trả lại scroll nền
  clearTimeout(confirmTimer);
});
</script>

<template>
  <div class="fixed inset-0 z-[70] flex items-center justify-center bg-black/90 p-2 backdrop-blur-sm sm:p-5" @click.self="close">
    <!-- Đóng -->
    <button @click="close" class="absolute right-4 top-4 z-30 grid h-10 w-10 place-items-center rounded-full bg-ink-900/90 text-cream-200 transition hover:bg-ink-700 hover:text-white" title="Đóng (Esc)" aria-label="Đóng">✕</button>
    <!-- Chuyển ảnh -->
    <button v-if="items.length > 1" @click="nav(-1)" class="absolute left-2 top-1/2 z-30 grid h-9 w-9 -translate-y-1/2 place-items-center rounded-full bg-ink-900/90 text-xl text-cream-100 transition hover:bg-brand-600 sm:h-10 sm:w-10" title="Ảnh trước (←)" aria-label="Ảnh trước">‹</button>
    <button v-if="items.length > 1" @click="nav(1)" class="absolute right-2 top-1/2 z-30 grid h-9 w-9 -translate-y-1/2 place-items-center rounded-full bg-ink-900/90 text-xl text-cream-100 transition hover:bg-brand-600 sm:h-10 sm:w-10" title="Ảnh sau (→)" aria-label="Ảnh sau">›</button>

    <div class="flex h-full w-full max-w-7xl flex-col gap-2 lg:flex-row">
      <!-- ══ Khu vực ảnh ══ -->
      <div class="relative flex min-h-0 flex-1 items-center justify-center overflow-hidden rounded-2xl border border-ink-700/60 bg-ink-900/40">
        <!-- Video: phát trực tiếp, không zoom/pan -->
        <video v-if="isVideo && current?.media_url" :src="current.media_url" controls autoplay loop muted playsinline
               class="max-h-full max-w-full rounded-xl object-contain"></video>
        <!-- Ảnh: zoom / pan -->
        <div v-else-if="current?.media_url && !imgError" class="grid h-full w-full cursor-grab place-items-center overflow-hidden active:cursor-grabbing" style="touch-action:none"
             @wheel.prevent="onWheel"
             @pointerdown="panStart" @pointermove="panMove" @pointerup="panEnd" @pointerleave="panEnd">
          <img :src="current.media_url" class="max-h-full max-w-full select-none object-contain transition-transform duration-75"
               :style="{ transform: 'translate(' + viewerPan.x + 'px, ' + viewerPan.y + 'px) scale(' + viewerZoom + ')' }" draggable="false"
               @dblclick="toggleZoom" @error="imgError = true" />
        </div>
        <p v-else class="text-sm text-cream-300/60">{{ imgError ? 'Không tải được nội dung.' : 'Không có nội dung.' }}</p>
        <!-- Badge trạng thái -->
        <span v-if="current" class="absolute left-3 top-3 inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-semibold" :class="statusMeta.cls">
          <span v-if="['pending','processing'].includes(current.status)" class="h-2.5 w-2.5 animate-spin rounded-full border border-current border-t-transparent"></span>
          {{ statusMeta.label }}
        </span>
        <!-- Bộ đếm x / y -->
        <span v-if="items.length > 1" class="absolute right-3 top-3 rounded-full border border-ink-700 bg-ink-900/90 px-2 py-0.5 text-[10px] font-semibold text-cream-200">{{ idx + 1 }} / {{ items.length }}</span>
        <!-- Zoom toolbar (chỉ khi có ảnh) -->
        <div v-if="current?.media_url && !isVideo" class="absolute bottom-3 left-1/2 z-10 flex -translate-x-1/2 items-center gap-0.5 rounded-full border border-ink-700 bg-ink-900/95 px-1.5 py-1 shadow-lg">
          <button @click="zoomOut" class="grid h-7 w-7 place-items-center rounded-full text-cream-200 transition hover:bg-ink-700" title="Thu nhỏ" aria-label="Thu nhỏ">−</button>
          <button @click="resetZoom" class="min-w-12 rounded-full px-2 py-0.5 text-[11px] font-semibold text-cream-100 transition hover:bg-ink-700" title="Về 100%">{{ Math.round(viewerZoom * 100) }}%</button>
          <button @click="zoomIn" class="grid h-7 w-7 place-items-center rounded-full text-cream-200 transition hover:bg-ink-700" title="Phóng to" aria-label="Phóng to">+</button>
        </div>
        <!-- Dải thumbnail: cuộn ngang bằng wheel (vertical scroll → horizontal) + kéo chuột/touch -->
        <div v-if="items.length > 1" ref="stripEl"
             class="absolute bottom-14 left-1/2 z-10 flex max-w-[92%] -translate-x-1/2 items-center gap-1.5 overflow-x-auto rounded-2xl border border-ink-700 bg-ink-900/95 p-1.5 shadow-lg"
             style="scrollbar-width:none; scroll-snap-type:x proximity; cursor:grab; touch-action:pan-x"
             @wheel.prevent="stripWheel"
             @pointerdown="stripDown" @pointermove="stripMove" @pointerup="stripUp" @pointerleave="stripUp">
          <button v-for="g in items" :key="g.id" @click="store.viewer = g"
                  :data-active="current?.id === g.id ? 'true' : 'false'"
                  class="relative h-11 w-11 shrink-0 snap-start overflow-hidden rounded-lg border-2 transition"
                  :class="current?.id === g.id ? 'border-brand-500' : 'border-ink-700/60 hover:border-ink-500'">
            <img :src="g.media_url" class="h-full w-full bg-ink-900 object-cover" loading="lazy" />
          </button>
        </div>
      </div>

      <!-- ══ Panel thông tin + hành động ══ -->
      <aside class="flex max-h-[42vh] w-full shrink-0 flex-col gap-3 overflow-y-auto rounded-2xl border border-ink-700 bg-ink-900/95 p-4 lg:max-h-none lg:w-80">
        <!-- Tiêu đề -->
        <div class="flex items-center justify-between">
          <p class="text-sm font-semibold text-cream-100">Ảnh #<span class="text-brand-300">{{ current?.id }}</span></p>
          <button @click="close" class="grid h-8 w-8 place-items-center rounded-full bg-ink-800 text-cream-300 transition hover:bg-ink-700 hover:text-white lg:hidden" title="Đóng" aria-label="Đóng">✕</button>
        </div>

        <!-- Thông tin nhanh -->
        <div class="grid grid-cols-2 gap-1.5">
          <div v-for="f in fields" :key="f.k" class="rounded-xl bg-ink-800/70 px-2.5 py-1.5">
            <p class="text-[9px] uppercase tracking-wide text-cream-300/50">{{ f.l }}</p>
            <p class="truncate text-xs font-medium text-cream-100">{{ current?.[f.k] ?? '—' }}</p>
          </div>
        </div>

        <!-- Prompt + copy -->
        <div class="rounded-xl border border-ink-700/60 bg-ink-800/70 p-2.5">
          <div class="mb-1 flex items-center justify-between">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-cream-300/50">Prompt</p>
            <button @click="copyPrompt" class="rounded-full bg-ink-700 px-2 py-0.5 text-[10px] font-semibold text-cream-200 transition hover:bg-brand-600 hover:text-white" title="Sao chép prompt">📋 Sao chép</button>
          </div>
          <p class="max-h-24 overflow-y-auto whitespace-pre-wrap text-[11px] leading-relaxed text-cream-100">{{ current?.prompt || '—' }}</p>
        </div>

        <!-- Nhóm secondary: Tải xuống · Tạo video -->
        <div class="grid grid-cols-2 gap-1.5">
          <a :href="current ? '/studio/generations/' + current.id + '/download' : '#'" class="btn-outline btn-sm w-full !py-2">⬇ Tải xuống</a>
          <button v-if="!isVideo" @click="store.goVideo(current)" class="btn-outline btn-sm w-full !py-2">🎬 Tạo video</button>
        </div>
        <!-- Nhóm primary: Chỉnh sửa (hành động chính) -->
        <button v-if="!isVideo" @click="store.goEdit(current)" class="btn-brand btn-sm w-full !py-2.5">✏️ Chỉnh sửa → Fitting Room</button>

        <!-- ══ Vùng nguy hiểm (tách biệt, xác nhận 2 bước) ══ -->
        <div class="mt-1 border-t border-ink-700/70 pt-3">
          <template v-if="!confirming">
            <button @click="startConfirm" class="w-full rounded-xl border border-red-500/40 bg-transparent py-2 text-xs font-semibold text-red-300 transition hover:bg-red-600/10">
              🗑 Xóa ảnh
            </button>
          </template>
          <template v-else>
            <p class="mb-1.5 text-center text-[11px] font-medium text-red-200">⚠ Xóa vĩnh viễn? Hành động này không thể hoàn tác.</p>
            <div class="flex gap-1.5">
              <button @click="resetConfirm" class="flex-1 rounded-xl border border-ink-600 bg-ink-800 py-2 text-xs font-semibold text-cream-200 transition hover:bg-ink-700">Hủy</button>
              <button @click="doDelete" class="flex-1 rounded-xl bg-red-600 py-2 text-xs font-semibold text-white transition hover:bg-red-500">🗑 Xóa vĩnh viễn</button>
            </div>
          </template>
          <p class="mt-1.5 text-center text-[10px] text-cream-300/40">Nhấn Esc để đóng · dùng ← → để xem ảnh khác</p>
        </div>
      </aside>
    </div>
  </div>
</template>

