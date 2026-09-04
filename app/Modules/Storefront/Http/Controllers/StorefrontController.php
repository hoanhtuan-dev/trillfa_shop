<?php

namespace App\Modules\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Storefront\StorefrontBridge;
use Illuminate\Http\Request;

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

    /**
     * Shop listing (shop + /danh-muc/{category}) — renders the Vue shop SPA,
     * or returns JSON when the request asks for it (filter/sort/page updates).
     */
    public function shop(Request $request, ?string $categorySlug = null)
    {
        $filters = [
            'q' => $request->input('q', ''),
            'brand' => $request->input('brand', ''),
            'min_price' => $request->input('min_price', ''),
            'max_price' => $request->input('max_price', ''),
            'sort' => $request->input('sort', 'newest'),
        ];
        $page = (int) $request->input('page', 1);

        $payload = $this->bridge->shop($categorySlug, $filters, $page);

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        if ($categorySlug && $payload['category_name']) {
            seo()->title($payload['category_name'].' | '.setting('site_name'))
                ->canonical(route('shop.category', $categorySlug));
        } else {
            seo()->title('Cửa hàng | '.setting('site_name'))
                ->canonical(route('shop.index'));
        }
        seo()->description('Khám phá bộ sưu tập thời trang & phong cách sống tại Trillfa Fa.');

        $payload['slug'] = $categorySlug;

        return view('storefront.shop', [
            'boot' => $payload,
        ]);
    }
}
