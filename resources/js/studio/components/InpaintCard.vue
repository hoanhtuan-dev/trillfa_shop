<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useStudioStore } from '../store.js';
import CompareSlider from './CompareSlider.vue';
import BaseModal from './BaseModal.vue';
const store = useStudioStore();

const beforeUrl = ref('');   // ảnh gốc trước khi sửa (để so sánh)
const compareOpen = ref(false);
function submitInpaint() {
  beforeUrl.value = store.preview?.media_url || '';
  store.inpaint(store.inpaintPrompt);
}

// ── Render đa góc: 4 slot tương ứng 4 góc chụp, người dùng bật/tắt + chỉnh prompt từng góc ──
const mvOpen = ref(false);
const mvBusy = ref(false);
const mvViews = ref([
  { id: 'front', icon: '🧍', label: 'Chính diện', enabled: true, prompt: 'full-body front view, facing straight ahead' },
  { id: 'back', icon: '🔙', label: 'Mặt sau', enabled: true, prompt: 'full-body back view' },
  { id: 'side', icon: '↔️', label: 'Nghiêng 45°', enabled: true, prompt: '45-degree side view, three-quarter angle' },
  { id: 'detail', icon: '🔍', label: 'Cận cảnh chi tiết', enabled: true, prompt: 'close-up detail of the fabric texture and stitching' },
]);
const mvCount = computed(() => mvViews.value.filter(v => v.enabled).length);
const srcImg = computed(() => store.upscaleSrc || store.preview?.media_url || '');

async function runMultiView() {
  const img = srcImg.value;
  if (!img) { store.toast('Chọn một ảnh để render đa góc.', 'error'); return; }
  const enabled = mvViews.value.filter(v => v.enabled);
  if (!enabled.length) { store.toast('Chọn ít nhất 1 góc chụp.', 'error'); return; }
  mvBusy.value = true;
  for (const v of enabled) {
    await store.reimagine(img,
      'render this fashion product at a new camera angle — ' + v.prompt + '. Keep the product, color, material, proportions and every detail exactly unchanged, no detail loss, crisp sharp, professional studio lighting',
      85, 1);
  }
  mvBusy.value = false;
  mvOpen.value = false;
  store.toast('Đã render ' + enabled.length + ' góc chụp.');
}

// ── Ghép (thay thế) khuôn mặt ──
const fsOpen = ref(false);
const fsBusy = ref(false);
const fsFaces = ref([]);
const fsSelected = ref(null);
async function loadFaces() {
  try {
    const r = await fetch('/studio/swap-models', { headers: { Accept: 'application/json' } });
    const d = await r.json();
    // CHỈ hiển thị khuôn mặt CÓ ẢNH THẬT — face chỉ có mô tả (image null) sẽ khiến model
    // thay bằng text thay vì khuôn mặt thật.
    fsFaces.value = (d.items || []).filter(f => f.image);
  } catch (e) { fsFaces.value = []; }
}
async function runFaceSwap() {
  const img = store.upscaleSrc || store.preview?.media_url;
  if (!img) { store.toast('Chọn ảnh người mẫu để thay khuôn mặt.', 'error'); return; }
  if (!fsSelected.value?.image) { store.toast('Chọn một khuôn mặt.', 'error'); return; }
  fsBusy.value = true;
  await store.faceSwap(img, fsSelected.value.image);
  fsBusy.value = false;
  fsOpen.value = false;
}

// Tải khuôn mặt tùy chỉnh từ máy
const fsUploading = ref(false);
const fsFileEl = ref(null);
const CSRF = () => (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
async function uploadFace(e) {
  const file = e.target.files?.[0];
  if (!file) return;
  fsUploading.value = true;
  try {
    const fd = new FormData();
    fd.append('image', file);
    const res = await fetch('/studio/upload-ref', { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF(), Accept: 'application/json' }, body: fd });
    const d = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(d.message || 'Lỗi tải khuôn mặt.');
    const img = { id: 'up-' + Date.now(), name: file.name.replace(/\.[^.]+$/, ''), image: d.url };
    fsFaces.value.push(img);
    fsSelected.value = img;
    store.toast('Đã tải khuôn mặt lên.');
  } catch (err) {
    store.toast(err.message || 'Lỗi tải khuôn mặt.', 'error');
  } finally {
    fsUploading.value = false;
    if (fsFileEl.value) fsFileEl.value.value = '';
  }
}

