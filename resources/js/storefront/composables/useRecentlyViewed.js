import { ref } from 'vue';

// Recently-viewed products (persisted client-side, capped).
const KEY = 'trillfa_recently_viewed';
const MAX = 8;

function read() {
    try {
        return JSON.parse(localStorage.getItem(KEY) || '[]');
    } catch (e) {
        return [];
    }
}
function write(items) {
    try {
        localStorage.setItem(KEY, JSON.stringify(items));
    } catch (e) {}
}

const list = ref(read());

export function useRecentlyViewed() {
    function add(product) {
        if (!product || !product.id) return;
        const items = list.value.filter((p) => String(p.id) !== String(product.id));
        items.unshift({
            id: product.id,
            name: product.name,
            url: product.url,
            image: product.image,
            price: product.price,
        });
        list.value = items.slice(0, MAX);
        write(list.value);
    }

    return { recentlyViewed: list, add };
}
