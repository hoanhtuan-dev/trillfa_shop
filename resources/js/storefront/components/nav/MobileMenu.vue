<script setup>
import { computed, ref } from 'vue';
import { useStorefrontStore } from '../../store.js';
import Icon from '../ui/Icon.vue';
import SearchBox from './SearchBox.vue';

const store = useStorefrontStore();
const nav = computed(() => store.boot?.nav || []);
const user = computed(() => store.user);
const openKeys = ref([]);

function hasChildren(item) {
    return Array.isArray(item.children) && item.children.length > 0;
}
function toggle(key) {
    openKeys.value = openKeys.value.includes(key)
        ? openKeys.value.filter((k) => k !== key)
        : [...openKeys.value, key];
}
function isOpen(key) {
    return openKeys.value.includes(key);
}
</script>

<template>
    <Teleport to="body">
        <Transition name="drawer">
            <div v-if="store.menuOpen" class="fixed inset-0 z-[80] lg:hidden">
                <div class="absolute inset-0 bg-ink-900/40 backdrop-blur-sm" @click="store.closeMenu()"></div>
                <div class="absolute left-0 top-0 flex h-full w-[86%] max-w-sm flex-col overflow-y-auto bg-cream-50 shadow-2xl">
                    <div class="sticky top-0 z-10 flex items-center justify-between border-b border-cream-200 bg-cream-50/85 px-4 py-3 backdrop-blur">
                        <span class="font-display text-base font-semibold text-ink-900">Menu</span>
                        <button @click="store.closeMenu()" class="sf-btn sf-btn-ghost !p-2" aria-label="Đóng menu">
                            <Icon name="x" :size="20" />
                        </button>
                    </div>

                    <div class="px-4 py-4">
                        <SearchBox id="mobile-menu-search" />
                    </div>

                    <div class="flex-1 space-y-1 px-4 pb-6">
                        <div v-for="item in nav" :key="item.label">
                            <div class="flex items-center">
                                <a
                                    :href="item.url"
                                    @click="store.closeMenu()"
                                    class="flex-1 rounded-2xl px-4 py-3 text-sm font-medium text-ink-900 transition hover:bg-white"
                                >{{ item.label }}</a>
                                <button
                                    v-if="hasChildren(item)"
                                    @click="toggle(item.label)"
                                    class="grid h-9 w-9 place-items-center rounded-full text-ink-500 transition hover:bg-white"
                                    :aria-label="isOpen(item.label) ? 'Thu gọn' : 'Mở rộng'"
                                >
                                    <Icon name="chevron-right" :size="16" :class="isOpen(item.label) ? 'rotate-90' : ''" />
                                </button>
                            </div>
                            <Transition name="acc">
                                <div v-if="hasChildren(item) && isOpen(item.label)" class="ml-3 border-l border-cream-200 py-1 pl-3">
                                    <a
                                        v-for="child in item.children"
                                        :key="child.label"
                                        :href="child.url"
                                        @click="store.closeMenu()"
                                        class="block rounded-2xl px-3 py-2.5 text-sm text-ink-700 transition hover:bg-white"
                                    >{{ child.label }}</a>
                                </div>
                            </Transition>
                        </div>
                    </div>

                    <div class="border-t border-cream-200 p-4 pb-safe">
                        <template v-if="user.authed">
                            <a href="/tai-khoan" @click="store.closeMenu()" class="sf-btn sf-btn-primary w-full">Tài khoản của tôi</a>
                        </template>
                        <template v-else>
                            <div class="grid grid-cols-2 gap-2">
                                <a href="/dang-nhap" @click="store.closeMenu()" class="sf-btn sf-btn-soft">Đăng nhập</a>
                                <a href="/dang-ky" @click="store.closeMenu()" class="sf-btn sf-btn-primary">Đăng ký</a>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.drawer-enter-active,
.drawer-leave-active {
    transition: opacity 0.25s ease;
}
.drawer-enter-active > div:last-child,
.drawer-leave-active > div:last-child {
    transition: transform 0.25s ease;
}
.drawer-enter-from,
.drawer-leave-to {
    opacity: 0;
}
.drawer-enter-from > div:last-child,
.drawer-leave-to > div:last-child {
    transform: translateX(-100%);
}
.acc-enter-active,
.acc-leave-active {
    transition: all 0.2s ease;
}
.acc-enter-from,
.acc-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}
</style>