const now = ref(Date.now());
let timer = null;
onMounted(() => { timer = setInterval(() => { now.value = Date.now(); }, 1000); loadFaces(); });
onBeforeUnmount(() => { if (timer) clearInterval(timer); });

const elapsedSec = computed(() => store.inpaintStartTs ? Math.max(0, Math.floor((now.value - store.inpaintStartTs) / 1000)) : 0);
const fmt = (s) => String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');

const activeGen = computed(() => store.inpaintGenId ? store.generations.find(g => g.id === Number(store.inpaintGenId)) : null);
const canSubmit = computed(() => !!store.previewId && !!store.preview?.media_url && !store.inpainting && !!store.inpaintPrompt.trim());
const running = computed(() => store.inpaintStage === 'send' || store.inpaintStage === 'processing');
const maskActive = computed(() => store.inpaintMaskMode !== 'none');
</script>
<template>
  <div class="card p-5" style="border:1px solid var(--color-brand-500); background: linear-gradient(160deg, rgba(124,200,90,.13), rgba(74,122,144,.06));">
    <h2 class="mb-1 font-display text-base font-semibold text-brand-300">✏️ Sửa ảnh (Inpaint)</h2>
    <p class="text-[11px] text-ink-500">Chỉnh sửa vùng/phần tử trên ảnh đang chọn — AI chỉ sửa đúng vùng được chọn, giữ phần còn lại.</p>

    <!-- Ảnh đang chọn -->
    <div v-if="store.preview?.media_url" class="mt-3 flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 p-2.5">
      <img :src="store.preview.media_url" class="h-14 w-14 rounded-xl bg-ink-900 object-cover">
      <div class="min-w-0 text-xs text-cream-200">
        <p class="truncate font-semibold">Ảnh kết quả #{{ store.preview.id }}</p>
        <p class="text-cream-300/60">Sẽ sửa trực tiếp trên ảnh này</p>
      </div>
    </div>
    <div v-else class="mt-3 rounded-2xl border border-dashed border-white/15 bg-white/5 p-3 text-xs text-cream-300/60">Chọn một ảnh kết quả trong <b>Outputs</b> để sửa.</div>

    <!-- Mask tools: chọn vùng trên canvas chính -->
    <div v-if="store.preview?.media_url" class="mt-2 flex gap-1.5">
      <button @click="store.toggleInpaintMask('rect')"
              :class="store.inpaintMaskMode === 'rect' ? 'bg-brand-600 text-white' : 'bg-ink-800 text-cream-200 hover:bg-ink-700'"
              class="rounded-full px-2.5 py-1 text-[10px] font-semibold transition-colors">
        ▭ Chọn vùng
      </button>
      <button @click="store.toggleInpaintMask('brush')"
              :class="store.inpaintMaskMode === 'brush' ? 'bg-brand-600 text-white' : 'bg-ink-800 text-cream-200 hover:bg-ink-700'"
              class="rounded-full px-2.5 py-1 text-[10px] font-semibold transition-colors">
        🖌 Vẽ mask
      </button>
      <button v-if="maskActive && store.inpaintMaskMode === 'rect' && (store.inpaintMaskBox.w || 0) >= 0.02"
              @click="store.resetInpaintMaskBox()"
              class="rounded-full bg-amber-600/30 px-2 py-1 text-[10px] font-semibold text-amber-200 hover:bg-amber-600"
              title="Bỏ vùng hiện tại để kéo chọn vùng mới">
        🔄 Vẽ lại
      </button>
      <button v-if="maskActive || store.inpaintMaskDone" @click="store.clearInpaintMask()"
              class="rounded-full bg-red-600/25 px-2 py-1 text-[10px] font-semibold text-red-200 hover:bg-red-600">
        ✕ Bỏ mask
      </button>
    </div>
    <div v-if="maskActive" class="mt-1.5 rounded-xl border border-brand-500/30 bg-brand-900/20 px-2.5 py-1.5 text-[10px] text-brand-200">
      <template v-if="store.inpaintMaskMode === 'rect'">
        <span v-if="(store.inpaintMaskBox.w || 0) >= 0.02">▭ Vùng {{ Math.round(store.inpaintMaskBox.w * 100) }}% × {{ Math.round(store.inpaintMaskBox.h * 100) }}% — kéo để di chuyển · đúp chuột để vẽ lại</span>
        <span v-else>▭ Kéo chọn vùng trên canvas — AI chỉ sửa trong vùng đã chọn</span>
      </template>
      <span v-else>🖌 Vẽ mask trên canvas — AI chỉ sửa vùng đã vẽ</span>
    </div>
    <!-- Trạng thái mask ĐÃ LƯU (bấm Xong, overlay tắt): hiển thị vùng sẽ xử lý + thumbnail -->
    <div v-else-if="store.inpaintMaskDone" class="mt-1.5 rounded-xl border border-emerald-500/30 bg-emerald-900/20 px-2.5 py-2 text-[10px] text-emerald-200">
      <div class="flex items-center gap-2.5">
        <!-- Thumbnail vùng đã chọn: rect (ô đen trên nền trắng) / brush (mask đen-trắng) -->
        <div v-if="store._inpaintMaskKind === 'rect'" class="relative h-12 w-12 shrink-0 overflow-hidden rounded-lg border border-white/20 bg-white">
          <div class="absolute bg-black/85" :style="{ left: (store.inpaintMaskBox.x || 0) * 100 + '%', top: (store.inpaintMaskBox.y || 0) * 100 + '%', width: (store.inpaintMaskBox.w || 0) * 100 + '%', height: (store.inpaintMaskBox.h || 0) * 100 + '%' }"></div>
        </div>
        <img v-else-if="store.inpaintBrushData" :src="'data:image/png;base64,' + store.inpaintBrushData" class="h-12 w-12 shrink-0 rounded-lg border border-white/20 bg-white object-contain" alt="Mask" />
        <div class="min-w-0 flex-1">
          <p class="font-semibold">{{ store._inpaintMaskKind === 'rect' ? '✅ Đã chọn vùng ' + Math.round((store.inpaintMaskBox.w || 0) * 100) + '% × ' + Math.round((store.inpaintMaskBox.h || 0) * 100) + '%' : '✅ Đã vẽ mask' }}</p>
          <p class="mt-0.5 text-emerald-200/70">AI sẽ chỉ sửa trong vùng tô đen bên cạnh.</p>
        </div>
        <button @click="store.toggleInpaintMask(store._inpaintMaskKind)" class="shrink-0 rounded-full bg-white/10 px-2 py-0.5 font-semibold hover:bg-white/20">Chỉnh lại</button>
      </div>
    </div>

    <!-- Chip nhanh -->
    <div class="mt-3">
      <p class="text-[10px] font-semibold text-cream-300/70">✨ Gợi ý nhanh</p>
      <div class="mt-1.5 flex flex-wrap gap-1.5">
        <button @click="mvOpen = true"
                class="rounded-full border border-ink-700 bg-ink-800/60 px-2.5 py-1 text-[11px] font-medium text-cream-200 transition hover:border-brand-400 hover:bg-brand-600/20">
          📐 Render đa góc
        </button>
        <button @click="fsOpen = true"
                class="rounded-full border border-ink-700 bg-ink-800/60 px-2.5 py-1 text-[11px] font-medium text-cream-200 transition hover:border-brand-400 hover:bg-brand-600/20">
          👤 Thay khuôn mặt
        </button>
      </div>
    </div>

    <!-- Modal render đa góc: 4 slot + hướng dẫn + kiểm soát -->
    <BaseModal v-model="mvOpen" title="📐 Render đa góc từ 1 ảnh" wide>
      <div class="mb-3 rounded-2xl border border-brand-500/30 bg-brand-900/20 p-3 text-xs leading-relaxed text-brand-100">
        <p class="font-semibold">💡 Cách dùng (3 bước):</p>
        <p class="mt-1 text-brand-100/80">① Chọn ảnh sản phẩm ở canvas · ② Bật/tắt các góc bạn muốn · ③ Bấm "Render N góc". AI giữ nguyên sản phẩm, chỉ đổi góc chụp.</p>
      </div>

      <div class="space-y-2">
        <div v-for="(v, i) in mvViews" :key="v.id"
             class="rounded-2xl border p-2.5 transition"
             :class="v.enabled ? 'border-brand-500/50 bg-brand-900/20' : 'border-ink-700 bg-ink-800/40 opacity-60'">
          <div class="flex items-center gap-2">
            <button @click="v.enabled = !v.enabled"
                    class="grid h-5 w-5 shrink-0 place-items-center rounded border text-xs"
                    :class="v.enabled ? 'bg-brand-600 text-white' : 'bg-ink-700 text-cream-300'">
              {{ v.enabled ? '✓' : '' }}
            </button>
            <span class="font-semibold text-cream-100">{{ v.icon }} {{ v.label }}</span>
            <span class="ml-auto text-[10px] text-cream-300/60">Slot {{ i + 1 }}</span>
          </div>
          <input v-if="v.enabled" v-model="v.prompt" class="input mt-2 !py-1.5 !text-xs" placeholder="Mô tả góc chụp…">
        </div>
      </div>

      <div class="mt-4 flex items-center justify-between">
        <span class="text-xs text-cream-300/70">Đã chọn: <b class="text-brand-300">{{ mvCount }}/4 góc</b></span>
        <button @click="runMultiView" :disabled="mvBusy || !mvCount" class="btn-brand whitespace-nowrap">
          {{ mvBusy ? 'Đang render…' : '📐 Render ' + mvCount + ' góc' }}
        </button>
      </div>
    </BaseModal>

    <!-- Modal thay khuôn mặt -->
    <BaseModal v-model="fsOpen" title="👤 Thay khuôn mặt cho người mẫu" wide>
      <div class="mb-3 rounded-2xl border border-brand-500/30 bg-brand-900/20 p-3 text-xs leading-relaxed text-brand-100">
        <p class="font-semibold">💡 Chọn 1 khuôn mặt để ghép lên ảnh người mẫu đang chọn.</p>
        <p class="mt-1 text-brand-100/80">AI giữ nguyên dáng, trang phục, bối cảnh và ánh sáng — chỉ thay khuôn mặt.</p>
      </div>

      <label class="mb-3 flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-brand-500/40 bg-brand-900/20 px-3 py-2.5 text-xs font-semibold text-brand-200 transition hover:bg-brand-900/40">
        <span>{{ fsUploading ? '⏳ Đang tải lên…' : '📤 Tải khuôn mặt từ máy' }}</span>
        <input ref="fsFileEl" type="file" accept="image/*" class="hidden" @change="uploadFace">
      </label>

      <div v-if="fsFaces.length" class="grid grid-cols-4 gap-2 sm:grid-cols-5">
        <button v-for="f in fsFaces" :key="f.id" @click="fsSelected = f"
                class="relative aspect-square overflow-hidden rounded-xl border-2 transition"
                :class="fsSelected?.id === f.id ? 'border-brand-500 ring-2 ring-brand-500/40' : 'border-ink-700 hover:border-ink-500'">
          <img v-if="f.image" :src="f.image" class="h-full w-full bg-ink-900 object-cover" loading="lazy">
          <span v-else class="grid h-full w-full place-items-center bg-ink-800 text-xl">👩</span>
          <span class="absolute inset-x-0 bottom-0 truncate bg-black/60 px-1 py-0.5 text-[9px] text-cream-200">{{ f.name }}</span>
        </button>
      </div>
      <p v-else class="rounded-2xl border border-dashed border-ink-600 p-4 text-center text-xs text-cream-300/60">Chưa có khuôn mặt — thêm trong <b>Settings → Face Presets</b>.</p>
      <p v-if="fsFaces.length" class="mt-2 text-[10px] text-cream-300/50">⚠️ Chỉ hiển thị khuôn mặt <b>có ảnh thật</b>. Khuôn mặt chỉ có mô tả (chưa upload ảnh) sẽ không hiện — hãy thêm ảnh trong <b>Settings → Face Presets → Ảnh tham chiếu</b>.</p>

      <div class="mt-4 flex items-center justify-between">
        <span class="text-xs text-cream-300/70">{{ fsSelected ? 'Đã chọn: <b class="text-brand-300">' + fsSelected.name + '</b>' : 'Chưa chọn khuôn mặt' }}</span>
        <button @click="runFaceSwap" :disabled="fsBusy || !fsSelected" class="btn-brand whitespace-nowrap">
          {{ fsBusy ? 'Đang ghép…' : '👤 Thay khuôn mặt' }}
        </button>
      </div>
    </BaseModal>

    <label class="label mt-3">Mô tả chỉnh sửa</label>
    <textarea v-model="store.inpaintPrompt" rows="3" maxlength="1000" class="input !text-xs" placeholder="VD: đổi màu áo thành đỏ, ngắn tay hơn, thêm túi trước…"></textarea>
    <p class="mt-1 text-right text-[10px] text-cream-300/50">{{ store.inpaintPrompt.length }}/1000</p>

    <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-cream-200">
      <label class="flex cursor-pointer items-center gap-1.5"><input type="checkbox" v-model="store.inpaintPreserveFace" class="h-3.5 w-3.5 accent-brand-500"> Giữ nguyên khuôn mặt & dáng</label>
      <label class="flex cursor-pointer items-center gap-1.5"><input type="checkbox" v-model="store.inpaintPreserveBg" class="h-3.5 w-3.5 accent-brand-500"> Giữ nguyên nền</label>
    </div>

    <button @click="submitInpaint" :disabled="!canSubmit" class="btn-brand mt-3 w-full whitespace-nowrap">
      <span v-if="store.inpainting && store.inpaintStage === 'send'">Đang gửi yêu cầu…</span>
      <span v-else-if="store.inpainting">AI đang chỉnh sửa…</span>
      <span v-else>✏️ Sửa ảnh <span class="opacity-70">· {{ store.imageCreditCost }} credit</span></span>
    </button>

    <!-- Tiến độ -->
    <div v-if="running" class="mt-3 rounded-2xl border border-brand-500/30 bg-brand-900/30 p-3">
      <div class="flex items-center gap-2 text-xs text-brand-100">
        <span class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-brand-300 border-t-transparent"></span>
        <span class="font-semibold">{{ store.inpaintStage === 'send' ? 'Đang gửi yêu cầu tới AI…' : 'AI đang chỉnh sửa ảnh…' }}</span>
      </div>
      <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-cream-200/80">
        <span>⏱ <b>{{ fmt(elapsedSec) }}</b></span>
        <span>Nhiệm vụ #{{ store.inpaintGenId }}</span>
        <span v-if="activeGen?.model">Model: {{ activeGen.model }}</span>
        <button @click="store.cancelInpaint()" class="ml-auto rounded-full bg-red-600/25 px-2.5 py-1 font-semibold text-red-200 hover:bg-red-600">✕ Hủy</button>
      </div>
      <div class="mt-2 h-1 w-full overflow-hidden rounded-full bg-white/10"><div class="h-full animate-pulse rounded-full bg-brand-400" style="width:60%"></div></div>
    </div>

    <!-- Thành công -->
    <div v-if="store.inpaintStage === 'done'" class="mt-3 flex items-center gap-2 rounded-2xl border border-emerald-500/40 bg-emerald-900/25 p-3 text-xs text-emerald-200">
      ✅ Đã sửa xong — ảnh mới đã được chọn trong Outputs.
      <button v-if="beforeUrl && activeGen?.media_url" @click="compareOpen = true" class="rounded-full bg-white/10 px-2 py-0.5 font-semibold hover:bg-white/20">🔍 So sánh Trước/Sau</button>
      <button @click="store.clearInpaintStatus()" class="ml-auto rounded-full bg-white/10 px-2 py-0.5 hover:bg-white/20">Đóng</button>
    </div>

    <!-- Lỗi -->
    <div v-if="store.inpaintStage === 'error' && store.inpaintError" class="mt-3 rounded-2xl border border-red-500/40 bg-red-900/25 p-3 text-xs text-red-200">
      <p class="font-semibold">⚠️ Sửa ảnh thất bại</p>
      <p class="mt-1 whitespace-pre-line leading-relaxed">{{ store.inpaintError }}</p>
      <div class="mt-2 flex gap-2">
        <button @click="store.inpaint(store.inpaintPrompt)" class="btn-brand btn-sm">🔄 Thử lại</button>
        <button @click="store.clearInpaintStatus()" class="btn-ghost btn-sm">Đóng</button>
      </div>
    </div>

    <!-- Đã hủy -->
    <div v-if="store.inpaintStage === 'cancelled'" class="mt-3 flex items-center gap-2 rounded-2xl border border-white/15 bg-white/5 p-3 text-xs text-cream-200">
      🛑 Đã hủy yêu cầu sửa ảnh.
      <button @click="store.clearInpaintStatus()" class="ml-auto rounded-full bg-white/10 px-2 py-0.5 hover:bg-white/20">Đóng</button>
    </div>

    <!-- So sánh Trước/Sau -->
    <CompareSlider v-model="compareOpen" :before="beforeUrl" :after="activeGen?.media_url || ''" />
  </div>
</template>
