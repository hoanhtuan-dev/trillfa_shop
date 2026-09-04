<script setup>
import { useStorefrontStore } from '../../store.js';

const store = useStorefrontStore();
</script>

<template>
    <div class="pointer-events-none fixed inset-x-0 top-4 z-[95] flex flex-col items-center gap-2 px-4">
        <TransitionGroup name="toast">
            <div
                v-for="t in store.toasts"
                :key="t.id"
                class="pointer-events-auto flex items-center gap-3 rounded-2xl border px-4 py-3 text-sm font-medium shadow-xl backdrop-blur-xl"
                :class="t.type === 'error' ? 'border-red-500/30 bg-red-500/90 text-white' : 'glass-strong text-ink-900'"
            >
                <span
                    class="grid h-5 w-5 shrink-0 place-items-center rounded-full text-white"
                    :class="t.type === 'error' ? 'bg-red-700' : 'bg-brand-600'"
                >
                    <svg viewBox="0 0 24 24" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                </span>
                {{ t.message }}
            </div>
        </TransitionGroup>
    </div>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: all 0.3s ease;
}
.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}
</style>
