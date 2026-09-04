<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    endsAt: { type: String, default: '' },
});

const now = ref(Date.now());
let timer = null;

const target = computed(() => (props.endsAt ? new Date(props.endsAt).getTime() : 0));
const remaining = computed(() => Math.max(0, target.value - now.value));

const parts = computed(() => {
    const s = Math.floor(remaining.value / 1000);
    return {
        days: Math.floor(s / 86400),
        hours: Math.floor((s % 86400) / 3600),
        minutes: Math.floor((s % 3600) / 60),
        seconds: s % 60,
    };
});
const active = computed(() => target.value > now.value);
const pad = (n) => String(n).padStart(2, '0');

function tick() {
    now.value = Date.now();
    if (!active.value && timer) {
        clearInterval(timer);
        timer = null;
    }
}
onMounted(() => {
    tick();
    timer = setInterval(tick, 1000);
});
onBeforeUnmount(() => clearInterval(timer));
</script>

<template>
    <div v-if="active" class="inline-flex items-center gap-2">
        <span class="text-xs font-semibold uppercase tracking-wide text-brand-300">Kết thúc sau:</span>
        <div class="flex items-center gap-1">
            <span v-if="parts.days > 0" class="grid h-7 min-w-7 place-items-center rounded-lg bg-white/10 px-1 font-display text-sm font-semibold text-white">{{ pad(parts.days) }}</span>
            <span v-if="parts.days > 0" class="text-brand-300">:</span>
            <span class="grid h-7 min-w-7 place-items-center rounded-lg bg-white/10 px-1 font-display text-sm font-semibold text-white">{{ pad(parts.hours) }}</span><span class="text-brand-300">:</span>
            <span class="grid h-7 min-w-7 place-items-center rounded-lg bg-white/10 px-1 font-display text-sm font-semibold text-white">{{ pad(parts.minutes) }}</span><span class="text-brand-300">:</span>
            <span class="grid h-7 min-w-7 place-items-center rounded-lg bg-white/10 px-1 font-display text-sm font-semibold text-white">{{ pad(parts.seconds) }}</span>
        </div>
    </div>
</template>
