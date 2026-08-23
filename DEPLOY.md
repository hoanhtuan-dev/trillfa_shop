# Hướng dẫn Deploy lên Hostinger (Shared Hosting)

Tài liệu này hướng dẫn đưa ứng dụng **Trillfa Fa (Laravel 13)** lên hosting chia sẻ Hostinger bằng **MySQL** (production) — chuyển từ SQLite local.

---

## 0. Yêu cầu
- Gói Hostinger hỗ trợ **PHP 8.3** (chọn trong hPanel → PHP Configuration).
- Bật **SSH / Terminal** trong hPanel (khuyến nghị). Không có SSH vẫn làm được (upload qua File Manager + tải trước vendor).
- Domain đã trỏ & có chứng chỉ SSL (bật HTTPS).

---

## 1. Chuẩn bị bản production ở local

Chỉnh `.env`:
```env
APP_NAME="Trillfa Fa"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=localhost            # dùng host hiển thị trong hPanel
DB_DATABASE=uXXXX_trillfa
DB_USERNAME=uXXXX_user
DB_PASSWORD=******

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
MAIL_MAILER=log
```

Build production:
```bash
composer install --no-dev --optimize-autoloader
npm install && npm run build        # tạo public/build + fonts
php artisan config:clear
```
> `vendor/` và `node_modules/` đã được `.gitignore`. Nếu upload bằng FTP hãy **giữ nguyên** `vendor/` (đã build) và `public/build/` (đã build).

> Phiên bản PHP local & Hostinger phải **khớp** (≥ 8.3), nếu không hãy downgrade composer (composer.lock) về 8.2.

---

## 2. Tạo Database trên Hostinger

hPanel → **Databases → MySQL Databases**:
1. Tạo database (vd `uXXXX_trillfa`).
2. Tạo user + password; gán quyền **ALL PRIVILEGES** cho DB đó.
3. Ghi lại: **host** (thường `localhost`), db name, user, password.

---

## 3. Cấu trúc thư mục & Upload

> **Quan trọng:** dự án này đã dùng **`public_html`** làm thư mục public (đã đổi từ `public` và ghim trong `bootstrap/app.php` qua `usePublicPath`). Vì vậy toàn bộ project có thể upload thẳng với `public_html` là web root — **không cần sửa index.php**.

### Cách A — Khuyến nghị: `public_html` là Document Root

Upload toàn bộ project vào thư mục chứa `public_html` (ví dụ `/home/uXXXX/domains/example.com/`), sao cho `public_html` (của app) chính là web root và các thư mục `app/`, `bootstrap/`, `vendor/` nằm cạnh nó.

Trong hPanel → **Websites** → đổi **Document Root** về:
```
/home/uXXXX/domains/example.com/public_html
```

Không cần sửa `index.php` (đường dẫn `../vendor`, `../bootstrap` đã đúng).
Hostinger hPanel (Websites → website → **Document Root**) cho phép trỏ document root tới thư mục bất kỳ.

1. Upload **toàn bộ** project vào `~/trillfa` (ví dụ `/home/uXXXX/domains/your-domain.com/trillfa` hoặc `~/trillfa`).
2. Trong hPanel → **Websites** → chọn domain → đổi **Document Root** thành:
   ```
   /home/uXXXX/trillfa/public
   ```
3. Không cần sửa `index.php`. `public/` chính là web root.

### Cách B — Không đổi được Document Root (copy public vào public_html)
1. Đặt **toàn bộ** project ở `~/trillfa-app` (cùng cấp với `public_html`).
2. Copy **nội dung** của `public/` vào `public_html/`:
   ```
   public_html/index.php
   public_html/.htaccess
   public_html/build/            (đã build)
   public_html/images/  public_html/icons/  public_html/samples/
   public_html/manifest.webmanifest  public_html/sw.js
   public_html/robots.txt  public_html/favicon.ico
   ```
3. Sửa `public_html/index.php`: đổi các đường dẫn `__DIR__.'/../...` thành `__DIR__.'/../trillfa-app/...`:
   ```php
   if (file_exists($maintenance = __DIR__.'/../trillfa-app/storage/framework/maintenance.php')) {
       require $maintenance;
   }
   require __DIR__.'/../trillfa-app/vendor/autoload.php';
   /** @var Illuminate\Foundation\Application $app */
   $app = require_once __DIR__.'/../trillfa-app/bootstrap/app.php';
   $app->handleRequest(Illuminate\Http\Request::capture());
   ```
4. Tạo symlink storage (chạy SSH) hoặc copy thủ công:
   ```bash
   ln -s ../trillfa-app/storage/app/public public_html/storage
   ```

> **Lưu ý:** ảnh mẫu nằm ở `public/samples` (đã là file thật), nên không cần symlink để hiển thị. Symlink chỉ cần cho **ảnh upload qua admin**.

---

## 4. Tạo file `.env` trên server

Trong thư mục gốc project (`~/trillfa` hoặc `~/trillfa-app`) tạo `.env` (copy từ `.env.example`) và điền đúng DB đã tạo ở bước 2. Sau đó:
```bash
php artisan key:generate
```

---

## 5. Chạy migrate, seed & cache (SSH / hPanel Terminal)

