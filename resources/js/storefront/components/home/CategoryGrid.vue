<script setup>
import Icon from '../ui/Icon.vue';

const props = defineProps({
    categories: { type: Array, default: () => [] },
});
</script>

<template>
    <section class="sf-container py-8">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6 lg:gap-4">
            <a
                v-for="cat in categories"
                :key="cat.id"
                :href="cat.url"
                class="group card-surface card-surface-hover flex flex-col items-center gap-3 rounded-3xl p-5 text-center"
            >
                <span
                    class="grid h-16 w-16 place-items-center rounded-2xl bg-gradient-to-br from-brand-600/15 to-brand-500/5 text-brand-600 ring-1 ring-brand-600/10 transition-all duration-300 group-hover:from-brand-600 group-hover:to-brand-500 group-hover:text-white group-hover:shadow-lg group-hover:shadow-brand-600/30"
                >
                    <img v-if="cat.icon_image" :src="cat.icon_image" :alt="cat.name" class="h-9 w-9 rounded-xl object-cover" loading="lazy" />
                    <Icon v-else :name="cat.icon || 'tag'" :size="26" />
                </span>
                <span class="text-sm font-medium leading-tight text-ink-900 transition-colors group-hover:text-brand-700">{{ cat.name }}</span>
            </a>
        </div>
    </section>
</template>
