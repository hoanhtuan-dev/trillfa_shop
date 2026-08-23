<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Setting;
use App\Support\Seo;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Seo::class, fn () => new Seo());
    }

    public function boot(): void
    {
        // Shared data for every view.
        View::composer('*', function ($view) {
            if (str_starts_with($view->name(), 'errors.')) {
                return;
            }
            $view->with([
                'storeSettings' => Setting::allAsArray(),
                'navbarCategories' => Category::active()
                    ->whereNull('parent_id')
                    ->with('children', fn ($q) => $q->active())
                    ->orderBy('sort_order')->get(),
            ]);
        });

        // SEO defaults (only fills empty fields so controllers can override).
        View::composer('*', function ($view) {
            if (str_starts_with($view->name(), 'errors.')) {
                return;
            }
            app(Seo::class)
                ->applyDefaults([
                    'title' => setting('site_name', 'Trillfa Fa'),
                    'description' => setting('site_tagline', 'Thời trang & Phong cách sống'),
                    'canonical' => url()->current(),
                    'image' => asset('images/logo.png'),
                    'robots' => 'index,follow',
                ]);

            // Pages that should not be indexed.
            if (request()->is('gio-hang', 'thanh-toan*', 'tai-khoan*', 'yeu-thich*', 'dang-nhap', 'dang-ky', 'admin*')) {
                app(Seo::class)->noindex();
            }
        });
    }
}