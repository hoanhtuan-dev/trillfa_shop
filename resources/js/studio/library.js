import { createApp } from 'vue';
import { createPinia } from 'pinia';
import LibraryApp from './LibraryApp.vue';
createApp(LibraryApp).use(createPinia()).mount('#library-root');
