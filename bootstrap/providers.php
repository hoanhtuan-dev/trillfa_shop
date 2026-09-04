<?php

use App\Modules\Storefront\StorefrontModuleServiceProvider;
use App\Modules\Studio\StudioModuleServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    StudioModuleServiceProvider::class,
    StorefrontModuleServiceProvider::class,
];
