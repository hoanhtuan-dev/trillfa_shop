// Lightweight analytics helper — pushes to GA4 (dataLayer) and Meta Pixel if
// the corresponding script is present. Safe no-op when not configured.
export function track(event, data = {}) {
    try {
        if (typeof window === 'undefined') return;
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({ event, ...data });
        if (typeof window.fbq === 'function') {
            window.fbq('track', event, data || {});
        }
    } catch (e) {
        // ignore
    }
}

// Standard e-commerce events used across the storefront.
export function trackAddToCart(product, quantity = 1) {
    track('add_to_cart', {
        currency: 'VND',
        value: Number(product.price || 0) * quantity,
        items: [{ item_id: product.id, item_name: product.name, price: product.price, quantity }],
    });
}

export function trackBeginCheckout(cart) {
    track('begin_checkout', {
        currency: 'VND',
        value: Number(cart.total || 0),
        items: (cart.items || []).map((i) => ({ item_id: i.product_id, item_name: i.name, price: i.price, quantity: i.quantity })),
    });
}

export function trackPurchase(order) {
    track('purchase', {
        currency: 'VND',
        value: Number(order.total || 0),
        transaction_id: String(order.order_number || order.id || ''),
        items: (order.items || []).map((i) => ({ item_id: i.product_id, item_name: i.name, price: i.price, quantity: i.quantity })),
    });
}
