<script setup>
import { onMounted } from 'vue';
import { useStorefrontStore } from './store.js';
import { csrfToken } from './composables/useApi.js';
import StorefrontLayout from './components/layout/StorefrontLayout.vue';

const store = useStorefrontStore();
store.ensureBoot();

const boot = window.__STORE_BOOT__ || {};
const key = boot.key || '';
const page = boot.page || {};

onMounted(() => store.fetchCart());
</script>

<template>
  <StorefrontLayout>
    <div class="sf-container py-10 sm:py-14">
      <!-- Hero -->
      <div class="mx-auto max-w-3xl text-center">
        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-brand-600">Trillfa Fa</p>
        <h1 class="mt-2 font-display text-4xl font-semibold text-ink-900 sm:text-5xl">{{ page.title }}</h1>
        <p v-if="page.intro" class="mt-4 text-lg leading-relaxed text-ink-500">{{ page.intro }}</p>
        <img v-if="page.image" :src="page.image" :alt="page.title" class="mx-auto mt-8 w-full max-w-2xl rounded-3xl object-cover" />
      </div>

      <!-- Contact form -->
      <div v-if="key === 'contact'" class="mx-auto mt-10 max-w-2xl">
        <div class="card-surface rounded-[2rem] p-7">
          <h2 class="font-display text-xl font-semibold text-ink-900">Gửi tin nhắn</h2>
          <form method="POST" action="/lien-he" class="mt-5 space-y-4">
            <input type="hidden" name="_token" :value="csrfToken()" />
            <div class="grid gap-4 sm:grid-cols-2">
              <input name="name" required class="sf-input" placeholder="Họ tên" />
              <input name="email" type="email" required class="sf-input" placeholder="Email" />
            </div>
            <input name="subject" class="sf-input" placeholder="Tiêu đề (tuỳ chọn)" />
            <textarea name="message" required rows="4" class="sf-input" placeholder="Nội dung"></textarea>
            <button type="submit" class="sf-btn sf-btn-primary w-full">Gửi liên hệ</button>
          </form>
        </div>
      </div>

      <!-- Prose body -->
      <div v-else-if="key !== 'contact' && page.body" class="prose-content mx-auto mt-12 max-w-3xl" v-html="page.body"></div>
    </div>
  </StorefrontLayout>
</template>
