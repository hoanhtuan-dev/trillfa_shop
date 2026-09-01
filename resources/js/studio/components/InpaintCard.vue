<script setup>
import { useStudioStore } from '../store.js';
const store = useStudioStore();
</script>
<template>
  <div class="card p-5" style="border:1px solid var(--color-brand-500); background: linear-gradient(160deg, rgba(124,200,90,.13), rgba(74,122,144,.06));">
    <h2 class="mb-1 font-display text-base font-semibold text-brand-300">✏️ Sửa ảnh (Inpaint)</h2>
    <p class="text-[11px] text-ink-500">Chỉnh sửa vùng/các phần tử trên ảnh đang chọn (giữ nền + khuôn mặt).</p>
    <textarea v-model="store.inpaintPrompt" rows="3" class="input !text-xs" placeholder="VD: đổi màu áo, ngắn tay hơn, thêm túi…"></textarea>
    <button @click="store.inpaint(store.inpaintPrompt)" :disabled="store.inpainting || !store.previewId" class="btn-brand mt-3 w-full whitespace-nowrap">{{ store.inpainting ? 'Đang sửa…' : '✏️ Sửa ảnh' }}</button>
  </div>
</template>
