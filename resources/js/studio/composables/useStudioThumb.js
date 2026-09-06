// Composable dùng chung cho thumbnail ảnh Studio (single source of truth).
// Trùng khớp 100% với PHP helper studio_image_thumb_url() ở app/Support/helpers.php:
// chỉ tạo thumbnail cho path AN TOÀN (chữ-số / _ . - / /); path đặc biệt → giữ ảnh gốc,
// tránh lỗi 500 do middleware ValidatePathEncoding khi URL chứa ký tự không hợp lệ.
//
// Ảnh gốc full-size vẫn dùng cho viewer (store.viewer = g → media_url gốc) và AI vision.
// Thumbnail 160px WebP/JPEG giảm ~50x dung lượng so với ảnh gốc 2K-4K.
//
// Dùng ở: OutputModule (right dock Outputs), GalleryModal (dải thumbnail viewer),
// LibraryApp (grid ảnh lớn), AdminProductsApp (Studio picker).

const SAFE = /^[a-zA-Z0-9/_.-]+$/;

/**
 * Tạo URL thumbnail cho ảnh Studio từ media_url gốc.
 * - /storage/...      → /studio/image-thumb/{path}
 * - /studio/image/... → /studio/image-thumb/{path}
 * - URL ngoài / data:URL / path đặc biệt → trả nguyên url gốc (fallback an toàn).
 * - url rỗng/null → trả nguyên (component tự xử lý v-if để tránh img rỗng).
 */
export function thumbUrl(url) {
  if (!url) return url;
  const clean = String(url).split(/[?#]/)[0]; // bỏ query string / fragment
  if (clean.startsWith('/storage/')) {
    const p = clean.slice(9);
    return SAFE.test(p) ? '/studio/image-thumb/' + p : url;
  }
  if (clean.startsWith('/studio/image/')) {
    const p = clean.slice(14);
    return SAFE.test(p) ? '/studio/image-thumb/' + p : url;
  }
  return url; // URL ngoài hoặc data:URL → giữ nguyên
}

/**
 * Fallback @error handler: khi thumbnail 404/lỗi, quay về ảnh gốc rồi placeholder.
 * Dùng cho <img @error="onThumbError($event, g.media_url)">.
 * Thứ tự: thumb → ảnh gốc → placeholder SVG (tránh vỡ ảnh trống).
 */
export function onThumbError(e, originalUrl) {
  const el = e.target;
  if (!el) return;
  // Lần lỗi đầu: thử ảnh gốc full-size (thumbnail có thể chưa tạo/xóa).
  if (el.dataset.fallback !== '1') {
    el.dataset.fallback = '1';
    if (originalUrl && el.src !== originalUrl) {
      el.src = originalUrl;
      return;
    }
  }
  // Lần lỗi thứ 2 (ảnh gốc cũng lỗi): placeholder SVG.
  el.src = '/images/placeholder.svg';
}

export function useStudioThumb() {
  return { thumbUrl, onThumbError };
}
