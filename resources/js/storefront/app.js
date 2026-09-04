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

const app = createApp(StorefrontApp);
app.use(createPinia());

// Lightweight scroll-reveal (fade-up) for sections. Falls back to instantly
// revealing when IntersectionObserver is unavailable.
app.directive('reveal', {
    mounted(el, binding) {
        el.classList.add('reveal-anim');

        const show = (delay = Number(binding.value) || 0) =>
            window.setTimeout(() => el.classList.add('is-revealed'), delay);

        const inView = () =>
            el.getBoundingClientRect().top < window.innerHeight - 60;

        // Reveal immediately if already within the viewport on mount.
        if (inView()) {
            show();
            return;
        }

        // Otherwise reveal on scroll; fall back to a timeout so content is
        // never left hidden.
        let done = false;
        const finish = () => {
            if (done) return;
            done = true;
            show();
        };
        if (typeof IntersectionObserver === 'undefined') {
            finish();
            return;
        }
        const io = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        io.unobserve(el);
                        finish();
                    }
                });
            },
            { threshold: 0.08, rootMargin: '0px 0px -40px 0px' }
        );
        io.observe(el);
        window.setTimeout(finish, 2500);
    },
});

app.mount('#store-root');
