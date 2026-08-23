<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AdminWidgetController extends Controller
{
    protected function widgets(): array
    {
        return [
            'hero' => ['label' => 'Hero slider', 'has_limit' => false],
            'secondary' => ['label' => 'Banner phụ', 'has_limit' => false],
            'categories' => ['label' => 'Khám phá theo danh mục', 'has_limit' => false],
            'featured' => ['label' => 'Sản phẩm nổi bật', 'has_limit' => true, 'default_limit' => 8],
            'new' => ['label' => 'Hàng mới nhất', 'has_limit' => true, 'default_limit' => 8],
            'bestsellers' => ['label' => 'Bán chạy', 'has_limit' => true, 'default_limit' => 8],
            'sale' => ['label' => 'Ưu đãi / Sale', 'has_limit' => true, 'default_limit' => 8],
            'blog' => ['label' => 'Blog preview', 'has_limit' => false],
            'cta' => ['label' => 'Khối CTA (kêu gọi hành động)', 'has_limit' => false],
        ];
    }

    public function index()
    {
        $widgets = collect($this->widgets())->map(function ($def, $key) {
            $def['key'] = $key;
            $def['enabled'] = widget_enabled($key, true);
            $def['limit'] = widget_limit($key, $def['default_limit'] ?? 8);

            return $def;
        })->values();

        return view('admin.widgets.index', compact('widgets'));
    }

    public function update(Request $request)
    {
        foreach ($this->widgets() as $key => $def) {
            // Flat checkbox name: hidden 0 + checkbox 1 -> $_POST holds last value.
            set_setting('widget_'.$key.'_enabled', $request->boolean('enabled_'.$key, true) ? '1' : '0');

            if ($def['has_limit']) {
                $limit = max(1, min(24, (int) $request->input('limit_'.$key, $def['default_limit'] ?? 8)));
                set_setting('widget_'.$key.'_limit', $limit);
            }
        }

        return back()->with('success', 'Đã lưu cài đặt widget.');
    }
}
