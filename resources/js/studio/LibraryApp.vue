<script setup>
import { ref, computed, onMounted } from 'vue';
import { useStudioStore } from './store.js';
import { thumbUrl, onThumbError } from './composables/useStudioThumb.js';
import GalleryModal from './components/GalleryModal.vue';

const store = useStudioStore();

const types = [
  { value: '', label: 'Ảnh + Video' },
  { value: 'image', label: 'Ảnh' },
  { value: 'video', label: 'Video' },
];

const statuses = [
  { value: '', label: 'Tất cả trạng thái' },
  { value: 'completed', label: 'Hoàn tất' },
  { value: 'pending', label: 'Đang chờ' },
  { value: 'processing', label: 'Đang xử lý' },
  { value: 'failed', label: 'Lỗi' },
  { value: 'cancelled', label: 'Đã hủy' },
];

const selectedCount = computed(() => store.librarySelection.length);
const stats = computed(() => store.libraryStats || {});
const allSelected = computed(() => store.libraryItems.length > 0 && store.librarySelection.length === store.libraryItems.length);
const selectedBytes = computed(() => {
  const ids = new Set(store.librarySelection);
  let bytes = 0;
  for (const g of store.libraryItems) if (ids.has(g.id) && g.media_url) bytes += (Number(g._size) || 0);
  return bytes;
});

// ── Files đã tải lên ──
const uploadStats = computed(() => store.uploadStats || {});
const uploadSelectedCount = computed(() => store.uploadSelection.length);
const uploadUnusedCount = computed(() => store.uploadItems.filter(f => !f.used).length);
function isUploadSelected(rel) { return store.uploadSelection.includes(rel); }

function statusLabel(s) { const f = statuses.find(x => x.value === s); return f ? f.label : (s || '—'); }
function fmtBytes(n) { return store.formatBytes(n); }
function fmtNum(n) { const v = Number(n) || 0; return v >= 1000 ? v.toLocaleString('vi-VN') : String(v); }

function toggleAll() { allSelected.value ? store.librarySelectNone() : store.librarySelectAll(); }
function isSelected(id) { return store.librarySelection.includes(id); }

// ── Xác nhận 2 bước cho các hành động nguy hiểm ──
const confirmAction = ref(''); // '' | 'bulk' | 'junk' | 'old' | 'orphans' | 'ubulk' | 'uclean'
let confirmTimer = null;
function ask(action) {
  if (!action) return;
  if (action === 'bulk' && !selectedCount.value) { store.toast('Chưa chọn ảnh nào để xóa.', 'error'); return; }
  if (action === 'ubulk' && !uploadSelectedCount.value) { store.toast('Chưa chọn file nào để xóa.', 'error'); return; }
  confirmAction.value = action;
  clearTimeout(confirmTimer);
  confirmTimer = setTimeout(() => { confirmAction.value = ''; }, 5000);
}
function cancelConfirm() { confirmAction.value = ''; clearTimeout(confirmTimer); }
async function runConfirm() {
  const a = confirmAction.value;
  confirmAction.value = '';
  clearTimeout(confirmTimer);
  if (a === 'bulk') await store.libraryBulkDelete();
  else if (a === 'junk') await store.libraryCleanup('junk');
  else if (a === 'old') await store.libraryCleanup('old');
  else if (a === 'orphans') await store.libraryCleanup('orphans');
  else if (a === 'ubulk') await store.uploadBulkDelete();
  else if (a === 'uclean') await store.uploadCleanup();
}

function onChangeType(e) { store.setLibraryFilter('type', e.target.value); }
function onChangeStatus(e) { store.setLibraryFilter('status', e.target.value); }
function onChangeOldDays() { store.refreshLibraryScan(); }

let searchTimer = null;
function onSearchInput(e) {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => store.setLibraryFilter('q', e.target.value || ''), 350);
}

function openViewer(g) { store.viewer = g; }

function switchTab(tab) {
  store.libraryTab = tab;
  if (tab === 'uploads' && !store.uploadItems.length) store.loadUploads();
}
function refresh() {
  if (store.libraryTab === 'uploads') store.loadUploads();
  else { store.loadLibrary(true); store.refreshLibraryScan(); }
}

