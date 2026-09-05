# Trillfa Fa

**Nền tảng thương mại điện tử tầm trung** xây dựng bằng **Laravel 13 + Tailwind CSS v4 + Alpine.js** — tối giản, hiện đại và tương tác cao, kèm hệ thống blog gọn nhẹ. Phù hợp cho doanh nghiệp vừa và nhỏ.

## ✨ Tính năng

### Mặt hàng (Storefront)
- Trang chủ sống động: hero slider, danh mục, sản phẩm nổi bật/bán chạy/hàng mới, ưu đãi, blog preview
- Sản phẩm: gallery + zoom, thông số, biến thể (size), đánh giá & xếp hạng, sản phẩm liên quan
- Bộ lọc shop: danh mục, khoảng giá, thương hiệu, sắp xếp, tìm kiếm
- Giỏ hàng off-canvas & trang giỏ hàng (thêm/xóa/đổi số lượng realtime)
- Mã giảm giá, chọn phương thức vận chuyển/thanh toán, tính tổng realtime
- Danh sách yêu thích (lưu local + tài khoản)
- Tìm kiếm gợi ý tức thì (Alpine fetch, API)

### Đặt hàng & Thanh toán
- Checkout 4 bước: liên hệ → giao hàng → vận chuyển → thanh toán
- COD, chuyển khoản, VNPay, MoMo (cổng thanh toán mô phỏng cho bản demo)
- Trang thành công, mock gateway, theo dõi/hủy đơn, tự hoàn kho
- Doanh thu tự động, giảm tồn kho, cập nhật mã giảm giá

### Tài khoản khách hàng
- Đăng ký / đăng nhập / đăng xuất
- Tổng quan tài khoản, danh sách đơn hàng, chi tiết đơn + hủy đơn
- Sổ địa chỉ, đổi mật khẩu, đánh giá của tôi

### Blog (gọn nhẹ)
- Danh mục bài viết, bài nổi bật, tìm kiếm, thẻ tag, bài liên quan
- Đếm lượt xem, ước tính thời gian đọc

### Quản trị (/admin)
- Dashboard: doanh thu, thống kê đơn hàng, sản phẩm bán chạy, cảnh báo tồn kho
- Quản lý: Sản phẩm (kèm biến thể), Danh mục, Đơn hàng, Người dùng, Mã giảm giá, Đánh giá, Bài viết, Banner, Vận chuyển, Thanh toán, Cài đặt
- Phân quyền admin middleware

## 🚀 Phiên bản Pro — Nâng cấp sản xuất

Ứng dụng đã được nâng cấp lên **phiên bản Pro** với các tính năng sẵn sàng vận hành thực tế:

- **Preview ảnh khi tải lên** — Sản phẩm (ảnh chính + galeri nhiều ảnh), bài viết, banner, danh mục: chọn ảnh hiển thị trước ngay lập tức, xóa/đổi ảnh, giữ nguyên ảnh cũ. Galeri hỗ trợ thêm/xóa từng ảnh (ảnh cũ + ảnh mới).
- **Trình soạn thảo Rich Text** — Mô tả sản phẩm & bài viết có toolbar (đậm, nghiêng, tiêu đề, danh sách, trích dẫn, liên kết), lưu HTML.
- **Tìm kiếm tiếng Việt không dấu** — Gõ `ao→ vẫn ra "Áo", `dam→ "Đầm". Dùng `search_text` đã chuẩn hóa.
- **Export đơn hàng CSV** — Nút "Export CSV" trong Admin → Đơn hàng, hỗ trợ Excel (UTF-8 BOM).
- **Email xác nhận đơn hàng** — `OrderConfirmation` Mailable gửi cho khách ngay khi đặt hàng (template HTML có thương hiệu).
- **Cảnh báo tồn kho** — Bảng sản phẩm sắp hết hàng ngay trên Dashboard quản trị.
- **Form quản trị chuẩn** — Quản lý danh mục với đầy đủ tạo/sửa/xóa, checkbox trạng thái hoạt động đúng chuẩn, giữ galeri cũ khi cập nhật sản phẩm.


## 🎨 Thương hiệu & PWA

- **Logo & favicon**: logo thật (public/images/logo.png) được gắn ở header, footer và admin. Favicon + icon các kích thước (favicon.ico, icons/favicon-32x32.png, apple-touch-icon.png, icon-192.png, icon-512.png, maskable-512.png).
- **PWA (Web App Manifest + Service Worker)**:
  - public/manifest.webmanifest — tên, mô tả, theme/background color (brand), icon mọi kích thước, shortcuts (Cửa hàng / Giỏ hàng / Blog).
  - public/sw.js — service worker: network-first cho điều hướng, cache-first cho static build/images/icons, hỗ trợ offline cơ bản, tự động đăng ký từ app.js.
  - Meta PWA trong layouts/app & layouts/admin: theme-color, apple-mobile-web-app-*, mobile-web-app-capable.
