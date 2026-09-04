<?php

use App\Modules\Storefront\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Storefront module routes
|--------------------------------------------------------------------------
| The public homepage and shop listing (Vue SPA), plus the JSON feeds the SPAs
| re-request for cache expiry or filter/sort/page updates. Loaded by
| StorefrontModuleServiceProvider so the feature is self-contained.
*/

Route::middleware('web')->group(function () {
    Route::get('/', [StorefrontController::class, 'home'])->name('home');

    // Product detail.
    Route::get('/san-pham/{slug}', [StorefrontController::class, 'product'])->name('product.show');

    // Cart.
    Route::get('/gio-hang', [StorefrontController::class, 'cart'])->name('cart.show');

    // Blog.
    Route::get('/blog', [StorefrontController::class, 'blogIndex'])->name('blog.index');
    Route::get('/blog/{slug}', [StorefrontController::class, 'blogPost'])->name('blog.show');

    // Wishlist.
    Route::get('/yeu-thich', [StorefrontController::class, 'wishlist'])->name('wishlist.index');
    Route::get('/api/storefront/wishlist', [StorefrontController::class, 'wishlistFeed'])->name('storefront.wishlist');

    // Checkout (order creation stays on the legacy POST /thanh-toan endpoint).
    Route::get('/thanh-toan', [StorefrontController::class, 'checkout'])
        ->name('checkout.show')->middleware('auth');

    // Shop listing (all products + per-category).
    Route::get('/shop', [StorefrontController::class, 'shop'])->name('shop.index');
    Route::get('/danh-muc/{categorySlug}', [StorefrontController::class, 'shop'])->name('shop.category');

    // JSON feeds.
    Route::get('/api/storefront/home', [StorefrontController::class, 'feed'])->name('storefront.feed');
    Route::get('/api/storefront/shop', [StorefrontController::class, 'shop'])->name('storefront.shop');
    Route::get('/api/storefront/shop/{categorySlug}', [StorefrontController::class, 'shop'])->name('storefront.shop.category');
});
