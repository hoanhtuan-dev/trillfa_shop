// Trillfa Studio — Service Worker (scope: /studio)
// Lưu ý: /studio trả về Cache-Control: no-store → KHÔNG cache HTML (cache.put sẽ reject).
// Chiến lược: navigation network-first; asset hashed /build//icons//images/ stale-while-revalidate.
const CACHE = 'trillfa-studio-v2';

self.addEventListener('install', (event) => {
  // Không cache shell HTML (no-store) — chỉ skipWaiting để SW kích hoạt ngay (điều kiện installable).
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  // Navigation (HTML): network-first, rơi về cache khi offline.
  if (req.mode === 'navigate') {
    event.respondWith(
      fetch(req).catch(() => caches.match(req).then((hit) => hit || caches.match('/studio')))
    );
    return;
  }

  // Asset hashed (/build/, /icons/, /images/): cache-first + cập nhật nền (SWR).
  if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/') || url.pathname.startsWith('/images/')) {
    event.respondWith(
      caches.match(req).then((hit) => {
        const network = fetch(req)
          .then((res) => {
            if (res && res.ok && res.type === 'basic') {
              const copy = res.clone();
              caches.open(CACHE).then((cache) => cache.put(req, copy)).catch(() => {});
            }
            return res;
          })
          .catch(() => hit);
        return hit || network;
      })
    );
  }
});
