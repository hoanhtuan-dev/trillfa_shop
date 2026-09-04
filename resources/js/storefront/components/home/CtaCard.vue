<script setup>
import Icon from '../ui/Icon.vue';
import BaseButton from '../ui/BaseButton.vue';

const props = defineProps({
    cta: { type: Object, required: true },
});
</script>

<template>
    <section class="sf-container py-12">
        <div class="card-surface relative flex flex-col items-center gap-4 overflow-hidden p-10 text-center">
            <!-- ambient gradient blobs -->
            <div class="pointer-events-none absolute -left-16 -top-16 h-56 w-56 rounded-full bg-brand-600/20 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-20 -right-16 h-64 w-64 rounded-full bg-clay-500/10 blur-3xl"></div>
            <div class="relative">
                <h2 class="font-display text-2xl font-semibold text-ink-900 sm:text-3xl">{{ cta.title }}</h2>
                <p class="mx-auto mt-3 max-w-lg text-ink-500">{{ cta.subtitle }}</p>
                <BaseButton :href="cta.button_link" variant="primary" size="lg" class="mt-6">
                    {{ cta.button_text }} <Icon name="arrow-right" :size="18" />
                </BaseButton>
            </div>
        </div>
    </section>
</template>
