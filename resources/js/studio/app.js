import { createApp } from 'vue';
import { createPinia } from 'pinia';
import StudioApp from './StudioApp.vue';

// Legacy Alpine pages từng đăng ký service worker tại /sw.js; SW đó vẫn kiểm soát /studio
// và trả về HTML/asset cũ ("cross-world resource mismatch"). Gỡ SW + xoá cache để luôn tải bản mới.
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.getRegistrations()
    .then((regs) => regs.forEach((reg) => reg.unregister()))
    .catch(() => {});
  if (typeof caches !== 'undefined') {
    caches.keys()
      .then((keys) => keys.forEach((k) => caches.delete(k)))
      .catch(() => {});
  }
}

createApp(StudioApp).use(createPinia()).mount('#studio-root');
