// Formatting helpers shared across the storefront SPA.

const moneyFormatter = new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 0 });

export function formatMoney(value) {
    const n = Number(value || 0);
    return moneyFormatter.format(n) + '₫';
}

export function formatDate(value) {
    if (!value) return '';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return String(value);
    return d.toLocaleDateString('vi-VN');
}

export function discountPercent(price, compare) {
    const p = Number(price || 0);
    const c = Number(compare || 0);
    if (!c || c <= p) return 0;
    return Math.round((1 - p / c) * 100);
}

export function starFill(value, index) {
    const v = Number(value || 0);
    return index < Math.round(v);
}
