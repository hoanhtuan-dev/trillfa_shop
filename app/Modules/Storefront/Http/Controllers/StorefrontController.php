<?php

namespace App\Modules\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Storefront\StorefrontBridge;

/**
 * Public storefront controller. Thin: delegates all data shaping to the bridge
 * and only cares about returning the mount view (boot payload) or JSON feed.
 */
class StorefrontController extends Controller
{
    public function __construct(protected StorefrontBridge $bridge)
    {
    }

    /**
     * The homepage — renders the Vue SPA mount view with a boot payload.
     */
    public function home()
    {
        // SEO + org schema (same intent as the old HomeController).
        seo()->organization()->website();

        return view('storefront.index', [
            'boot' => $this->bridge->homePayload(),
        ]);
    }

    /**
     * JSON feed — lets the SPA re-request a fresh payload after a cache expiry.
     */
    public function feed()
    {
        return response()->json($this->bridge->homePayload());
    }
}
