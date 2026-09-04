<?php

namespace App\Modules\Storefront;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * StorefrontBridge — the single contract between the Laravel backend and the
 * Vue storefront SPA. Every controller and the boot view pulls data from here
 * so the widget settings, models and JSON shape stay in one place.
 *
 * The outputs are deliberately plain JSON-safe arrays (no Eloquent models, no
 * Carbon, no `url` facades leaking through eager-loading) so the SPA can render
 * them directly, and so the same payload works both as `<script>` boot JSON and
 * as the `/api/storefront/home` JSON feed.
 */
class StorefrontBridge
{
    /**
     * Full homepage payload for the Vue app.
     */
    public function homePayload(): array
    {
        return [
            'site' => $this->site(),
            'user' => $this->user(),
            'nav' => $this->nav('header'),
            'contact' => $this->contact(),
            'hero' => $this->hero(),
            'benefits' => $this->benefits(),
            'categories' => $this->categories(),
            'featured' => $this->productsSection('featured'),
            'new_arrivals' => $this->productsSection('new'),
            'on_sale' => $this->productsSection('sale'),
            'bestsellers' => $this->productsSection('bestsellers'),
            'secondary' => $this->secondary(),
            'blog' => $this->blog(),
            'cta' => $this->cta(),
        ];
    }

    /**
     * Shop / category listing payload for the Vue shop SPA.
     * Reuses the same product serializer so cards look identical to home.
     */
    public function shop(?string $categorySlug = null, array $filters = [], int $page = 1): array
    {
        $query = Product::active()->with('category', 'variants');

        $category = null;
        if ($categorySlug) {
            $category = Category::active()->where('slug', $categorySlug)->first();
            if ($category) {
                $ids = collect([$category->id])->merge($category->children->pluck('id'));
                $query->whereIn('category_id', $ids);
            }
        }

        if (! empty($filters['q'])) {
            $query->search($filters['q']);
        }
        if (! empty($filters['brand'])) {
            $query->where('brand', $filters['brand']);
        }
        if (isset($filters['min_price']) && $filters['min_price'] !== '') {
            $query->where('price', '>=', (float) $filters['min_price']);
        }
        if (isset($filters['max_price']) && $filters['max_price'] !== '') {
            $query->where('price', '<=', (float) $filters['max_price']);
        }

        $sort = $filters['sort'] ?? 'newest';
        $query = match ($sort) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'popular' => $query->orderByDesc('sales_count'),
            'rating' => $query->orderByDesc('rating_avg'),
            default => $query->orderByDesc('featured')->orderByDesc('id'),
        };

        $p = $query->paginate(12, ['*'], 'page', $page);

