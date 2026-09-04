import { createApp } from 'vue';
import { createPinia } from 'pinia';
import StorefrontApp from './StorefrontApp.vue';

window.storefrontLoaded = true;

// The storefront page does not register a service worker itself, but a stale
// service worker from the legacy Alpine page may still be controlling this
// page and serving cached old HTML/assets ("cross-world resource mismatch").
// Unregister it and wipe its caches so the SPA loads the fresh build.
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

createApp(StorefrontApp)
    .use(createPinia())
    .mount('#store-root');