- **Ảnh sản phẩm mẫu**: thay picsum.photos bằng 32 ảnh mẫu thật trong public/samples/ (thời trang), dùng cho sản phẩm (ảnh chính + galeri), banner, bài viết. Helper asset_image() phân giải ảnh samples/ / storage/ / url ngoài. Ảnh fallback dùng public/images/placeholder.svg (local, không phụ thuộc internet).



## 🔍 SEO (chuẩn hiện đại)

- **Meta đầy đủ**: title/description/keywords/robots, Open Graph (og:title/description/image/type/locale), Twitter Card, canonical URL, hreflang vi-VN.
- **JSON-LD structured data** tự động theo từng trang:
  - Trang chủ: Organization + WebSite + SearchAction.
  - Danh mục / sản phẩm: BreadcrumbList + CollectionPage; sản phẩm thêm Product + Offer (giá, tồn kho) + AggregateRating.
  - Blog: Article + BreadcrumbList. Trang tĩnh: BreadcrumbList.
- **Sitemap.xml** động: /sitemap.xml (trang, danh mục, sản phẩm, bài viết, trang tĩnh) với priority/changefreq/lastmod.
- **robots.txt** động: /robots.txt disallow /admin, /gio-hang, /thanh-toan, /tai-khoan... và trỏ sitemap.
- **Trang lỗi thương hiệu** 404/403/500/503 (noindex, không phụ thuộc DB).
- **URL thân thiện** (slug), 1 thẻ h1 mỗi trang, alt text ảnh, thẻ div semantic, loading lazy cho ảnh dưới fold.


## 🚀 Cài đặt & Chạy

**Yêu cầu:** PHP ≥ 8.3, Composer, Node.js ≥ 20, SQLite (mặc định).

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
npm run build
php artisan serve   # http://localhost:8000
```

> **Lưu ý:** chạy `php artisan storage:link` để dùng upload ảnh qua admin. Ảnh mẫu dùng `picsum.photos` nên cần internet.

### Tài khoản mẫu

| Vai trò | Email | Mật khẩu |
|---|---|---|
| **Super Admin** (quản lý tài khoản) | `tuan.ho.designer@gmail.com` | `hattf2768` |
| Admin (quản trị nội dung) | `admin@trillfa.com` | `password` |
| Khách | `customer@trillfa.com` | `password` |

> **Phân quyền:** chỉ **Super Admin** mới có quyền vào `/admin/users` để tạo, sửa, xóa, khóa và đặt lại mật khẩu tài khoản khác (middleware `superadmin` + `UserPolicy`). Admin thường truy cập các khu vực quản trị khác nhưng bị chặn (403) khi đụng tới quản lý người dùng.

### Mã giảm giá mẫu
`WELCOME10` (10% tối đa 200k) · `SALE15` (15% tối đa 300k) · `GIAM50K` (cố định 50k)

## 🧪 Kiểm thử

```bash
php artisan test   # 6 bài test cốt lõi: pages, cart, coupon, checkout, admin, register
```

## 🛠 Công nghệ & Kiến trúc

- **Backend:** Laravel 13 — Eloquent, Migrations, Services (`CartService`), Blade components
- **Frontend:** Tailwind CSS v4 (`@theme` design tokens), Alpine.js 3 (`$store` cart/toast/wishlist + plugins)
- **Database:** SQLite (0 cấu hình) — sẵn sàng chuyển MySQL/PostgreSQL
- **Vite:** bundle CSS/JS + font Inter & Fraunces

```
app/
  Services/CartService.php
  Models/                 # Product, Order, Cart, Coupon, Post, Banner, ...
  Http/Controllers/       # Storefront, Api, Admin
  Http/Middleware/EnsureUserIsAdmin.php
database/seeders/DatabaseSeeder.php
resources/css/app.css     # design system Tailwind v4
resources/js/app.js       # Alpine store + components
resources/views/          # storefront · account · checkout · blog · admin · partials
```

## 📝 Ghi chú

- Thanh toán online (VNPay/MoMo) là **mô phỏng**; tích hợp gateway thật bằng cách thay `CheckoutController`.
- Thiết kế tối giản: palette **brand (xanh rêu) + cream + ink**, font **Inter** (body) & **Fraunces** (heading).