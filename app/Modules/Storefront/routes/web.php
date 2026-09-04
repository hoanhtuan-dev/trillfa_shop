<?php

use App\Modules\Storefront\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Storefront module routes
|--------------------------------------------------------------------------
| The public homepage and the JSON feed the SPA can re-request if the static
| boot payload is missing (e.g. after an SSR cache expires) or for a refresh.
| Loaded by StorefrontModuleServiceProvider so the feature is self-contained.
*/

Route::middleware('web')->group(function () {
    Route::get('/', [StorefrontController::class, 'home'])->name('home');

    Route::get('/api/storefront/home', [StorefrontController::class, 'feed'])
        ->name('storefront.feed');
});
