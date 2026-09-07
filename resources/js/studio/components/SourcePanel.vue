<script setup>
// SourcePanel — card "Nguồn": chỉ còn 1 slot duy nhất (icon tải ảnh khi trống | hiển thị ảnh
// nguồn đang dùng khi đã có). Bấm vào slot → mở SourcePickerPopup: popup GỘP 2 nguồn
// (Thư viện ảnh đã tải lên + Sản phẩm) thành 2 tab trong 1 popup — vẫn thêm nhiều layer ảnh.
import { ref } from 'vue';
import { useStudioStore } from '../store.js';
import { thumbUrl, onThumbError } from '../composables/useStudioThumb.js';
import SourcePickerPopup from './SourcePickerPopup.vue';
import StudioIcon from './StudioIcon.vue';
const store = useStudioStore();
const pickerOpen = ref(false);
</script>
<template>
  <div class="card overflow-hidden">
    <div class="panel-head border-b border-ink-700">
      <span class="panel-title"><StudioIcon name="image" size="h-4 w-4"/>Nguồn</span>
      <button v-if="store.editSource" @click="store.removeEditSource()" class="icon-btn text-red-300 hover:bg-red-600 hover:text-white" title="Bỏ ảnh nguồn khỏi canvas" :aria-label="'Bỏ ảnh nguồn khỏi canvas'"><StudioIcon name="x" size="h-3.5 w-3.5"/></button>
    </div>
    <div class="p-2">
      <!-- Slot duy nhất: icon tải ảnh (trống) | hiển thị ảnh nguồn (đã có) — bấm mở popup 2 tab -->
      <button
        @click="pickerOpen = true"
        class="group relative block w-full overflow-hidden rounded-xl transition-colors"
        :class="store.editSource ? 'border border-ink-700 bg-ink-900 hover:border-brand-500/70' : 'border border-dashed border-ink-600 bg-ink-800/50 hover:border-brand-500/70 hover:bg-ink-800'"
        :title="store.editSource ? 'Đổi / thêm ảnh nguồn — thư viện đã tải lên hoặc sản phẩm' : 'Thêm ảnh nguồn — thư viện đã tải lên hoặc sản phẩm'"
        :aria-label="'Thêm ảnh nguồn'"
      >
        <template v-if="store.editSource">
          <img :src="thumbUrl(store.editSource.url)" class="aspect-square w-full bg-ink-900 object-cover" alt="" loading="lazy" @error="onThumbError($event, store.editSource.url)">
          <span class="absolute inset-x-0 bottom-0 flex items-center gap-0.5 bg-black/60 px-1 py-0.5 text-[8px] font-medium text-cream-100"><StudioIcon name="image" size="h-2.5 w-2.5" class="shrink-0"/><span class="truncate">{{ store.editSource.name || 'Ảnh nguồn' }}</span></span>
          <span class="absolute right-1 top-1 grid h-5 w-5 place-items-center rounded-md bg-black/55 text-cream-200 transition-colors group-hover:bg-brand-600 group-hover:text-white" :title="'Thay ảnh nguồn'"><StudioIcon name="imagePlus" size="h-3 w-3"/></span>
        </template>
        <template v-else>
          <span class="flex aspect-square w-full flex-col items-center justify-center gap-1.5 text-cream-300/70">
            <StudioIcon name="imagePlus" size="h-6 w-6"/>
            <span class="px-1 text-center text-[9px] font-medium leading-tight">Thêm ảnh nguồn</span>
          </span>
        </template>
      </button>
      <p class="mt-1.5 text-center text-[8px] leading-tight text-cream-300/40">Thư viện · Sản phẩm</p>
    </div>

    <!-- Popup gộp 2 nguồn thành 2 tab (Thư viện ảnh / Sản phẩm) trong 1 popup -->
    <SourcePickerPopup v-model="pickerOpen" />
  </div>
</template>