        return [
            'categories' => Category::active()->whereNull('parent_id')
                ->with('children', fn ($q) => $q->active())
                ->orderBy('sort_order')->get()
                ->map(fn (Category $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'icon' => $c->icon,
                    'icon_image' => $c->icon_image_url,
                    'url' => route('shop.category', $c->slug),
                    'children' => $c->children->map(fn ($ch) => [
                        'name' => $ch->name,
                        'slug' => $ch->slug,
                        'url' => route('shop.category', $ch->slug),
                    ])->values()->all(),
                ])->values()->all(),
            'brands' => Product::active()->whereNotNull('brand')->distinct()->orderBy('brand')->pluck('brand')->values()->all(),
            'active' => [
                'category' => $categorySlug,
                'q' => $filters['q'] ?? '',
                'brand' => $filters['brand'] ?? '',
                'min_price' => $filters['min_price'] ?? '',
                'max_price' => $filters['max_price'] ?? '',
                'sort' => $sort,
            ],
            'category_name' => $category?->name,
            'products' => $p->getCollection()->map(fn ($product) => $this->product($product))->values()->all(),
            'total' => $p->total(),
            'per_page' => $p->perPage(),
            'current_page' => $p->currentPage(),
            'last_page' => $p->lastPage(),
        ];
    }

    // ---------------------------------------------------------------- sections

    public function site(): array
    {
        return [
            'name' => setting('site_name', 'Trillfa Fa'),
            'logo' => asset('images/logo.png'),
            'announcement_enabled' => widget_enabled('announcement'),
            'announcement_text' => widget_field('announcement', 'text', 'Miễn phí vận chuyển cho đơn hàng từ 500.000đ'),
            'free_shipping_threshold' => (float) setting('free_shipping_threshold', 0),
        ];
    }

    public function user(): array
    {
        $user = auth()->user();

        return [
            'authed' => (bool) $user,
            'name' => $user?->name,
            'is_admin' => $user ? (bool) $user->isAdmin() : false,
        ];
    }

    public function nav(string $location = 'header')
    {
        return menu_tree($location)->map(fn ($item) => [
            'label' => $item->label,
            'url' => $item->url,
            'children' => collect($item->children ?? [])->map(fn ($c) => [
                'label' => $c->label,
                'url' => $c->url,
            ])->values()->all(),
        ])->values()->all();
    }

    public function contact(): array
    {
        return [
            'hotline' => setting('contact_hotline', '1900 0000'),
            'email' => setting('contact_email', 'support@trillfa.shop'),
            'address' => setting('contact_address', ''),
        ];
    }

    public function hero(): array
    {
        $banners = widget_enabled('hero')
            ? Banner::active()->where('position', 'hero')->orderBy('sort_order')->get()
            : collect();

        return [
            'enabled' => widget_enabled('hero'),
            'slides' => $banners->map(fn (Banner $b) => [
                'id' => $b->id,
                'title' => $b->title,
                'subtitle' => $b->subtitle,
                'image' => $this->image($b->image),
                'button_text' => $b->button_text,
                'button_link' => $b->button_link ?: route('shop.index'),
            ])->values()->all(),
        ];
    }

    public function benefits(): array
    {
        return [
            ['icon' => 'truck', 'title' => 'Miễn phí vận chuyển', 'subtitle' => 'Cho đơn hàng từ 500.000₫'],
            ['icon' => 'shield', 'title' => 'Thanh toán an toàn', 'subtitle' => 'COD, VNPay, MoMo, chuyển khoản'],
            ['icon' => 'refresh', 'title' => 'Đổi trả dễ dàng', 'subtitle' => 'Trong vòng 7 ngày'],
            ['icon' => 'chat', 'title' => 'Hỗ trợ 24/7', 'subtitle' => 'Luôn sẵn sàng phục vụ bạn'],
        ];
    }

    public function categories(): array
    {
        $items = widget_enabled('categories')
            ? Category::active()->whereNull('parent_id')
                ->with('children', fn ($q) => $q->active())
                ->orderBy('sort_order')->get()
            : collect();

        return [
            'enabled' => widget_enabled('categories'),
            'kicker' => widget_field('categories', 'kicker', 'Danh mục'),
            'title' => widget_field('categories', 'title', 'Khám phá theo danh mục'),
            'link_text' => widget_field('categories', 'link_text', 'Xem tất cả'),
            'items' => $items->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'url' => route('shop.category', $c->slug),
                'icon' => $c->icon,
                'icon_image' => $c->icon_image_url,
                'image' => $c->image_url,
            ])->values()->all(),
        ];
    }

    /**
     * Build a "products grid" section (featured / new / sale / bestsellers).
     */
    public function productsSection(string $key): array
    {
        if (! widget_enabled($key === 'new' ? 'new' : $key)) {
            return $this->sectionSkeleton($key, false);
        }

        $limit = widget_limit($key === 'new' ? 'new' : $key);
        $products = match ($key) {
            'featured' => Product::active()->featured()->inStock()->with('category')->latest()->limit($limit)->get(),
            'new' => Product::active()->inStock()->with('category')->latest()->limit($limit)->get(),
            'bestsellers' => Product::active()->inStock()->withCount('orderItems')
                ->orderByDesc('order_items_count')->orderByDesc('sales_count')->limit($limit)->get(),
            'sale' => Product::active()->inStock()->where('compare_price', '>', 0)
                ->orderByDesc('id')->limit($limit)->get(),
            default => collect(),
        };

        return [
            'enabled' => true,
            'kicker' => widget_field($key === 'new' ? 'new' : $key, 'kicker', $this->productsDefaults($key)['kicker']),
            'title' => widget_field($key === 'new' ? 'new' : $key, 'title', $this->productsDefaults($key)['title']),
            'link_text' => widget_field($key === 'new' ? 'new' : $key, 'link_text', 'Xem tất cả'),
            'products' => $products->map(fn (Product $p) => $this->product($p))->values()->all(),
        ];
    }

    public function secondary(): array
    {
        $banners = widget_enabled('secondary')
            ? Banner::active()->where('position', 'secondary')->orderBy('sort_order')->limit(4)->get()
            : collect();

        return [
            'enabled' => widget_enabled('secondary'),
            'banners' => $banners->map(fn (Banner $b) => [
                'id' => $b->id,
                'title' => $b->title,
                'subtitle' => $b->subtitle,
                'image' => $this->image($b->image),
                'button_text' => $b->button_text,
                'link' => $b->link ?: ($b->button_link ?: route('shop.index')),
            ])->values()->all(),
        ];
    }

    public function blog(): array
    {
        $posts = widget_enabled('blog')
            ? Post::published()->with('author', 'category')->latest()->limit(3)->get()
            : collect();

        return [
            'enabled' => widget_enabled('blog'),
            'kicker' => widget_field('blog', 'kicker', 'Blog'),
            'title' => widget_field('blog', 'title', 'Câu chuyện & Phong cách'),
            'link_text' => widget_field('blog', 'link_text', 'Đọc tất cả'),
            'posts' => $posts->map(fn (Post $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'slug' => $p->slug,
                'url' => route('blog.show', $p->slug),
                'excerpt' => $p->excerpt,
                'image' => $this->image($p->image),
                'category' => $p->category?->name,
                'published_at' => $p->published_at?->format('d/m/Y'),
                'reading_time' => $p->reading_time,
            ])->values()->all(),
        ];
    }

    public function cta(): array
    {
        return [
            'enabled' => widget_enabled('cta'),
            'title' => widget_field('cta', 'title', 'Sẵn sàng nâng cấp phong cách của bạn?'),
            'subtitle' => widget_field('cta', 'subtitle', 'Khám phá bộ sưu tập mới nhất và tận hưởng ưu đãi hấp dẫn dành riêng cho bạn.'),
            'button_text' => widget_field('cta', 'button_text', 'Mua sắm ngay'),
            'button_link' => widget_field('cta', 'button_link', '/shop'),
        ];
    }

    // ---------------------------------------------------------------- helpers

    /**
     * Resolve a stored image path to a public URL. Handles the legacy `samples/`
     * dev seed paths (which the Product model treats via asset_image() but the
     * Banner/Post models would wrongly prefix with storage/) as well as real
     * storage uploads.
     */
    protected function image(?string $path): ?string
    {
        if (! $path) {
            return asset('images/placeholder.svg');
        }
        if (str_starts_with($path, 'http')) {
            return $path;
        }
        if (str_starts_with($path, 'samples/')) {
            return asset($path);
        }

        return asset('storage/'.$path);
    }

    protected function product(Product $p): array
    {
        $price = (float) $p->min_price;

        // "Was" price: prefer the parent compare_price (what shoppers see next to
        // the price), then fall back to the cheapest variant compare_price.
        $compare = (float) $p->compare_price;
        if ($compare <= $price) {
            $compare = $p->variants->isNotEmpty()
                ? (float) ($p->variants->where('compare_price', '>', 0)->min('compare_price') ?: 0)
                : 0;
        }
        $onSale = $compare && $compare > $price;

        return [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'url' => route('product.show', $p->slug),
            'price' => $price,
            'compare_price' => $onSale ? $compare : null,
            'discount_percent' => $onSale ? (int) round((1 - $price / $compare) * 100) : 0,
            'rating_avg' => (float) $p->rating_avg,
            'rating_count' => (int) $p->rating_count,
            'image' => $p->image_url ?: ($p->gallery_urls[0] ?? asset('images/placeholder.svg')),
            'gallery' => $p->gallery_urls,
            'short_description' => $p->short_description,
            'in_stock' => $p->total_stock > 0,
            'total_stock' => (int) $p->total_stock,
        ];
    }

    protected function productsDefaults(string $key): array
    {
        return [
            'featured' => ['kicker' => 'Tuyển chọn', 'title' => 'Sản phẩm nổi bật'],
            'new' => ['kicker' => 'Mới về', 'title' => 'Hàng mới nhất'],
            'bestsellers' => ['kicker' => 'Bán chạy', 'title' => 'Được yêu thích nhất'],
            'sale' => ['kicker' => 'Deal hot', 'title' => 'Ưu đãi đặc biệt &mdash; giảm sâu'],
        ][$key] ?? ['kicker' => '', 'title' => ''];
    }

    protected function sectionSkeleton(string $key, bool $enabled): array
    {
        return [
            'enabled' => $enabled,
            'kicker' => $this->productsDefaults($key)['kicker'] ?? '',
            'title' => $this->productsDefaults($key)['title'] ?? '',
            'link_text' => 'Xem tất cả',
            'products' => [],
        ];
    }
}
