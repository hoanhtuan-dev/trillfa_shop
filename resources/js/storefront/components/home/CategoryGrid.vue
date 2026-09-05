<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import Icon from '../ui/Icon.vue';

const props = defineProps({
    categories: { type: Array, default: () => [] },
});

const grid = ref(null);
const visible = ref(false);
let io = null;

onMounted(() => {
    if (typeof IntersectionObserver === 'undefined' || !grid.value) {
        visible.value = true;
        return;
    }
    io = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                visible.value = true;
                io.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    io.observe(grid.value);
    // Safety net: never leave tiles hidden if the observer misbehaves.
    window.setTimeout(() => { visible.value = true; }, 1800);
});

onBeforeUnmount(() => { if (io) io.disconnect(); });
</script>

<template>
    <div
        ref="grid"
        class="cat-grid"
        :class="{ 'is-visible': visible }"
        role="list"
    >
        <a
            v-for="(cat, i) in categories"
            :key="cat.id"
            :href="cat.url"
            class="cat-tile group"
            :style="{ '--i': i }"
            role="listitem"
            :aria-label="cat.name"
        >
            <!-- Image (database cover) -->
            <img
                v-if="cat.image"
                :src="cat.image"
                :alt="cat.name"
                class="cat-img"
                loading="lazy"
            />

            <!-- Graceful fallback when no cover image -->
            <div
                v-else
                class="cat-fallback"
                :class="cat.icon ? '' : 'cat-fallback--plain'"
            >
                <span class="cat-fallback-glow"></span>
                <Icon :name="cat.icon || 'tag'" :size="40" class="cat-fallback-icon" />
            </div>

            <!-- Thumbnail icon chip (when a full image exists + icon_image present) -->
            <span
                v-if="cat.image && cat.icon_image"
                class="cat-chip"
            >
                <img :src="cat.icon_image" :alt="''" class="h-6 w-6 rounded-lg object-cover" loading="lazy" />
            </span>

            <!-- Index badge -->
            <span class="cat-index">{{ String(i + 1).padStart(2, '0') }}</span>

            <!-- Gradient legibility overlay -->
            <span class="cat-overlay"></span>

            <!-- Hover sheen sweep -->
            <span class="cat-sheen" aria-hidden="true"></span>

            <!-- Caption -->
            <span class="cat-caption">
                <span class="cat-name">{{ cat.name }}</span>
                <span class="cat-cta">
                    Khám phá
                    <Icon name="arrow-right" :size="14" class="cat-cta-arrow" />
                </span>
            </span>
        </a>
    </div>
</template>

<style scoped>
.cat-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem;
}
@media (min-width: 640px) {
    .cat-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; }
}
@media (min-width: 1024px) {
    .cat-grid { grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 1.125rem; }
}

