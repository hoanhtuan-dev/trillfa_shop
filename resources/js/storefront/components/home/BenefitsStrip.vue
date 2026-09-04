<script setup>
import Icon from '../ui/Icon.vue';

const props = defineProps({
    benefits: { type: Array, default: () => [] },
});
</script>

<template>
    <section class="sf-container py-6">
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
            <div
                v-for="b in benefits"
                :key="b.title"
                class="card-surface card-surface-hover flex items-start gap-3 rounded-3xl p-4"
            >
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-brand-600/15 to-brand-500/5 text-brand-600 ring-1 ring-brand-600/10">
                    <Icon :name="b.icon" :size="20" />
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-ink-900">{{ b.title }}</p>
                    <p class="mt-0.5 text-xs leading-snug text-ink-500">{{ b.subtitle }}</p>
                </div>
            </div>
        </div>
    </section>
</template>
