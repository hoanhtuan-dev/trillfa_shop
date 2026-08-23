<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AdminBannerController;
use App\Http\Controllers\Admin\AdminCategoryController;
use App\Http\Controllers\Admin\AdminCouponController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminPaymentController;
use App\Http\Controllers\Admin\AdminPostController;
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\Admin\AdminReviewController;
use App\Http\Controllers\Admin\AdminSettingController;
use App\Http\Controllers\Admin\AdminReportController;
use App\Http\Controllers\Admin\AdminShippingController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminWidgetController;
use App\Http\Controllers\Api\CartApiController;
use App\Http\Controllers\Api\CouponApiController;
use App\Http\Controllers\Api\SearchApiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\WishlistController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

// SEO
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/robots.txt', [RobotsController::class, 'index'])->name('robots');

// Shop
Route::get('/shop', [ShopController::class, 'index'])->name('shop.index');
Route::get('/danh-muc/{categorySlug}', [ShopController::class, 'index'])->name('shop.category');
Route::get('/san-pham/{slug}', [ProductController::class, 'show'])->name('product.show');

// Cart
Route::get('/gio-hang', [CartController::class, 'show'])->name('cart.show');

// Auth
Route::middleware('guest')->group(function () {
    Route::get('/dang-nhap', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/dang-nhap', [AuthController::class, 'login'])->name('login.store');
    Route::get('/dang-ky', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/dang-ky', [AuthController::class, 'register'])->name('register.store');
});
Route::post('/dang-xuat', [AuthController::class, 'logout'])->name('logout');

// Wishlist
Route::middleware('auth')->group(function () {
    Route::get('/yeu-thich', [WishlistController::class, 'index'])->name('wishlist.index');
    Route::post('/yeu-thich/toggle', [WishlistController::class, 'toggle'])->name('wishlist.toggle');
});

// Reviews
Route::post('/san-pham/{product}/danh-gia', [ReviewController::class, 'store'])->name('review.store')->middleware('auth');
Route::post('/danh-gia/{review}/huu-ich', [ReviewController::class, 'helpful'])->name('review.helpful');

// Checkout
Route::middleware('auth')->group(function () {
    Route::get('/thanh-toan', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/thanh-toan', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/thanh-toan/{order}/thanh-toan', [CheckoutController::class, 'pay'])->name('checkout.pay');
    Route::post('/thanh-toan/{order}/thanh-toan', [CheckoutController::class, 'confirmPay'])->name('checkout.confirm');
    Route::get('/thanh-toan/{order}/hoan-tat', [CheckoutController::class, 'success'])->name('checkout.success');
});

// Account (auth)
Route::middleware('auth')->prefix('tai-khoan')->name('account.')->group(function () {
    Route::get('/', [AccountController::class, 'dashboard'])->name('dashboard');
    Route::get('/don-hang', [AccountController::class, 'orders'])->name('orders');
    Route::get('/don-hang/{order}', [AccountController::class, 'order'])->name('order');
    Route::post('/don-hang/{order}/huy', [AccountController::class, 'cancelOrder'])->name('order.cancel');
    Route::get('/ho-so', [AccountController::class, 'profile'])->name('profile');
    Route::post('/ho-so', [AccountController::class, 'updateProfile'])->name('profile.update');
    Route::post('/mat-khau', [AccountController::class, 'changePassword'])->name('password.update');
    Route::get('/dia-chi', [AccountController::class, 'addresses'])->name('addresses');
    Route::post('/dia-chi', [AccountController::class, 'storeAddress'])->name('addresses.store');
    Route::put('/dia-chi/{address}', [AccountController::class, 'updateAddress'])->name('addresses.update');
    Route::delete('/dia-chi/{address}', [AccountController::class, 'deleteAddress'])->name('addresses.delete');
    Route::get('/danh-gia', [AccountController::class, 'reviews'])->name('reviews');
});

// Blog
Route::prefix('blog')->name('blog.')->group(function () {
    Route::get('/', [BlogController::class, 'index'])->name('index');
    Route::get('/danh-muc/{slug}', [BlogController::class, 'category'])->name('category');
    Route::get('/{slug}', [BlogController::class, 'show'])->name('show');
});

// Static pages
Route::get('/gioi-thieu', [PageController::class, 'about'])->name('page.about');
Route::get('/lien-he', [PageController::class, 'contact'])->name('page.contact');
Route::post('/lien-he', [PageController::class, 'sendContact'])->name('page.contact.send');
Route::get('/hoi-dap', [PageController::class, 'faq'])->name('page.faq');
Route::get('/chinh-sach-bao-mat', [PageController::class, 'privacy'])->name('page.privacy');
Route::get('/dieu-khoan', [PageController::class, 'terms'])->name('page.terms');

// API (cart / coupon / search) — JSON
Route::prefix('api')->name('api.')->group(function () {
    Route::get('/cart', [CartApiController::class, 'index'])->name('cart.index');
    Route::post('/cart/add', [CartApiController::class, 'add'])->name('cart.add');
    Route::post('/cart/update', [CartApiController::class, 'update'])->name('cart.update');
    Route::post('/cart/remove', [CartApiController::class, 'remove'])->name('cart.remove');
    Route::post('/cart/clear', [CartApiController::class, 'clear'])->name('cart.clear');
    Route::post('/cart/shipping', [CartApiController::class, 'shipping'])->name('cart.shipping');
    Route::post('/coupon/apply', [CouponApiController::class, 'apply'])->name('coupon.apply');
    Route::delete('/coupon/remove', [CouponApiController::class, 'remove'])->name('coupon.remove');
    Route::get('/search', [SearchApiController::class, 'index'])->name('search');
});

// Admin
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::get('/reports', [AdminReportController::class, 'index'])->name('reports.index');
    Route::get('/widgets', [AdminWidgetController::class, 'index'])->name('widgets.index');
    Route::post('/widgets', [AdminWidgetController::class, 'update'])->name('widgets.update');

    Route::get('/products', [AdminProductController::class, 'index'])->name('products.index');
    Route::get('/products/create', [AdminProductController::class, 'create'])->name('products.create');
    Route::post('/products', [AdminProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}/edit', [AdminProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [AdminProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{product}', [AdminProductController::class, 'destroy'])->name('products.destroy');
    Route::post('/products/{product}/toggle', [AdminProductController::class, 'toggleActive'])->name('products.toggle');

    Route::get('/categories', [AdminCategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories', [AdminCategoryController::class, 'store'])->name('categories.store');
    Route::put('/categories/{category}', [AdminCategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy'])->name('categories.destroy');

    Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/export', [AdminOrderController::class, 'export'])->name('orders.export');
    Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::put('/orders/{order}', [AdminOrderController::class, 'updateStatus'])->name('orders.update');
    Route::delete('/orders/{order}', [AdminOrderController::class, 'destroy'])->name('orders.destroy');

    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
    Route::post('/users/{user}/password', [AdminUserController::class, 'updatePassword'])->name('users.password');
    Route::post('/users/{user}/toggle', [AdminUserController::class, 'toggleActive'])->name('users.toggle');
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');

    Route::get('/coupons', [AdminCouponController::class, 'index'])->name('coupons.index');
    Route::post('/coupons', [AdminCouponController::class, 'store'])->name('coupons.store');
    Route::put('/coupons/{coupon}', [AdminCouponController::class, 'update'])->name('coupons.update');
    Route::post('/coupons/{coupon}/toggle', [AdminCouponController::class, 'toggleActive'])->name('coupons.toggle');
    Route::delete('/coupons/{coupon}', [AdminCouponController::class, 'destroy'])->name('coupons.destroy');

    Route::get('/reviews', [AdminReviewController::class, 'index'])->name('reviews.index');
    Route::post('/reviews/{review}/toggle', [AdminReviewController::class, 'toggleActive'])->name('reviews.toggle');
    Route::delete('/reviews/{review}', [AdminReviewController::class, 'destroy'])->name('reviews.destroy');

    Route::get('/posts', [AdminPostController::class, 'index'])->name('posts.index');
    Route::get('/posts/create', [AdminPostController::class, 'create'])->name('posts.create');
    Route::post('/posts', [AdminPostController::class, 'store'])->name('posts.store');
    Route::get('/posts/{post}/preview', [AdminPostController::class, 'preview'])->name('posts.preview');
    Route::get('/posts/{post}/edit', [AdminPostController::class, 'edit'])->name('posts.edit');
    Route::put('/posts/{post}', [AdminPostController::class, 'update'])->name('posts.update');
    Route::delete('/posts/{post}', [AdminPostController::class, 'destroy'])->name('posts.destroy');

    Route::get('/banners', [AdminBannerController::class, 'index'])->name('banners.index');
    Route::post('/banners', [AdminBannerController::class, 'store'])->name('banners.store');
    Route::put('/banners/{banner}', [AdminBannerController::class, 'update'])->name('banners.update');
    Route::post('/banners/{banner}/toggle', [AdminBannerController::class, 'toggleActive'])->name('banners.toggle');
    Route::delete('/banners/{banner}', [AdminBannerController::class, 'destroy'])->name('banners.destroy');

    Route::get('/settings', [AdminSettingController::class, 'index'])->name('settings.index');
    Route::post('/settings', [AdminSettingController::class, 'update'])->name('settings.update');

    Route::get('/shipping', [AdminShippingController::class, 'index'])->name('shipping.index');
    Route::post('/shipping', [AdminShippingController::class, 'store'])->name('shipping.store');
    Route::put('/shipping/{method}', [AdminShippingController::class, 'update'])->name('shipping.update');
    Route::post('/shipping/{method}/toggle', [AdminShippingController::class, 'toggleActive'])->name('shipping.toggle');
    Route::delete('/shipping/{method}', [AdminShippingController::class, 'destroy'])->name('shipping.destroy');

    Route::get('/payments', [AdminPaymentController::class, 'index'])->name('payments.index');
    Route::put('/payments/{method}', [AdminPaymentController::class, 'update'])->name('payments.update');
    Route::post('/payments/{method}/toggle', [AdminPaymentController::class, 'toggleActive'])->name('payments.toggle');
});