<script setup>
import Icon from '../ui/Icon.vue';
import BaseButton from '../ui/BaseButton.vue';

const props = defineProps({
    cta: { type: Object, required: true },
});
</script>

<template>
    <section class="sf-container py-8 sm:py-12">
        <div class="relative overflow-hidden rounded-[2rem] bg-gradient-to-br from-ink-900 via-brand-800 to-ink-900 p-8 text-center text-cream-50 sm:p-14">
            <!-- ambient glow -->
            <div class="pointer-events-none absolute -left-16 -top-16 h-56 w-56 rounded-full bg-brand-500/30 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-24 -right-16 h-64 w-64 rounded-full bg-clay-500/20 blur-3xl"></div>
            <div class="relative mx-auto max-w-2xl">
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-brand-300">Trillfa Fa</p>
                <h2 class="mt-3 font-display text-3xl font-semibold text-white sm:text-4xl">{{ cta.title }}</h2>
                <p class="mx-auto mt-4 max-w-xl text-cream-200/90">{{ cta.subtitle }}</p>
                <BaseButton :href="cta.button_link" variant="primary" size="lg" class="mt-8 !bg-white !text-ink-900 hover:!bg-brand-50">
                    {{ cta.button_text }} <Icon name="arrow-right" :size="18" />
                </BaseButton>
            </div>
        </div>
    </section>
</template>
