<script setup>
import { useStudioStore } from '../store.js';
import { thumbUrl, onThumbError } from '../composables/useStudioThumb.js';
const store = useStudioStore();
function openGallery() { store.viewer = store.preview || store.generations[0] || null; }
function goLibrary() { window.location.href = '/studio/library'; }
// Thumbnail URL lấy từ composable dùng chung (useStudioThumb) — cùng logic với PHP helper
// studio_image_thumb_url(): giảm ~50x dung lượng so với ảnh gốc 2K; path đặc biệt → fallback ảnh gốc.
// Ảnh gốc full-size vẫn dùng khi mở viewer (store.viewer = g → media_url gốc).
</script>
<template>
  <div class="card flex flex-1 flex-col p-3" style="min-height:0">
    <div class="flex items-center justify-between gap-1"><p class="text-xs font-semibold text-cream-200">Outputs <span class="text-cream-300/50">({{ store.generations.length }})</span></p><button @click="goLibrary" class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-ink-800 text-cream-200 transition-colors hover:bg-ink-700" title="Xem thư viện"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg></button></div>
    <div class="scrollbar-hide mt-2 grid flex-1 auto-rows-min grid-cols-2 gap-1.5 overflow-y-auto">
      <div v-for="g in store.generations" :key="g.id" class="group relative aspect-square overflow-hidden rounded-lg border-2" :class="store.previewId === g.id ? 'border-brand-500' : 'border-ink-700'">
        <!-- Ảnh hoàn tất -->
        <template v-if="g.status === 'completed' && g.media_url">
          <button @click="store.viewer = g" class="absolute inset-0"><img :src="thumbUrl(g.media_url)" class="h-full w-full bg-ink-900 object-cover" loading="lazy"></button>
        </template>
        <!-- Đang xử lý / chờ: skeleton shimmer + overlay tiến độ -->
        <template v-else>
          <div class="skeleton-shimmer absolute inset-0"></div>
          <div class="absolute inset-0 flex flex-col items-center justify-center gap-1.5 bg-black/40 text-[10px] text-cream-200">
            <span v-if="['pending','processing'].includes(g.status)" class="h-5 w-5 animate-spin rounded-full border-2 border-brand-300 border-t-transparent"></span>
            <span v-else-if="g.status === 'failed'" class="text-base">⚠️</span>
            <span v-else-if="g.status === 'cancelled'" class="text-base">🚫</span>
            <span class="font-semibold">{{ store.statusLabel(g.status) }}</span>
            <span v-if="['pending','processing'].includes(g.status)" class="flex items-center gap-0.5">
              <span v-for="i in 3" :key="i" class="status-dot" :style="{ animationDelay: (i - 1) * 0.2 + 's' }"></span>
            </span>
          </div>
        </template>
        <span v-if="g.media_url" class="absolute bottom-1 left-1 max-w-[92%] truncate rounded-full bg-black/60 px-1.5 py-0.5 text-[9px] text-cream-100">{{ store.genName(g) }}</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Skeleton shimmer — hiệu ứng quét sáng chạy ngang ảnh slot đang chờ/xử lý */
.skeleton-shimmer {
  background: linear-gradient(100deg, #1c2333 20%, #2a3347 40%, #3a4560 60%, #2a3347 80%, #1c2333 100%);
  background-size: 200% 100%;
  animation: shimmerSweep 1.8s linear infinite;
}
@keyframes shimmerSweep {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
/* 3 dot "đang chờ" — nhấp nháy tuần tự */
.status-dot {
  width: 3px;
  height: 3px;
  border-radius: 9999px;
  background: #f5c06a;
  animation: dotBlink 1.2s ease-in-out infinite;
}
@keyframes dotBlink {
  0%, 80%, 100% { opacity: 0.25; transform: translateY(0); }
  40% { opacity: 1; transform: translateY(-2px); }
}
</style>