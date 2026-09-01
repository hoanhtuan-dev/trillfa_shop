<?php
namespace AppModulesStudio;

use IlluminateSupportServiceProvider;

/**
 * Studio module — wires the Studio feature as a self-contained, reusable unit
 * (config + views + the Vue frontend bundle). Routes stay in web.php but the module
 * owns its config/views/namespace so it can be shared or extracted later.
 */
class StudioModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config.php', 'studio_module');
        // Reusable studio services as module listeners (accessed via app('studio.*')).
        $this->app->singleton('studio.stylist_catalog', fn () => new AppServicesStylistCatalog());
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../views', 'studio');
        // Register the module's JSON endpoints namespace (routes live in web.php studio group).
        $this->app->bind('studio.bridge', fn () => new StudioBridge());
    }
}
