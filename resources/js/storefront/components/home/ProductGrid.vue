<script setup>
import ProductCard from './ProductCard.vue';

const props = defineProps({
    products: { type: Array, default: () => [] },
});
</script>

<template>
    <div class="grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 lg:grid-cols-4">
        <ProductCard v-for="p in products" :key="p.id" :product="p" />
    </div>
</template>
