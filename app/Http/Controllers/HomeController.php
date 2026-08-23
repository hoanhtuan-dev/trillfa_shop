<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Post;
use App\Models\Product;

class HomeController extends Controller
{
    public function index()
    {
        seo()->organization()->website();
        $featured = Product::active()->featured()->inStock()
            ->with('category')->latest()->limit(8)->get();

        $newArrivals = Product::active()->inStock()
            ->with('category')->latest()->limit(8)->get();

        $bestsellers = Product::active()->inStock()
            ->withCount('orderItems')
            ->orderByDesc('order_items_count')
            ->orderByDesc('sales_count')
            ->limit(8)->get();

        $onSale = Product::active()->inStock()
            ->where('compare_price', '>', 0)
            ->orderByDesc('id')->limit(8)->get();

        $categories = Category::active()
            ->whereNull('parent_id')
            ->with('children', fn ($q) => $q->active())
            ->orderBy('sort_order')->get();

        $heroBanners = Banner::active()->where('position', 'hero')->orderBy('sort_order')->get();
        $secondaryBanners = Banner::active()->where('position', 'secondary')->orderBy('sort_order')->limit(4)->get();

        $latestPosts = Post::published()->with('author', 'category')->latest()->limit(3)->get();

        return view('home', compact(
            'featured', 'newArrivals', 'bestsellers', 'onSale',
            'categories', 'heroBanners', 'secondaryBanners', 'latestPosts'
        ));
    }
}