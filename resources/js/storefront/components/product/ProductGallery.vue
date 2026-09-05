<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import Icon from '../ui/Icon.vue';

/**
 * ProductGallery — ảnh sản phẩm "fit" trọn vẹn trong khung khi mở trang
 * (object-contain, không cắt ảnh) và zoom mượt mà theo vị trí trỏ chuột:
 *  - Desktop: rê chuột để phóng (2.2x), tâm zoom bám theo con trỏ một cách
 *    trơn tru (transition cho cả transform lẫn transform-origin).
 *  - Touch: chạm một lần để phóng tại điểm chạm, chạm lại để thu về.
 *  - Bàn phím: Enter / Space bật–tắt zoom tại tâm khung.
 *  - Tôn trọng prefers-reduced-motion (bỏ transition).
 */

const props = defineProps({
    src: { type: String, default: '' },
    alt: { type: String, default: '' },
});

const ZOOM = 2.2;

const frame = ref(null);
const zoomed = ref(false);
const origin = ref({ x: 50, y: 50 });
const finePointer = ref(true);   // mouse/trackpad (hover) vs touch (tap)
const reducedMotion = ref(false);

onMounted(() => {
    finePointer.value = !(window.matchMedia?.('(pointer: coarse)')?.matches ?? false);
    reducedMotion.value = window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches ?? false;
});

// Đổi ảnh (bấm thumbnail) → về trạng thái zoom ban đầu, ảnh fit trong khung.
watch(() => props.src, () => {
    zoomed.value = false;
    origin.value = { x: 50, y: 50 };
});

// Vị trí con trỏ dạng % so với khung ảnh (bị chặn trong [0, 100]).
function position(e) {
    const el = frame.value;
    if (!el) return { x: 50, y: 50 };
    const rect = el.getBoundingClientRect();
    if (!rect.width || !rect.height) return { x: 50, y: 50 };
    const x = ((e.clientX - rect.left) / rect.width) * 100;
    const y = ((e.clientY - rect.top) / rect.height) * 100;
    return {
        x: Math.min(100, Math.max(0, x)),
        y: Math.min(100, Math.max(0, y)),
    };
}

function onEnter(e) {
    if (!finePointer.value) return; // touch xử lý bằng tap
    origin.value = position(e);
    zoomed.value = true;
}

function onMove(e) {
    if (!zoomed.value) return;
    origin.value = position(e);
}

function onLeave() {
    zoomed.value = false;
    origin.value = { x: 50, y: 50 };
}

// Touch: chạm bật/tắt zoom tại điểm chạm; bàn phím: zoom giữa khung.
function toggleAt(x, y) {
    if (zoomed.value) {
        zoomed.value = false;
        origin.value = { x: 50, y: 50 };
    } else {
        origin.value = { x, y };
        zoomed.value = true;
    }
}

function onClick(e) {
    if (finePointer.value) return; // hover đã zoom, click không tranh chấp
    toggleAt(...Object.values(position(e)));
}

function onKeydown(e) {
    if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        toggleAt(50, 50);
    }
}

const imgStyle = computed(() => ({
    transform: zoomed.value ? `scale(${ZOOM})` : 'scale(1)',
    transformOrigin: `${origin.value.x}% ${origin.value.y}%`,
    transition: reducedMotion.value
        ? 'none'
        : 'transform 0.28s cubic-bezier(0.22, 1, 0.36, 1), transform-origin 0.16s ease-out',
    willChange: 'transform',
}));

const frameStyle = computed(() => ({
    aspectRatio: '4 / 5',
    maxHeight: 'min(72vh, 44rem)',
}));

const hintText = computed(() => (finePointer.value ? 'Di chuột để phóng to' : 'Chạm để phóng to'));
</script>

<template>
    <div
        ref="frame"
        data-zoom-frame
        class="zoom-backdrop relative w-full cursor-zoom-in touch-manipulation select-none overflow-hidden"
        :style="frameStyle"
        role="group"
        aria-label="Xem ảnh sản phẩm — di chuột hoặc chạm để phóng to"
        tabindex="0"
        @mouseenter="onEnter"
        @mousemove="onMove"
        @mouseleave="onLeave"
        @click="onClick"
        @keydown="onKeydown"
    >
        <img
            :key="src"
            :src="src"
            :alt="alt"
            data-zoom-img
            class="h-full w-full object-contain"
            draggable="false"
            :style="imgStyle"
        />

        <!-- Gợi ý zoom (ẩn nhẹ khi đang phóng) -->
        <span
            class="pointer-events-none absolute bottom-3 right-3 z-10 inline-flex items-center gap-1.5 rounded-full bg-ink-900/55 px-2.5 py-1 text-[10px] font-medium text-cream-50 backdrop-blur transition-opacity duration-300"
            :class="zoomed ? 'opacity-0' : 'opacity-100'"
        >
            <Icon name="search" :size="13" />
            {{ hintText }}
        </span>
    </div>
</template>

<style scoped>
/* Phông studio mềm cho ảnh nền — ảnh được object-contain nên phần thừa
   hiển thị nền này thay vì bị cắt. */
.zoom-backdrop {
    background:
        radial-gradient(130% 100% at 30% 18%, #faf9f6 0%, #f3f0e9 52%, #e9e4d8 100%);
}
</style>
