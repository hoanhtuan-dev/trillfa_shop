import { createApp } from 'vue';
import { createPinia } from 'pinia';

/**
 * Shared storefront bootstrap: registers Pinia + the v-reveal directive, cleans
 * up any stale legacy service worker, and mounts the given root component on
 * #store-root. Used by both the home and shop entries.
 */
export function createStorefrontApp(Component) {
    // The storefront pages never register a service worker themselves, but a
    // stale SW from the legacy Alpine pages may still control this page and
    // serve cached old HTML/assets ("cross-world resource mismatch"). Unregister
    // it and wipe its caches so the SPA always loads the fresh build.
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

    const app = createApp(Component);
    app.use(createPinia());

    // Lightweight scroll-reveal (fade-up) for sections. Reveals in-view
    // elements immediately and falls back to a timeout so nothing stays hidden.
    app.directive('reveal', {
        mounted(el, binding) {
            el.classList.add('reveal-anim');
            const show = (delay = Number(binding.value) || 0) =>
                window.setTimeout(() => el.classList.add('is-revealed'), delay);
            const inView = () => el.getBoundingClientRect().top < window.innerHeight - 60;
            if (inView()) { show(); return; }
            let done = false;
            const finish = () => { if (!done) { done = true; show(); } };
            if (typeof IntersectionObserver === 'undefined') { finish(); return; }
            const io = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) { io.unobserve(el); finish(); }
                });
            }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });
            io.observe(el);
            window.setTimeout(finish, 2500);
        },
    });

    app.mount('#store-root');
}
