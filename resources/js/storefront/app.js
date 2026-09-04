import { createApp } from 'vue';
import { createPinia } from 'pinia';
import StorefrontApp from './StorefrontApp.vue';

window.storefrontLoaded = true;

createApp(StorefrontApp)
    .use(createPinia())
    .mount('#store-root');
