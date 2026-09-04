<script setup>
import { ref, computed } from 'vue';
import { useStorefrontStore } from '../../store.js';
import Icon from '../ui/Icon.vue';
import { apiFetch } from '../../composables/useApi.js';

const store = useStorefrontStore();

const email = ref('');
const sending = ref(false);

const site = computed(() => store.site);
const nav = computed(() => store.boot?.nav || []);
const categories = computed(() => store.boot?.categories?.items || []);
const contact = computed(() => store.boot?.contact || {});

const socials = [
    { icon: 'facebook', href: 'https://facebook.com' },
    { icon: 'instagram', href: 'https://instagram.com' },
    { icon: 'youtube', href: 'https://youtube.com' },
];

async function subscribe() {
    if (!email.value.trim()) return;
    sending.value = true;
    try {
        await apiFetch('/dang-ky-ban-tin', {
            method: 'POST',
            body: { email: email.value.trim() },
        });
        store.toast('Đăng ký nhận tin thành công');
        email.value = '';
    } catch (e) {
        store.toast(e.message, 'error');
    } finally {
        sending.value = false;
    }
}

const year = new Date().getFullYear();
</script>

<template>
    <footer class="relative mt-16 overflow-hidden">
        <div class="sf-container">
            <!-- Newsletter card (floats above footer) -->
            <div class="glass-strong relative -mb-10 z-10 rounded-[2rem] p-6 sm:p-10">
                <div class="flex flex-col items-start justify-between gap-6 lg:flex-row lg:items-center">
                    <div class="max-w-md">
                        <h3 class="font-display text-2xl font-semibold text-ink-900">Nhận ưu đãi &amp; xu hướng mới</h3>
                        <p class="mt-2 text-sm text-ink-500">Đăng ký nhận thông tin về bộ sưu tập mới và khuyến mãi độc quyền.</p>
                    </div>
                    <form class="flex w-full max-w-md flex-col gap-3 sm:flex-row sm:items-center" @submit.prevent="subscribe">
                        <input v-model="email" type="email" required placeholder="Email của bạn" class="sf-input flex-1" />
                        <button type="submit" class="sf-btn sf-btn-primary w-full shrink-0 sm:w-auto" :disabled="sending">
                            {{ sending ? 'Đang gửi…' : 'Đăng ký' }}
                        </button>
                    </form>
                </div>
            </div>

            <!-- Main footer -->
            <div class="rounded-t-[2rem] bg-ink-900 px-6 pb-8 pt-20 text-cream-300 sm:px-10 lg:px-16">
                <div class="grid gap-10 md:grid-cols-2 lg:grid-cols-4">
                    <!-- Brand -->
                    <div>
                        <a href="/" class="flex items-center gap-2.5">
                            <img :src="site.logo" :alt="site.name" class="h-9 w-auto object-contain" loading="lazy" />
                            <span class="font-display text-xl font-bold text-cream-50">Trillfa<span class="text-brand-300"> Fa</span></span>
                        </a>
                        <p class="mt-4 text-sm leading-relaxed text-cream-300/70">
                            Trillfa Fa — thời trang &amp; phong cách cho cuộc sống hiện đại.
                        </p>
                        <div class="mt-5 flex gap-2">
                            <a v-for="s in socials" :key="s.icon" :href="s.href" class="grid h-9 w-9 place-items-center rounded-full bg-white/5 text-cream-200 transition hover:bg-brand-600 hover:text-white">
                                <Icon :name="s.icon" :size="18" />
                            </a>
                        </div>
                    </div>

                    <!-- Categories -->
                    <div v-if="categories.length">
                        <h4 class="text-sm font-semibold uppercase tracking-wide text-cream-50">Danh mục</h4>
                        <ul class="mt-4 space-y-2.5 text-sm">
                            <li v-for="c in categories" :key="c.id">
                                <a :href="c.url" class="text-cream-300/80 transition hover:text-cream-50">{{ c.name }}</a>
                            </li>
                        </ul>
                    </div>

                    <!-- Quick links -->
                    <div>
                        <h4 class="text-sm font-semibold uppercase tracking-wide text-cream-50">Liên kết</h4>
                        <ul class="mt-4 space-y-2.5 text-sm">
                            <li><a href="/shop" class="text-cream-300/80 transition hover:text-cream-50">Cửa hàng</a></li>
                            <li><a href="/blog" class="text-cream-300/80 transition hover:text-cream-50">Blog</a></li>
                            <li><a href="/gioi-thieu" class="text-cream-300/80 transition hover:text-cream-50">Giới thiệu</a></li>
                            <li><a href="/lien-he" class="text-cream-300/80 transition hover:text-cream-50">Liên hệ</a></li>
                            <li><a href="/hoi-dap" class="text-cream-300/80 transition hover:text-cream-50">Câu hỏi thường gặp</a></li>
                        </ul>
                    </div>

                    <!-- Contact -->
                    <div>
                        <h4 class="text-sm font-semibold uppercase tracking-wide text-cream-50">Liên hệ</h4>
                        <ul class="mt-4 space-y-3 text-sm">
                            <li class="flex items-center gap-3"><Icon name="phone" :size="16" class="shrink-0 text-brand-300" />{{ contact.hotline || '1900 0000' }}</li>
                            <li class="flex items-center gap-3"><Icon name="mail" :size="16" class="shrink-0 text-brand-300" />{{ contact.email }}</li>
                            <li v-if="contact.address" class="flex items-start gap-3"><Icon name="map-pin" :size="16" class="mt-0.5 shrink-0 text-brand-300" />{{ contact.address }}</li>
                        </ul>
                        <a v-if="nav.length" :href="nav[0].url" class="sf-btn sf-btn-soft mt-5 !border-white/10 !bg-white/5 !text-cream-100">{{ nav[0].label }}</a>
                    </div>
                </div>

                <div class="mt-12 flex flex-col items-center justify-between gap-3 border-t border-white/10 pt-6 text-xs text-cream-300/60 sm:flex-row">
                    <p>© {{ year }} {{ site.name }}.</p>
                    <div class="flex gap-5">
                        <a href="/chinh-sach-bao-mat" class="transition hover:text-cream-50">Chính sách bảo mật</a>
                        <a href="/dieu-khoan" class="transition hover:text-cream-50">Điều khoản</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>
</template>
