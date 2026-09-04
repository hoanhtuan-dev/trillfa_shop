// Lightweight JSON fetch that mirrors the Alpine helper used by the legacy
// storefront. Always sends the CSRF header and parses JSON bodies.

export function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

export async function apiFetch(url, options = {}) {
    const opts = { ...options, headers: { ...(options.headers || {}) } };
    opts.headers.Accept = 'application/json';
    const csrf = csrfToken();
    if (csrf) opts.headers['X-CSRF-TOKEN'] = csrf;

    if (opts.body && typeof opts.body !== 'string') {
        opts.headers['Content-Type'] = 'application/json';
        opts.body = JSON.stringify(opts.body);
    }

    const res = await fetch(url, opts);
    const data = await res.json().catch(() => ({}));

    if (!res.ok) {
        const msg = data.message || data.error || 'Đã có lỗi xảy ra.';
        const err = new Error(msg);
        err.status = res.status;
        err.data = data;
        throw err;
    }

    return data;
}
