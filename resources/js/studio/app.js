import { createApp } from 'vue';
import { createPinia } from 'pinia';
import StudioApp from './StudioApp.vue';
createApp(StudioApp).use(createPinia()).mount('#studio-root');
