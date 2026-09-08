<script setup>
import { useStudioStore } from '../store.js';
import StudioIcon from './StudioIcon.vue';
const store = useStudioStore();
</script>
<template>
  <div class="card p-5" style="background: linear-gradient(160deg, rgba(74,122,144,.12), rgba(124,58,237,.06));">
    <h2 class="mb-3 flex items-center gap-2 font-display text-base font-semibold text-cream-200"><StudioIcon name="film" /> Kịch bản quay</h2>
    <label class="label">Thời lượng</label>
    <select v-model="store.videoDuration" class="input !py-2"><option v-for="d in ['5','8','10','15','20']" :key="d" :value="d">{{ d }}</option></select>
    <label class="label">Độ phân giải</label>
    <select v-model="store.videoRes" class="input !py-2"><option v-for="r in ['480','720','1080']" :key="r" :value="r">{{ r }}</option></select>
    <label class="label">Kịch bản quay <span class="text-cream-300/50">(từ Prompt Templates)</span></label>
    <div v-if="store.videoScenes.length" class="flex flex-wrap gap-1.5">
      <button v-for="sc in store.videoScenes" :key="sc.id" type="button" @click="store.videoScene = String(store.videoScene) === String(sc.id) ? '' : sc.id" :title="sc.prompt" class="rounded-full border px-3 py-1.5 text-xs" :class="String(store.videoScene) === String(sc.id) ? 'border-brand-600 bg-brand-600 font-semibold text-white' : 'border-ink-700 text-cream-200 hover:border-brand-400'">{{ sc.label }}</button>
    </div>
    <p v-else class="mt-1 rounded-lg border border-dashed border-white/10 bg-white/5 p-2 text-[11px] text-cream-300/60">Chưa có preset Kịch bản quay — thêm ở <b>Cài đặt → Prompt Templates</b> (category video_scene).</p>
    <label class="label mt-3">Nguồn ảnh (Bước 2)</label>
    <div v-if="store.upscaleSrc" class="mt-1 flex items-center gap-2 rounded-lg border border-white/10 bg-white/5 p-2">
      <img :src="store.upscaleSrc" class="h-10 w-10 rounded-md bg-ink-900 object-cover" alt="nguồn video">
      <span class="truncate text-[11px] text-cream-200">Đang dùng ảnh trên canvas làm frame đầu.</span>
    </div>
    <div v-else class="mt-1 rounded-lg border border-white/10 bg-white/5 p-2 text-[11px] text-cream-300/60">Chưa có ảnh nguồn — sẽ render text-to-video (không có frame đầu).</div>
    <label class="label mt-3">Prompt video</label>
    <textarea v-model="store.videoPromptEn" rows="3" class="input !text-xs" placeholder="(để trống để ghép tự động từ prompt ảnh)"></textarea>
    <button @click="store.renderVideo()" :disabled="store.videoBusy || (!store.videoPromptEn && !store.upscaleSrc)" class="btn-brand mt-3 w-full whitespace-nowrap">{{ store.videoBusy ? 'Đang gửi…' : 'Render Video' }}</button>
  </div>
</template>
