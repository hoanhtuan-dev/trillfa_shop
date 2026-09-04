<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import Icon from '../ui/Icon.vue';
import BaseButton from '../ui/BaseButton.vue';

const props = defineProps({
    slides: { type: Array, default: () => [] },
});

const index = ref(0);
let timer = null;

const count = computed(() => props.slides.length);

function goTo(i) {
    index.value = (i + count.value) % count.value;
}

function start() {
    if (count.value <= 1) return;
    timer = setInterval(() => goTo(index.value + 1), 6000);
}

function stop() {
    if (timer) clearInterval(timer);
    timer = null;
}

onMounted(start);
onBeforeUnmount(stop);
</script>

<template>
    <section
        v-if="slides.length"
        class="relative overflow-hidden"
        @mouseenter="stop"
        @mouseleave="start"
    >
        <!-- Slides track -->
        <div class="relative">
            <div
                class="flex transition-transform duration-[900ms] ease-[cubic-bezier(0.22,1,0.36,1)]"
                :style="{ transform: 'translateX(-' + index * 100 + '%)' }"
            >
                <div v-for="(slide, i) in slides" :key="slide.id" class="relative w-full shrink-0">
                    <div class="relative h-[60vh] min-h-[460px] w-full sm:h-[70vh]">
                        <img :src="slide.image" :alt="slide.title" class="absolute inset-0 h-full w-full object-cover" loading="eager" />
                        <!-- gradient overlay -->
                        <div class="absolute inset-0 bg-gradient-to-r from-ink-950/80 via-ink-900/40 to-transparent"></div>
                        <!-- glass content card -->
                        <div class="sf-container relative z-10 flex h-full items-center pb-6">
                            <div class="w-full max-w-xl min-w-0">
                                <div class="glass max-w-full !rounded-[2rem] p-6 sm:p-9">
                                    <p class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.22em] text-brand-200">
                                        <span class="h-px w-6 bg-brand-200/70"></span>{{ slide.subtitle }}
                                    </p>
                                    <h1 class="mt-3 font-display text-3xl font-semibold leading-tight text-white text-balance sm:text-5xl lg:text-6xl">{{ slide.title }}</h1>
                                    <BaseButton
                                        v-if="slide.button_text"
                                        :href="slide.button_link"
                                        variant="primary"
                                        size="lg"
                                        class="mt-6 !bg-white !text-ink-900 shadow-lg hover:!bg-brand-50"
                                    >
                                        {{ slide.button_text }} <Icon name="arrow-right" :size="18" />
                                    </BaseButton>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Prev / Next (desktop) -->
            <template v-if="count > 1">
                <button
                    @click="goTo(index - 1)"
                    class="absolute left-4 top-1/2 z-20 hidden h-11 w-11 -translate-y-1/2 place-items-center rounded-full glass text-ink-900 shadow-lg transition-transform hover:scale-110 lg:grid"
                    aria-label="Bài trước"
                >
                    <Icon name="chevron-left" :size="22" />
                </button>
                <button
                    @click="goTo(index + 1)"
                    class="absolute right-4 top-1/2 z-20 hidden h-11 w-11 -translate-y-1/2 place-items-center rounded-full glass text-ink-900 shadow-lg transition-transform hover:scale-110 lg:grid"
                    aria-label="Bài sau"
                >
                    <Icon name="chevron-right" :size="22" />
                </button>
            </template>

            <!-- Dots -->
            <div v-if="count > 1" class="absolute bottom-6 left-1/2 z-20 flex -translate-x-1/2 gap-2">
                <button
                    v-for="(slide, i) in slides"
                    :key="'dot' + i"
                    @click="goTo(i)"
                    class="h-2 rounded-full transition-all"
                    :class="index === i ? 'w-8 bg-white' : 'w-2 bg-white/50 hover:bg-white/80'"
                    :aria-label="'Slide ' + (i + 1)"
                ></button>
            </div>
        </div>
    </section>
</template>