/* --- Tile shell --- */
.cat-tile {
    position: relative;
    display: block;
    aspect-ratio: 4 / 5;
    overflow: hidden;
    border-radius: 1.5rem;
    border: 1px solid rgba(233, 229, 219, 0.9);
    background: linear-gradient(145deg, #ffffff 0%, #f4f2ec 100%);
    box-shadow:
        0 1px 0 rgba(255, 255, 255, 0.6) inset,
        0 10px 30px -18px rgba(11, 18, 16, 0.35);
    isolation: isolate;
    text-decoration: none;
    /* Entrance (staggered via --i) */
    opacity: 0;
    transform: translateY(22px) scale(0.96);
    transition:
        transform 0.45s cubic-bezier(0.22, 1, 0.36, 1),
        box-shadow 0.45s cubic-bezier(0.22, 1, 0.36, 1),
        opacity 0.6s cubic-bezier(0.22, 1, 0.36, 1);
}
.cat-grid.is-visible .cat-tile {
    opacity: 1;
    transform: translateY(0) scale(1);
    transition-delay: calc(var(--i, 0) * 80ms);
}

/* Card hover lift + glow */
.cat-tile:hover {
    transform: translateY(-6px);
    box-shadow:
        0 1px 0 rgba(255, 255, 255, 0.8) inset,
        0 26px 50px -22px rgba(11, 18, 16, 0.55),
        0 0 0 1px rgba(45, 111, 77, 0.25),
        0 18px 40px -18px rgba(45, 111, 77, 0.45);
}

/* --- Image --- */
.cat-img {
    position: absolute;
    inset: 0;
    height: 100%;
    width: 100%;
    object-fit: cover;
    transform: scale(1.02);
    transition: transform 0.85s cubic-bezier(0.22, 1, 0.36, 1), filter 0.45s ease;
    will-change: transform;
}
.cat-tile:hover .cat-img {
    transform: scale(1.14);
    filter: saturate(1.12) contrast(1.04) brightness(0.98);
}

/* --- Fallback panel (no DB image) --- */
.cat-fallback {
    position: absolute;
    inset: 0;
    display: grid;
    place-items: center;
    background:
        radial-gradient(120% 120% at 20% 10%, rgba(85, 155, 120, 0.32), transparent 55%),
        radial-gradient(120% 120% at 90% 90%, rgba(178, 106, 59, 0.22), transparent 55%),
        linear-gradient(150deg, #265d40 0%, #193d2b 55%, #17150f 100%);
    color: #f4f2ec;
}
.cat-fallback--plain {
    background: linear-gradient(150deg, #f4f2ec 0%, #e9e5db 100%);
    color: #2d6f4d;
}
.cat-fallback-icon {
    position: relative;
    z-index: 1;
    transition: transform 0.55s cubic-bezier(0.22, 1, 0.36, 1);
}
.cat-tile:hover .cat-fallback-icon {
    transform: scale(1.12) rotate(-6deg);
}
.cat-fallback-glow {
    position: absolute;
    width: 70%;
    height: 70%;
    border-radius: 999px;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.35), transparent 70%);
    filter: blur(18px);
    opacity: 0.55;
    transition: opacity 0.5s ease, transform 0.6s ease;
}
.cat-tile:hover .cat-fallback-glow {
    opacity: 0.9;
    transform: scale(1.25);
}

/* --- Top-left icon chip (full image + icon_image present) --- */
.cat-chip {
    position: absolute;
    top: 0.6rem;
    left: 0.6rem;
    z-index: 3;
    display: inline-grid;
    place-items: center;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 0.85rem;
    background: rgba(255, 255, 255, 0.82);
    border: 1px solid rgba(255, 255, 255, 0.7);
    box-shadow: 0 6px 16px -8px rgba(11, 18, 16, 0.5);
    backdrop-filter: blur(6px);
    opacity: 0;
    transform: translateY(-6px) scale(0.9);
    transition: opacity 0.35s ease, transform 0.35s cubic-bezier(0.22, 1, 0.36, 1);
}
.cat-tile:hover .cat-chip {
    opacity: 1;
    transform: translateY(0) scale(1);
}

/* --- Index badge --- */
.cat-index {
    position: absolute;
    top: 0.6rem;
    right: 0.6rem;
    z-index: 3;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 1.5rem;
    min-width: 1.5rem;
    padding: 0 0.4rem;
    border-radius: 999px;
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    color: #faf9f6;
    background: rgba(11, 18, 16, 0.42);
    border: 1px solid rgba(255, 255, 255, 0.18);
    backdrop-filter: blur(6px);
}

/* --- Gradient overlay --- */
.cat-overlay {
    position: absolute;
    inset: 0;
    z-index: 2;
    background:
        linear-gradient(to top, rgba(13, 20, 16, 0.86) 0%, rgba(13, 20, 16, 0.45) 32%, rgba(13, 20, 16, 0.05) 60%, transparent 100%),
        linear-gradient(to bottom, rgba(13, 20, 16, 0.28) 0%, transparent 28%);
    transition: opacity 0.45s ease, background 0.45s ease;
}
.cat-tile:hover .cat-overlay {
    background:
        linear-gradient(to top, rgba(13, 20, 16, 0.92) 0%, rgba(13, 20, 16, 0.55) 42%, rgba(13, 20, 16, 0.18) 72%, transparent 100%),
        linear-gradient(to bottom, rgba(13, 20, 16, 0.42) 0%, transparent 34%);
}

/* --- Sheen sweep on hover --- */
.cat-sheen {
    position: absolute;
    inset: 0;
    z-index: 3;
    pointer-events: none;
    background: linear-gradient(115deg, transparent 30%, rgba(255, 255, 255, 0.35) 48%, transparent 62%);
    transform: translateX(-130%);
    transition: transform 0.9s cubic-bezier(0.22, 1, 0.36, 1);
    mix-blend-mode: overlay;
}
.cat-tile:hover .cat-sheen {
    transform: translateX(130%);
}

/* --- Caption --- */
.cat-caption {
    position: absolute;
    inset: auto 0 0 0;
    z-index: 4;
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    padding: 0.75rem 0.85rem 0.8rem;
    color: #faf9f6;
}
.cat-name {
    font-family: 'Fraunces', ui-serif, Georgia, serif;
    font-size: 0.95rem;
    font-weight: 600;
    line-height: 1.15;
    letter-spacing: -0.01em;
    text-shadow: 0 1px 14px rgba(0, 0, 0, 0.35);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    transition: color 0.3s ease;
}
.cat-tile:hover .cat-name {
    color: #dcece1;
}
.cat-cta {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.72rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    color: rgba(220, 236, 225, 0.85);
    opacity: 0;
    transform: translateY(6px);
    transition: opacity 0.35s ease, transform 0.35s cubic-bezier(0.22, 1, 0.36, 1);
}
.cat-tile:hover .cat-cta {
    opacity: 1;
    transform: translateY(0);
}
.cat-cta-arrow {
    transition: transform 0.35s cubic-bezier(0.22, 1, 0.36, 1);
}
.cat-tile:hover .cat-cta-arrow {
    transform: translateX(4px);
}

/* --- Reduced motion --- */
@media (prefers-reduced-motion: reduce) {
    .cat-tile,
    .cat-img,
    .cat-fallback-icon,
    .cat-fallback-glow,
    .cat-chip,
    .cat-overlay,
    .cat-sheen,
    .cat-cta,
    .cat-cta-arrow {
        transition: none !important;
        animation: none !important;
    }
    .cat-tile { opacity: 1 !important; transform: none !important; }
    .cat-sheen { display: none; }
}
</style>
