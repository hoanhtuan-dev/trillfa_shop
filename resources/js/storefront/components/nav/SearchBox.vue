<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { apiFetch } from '../../composables/useApi.js';
import { formatMoney } from '../../composables/useFormat.js';
import Icon from '../ui/Icon.vue';

const props = defineProps({
    autoFocus: { type: Boolean, default: false },
    id: { type: String, default: 'searchbox' },
});

const open = ref(false);
const query = ref('');
const results = ref([]);
const loading = ref(false);
let timer = null;

async function runSearch() {
    const q = query.value.trim();
    if (q.length < 2) {
        results.value = [];
        open.value = false;
        return;
    }
    loading.value = true;
    try {
        const data = await apiFetch('/api/search?q=' + encodeURIComponent(q));
        results.value = data.products || [];
        open.value = true;
    } catch (e) {
        results.value = [];
    } finally {
        loading.value = false;
    }
}

function onInput() {
    clearTimeout(timer);
    timer = setTimeout(runSearch, 250);
}

function go() {
    if (query.value.trim()) window.location.href = '/shop?q=' + encodeURIComponent(query.value.trim());
}

function clear() {
    query.value = '';
    results.value = [];
    open.value = false;
}

function close(e) {
    if (!e.target.closest('#' + props.id)) open.value = false;
}

function onEscape() {
    clear();
}

onMounted(() => {
    document.addEventListener('click', close);
});

onBeforeUnmount(() => {
    clearTimeout(timer);
    document.removeEventListener('click', close);
});

function onInputEl(e) {
    open.value = true;
}
</script>

<template>
    <div :id="id" class="relative w-full">
        <div class="relative">
            <input
                v-model="query"
                type="text"
                placeholder="Tìm kiếm sản phẩm..."
                class="sf-input !rounded-full !py-2.5 pl-4 pr-16"
                @input="onInput"
                @keydown.enter.prevent="go"
                @keydown.escape.prevent="onEscape"
                @focus="onInputEl"
                :autofocus="autoFocus"
            />
            <button
                v-if="query.length > 0"
                @click="clear"
                type="button"
                class="absolute right-11 top-1/2 -translate-y-1/2 rounded-full p-1.5 text-ink-500 hover:text-ink-900"
                aria-label="Thoát tìm kiếm"
            >
                <Icon name="x" :size="16" />
            </button>
            <button
                @click="go"
                class="absolute right-1.5 top-1/2 -translate-y-1/2 rounded-full bg-ink-900 p-2 text-cream-50 transition hover:bg-brand-700"
                aria-label="Tìm kiếm"
            >
                <Icon name="search" :size="16" />
            </button>
        </div>

        <Transition name="sf">
            <div
                v-if="open && results.length"
                class="absolute z-50 mt-2 w-full overflow-hidden rounded-2xl border border-cream-200 bg-white shadow-2xl"
            >
                <a
                    v-for="p in results"
                    :key="p.id"
                    :href="p.url"
                    @click="clear"
                    class="flex items-center gap-3 border-b border-cream-100 p-3 last:border-0 hover:bg-cream-100"
                >
                    <img :src="p.image" class="h-10 w-10 rounded-lg object-cover" alt="" />
                    <div class="flex-1">
                        <p class="text-sm font-medium text-ink-900">{{ p.name }}</p>
                        <p class="text-xs text-brand-600">{{ formatMoney(p.price) }}</p>
                    </div>
                </a>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.sf-enter-active,
.sf-leave-active {
    transition: opacity 0.15s ease;
}
.sf-enter-from,
.sf-leave-to {
    opacity: 0;
}
</style>
