import { createStorefrontApp } from './bootstrap.js';
import StorefrontApp from './StorefrontApp.vue';

window.storefrontLoaded = true;

createStorefrontApp(StorefrontApp);
