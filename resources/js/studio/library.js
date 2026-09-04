import { createApp } from 'vue';
import { createPinia } from 'pinia';
import LibraryApp from './LibraryApp.vue';

if ('serviceWorker' in navigator) {
  navigator.serviceWorker.getRegistrations().then((regs) => regs.forEach((reg) => reg.unregister())).catch(() => {});
  if (typeof caches !== 'undefined') { caches.keys().then((keys) => keys.forEach((k) => caches.delete(k))).catch(() => {}); }
}

createApp(LibraryApp).use(createPinia()).mount('#library-root');
