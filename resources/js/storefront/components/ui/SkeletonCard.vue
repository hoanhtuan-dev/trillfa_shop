<script setup>
defineProps({
    count: { type: Number, default: 4 },
});
</script>

<template>
    <div class="grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 lg:grid-cols-4">
        <div v-for="i in count" :key="i" class="animate-pulse">
            <div class="aspect-[4/5] rounded-3xl bg-cream-200/70"></div>
            <div class="mt-3 h-3 w-3/4 rounded bg-cream-200/70"></div>
            <div class="mt-2 h-3 w-1/2 rounded bg-cream-200/70"></div>
        </div>
    </div>
</template>
