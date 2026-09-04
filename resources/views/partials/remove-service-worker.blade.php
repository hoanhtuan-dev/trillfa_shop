<script>
    // Dọn Service Worker cũ: trước đây /sw.js (scope "/") được các trang Alpine đăng ký và điều
    // khiển cả /studio → preload JS bị "cross-world service worker resource mismatch" và có thể
    // phục vụ HTML/asset cũ qua cache. Chạy SỚM trong <head>, trước khi trình duyệt dùng preload,
    // để lần tải KẾ TIẾP không còn SW nào kiểm soát trang (lần tải hiện tại đã bị SW chặn trước đó).
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.getRegistrations()
            .then(function (regs) { regs.forEach(function (reg) { reg.unregister(); }); })
            .catch(function () {});
        if (typeof caches !== 'undefined') {
            caches.keys()
                .then(function (keys) { keys.forEach(function (k) { caches.delete(k); }); })
                .catch(function () {});
        }
    }
</script>
