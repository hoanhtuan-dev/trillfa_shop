import { defineStore } from 'pinia';
import { apiFetch } from './composables/useApi.js';
import { trackAddToCart } from './composables/useAnalytics.js';

const WISHLIST_KEY = 'trillfa_wishlist';

function readBoot() {
    return (typeof window !== 'undefined' && window.__STORE_BOOT__) || null;
}

function readWishlist() {
    try {
        return JSON.parse(localStorage.getItem(WISHLIST_KEY) || '[]');
    } catch (e) {
        return [];
    }
}

function writeWishlist(ids) {
    try {
        localStorage.setItem(WISHLIST_KEY, JSON.stringify(ids));
    } catch (e) {}
}

/**
 * The single storefront store. Holds the server boot payload (read once from
 * window.__STORE_BOOT__), the global UI state (cart/menu/toasts) and the
 * client-side cart + wishlist. This is the shared "brain" every storefront
 * card reads from, so components stay small and reusable.
 */
export const useStorefrontStore = defineStore('storefront', {
    state: () => ({
        boot: readBoot(),

        // UI
        cartOpen: false,
        menuOpen: false,
        searchOpen: false,
        quickViewProduct: null,

        // Cart
        cart: {
            initialized: false,
            loading: false,
            adding: false,
            items: [],
            coupon: null,
            subtotal: 0,
            discount: 0,
            shippingFee: 0,
            total: 0,
        },

        // Wishlist (persisted client-side)
        wishlist: readWishlist(),

        // Toasts
        toasts: [],
    }),

    getters: {
        site: (state) => state.boot?.site || { name: 'Trillfa Fa', logo: '', announcement_enabled: false, announcement_text: '' },
        user: (state) => state.boot?.user || { authed: false, name: null, is_admin: false },
        sections: (state) => state.boot || {},

        cartCount: (state) =>
            state.cart.items.reduce((sum, i) => sum + Number(i.quantity || 0), 0),
        cartIsEmpty: (state) => state.cart.items.length === 0,

        wishlistCount: (state) => state.wishlist.length,
        wishlistHas: (state) => (id) => state.wishlist.map(String).includes(String(id)),
    },

    actions: {
        // ---- boot (re-request if the static payload is missing) ----
        async ensureBoot() {
            if (this.boot) return this.boot;
            try {
                const data = await apiFetch('/api/storefront/home');
                this.boot = data;
            } catch (e) {
                this.boot = {};
            }
            return this.boot;
        },

        // ---- cart ----
        async fetchCart() {
            this.cart.loading = true;
            try {
                const data = await apiFetch('/api/cart');
                this.applyCart(data);
                this.cart.initialized = true;
            } catch (e) {
                console.error('cart fetch failed', e);
            } finally {
                this.cart.loading = false;
            }
        },

        applyCart(data) {
            this.cart.items = data.items || [];
            this.cart.subtotal = Number(data.subtotal || 0);
            this.cart.discount = Number(data.discount || 0);
            this.cart.shippingFee = Number(data.shipping_fee || 0);
            this.cart.total = Number(data.total || 0);
            this.cart.coupon = data.coupon || null;
        },

        async addToCart(productId, quantity = 1, product = null) {
            this.cart.adding = true;
            try {
                await apiFetch('/api/cart/add', {
                    method: 'POST',
                    body: { product_id: productId, quantity },
                });
                await this.fetchCart();
                this.toast('Đã thêm vào giỏ hàng');
                this.openCart();
                trackAddToCart(product || { id: productId, name: 'Sản phẩm', price: 0 }, quantity);
            } catch (e) {
                this.toast(e.message, 'error');
            } finally {
                this.cart.adding = false;
            }
        },

        async updateCartItem(itemId, quantity) {
            if (quantity < 1) return this.removeCartItem(itemId);
            try {
                await apiFetch('/api/cart/update', { method: 'POST', body: { id: itemId, quantity } });
                await this.fetchCart();
            } catch (e) {
                this.toast(e.message, 'error');
            }
        },

        async removeCartItem(itemId) {
            try {
                await apiFetch('/api/cart/remove', { method: 'POST', body: { id: itemId } });
                await this.fetchCart();
            } catch (e) {
                this.toast(e.message, 'error');
            }
        },

        // ---- wishlist ----
        toggleWishlist(id) {
            id = String(id);
            if (this.wishlistHas(id)) {
                this.wishlist = this.wishlist.filter((x) => x !== id);
                writeWishlist(this.wishlist);
                this.toast('Đã xóa khỏi danh sách yêu thích');
            } else {
                this.wishlist.push(id);
                writeWishlist(this.wishlist);
                this.toast('Đã thêm vào danh sách yêu thích');
            }
        },

        // ---- ui ----
        openCart() {
            this.cartOpen = true;
            this.lockScroll();
        },
        closeCart() {
            this.cartOpen = false;
            this.unlockScroll();
        },
        openMenu() {
            this.menuOpen = true;
            this.lockScroll();
        },
        closeMenu() {
            this.menuOpen = false;
            this.unlockScroll();
        },
        lockScroll() {
            document.body.style.overflow = 'hidden';
        },
        unlockScroll() {
            if (!this.cartOpen && !this.menuOpen) document.body.style.overflow = '';
        },

        // ---- toasts ----
        toast(message, type = 'success') {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, message, type });
            setTimeout(() => this.removeToast(id), 3200);
        },
        removeToast(id) {
            this.toasts = this.toasts.filter((t) => t.id !== id);
        },
    },
});
