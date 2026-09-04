<script setup>
import { ref, computed } from 'vue';
import Icon from '../ui/Icon.vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
    maxPrimary: { type: Number, default: 7 },
});

const path = typeof window !== 'undefined' ? window.location.pathname : '/';
const moreOpen = ref(false);
const moreRef = ref(null);

const primary = computed(() => props.items.slice(0, props.maxPrimary));
const overflow = computed(() => props.items.slice(props.maxPrimary));
const hasMore = computed(() => overflow.value.length > 0);

function hasChildren(item) {
    return Array.isArray(item.children) && item.children.length > 0;
}
function isActive(url) {
    if (!url) return false;
    if (url === '/') return path === '/';
    return path === url || path.startsWith(url.endsWith('/') ? url : url + '/');
}
function onDocClick(e) {
    if (moreOpen.value && moreRef.value && !moreRef.value.contains(e.target)) moreOpen.value = false;
}
if (typeof window !== 'undefined') document.addEventListener('click', onDocClick);
</script>

<template>
    <nav class="hidden h-12 items-center gap-1 lg:flex">
        <template v-for="item in primary" :key="item.label">
            <div class="relative">
                <a
                    :href="item.url"
                    class="group relative flex h-7 items-center gap-1 whitespace-nowrap rounded-full px-3 text-sm font-medium transition-all duration-200"
                    :class="isActive(item.url) ? 'bg-brand-600/10 text-brand-700' : 'text-ink-700 hover:bg-cream-100/80 hover:text-brand-700'"
                >
                    {{ item.label }}
                    <Icon v-if="hasChildren(item)" name="chevron-right" :size="13" class="rotate-90 opacity-60 transition-transform duration-200 group-hover:rotate-180" />
                </a>

                <!-- Nested dropdown -->
                <div
                    v-if="hasChildren(item)"
                    class="glass-strong absolute left-0 top-full z-50 mt-2 hidden min-w-56 rounded-2xl p-2 shadow-xl group-hover:block"
                >
                    <template v-for="child in item.children" :key="child.label">
                        <a
                            v-if="!hasChildren(child)"
                            :href="child.url"
                            class="block whitespace-nowrap rounded-xl px-3 py-2 text-sm text-ink-700 transition-colors hover:bg-brand-600/10 hover:text-brand-700"
                        >{{ child.label }}</a>
                        <div v-else class="relative">
                            <a :href="child.url" class="flex items-center justify-between rounded-xl px-3 py-2 text-sm text-ink-700 transition-colors hover:bg-brand-600/10 hover:text-brand-700">
                                <span>{{ child.label }}</span>
                                <Icon name="chevron-right" :size="13" class="opacity-60" />
                            </a>
                            <div class="absolute left-full top-0 hidden pl-1 group-hover:block">
                                <div class="glass-strong min-w-48 rounded-2xl p-2">
                                    <a
                                        v-for="gc in child.children"
                                        :key="gc.label"
                                        :href="gc.url"
                                        class="block whitespace-nowrap rounded-xl px-3 py-2 text-sm text-ink-700 transition-colors hover:bg-brand-600/10 hover:text-brand-700"
                                    >{{ gc.label }}</a>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        <!-- Overflow into "Xem thêm" -->
        <div v-if="hasMore" ref="moreRef" class="relative">
            <button
                @click="moreOpen = !moreOpen"
                class="flex h-7 items-center gap-1 whitespace-nowrap rounded-full px-3 text-sm font-medium text-ink-700 transition-colors hover:bg-cream-100/80 hover:text-brand-700"
            >
                Xem thêm
                <Icon name="chevron-right" :size="13" class="rotate-90 opacity-60" :class="moreOpen ? 'rotate-180' : ''" />
            </button>
            <div v-if="moreOpen" class="glass-strong absolute right-0 top-full z-50 mt-2 w-72 rounded-2xl p-2 shadow-xl">
                <template v-for="item in overflow" :key="item.label">
                    <a :href="item.url" class="block whitespace-nowrap rounded-xl px-3 py-2 text-sm transition-colors hover:bg-brand-600/10 hover:text-brand-700" :class="isActive(item.url) ? 'bg-brand-600/10 text-brand-700' : 'text-ink-700'">{{ item.label }}</a>
                    <div v-if="hasChildren(item)" class="ml-3 border-l border-cream-200 pl-2">
                        <a
                            v-for="child in item.children"
                            :key="child.label"
                            :href="child.url"
                            class="block whitespace-nowrap rounded-xl px-3 py-1.5 text-[13px] transition-colors hover:text-brand-700"
                            :class="isActive(child.url) ? 'font-semibold text-brand-700' : 'text-ink-500'"
                        >{{ child.label }}</a>
                    </div>
                </template>
            </div>
        </div>
    </nav>
</template>
