<script setup>
import { ref, computed } from 'vue';
import Icon from '../ui/Icon.vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
    maxPrimary: { type: Number, default: 7 },
});

const moreOpen = ref(false);
const moreRef = ref(null);

const primary = computed(() => props.items.slice(0, props.maxPrimary));
const overflow = computed(() => props.items.slice(props.maxPrimary));
const hasMore = computed(() => overflow.value.length > 0);

function onDocClick(e) {
    if (moreOpen.value && moreRef.value && !moreRef.value.contains(e.target)) moreOpen.value = false;
}
if (typeof window !== 'undefined') document.addEventListener('click', onDocClick);

// Renders a dropdown list of children (supports one nested level).
function hasChildren(item) {
    return Array.isArray(item.children) && item.children.length > 0;
}
</script>

<template>
    <nav class="hidden h-12 items-center gap-0.5 overflow-hidden lg:flex">
        <a
            v-for="item in primary"
            :key="item.label"
            :href="item.url"
            class="group relative flex h-full shrink-0 items-center whitespace-nowrap rounded-lg px-3 text-sm font-medium text-ink-700 transition-colors hover:text-brand-700"
        >
            {{ item.label }}
            <Icon v-if="hasChildren(item)" name="chevron-right" :size="13" class="ml-0.5 rotate-90 opacity-60" />

            <!-- Nested dropdown -->
            <div
                v-if="hasChildren(item)"
                class="glass-strong absolute left-0 top-full z-50 hidden min-w-56 rounded-2xl p-2 group-hover:block"
            >
                <template v-for="child in item.children" :key="child.label">
                    <a
                        v-if="!hasChildren(child)"
                        :href="child.url"
                        class="block whitespace-nowrap rounded-xl px-3 py-2 text-sm text-ink-700 hover:bg-cream-100"
                    >{{ child.label }}</a>
                    <div v-else class="relative rounded-xl">
                        <a :href="child.url" class="flex items-center justify-between whitespace-nowrap rounded-xl px-3 py-2 text-sm text-ink-700 hover:bg-cream-100">
                            <span>{{ child.label }}</span>
                            <Icon name="chevron-right" :size="13" class="opacity-60" />
                        </a>
                        <div class="absolute left-full top-0 hidden pl-1 group-hover:block">
                            <div class="glass-strong min-w-48 rounded-2xl p-2">
                                <a
                                    v-for="gc in child.children"
                                    :key="gc.label"
                                    :href="gc.url"
                                    class="block whitespace-nowrap rounded-xl px-3 py-2 text-sm text-ink-700 hover:bg-cream-100"
                                >{{ gc.label }}</a>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </a>

        <!-- Overflow into "Xem thêm" -->
        <div v-if="hasMore" ref="moreRef" class="relative">
            <button
                @click="moreOpen = !moreOpen"
                class="flex h-full items-center gap-1 whitespace-nowrap rounded-lg px-3 text-sm font-medium text-ink-700 transition-colors hover:text-brand-700"
            >
                Xem thêm
                <Icon name="chevron-right" :size="13" class="rotate-90 opacity-60" />
            </button>
            <div v-if="moreOpen" class="glass-strong absolute right-0 top-full z-50 mt-1 w-64 rounded-2xl p-2">
                <template v-for="item in overflow" :key="item.label">
                    <a :href="item.url" class="block whitespace-nowrap rounded-xl px-3 py-2 text-sm text-ink-700 hover:bg-cream-100">{{ item.label }}</a>
                    <div v-if="hasChildren(item)" class="ml-3 border-l border-cream-200 pl-2">
                        <a
                            v-for="child in item.children"
                            :key="child.label"
                            :href="child.url"
                            class="block whitespace-nowrap rounded-xl px-3 py-1.5 text-[13px] text-ink-500 hover:text-brand-700"
                        >{{ child.label }}</a>
                    </div>
                </template>
            </div>
        </div>
    </nav>
</template>