onMounted(async () => {
  store.libraryManage = false;
  await store.loadLibrary(true);
  await store.refreshLibraryScan();
  store.loadUploads();
});
</script>

<template>
  <div class="studio-dark min-h-screen bg-ink-900 p-4 text-cream-100 sm:p-6">
    <div class="mx-auto max-w-7xl">
      <!-- ══ Header ══ -->
      <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <h1 class="font-display text-xl font-semibold sm:text-2xl">🖼 Thư viện <span class="text-cream-300/50">({{ fmtNum(store.libraryTab === 'uploads' ? (uploadStats.total ?? store.uploadItems.length) : store.libraryTotal) }})</span></h1>
          <a href="/studio" class="rounded-xl bg-ink-800 px-3 py-1.5 text-xs font-semibold text-cream-200 hover:bg-ink-700">← Về Studio</a>
        </div>
        <div class="flex items-center gap-2">
          <button @click="refresh" :disabled="store.libraryLoading || store.uploadLoading"
                  class="rounded-xl bg-ink-800 px-3 py-1.5 text-xs font-semibold text-cream-200 hover:bg-ink-700 disabled:opacity-50">
            ⟳ Làm mới
          </button>
          <button @click="store.libraryManage = !store.libraryManage"
                  class="rounded-xl px-3 py-1.5 text-xs font-semibold transition"
                  :class="store.libraryManage ? 'bg-brand-600 text-white hover:bg-brand-500' : 'bg-ink-800 text-cream-200 hover:bg-ink-700'">
            {{ store.libraryManage ? '✓ Đang quản lý' : '🗂 Quản lý / Dọn dẹp' }}
          </button>
        </div>
      </div>

      <!-- ══ Tab: Ảnh đã tạo / File tải lên ══ -->
      <div class="mb-4 flex gap-1 rounded-2xl border border-ink-700 bg-ink-800 p-1">
        <button @click="switchTab('generations')"
                :class="store.libraryTab === 'generations' ? 'bg-brand-600 text-white shadow' : 'text-cream-200 hover:bg-ink-700'"
                class="flex-1 rounded-xl px-3 py-2 text-sm font-semibold transition">
          🖼 Ảnh đã tạo
        </button>
        <button @click="switchTab('uploads')"
                :class="store.libraryTab === 'uploads' ? 'bg-brand-600 text-white shadow' : 'text-cream-200 hover:bg-ink-700'"
                class="flex-1 rounded-xl px-3 py-2 text-sm font-semibold transition">
          📁 File tải lên <span v-if="uploadStats.unused_count" class="ml-1 rounded-full bg-red-500/30 px-1.5 py-0.5 text-[10px] text-red-100">{{ uploadStats.unused_count }}</span>
        </button>
      </div>

      <template v-if="store.libraryTab === 'generations'">
      <!-- ══ Thống kê ══ -->
      <div class="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
        <div class="rounded-2xl border border-ink-700 bg-ink-800 p-3">
          <p class="text-[10px] uppercase tracking-wide text-cream-300/50">Tổng mục</p>
          <p class="text-lg font-semibold text-cream-100">{{ fmtNum(stats.total ?? store.libraryTotal) }}</p>
        </div>
        <div class="rounded-2xl border border-ink-700 bg-ink-800 p-3">
          <p class="text-[10px] uppercase tracking-wide text-cream-300/50">Hoàn tất</p>
          <p class="text-lg font-semibold text-emerald-300">{{ fmtNum(stats.completed ?? 0) }}</p>
        </div>
        <div class="rounded-2xl border border-ink-700 bg-ink-800 p-3">
          <p class="text-[10px] uppercase tracking-wide text-cream-300/50">Ảnh rác</p>
          <p class="text-lg font-semibold text-amber-300">{{ fmtNum(stats.junk_count ?? 0) }}</p>
          <p v-if="stats.junk_bytes" class="text-[10px] text-cream-300/50">{{ fmtBytes(stats.junk_bytes) }}</p>
        </div>
        <div class="rounded-2xl border border-ink-700 bg-ink-800 p-3">
          <p class="text-[10px] uppercase tracking-wide text-cream-300/50">Ảnh cũ</p>
          <p class="text-lg font-semibold text-sky-300">{{ fmtNum(stats.old_count ?? 0) }}</p>
          <p v-if="stats.old_bytes" class="text-[10px] text-cream-300/50">{{ fmtBytes(stats.old_bytes) }}</p>
        </div>
        <div class="rounded-2xl border border-ink-700 bg-ink-800 p-3">
          <p class="text-[10px] uppercase tracking-wide text-cream-300/50">File mồ côi</p>
          <p class="text-lg font-semibold text-red-300">{{ fmtNum(stats.orphan_count ?? 0) }}</p>
          <p v-if="stats.orphan_bytes" class="text-[10px] text-cream-300/50">{{ fmtBytes(stats.orphan_bytes) }}</p>
        </div>
        <div class="rounded-2xl border border-ink-700 bg-ink-800 p-3">
          <p class="text-[10px] uppercase tracking-wide text-cream-300/50">Ngưỡng ảnh cũ</p>
          <div class="mt-1 flex items-center gap-1">
            <input v-model.number="store.libraryFilters.old_days" type="number" min="1" max="365" @change="onChangeOldDays"
                   class="w-16 rounded-lg border border-ink-700 bg-ink-900 px-2 py-1 text-sm text-cream-100 focus:border-brand-400 focus:outline-none" />
            <span class="text-xs text-cream-300/60">ngày</span>
          </div>
        </div>
      </div>

      <!-- ══ Bộ lọc ══ -->
      <div class="mb-4 flex flex-wrap items-end gap-2 rounded-2xl border border-ink-700 bg-ink-800 p-3">
        <div class="w-36">
          <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-cream-300">Loại</label>
          <select @change="onChangeType" class="w-full rounded-lg border border-ink-700 bg-ink-900 px-2 py-1.5 text-sm text-cream-100 focus:border-brand-400 focus:outline-none">
            <option v-for="t in types" :key="t.value" :value="t.value" :selected="store.libraryFilters.type === t.value">{{ t.label }}</option>
          </select>
        </div>
        <div class="w-44">
          <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-cream-300">Trạng thái</label>
          <select @change="onChangeStatus" class="w-full rounded-lg border border-ink-700 bg-ink-900 px-2 py-1.5 text-sm text-cream-100 focus:border-brand-400 focus:outline-none">
            <option v-for="s in statuses" :key="s.value" :value="s.value" :selected="store.libraryFilters.status === s.value">{{ s.label }}</option>
          </select>
        </div>
        <div class="min-w-[200px] flex-1">
          <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-cream-300">Tìm prompt / model</label>
          <input :value="store.libraryFilters.q" @input="onSearchInput" type="text" placeholder="Nhập từ khóa…"
                 class="w-full rounded-lg border border-ink-700 bg-ink-900 px-3 py-1.5 text-sm text-cream-100 placeholder:text-cream-300/40 focus:border-brand-400 focus:outline-none" />
        </div>
      </div>

      <!-- ══ Thanh quản lý ══ -->
      <div v-if="store.libraryManage" class="mb-4 space-y-2 rounded-2xl border border-brand-600/40 bg-brand-900/30 p-3">
        <div class="flex flex-wrap items-center gap-2">
          <span class="text-sm font-semibold text-cream-100">Đã chọn <span class="text-brand-300">{{ selectedCount }}</span> mục</span>
          <button @click="toggleAll" class="rounded-lg border border-ink-700 bg-ink-800 px-2.5 py-1 text-xs text-cream-200 hover:bg-ink-700">{{ allSelected ? 'Bỏ chọn tất cả' : 'Chọn tất cả' }}</button>
          <button @click="store.librarySelectJunk" class="rounded-lg border border-amber-500/40 bg-amber-500/10 px-2.5 py-1 text-xs text-amber-200 hover:bg-amber-500/20">Chọn ảnh rác</button>
          <button @click="store.librarySelectOld" class="rounded-lg border border-sky-500/40 bg-sky-500/10 px-2.5 py-1 text-xs text-sky-200 hover:bg-sky-500/20">Chọn ảnh cũ ({{ store.libraryFilters.old_days }} ngày)</button>
        </div>

        <div class="flex flex-wrap items-center gap-2 border-t border-ink-700/60 pt-2">
          <template v-if="confirmAction === ''">
            <button @click="ask('bulk')" :disabled="!selectedCount" class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-500 disabled:opacity-40">🗑 Xóa đã chọn ({{ selectedCount }})</button>
            <span class="text-cream-300/40">·</span>
            <button @click="ask('junk')" class="rounded-lg border border-amber-500/40 px-3 py-1.5 text-xs text-amber-200 hover:bg-amber-500/15" :disabled="store.libraryCleaning">🧹 Dọn ảnh rác ({{ fmtNum(stats.junk_count ?? 0) }})</button>
            <button @click="ask('old')" class="rounded-lg border border-sky-500/40 px-3 py-1.5 text-xs text-sky-200 hover:bg-sky-500/15" :disabled="store.libraryCleaning">⏳ Dọn ảnh cũ ({{ fmtNum(stats.old_count ?? 0) }})</button>
            <button @click="ask('orphans')" class="rounded-lg border border-red-500/40 px-3 py-1.5 text-xs text-red-300 hover:bg-red-500/15" :disabled="store.libraryCleaning">🧾 Dọn file mồ côi ({{ fmtNum(stats.orphan_count ?? 0) }})</button>
          </template>
          <template v-else>
            <p class="text-xs font-semibold text-red-200">
              ⚠
              <span v-if="confirmAction === 'bulk'">Xóa vĩnh viễn {{ selectedCount }} ảnh đã chọn?</span>
              <span v-else-if="confirmAction === 'junk'">Xóa toàn bộ ảnh rác (lỗi / đã hủy)?</span>
              <span v-else-if="confirmAction === 'old'">Xóa ảnh cũ hơn {{ store.libraryFilters.old_days }} ngày?</span>
              <span v-else>Xóa các file không còn được tham chiếu?</span>
            </p>
            <div class="flex gap-2">
              <button @click="cancelConfirm" class="rounded-lg border border-ink-600 bg-ink-800 px-3 py-1.5 text-xs text-cream-200 hover:bg-ink-700">Hủy</button>
              <button @click="runConfirm" class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-500">Xác nhận xóa</button>
            </div>
          </template>
        </div>
      </div>

      <!-- ══ Lưới ảnh ══ -->
      <div v-if="store.libraryLoading && !store.libraryItems.length" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
        <div v-for="i in 10" :key="i" class="aspect-[3/4] animate-pulse rounded-2xl border-2 border-ink-700 bg-ink-800"></div>
      </div>

      <div v-else-if="!store.libraryItems.length" class="rounded-2xl border border-ink-700 bg-ink-800 py-16 text-center">
        <p class="text-sm text-cream-300/50">Chưa có ảnh / video nào khớp bộ lọc.</p>
        <a href="/studio" class="mt-2 inline-block text-xs text-brand-300 hover:text-brand-200">Tạo ảnh mới trong Studio →</a>
      </div>

      <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
        <div v-for="g in store.libraryItems" :key="g.id"
             class="group relative overflow-hidden rounded-2xl border-2 transition"
             :class="isSelected(g.id) ? 'border-brand-400' : 'border-ink-700'">
          <div class="relative cursor-pointer" @click="openViewer(g)">
            <img v-if="g.media_url" :src="thumbUrl(g.media_url)" class="aspect-[3/4] w-full bg-ink-900 object-cover" loading="lazy" @error="onThumbError($event, g.media_url)">
            <div v-else class="grid aspect-[3/4] w-full place-items-center bg-ink-800 text-xs text-cream-300/60">
              {{ g.status === 'failed' ? 'Lỗi' : (g.status === 'cancelled' ? 'Đã hủy' : (g.type === 'video' ? '▶ Video' : 'Không có ảnh')) }}
            </div>

            <!-- Badge trạng thái -->
            <span class="absolute left-2 top-2 rounded-full border px-2 py-0.5 text-[9px] font-semibold uppercase tracking-wide"
                  :class="{
                    'border-emerald-500/40 bg-emerald-500/15 text-emerald-200': g.status === 'completed',
                    'border-red-500/40 bg-red-500/15 text-red-200': g.status === 'failed',
                    'border-ink-600 bg-ink-800 text-cream-300/70': g.status === 'cancelled',
                    'border-amber-500/40 bg-amber-500/15 text-amber-200': ['pending','processing'].includes(g.status),
                  }">
              {{ statusLabel(g.status) }}
            </span>
            <span v-if="g.type === 'video'" class="absolute right-2 top-2 grid h-7 w-7 place-items-center rounded-full bg-ink-900/80 text-white">▶</span>

            <!-- Overlay hover -->
            <div class="absolute inset-0 bg-black/40 opacity-0 transition group-hover:opacity-100"></div>
            <div class="absolute inset-x-0 bottom-0 p-2 text-[10px] text-cream-100 opacity-0 transition group-hover:opacity-100">
              {{ store.genName(g) }} <span v-if="g.created_at" class="text-cream-300/70">· {{ g.created_at }}</span>
            </div>
          </div>

          <!-- Checkbox chọn (luôn hiện ở chế độ quản lý) -->
          <button v-if="store.libraryManage" @click.stop="store.toggleLibrarySelect(g.id)"
                  class="absolute right-2 bottom-2 grid h-7 w-7 place-items-center rounded-lg border text-sm"
                  :class="isSelected(g.id) ? 'border-brand-400 bg-brand-600 text-white' : 'border-cream-300/50 bg-ink-900/70 text-transparent hover:border-cream-200'">
            ✓
          </button>
        </div>
      </div>

      <!-- ══ Xem thêm ══ -->
      <div v-if="store.libraryHasMore" class="mt-6 flex justify-center">
        <button @click="store.loadMoreLibrary()" :disabled="store.libraryLoading"
                class="rounded-xl border border-ink-700 bg-ink-800 px-5 py-2 text-sm font-semibold text-cream-200 hover:bg-ink-700 disabled:opacity-50">
          {{ store.libraryLoading ? 'Đang tải…' : 'Xem thêm' }}
        </button>
      </div>
      </template>

      <!-- ══ Tab: FILE TẢI LÊN ══ -->
      <template v-else>
        <div class="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
          <div class="rounded-2xl border border-ink-700 bg-ink-800 p-3">
            <p class="text-[10px] uppercase tracking-wide text-cream-300/50">Tổng file</p>
            <p class="text-lg font-semibold text-cream-100">{{ fmtNum(uploadStats.total ?? store.uploadItems.length) }}</p>
          </div>
          <div class="rounded-2xl border border-ink-700 bg-ink-800 p-3">
            <p class="text-[10px] uppercase tracking-wide text-cream-300/50">Dung lượng</p>
            <p class="text-lg font-semibold text-cream-100">{{ fmtBytes(uploadStats.total_bytes ?? 0) }}</p>
          </div>
          <div class="rounded-2xl border border-ink-700 bg-ink-800 p-3">
            <p class="text-[10px] uppercase tracking-wide text-cream-300/50">File mồ côi (chưa dùng)</p>
            <p class="text-lg font-semibold text-red-300">{{ fmtNum(uploadUnusedCount) }}</p>
            <p v-if="uploadStats.unused_bytes" class="text-[10px] text-cream-300/50">{{ fmtBytes(uploadStats.unused_bytes) }}</p>
          </div>
          <div class="rounded-2xl border border-ink-700 bg-ink-800 p-3">
            <p class="text-[10px] uppercase tracking-wide text-cream-300/50">Đang dùng</p>
            <p class="text-lg font-semibold text-emerald-300">{{ fmtNum((uploadStats.total ?? 0) - (uploadStats.unused_count ?? 0)) }}</p>
          </div>
        </div>

        <!-- Thanh quản lý uploads -->
        <div v-if="store.libraryManage" class="mb-4 space-y-2 rounded-2xl border border-brand-600/40 bg-brand-900/30 p-3">
          <div class="flex flex-wrap items-center gap-2">
            <span class="text-sm font-semibold text-cream-100">Đã chọn <span class="text-brand-300">{{ uploadSelectedCount }}</span> file</span>
            <button @click="store.uploadSelectUnused" class="rounded-lg border border-red-500/40 bg-red-500/10 px-2.5 py-1 text-xs text-red-200 hover:bg-red-500/20">Chọn file mồ côi</button>
            <button @click="store.uploadSelectNone" class="rounded-lg border border-ink-700 bg-ink-800 px-2.5 py-1 text-xs text-cream-200 hover:bg-ink-700">Bỏ chọn</button>
          </div>
          <div class="flex flex-wrap items-center gap-2 border-t border-ink-700/60 pt-2">
            <template v-if="confirmAction === ''">
              <button @click="ask('ubulk')" :disabled="!uploadSelectedCount" class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-500 disabled:opacity-40">🗑 Xóa đã chọn ({{ uploadSelectedCount }})</button>
              <button @click="ask('uclean')" :disabled="!uploadUnusedCount" class="rounded-lg border border-red-500/40 px-3 py-1.5 text-xs text-red-300 hover:bg-red-500/15">🧾 Dọn file mồ côi ({{ uploadUnusedCount }})</button>
            </template>
            <template v-else>
              <p class="text-xs font-semibold text-red-200">
                ⚠
                <span v-if="confirmAction === 'ubulk'">Xóa vĩnh viễn {{ uploadSelectedCount }} file đã chọn?</span>
                <span v-else>Dọn toàn bộ file đã tải lên không còn dùng?</span>
              </p>
              <div class="flex gap-2">
                <button @click="cancelConfirm" class="rounded-lg border border-ink-600 bg-ink-800 px-3 py-1.5 text-xs text-cream-200 hover:bg-ink-700">Hủy</button>
                <button @click="runConfirm" class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-500">Xác nhận xóa</button>
              </div>
            </template>
          </div>
        </div>

        <!-- Lưới file tải lên -->
        <div v-if="store.uploadLoading && !store.uploadItems.length" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
          <div v-for="i in 10" :key="i" class="aspect-square animate-pulse rounded-2xl border-2 border-ink-700 bg-ink-800"></div>
        </div>
        <div v-else-if="!store.uploadItems.length" class="rounded-2xl border border-ink-700 bg-ink-800 py-16 text-center">
          <p class="text-sm text-cream-300/50">Chưa có file nào được tải lên.</p>
        </div>
        <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
          <div v-for="f in store.uploadItems" :key="f.rel"
               class="group relative overflow-hidden rounded-2xl border-2 transition"
               :class="isUploadSelected(f.rel) ? 'border-brand-400' : (f.used ? 'border-ink-700' : 'border-red-500/40')">
            <div class="relative cursor-pointer">
              <img :src="f.url" class="aspect-square w-full bg-ink-900 object-cover" loading="lazy" @error="$event.target.src='/images/placeholder.svg'">
              <span v-if="f.used" class="absolute left-2 top-2 rounded-full border border-emerald-500/40 bg-emerald-500/15 px-2 py-0.5 text-[9px] font-semibold text-emerald-200">đang dùng</span>
              <span v-else class="absolute left-2 top-2 rounded-full border border-red-500/40 bg-red-500/15 px-2 py-0.5 text-[9px] font-semibold text-red-200">chưa dùng</span>
              <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/85 via-black/40 to-transparent px-2 pb-1.5 pt-6">
                <p class="truncate text-[10px] font-medium text-cream-100">{{ f.name }}</p>
                <p class="truncate text-[9px] text-cream-300/75">{{ f.width }}×{{ f.height }} · {{ fmtBytes(f.size) }} · {{ f.kind === 'asset' ? 'tài nguyên' : 'ảnh nguồn' }}</p>
              </div>
            </div>
            <button v-if="store.libraryManage && !f.used" @click.stop="store.toggleUploadSelect(f.rel)"
                    class="absolute right-2 top-2 grid h-7 w-7 place-items-center rounded-lg border text-sm"
                    :class="isUploadSelected(f.rel) ? 'border-brand-400 bg-brand-600 text-white' : 'border-cream-300/50 bg-ink-900/70 text-transparent hover:border-cream-200'">
              ✓
            </button>
          </div>
        </div>
      </template>
    </div>

    <GalleryModal v-if="store.viewer" />
  </div>
</template>