```bash
cd ~/trillfa          # hoặc ~/trillfa-app
php artisan migrate --force
php artisan db:seed --force        # (tuỳ chọn) nạp dữ liệu mẫu
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Phân quyền thư mục (Hostinger cần ghi):
```bash
chmod -R 775 storage bootstrap/cache
```

> **Nếu không có SSH:** nhờ support Hostinger hoặc dùng hPanel tạo file để chạy `php artisan`? Không có CLI thì:
> - Upload sẵn `vendor/` (đã build local đúng PHP).
> - Migration phải chạy máy khác. Cách nhanh nhất là **bật SSH** (Hostinger cho phép bật miễn phí) hoặc liên hệ support để chạy 1 lần.

---

## 6. HTTPS & Domain

- hPanel → **Security → SSL** → bật miễn phí (Let's Encrypt), chọn force HTTPS.
- Đảm bảo `APP_URL=https://your-domain.com`.
- Cloudflare (nếu dùng) → SSL Full (strict), enable Always Use HTTPS.

---

## 7. Kiểm tra

- Mở trang chủ: `https://your-domain.com` → không lỗi.
- `/admin` → đăng nhập bằng tài khoản admin (đã seed) → dashboard load.
- `/build/assets/app-*.css`, `/images/logo.png`, `/manifest.webmanifest`, `/sw.js` → 200.
- Product/blog hiện ảnh `/samples/...` (đã là file thật).
- `/storage/` cho ảnh admin upload (cần symlink).

---

## 8. Các lỗi thường gặp

| Triệu chứng | Nguyên nhân / cách sửa |
|---|---|
| 500 / white page | `APP_DEBUG=false` che lỗi. Bật `APP_DEBUG=true` tạm, xem log `storage/logs/laravel.log`. Thường do: sai `.env`, chưa `key:generate`, storage không ghi được. |
| `No application encryption key` | Chạy `php artisan key:generate`. |
| `Access denied for user` | Sai DB_HOST/DB_USERNAME/DB_PASSWORD; kiểm tra hPanel. |
| Không có CSS/JS | Thiếu `public/build/` (npm run build) hoặc upload thiếu. |
| `Table not found` | Chưa chạy `php artisan migrate --force`. |
| 419 Page Expired | Vấn đề session/CSRF. Đảm bảo `SESSION_DRIVER=database` + đã migrate; với HTTPS bật `SESSION_SECURE_COOKIE=true`? (chỉ khi HTTPS). |
| Logo/ảnh không hiện | Đường dẫn tuyệt đối trong `asset()` theo `APP_URL`; chạy `php artisan config:cache` sau khi đổi URL. |

---

## 9. Kiến trúc sau deploy

```
/home/uXXXX/
  trillfa/                    (hoặc trillfa-app/)
    app/ bootstrap/ config/ database/ public/ resources/ routes/ storage/ vendor/
    .env
  public_html/                (web root — chính là thư mục public của app)
    index.php  .htaccess  build/  images/  icons/  samples/  manifest.webmanifest  sw.js
```

---

## ⚠️ Shared Hosting (Hostinger): `proc_open` / `exec` / `symlink` bị tắt

Hostinger (và nhiều shared hosting) disable `proc_open`, `exec`, `shell_exec`, `symlink` trong `disable_functions`. Điều này gây 3 lỗi phổ biến khi deploy:

### 1. `composer install` lỗi: "Process class relies on proc_open …"
- Composer chạy script **post-autoload-dump** (`@php artisan package:discover`) bằng Symfony Process (cần `proc_open`).
- **Cách 1 (khuyến nghị):** tránh chạy Composer trên server — build sẵn `vendor/` ở local rồi upload:
  ```bash
  # Ở máy local (đúng PHP 8.3):
  composer install --no-dev --optimize-autoloader
  ```
  rồi upload toàn bộ project (bao gồm `vendor/`) lên host.
- **Cách 2:** chạy trên server nhưng **bỏ script**:
  ```bash
  composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts
  ```
  (`--no-scripts` bỏ qua `package:discover`. Nếu cần, chạy thủ công `php artisan package:discover`; nếu lỗi thì bỏ qua — Laravel vẫn tự discover ở request đầu.)

### 2. `php artisan storage:link` lỗi: "Call to undefined function Illuminate\Filesystem\exec()"
- Tạo symlink bằng PHP cần `symlink()` hoặc `exec('ln -s')` — đều bị tắt.
- **Cách xử lý:** tạo symlink bằng **SSH shell** (không qua PHP):
  ```bash
  cd /home/uXXXX/domains/your-domain
  ln -s ../storage/app/public public_html/storage
  ```
  Kiểm tra: `readlink public_html/storage` → `../storage/app/public`.

### 3. "No arguments expected for config:cache, got route:cache"
- Artisan **không** nhận nhiều lệnh trong một dòng. Chạy riêng từng lệnh (nối bằng `&&`):
  ```bash
  php artisan config:cache && php artisan route:cache && php artisan view:cache
  ```

### Chuỗi deploy khuyến nghị trên Hostinger
```bash
cd /home/uXXXX/domains/your-domain
composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts   # hoặc upload vendor sẵn
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force          # (tuỳ chọn) dữ liệu mẫu
ln -s ../storage/app/public public_html/storage   # thay cho artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> **Lưu ý:** không dùng `php artisan serve` trên shared hosting (cần `proc_open`). Host dùng Apache/nginx, truy cập qua `https://domain` là đủ.
