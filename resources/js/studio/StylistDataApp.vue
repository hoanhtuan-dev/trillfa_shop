<script setup>
import StylistDataManager from './components/StylistDataManager.vue';
</script>
<template>
  <div class="studio-dark mx-auto max-w-4xl p-5">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
      <div>
        <h1 class="font-display text-lg font-semibold text-cream-50">✨ Trợ lý thiết kế · Quản lý data</h1>
        <p class="mt-0.5 text-[11px] text-ink-500">Loại trang phục + ma trận câu hỏi dùng bởi card ✨ Trợ lý thiết kế trong Studio.</p>
      </div>
      <a href="/studio" class="btn-outline btn-sm whitespace-nowrap">← Về Studio</a>
    </div>
    <StylistDataManager />
  </div>
</template>
