<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
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
        $categories = Category::active()->orderBy('sort_order')->get();

        return view('admin.products.form', compact('categories'))->with('product', new Product());
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
        $categories = Category::active()->orderBy('sort_order')->get();
        $product->load('variants');

        return view('admin.products.form', compact('categories', 'product'));
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