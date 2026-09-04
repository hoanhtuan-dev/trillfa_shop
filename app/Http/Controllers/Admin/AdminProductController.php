<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Generation;
use App\Models\Product;
use App\Services\ProductAIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('category', 'variants')->latest();

        if ($request->filled('q')) {
            $query->search($request->input('q'));
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }
        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        $products = $query->paginate(15)->withQueryString();
        $categories = Category::active()->get();

        return view('admin.products.index', compact('products', 'categories'));
    }

    public function create()
    {
        return $this->renderVue(new Product());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $product = Product::create($this->fillData($data));
        $this->saveImages($request, $product);
        $this->syncVariants($request, $product);

        return redirect()->route('admin.products.index')->with('success', 'Đã tạo sản phẩm.');
    }

    public function edit(Product $product)
    {
        $product->load('variants');

        return $this->renderVue($product);
    }

    /**
     * Render the Vue product create/edit SPA (card-based, Studio + AI).
     */
    protected function renderVue(Product $product)
    {
        $categories = Category::active()->orderBy('sort_order')->with('children')->get();

        return view('admin.products.vue', [
            'boot' => [
                'product' => $product->exists ? $this->productPayload($product) : null,
                'categories' => $categories->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'children' => $c->children->map(fn ($ch) => ['id' => $ch->id, 'name' => $ch->name])->values()->all(),
                ])->values()->all(),
                'ai' => [
                    'model' => (string) studio_config('qwen_prompt_model', 'qwen3.8-flash'),
                    'provider' => (string) studio_config('prompt_provider', 'qwen'),
                    'enabled' => true,
                ],
            ],
        ]);
    }

    protected function productPayload(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'brand' => $product->brand,
            'category_id' => $product->category_id,
            'short_description' => $product->short_description,
            'description' => $product->description,
            'price' => (float) $product->price,
            'compare_price' => $product->compare_price !== null ? (float) $product->compare_price : null,
            'cost_price' => $product->cost_price !== null ? (float) $product->cost_price : null,
            'stock' => (int) $product->stock,
            'featured' => (bool) $product->featured,
            'is_active' => (bool) $product->is_active,
            'meta_title' => $product->meta_title,
            'meta_description' => $product->meta_description,
            'tags' => (array) $product->tags,
            'image' => $product->image_url,
            'gallery' => $product->gallery_urls,
            'variants' => $product->variants->map(fn ($v) => [
                'name' => $v->name,
                'sku' => $v->sku,
                'price' => (float) $v->price,
                'compare_price' => $v->compare_price !== null ? (float) $v->compare_price : null,
                'stock' => (int) $v->stock,
            ])->values()->all(),
        ];
    }

    /**
     * Deep-AI content + SEO suggestions (qwen configurable).
     */
    public function aiSuggest(Request $request)
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:120'],
            'brand' => ['nullable', 'string', 'max:120'],
            'hint' => ['nullable', 'string', 'max:1000'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'force' => ['nullable', 'boolean'],
        ]);

        $imagePath = null;
        if (! empty($data['image_url'])) {
            $imagePath = $this->resolveImagePath($data['image_url']);
        }

        // Run INLINE (synchronous) with a hard total budget inside ProductAIService:
        // qwen3.8-flash works fast, and the bounded attempts + per-call timeout +
        // wall-clock deadline guarantee a result well under the gateway 504 limit.
        // This removes the fragile queue-worker + poll dependency on shared hosting.
        @set_time_limit(90);

        /** @var \App\Services\ProductAIService $service */
        $service = app(\App\Services\ProductAIService::class);
        if ($imagePath && is_file($imagePath)) {
            $result = $service->generateFromImage($data, $imagePath, (bool) ($data['force'] ?? false));
        } else {
            $result = $service->generate($data, null);
        }
        $result['source'] ??= 'stub';
        $result['model'] = $result['model'] ?? (string) studio_config('qwen_prompt_model', 'qwen3.8-flash');
        $result['provider'] = $result['provider'] ?? (string) studio_config('prompt_provider', 'qwen');

        return response()->json(['status' => 'done', 'data' => $result]);
    }

    public function aiSuggestPoll(Request $request)
    {
        $token = (string) $request->input('token');
        $result = \Illuminate\Support\Facades\Cache::get('product_ai:'.$token);
        if ($result) {
            \Illuminate\Support\Facades\Cache::forget('product_ai:'.$token);

            return response()->json(['status' => 'done', 'data' => $result]);
        }

        return response()->json(['status' => 'processing']);
    }

    protected function resolveImagePath(string $url): ?string
    {
        $path = ltrim((string) parse_url($url, PHP_URL_PATH), '/');
        foreach ([public_path($path), storage_path('app/public/'.$path)] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Studio library image feed for the product image picker. Returns the
     * admin's latest generations + public studio assets.
     */
    public function studioImages(Request $request)
    {
        $images = [];

        // Latest generations — media_url is stored as /storage/{path}; expose an
        // ABSOLUTE url so asset_image() returns it unchanged and the AI image
        // resolver can locate the file on disk.
        $gens = auth()->user()->generations()->whereNotNull('media_url')->latest()->limit(60)->get();
        foreach ($gens as $g) {
            $media = (string) $g->media_url;
            $url = str_starts_with($media, 'http') ? $media : asset($media);
            $images[] = ['id' => 'gen-'.$g->id, 'url' => $url, 'type' => 'generation', 'label' => $g->prompt];
        }

        // Public studio assets (uploaded images).
        $assetsDir = public_path('studio/images/assets');
        if (is_dir($assetsDir)) {
            foreach (glob($assetsDir.'/*.{png,jpg,jpeg,webp}', GLOB_BRACE) as $f) {
                $name = basename($f);
                $images[] = ['id' => 'asset-'.$name, 'url' => asset('studio/images/assets/'.$name), 'type' => 'asset', 'label' => $name];
            }
        }

        return response()->json(['images' => $images]);
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validated($request);

        $product->update($this->fillData($data));
        $this->saveImages($request, $product, true);
        $this->syncVariants($request, $product);

        return redirect()->route('admin.products.index')->with('success', 'Đã cập nhật sản phẩm.');
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->route('admin.products.index')->with('success', 'Đã xóa sản phẩm.');
    }

    public function toggleActive(Product $product)
    {
        $product->update(['is_active' => ! $product->is_active]);

        return back()->with('success', 'Đã cập nhật trạng thái.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'category_id' => ['nullable', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:120'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'compare_price' => ['nullable', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'featured' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'max:4096'],
            'gallery.*' => ['nullable', 'image', 'max:4096'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
        ]);
    }

    protected function fillData(array $data): array
    {
        $fill = [
            'category_id' => $data['category_id'] ?? null,
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.substr(uniqid(), -4),
            'sku' => $data['sku'] ?? null,
            'brand' => $data['brand'] ?? null,
            'short_description' => $data['short_description'] ?? null,
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'compare_price' => $data['compare_price'] ?? null,
            'cost_price' => $data['cost_price'] ?? null,
            'stock' => $data['stock'] ?? 0,
            'featured' => (bool) ($data['featured'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
        ];

        return $fill;
    }

    protected function saveImages(Request $request, Product $product, bool $replace = false): void
    {
        if ($request->hasFile('image')) {
            $product->update(['image' => $request->file('image')->store('products', 'public')]);
        } elseif ($request->filled('cover_url')) {
            // Cover picked from the Studio library (absolute /storage/... URL).
            $product->update(['image' => $request->input('cover_url')]);
        }

        // Preserve existing gallery, drop removed ones, then append freshly uploaded images.
        $gallery = array_values(array_filter((array) $product->gallery));
        $removed = array_values(array_filter((array) $request->input('gallery_remove', [])));
        if ($removed) {
            $gallery = array_values(array_diff($gallery, $removed));
        }

        if ($request->hasFile('gallery')) {
            foreach ($request->file('gallery') as $file) {
                if ($file->isValid()) {
                    $gallery[] = $file->store('products', 'public');
                }
            }
        }

        // Merge Studio/library images (absolute URLs selected in the Vue form).
        foreach (array_values(array_filter((array) $request->input('studio_gallery', []))) as $path) {
            if (is_string($path) && $path !== '' && ! in_array($path, $gallery, true)) {
                $gallery[] = $path;
            }
        }

        if (array_values((array) $product->gallery) !== $gallery) {
            $product->update(['gallery' => $gallery]);
        }
    }

    protected function syncVariants(Request $request, Product $product): void
    {
        // Skip if no variants submitted.
        if (! $request->filled('variants')) {
            return;
        }

        $product->variants()->delete();

        foreach (array_filter($request->input('variants', [])) as $v) {
            if (empty($v['name'])) {
                continue;
            }

            $product->variants()->create([
                'name' => $v['name'],
                'sku' => $v['sku'] ?? null,
                'price' => ($v['price'] !== '' && $v['price'] !== null) ? $v['price'] : $product->price,
                'compare_price' => ($v['compare_price'] ?? '') !== '' ? $v['compare_price'] : null,
                'stock' => $v['stock'] ?? 0,
                'options' => $this->deriveOptions($v['name']) ?: null,
                'is_active' => true,
            ]);
        }
    }

    protected function deriveOptions(string $name): array
    {
        $parts = array_map('trim', explode('/', $name));

        return array_filter($parts, fn ($p) => $p !== '') ?: [];
    }
}