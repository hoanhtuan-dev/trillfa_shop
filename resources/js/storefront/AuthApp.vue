<script setup>
import { ref, computed, onMounted } from 'vue';
import { useStorefrontStore } from './store.js';
import { csrfToken } from './composables/useApi.js';
import StorefrontLayout from './components/layout/StorefrontLayout.vue';
import Icon from './components/ui/Icon.vue';

const store = useStorefrontStore();
store.ensureBoot();

const mode = window.__STORE_BOOT__?.mode || 'login';
const isRegister = computed(() => mode === 'register');
const action = computed(() => (isRegister.value ? '/dang-ky' : '/dang-nhap'));

const form = ref({ name: '', email: '', phone: '', password: '', password_confirmation: '', remember: false });

function submit() {
    const el = document.querySelector('#auth-form');
    if (el) el.submit();
}

onMounted(() => store.fetchCart());
</script>

<template>
    <StorefrontLayout>
        <div class="sf-container flex justify-center py-10 sm:py-16">
            <div class="card-surface w-full max-w-md rounded-[2rem] p-7 sm:p-9">
                <div class="text-center">
                    <div class="mx-auto mb-4 grid h-14 w-14 place-items-center rounded-2xl bg-gradient-to-br from-brand-600 to-brand-500 text-white shadow-lg shadow-brand-600/30">
                        <Icon name="user" :size="26" />
                    </div>
                    <h1 class="font-display text-2xl font-semibold text-ink-900">{{ isRegister ? 'Tạo tài khoản' : 'Đăng nhập' }}</h1>
                    <p class="mt-2 text-sm text-ink-500">{{ isRegister ? 'Đăng ký để mua sắm & theo dõi đơn hàng.' : 'Chào mừng trở lại với Trillfa Fa.' }}</p>
                </div>

                <form id="auth-form" :method="'POST'" :action="action" @submit.prevent="submit" class="mt-6 space-y-4">
                    <input type="hidden" name="_token" :value="csrfToken()" />

                    <div v-if="isRegister">
                        <label class="label">Họ tên</label>
                        <input v-model="form.name" name="name" required class="sf-input" placeholder="Họ và tên" />
                    </div>

                    <div>
                        <label class="label">Email</label>
                        <input v-model="form.email" name="email" type="email" required class="sf-input" placeholder="Email" />
                    </div>

                    <div v-if="isRegister">
                        <label class="label">Số điện thoại</label>
                        <input v-model="form.phone" name="phone" class="sf-input" placeholder="Số điện thoại (tuỳ chọn)" />
                    </div>

                    <div>
                        <label class="label">Mật khẩu</label>
                        <input v-model="form.password" name="password" type="password" required class="sf-input" placeholder="Mật khẩu" />
                    </div>

                    <div v-if="isRegister">
                        <label class="label">Nhập lại mật khẩu</label>
                        <input v-model="form.password_confirmation" name="password_confirmation" type="password" required class="sf-input" placeholder="Nhập lại mật khẩu" />
                    </div>

                    <label v-if="!isRegister" class="flex items-center gap-2 text-sm text-ink-700">
                        <input type="checkbox" name="remember" v-model="form.remember" class="h-4 w-4 accent-brand-600" />
                        Ghi nhớ đăng nhập
                    </label>

                    <button type="submit" class="sf-btn sf-btn-primary w-full">{{ isRegister ? 'Đăng ký' : 'Đăng nhập' }}</button>

                    <p class="text-center text-sm text-ink-500">
                        <template v-if="isRegister">
                            Đã có tài khoản? <a href="/dang-nhap" class="font-medium text-brand-700 hover:underline">Đăng nhập</a>
                        </template>
                        <template v-else>
                            Chưa có tài khoản? <a href="/dang-ky" class="font-medium text-brand-700 hover:underline">Đăng ký</a>
                        </template>
                    </p>
                </form>
            </div>
        </div>
    </StorefrontLayout>
</template>
