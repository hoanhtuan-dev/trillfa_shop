<script setup>
// Thanh ngữ cảnh nổi khi CHỌN NHIỀU layer (shift+click): căn lề · chia đều khoảng cách (x/y) ·
// bắt điểm theo khoảng cách preset · xóa. Là "tiền đề mở rộng" — thêm nút mới dễ dàng.
import { useStudioStore } from '../store.js';
import StudioIcon from './StudioIcon.vue';
const store = useStudioStore();

const snapOptions = [['0','Tắt'],['8','8px'],['16','16px'],['24','24px'],['32','32px']];
const alignActions = [
  ['left','Căn trái','Align left'], ['hcenter','Căn ngang','Align horizontal center'], ['right','Căn phải','Align right'],
  ['top','Căn trên','Align top'], ['vcenter','Căn dọc','Align vertical center'], ['bottom','Căn dưới','Align bottom'],
];
const mkBtn = 'rounded-lg px-1.5 py-0.5 text-[10px] font-medium text-cream-200 transition hover:bg-ink-700 hover:text-cream-100 disabled:opacity-40';
const sep = 'h-4 w-px shrink-0 bg-ink-600';
</script>
<template>
  <div class="pointer-events-auto flex flex-wrap items-center gap-1 rounded-2xl border border-ink-700 bg-ink-900/90 px-2 py-1 text-[11px] shadow-xl backdrop-blur">
    <span class="px-1 font-semibold text-cream-300/70">{{ store.selectionCount }} layer</span>
    <span :class="sep"></span>

    <!-- Căn lề -->
    <button v-for="a in alignActions" :key="a[0]" @click="store.alignSelection(a[0])" :class="mkBtn" :title="a[2]">{{ a[1] }}</button>
    <span :class="sep"></span>

    <!-- Chia đều khoảng cách X/Y -->
    <button @click="store.distributeSelection('x')" :class="mkBtn" :disabled="store.selectionCount < 3" title="Chia đều khoảng cách theo trục X">Chia đều X</button>
    <button @click="store.distributeSelection('y')" :class="mkBtn" :disabled="store.selectionCount < 3" title="Chia đều khoảng cách theo trục Y">Chia đều Y</button>
    <span :class="sep"></span>

    <!-- Bắt điểm preset (khoảng cách) -->
    <span class="flex items-center gap-1 px-1 text-[10px] text-cream-300/70">
      <StudioIcon name="target" size="h-3 w-3" />
      Bắt điểm
    </span>
    <select :value="store.snapGrid" @change="store.setSnapGrid($event.target.value)"
            class="h-7 rounded-lg border border-ink-700 bg-ink-800 px-1.5 text-[10px] text-cream-100 focus:border-brand-400 focus:outline-none" title="Khoảng cách bắt điểm khi kéo layer">
      <option v-for="o in snapOptions" :key="o[0]" :value="o[0]">{{ o[1] }}</option>
    </select>
    <span :class="sep"></span>

    <!-- Xóa (phím Delete cũng mở xác nhận) -->
    <button @click="store.deleteSelection()" class="flex items-center gap-1 rounded-lg px-1.5 py-0.5 text-[10px] font-medium text-red-300 transition hover:bg-red-600/25 hover:text-red-200" title="Xóa các layer đang chọn (Delete)">
      <StudioIcon name="trash" size="h-3 w-3" /> Xóa
    </button>
  </div>
</template>
