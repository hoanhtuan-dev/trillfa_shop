<?php

use App\Models\Setting;

if (! function_exists('setting')) {
    function setting($key, $default = null)
    {
        return Setting::get($key, $default);
    }
}

if (! function_exists('set_setting')) {
    function set_setting($key, $value): void
    {
        Setting::set($key, $value);
    }
}

if (! function_exists('format_price')) {
    function format_price($value): string
    {
        $n = (float) $value;

        return number_format($n, 0, ',', '.').'₫';
    }
}

if (! function_exists('get_cart')) {
    function get_cart(): \App\Models\Cart
    {
        return app(\App\Services\CartService::class)->cart();
    }
}

if (! function_exists('cart_payload')) {
    function cart_payload(): array
    {
        return app(\App\Services\CartService::class)->payload();
    }
}

if (! function_exists('normalize_vn')) {
    function normalize_vn(?string $value): string
    {
        $value = (string) $value;
        if (class_exists('\\Transliterator')) {
            $value = transliterator_transliterate('Any-Latin; Latin-ASCII', $value);
        } else {
            $map = [
                'à'=>'a','á'=>'a','ả'=>'a','ã'=>'a','ạ'=>'a', 'ă'=>'a','ằ'=>'a','ắ'=>'a','ẳ'=>'a','ẵ'=>'a','ặ'=>'a',
                'â'=>'a','ầ'=>'a','ấ'=>'a','ẩ'=>'a','ẫ'=>'a','ậ'=>'a',
                'è'=>'e','é'=>'e','ẻ'=>'e','ẽ'=>'e','ẹ'=>'e', 'ê'=>'e','ề'=>'e','ế'=>'e','ể'=>'e','ễ'=>'e','ệ'=>'e',
                'ì'=>'i','í'=>'i','ỉ'=>'i','ĩ'=>'i','ị'=>'i',
                'ò'=>'o','ó'=>'o','ỏ'=>'o','õ'=>'o','ọ'=>'o', 'ô'=>'o','ồ'=>'o','ố'=>'o','ổ'=>'o','ỗ'=>'o','ộ'=>'o',
                'ơ'=>'o','ờ'=>'o','ớ'=>'o','ở'=>'o','ỡ'=>'o','ợ'=>'o',
                'ù'=>'u','ú'=>'u','ủ'=>'u','ũ'=>'u','ụ'=>'u', 'ư'=>'u','ừ'=>'u','ứ'=>'u','ử'=>'u','ữ'=>'u','ự'=>'u',
                'ỳ'=>'y','ý'=>'y','ỷ'=>'y','ỹ'=>'y','ỵ'=>'y',
                'đ'=>'d', 'Đ'=>'D',
                'À'=>'A','Á'=>'A','Ả'=>'A','Ã'=>'A','Ạ'=>'A','Ă'=>'A','Ằ'=>'A','Ắ'=>'A','Ẳ'=>'A','Ẵ'=>'A','Ặ'=>'A',
                'Â'=>'A','Ầ'=>'A','Ấ'=>'A','Ẩ'=>'A','Ẫ'=>'A','Ậ'=>'A',
                'È'=>'E','É'=>'E','Ẻ'=>'E','Ẽ'=>'E','Ẹ'=>'E','Ê'=>'E','Ề'=>'E','Ế'=>'E','Ể'=>'E','Ễ'=>'E','Ệ'=>'E',
                'Ì'=>'I','Í'=>'I','Ỉ'=>'I','Ĩ'=>'I','Ị'=>'I',
                'Ò'=>'O','Ó'=>'O','Ỏ'=>'O','Õ'=>'O','Ọ'=>'O','Ô'=>'O','Ồ'=>'O','Ố'=>'O','Ổ'=>'O','Ỗ'=>'O','Ộ'=>'O',
                'Ơ'=>'O','Ờ'=>'O','Ớ'=>'O','Ở'=>'O','Ỡ'=>'O','Ợ'=>'O',
                'Ù'=>'U','Ú'=>'U','Ủ'=>'U','Ũ'=>'U','Ụ'=>'U','Ư'=>'U','Ừ'=>'U','Ứ'=>'U','Ử'=>'U','Ữ'=>'U','Ự'=>'U',
                'Ỳ'=>'Y','Ý'=>'Y','Ỷ'=>'Y','Ỹ'=>'Y','Ỵ'=>'Y',
            ];
            $value = strtr($value, $map);
        }

        $value = mb_strtolower($value, 'UTF-8');
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);

        return trim($value);
    }
}

if (! function_exists('asset_image')) {
    function asset_image(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http')) {
            return $path;
        }

        if (str_starts_with($path, 'samples/')) {
            return asset($path);
        }

        return asset('storage/'.$path);
    }
}

if (! function_exists('seo')) {
    function seo(): \App\Support\Seo
    {
        return app(\App\Support\Seo::class);
    }
}

if (! function_exists('category_icons')) {
    function category_icons(): array
    {
        return [
            'tag' => ['label' => 'Danh mục', 'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z"/>'],
            'bag' => ['label' => 'Túi xách', 'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z"/>'],
            'home' => ['label' => 'Nhà cửa', 'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75"/>'],
            'clock' => ['label' => 'Đồng hồ', 'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>'],
            'eye' => ['label' => 'Kính mắt', 'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>'],
            'star' => ['label' => 'Nổi bật', 'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-6.98-2.885-6.98 2.885a.562.562 0 01-.84-.61l1.285-5.385a.563.563 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/>'],
            'heart' => ['label' => 'Yêu thích', 'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/>'],
            'shirt' => ['label' => 'Thời trang', 'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3.5L3.5 6.25l2 4.5 2.5-1.5V20.5h8V9.25l2.5 1.5 2-4.5L15.75 3.5a4.75 4.75 0 01-7.5 0z"/>'],
            'sparkles' => ['label' => 'Làm đẹp', 'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 4.5l1.2 3.8 3.8 1.2-3.8 1.2L9 14.5l-1.2-3.8L4 9.5l3.8-1.2L9 4.5z"/><path stroke-linecap="round" stroke-linejoin="round" d="M17.5 12l.9 2.6 2.6.9-2.6.9-.9 2.6-.9-2.6-2.6-.9 2.6-.9.9-2.6z"/>'],
            'gift' => ['label' => 'Quà tặng', 'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12M12 6l-3.3-3.3a2.1 2.1 0 10-3 3L9 6m3-3.5L15.3 5.6a2.1 2.1 0 113-3L12 6.5M4.5 9h15A1.5 1.5 0 0121 10.5v4A1.5 1.5 0 0119.5 16h-15A1.5 1.5 0 013 14.5v-4A1.5 1.5 0 014.5 9z"/>'],

            'chat' => ['label' => 'Liên hệ', 'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/>'],
            'phone' => ['label' => 'Điện thoại', 'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/>'],
            'camera' => ['label' => 'Máy ảnh', 'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"/>'],
            'book' => ['label' => 'Sách', 'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/>'],
            'music' => ['label' => 'Âm nhạc', 'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 9l10.5-3m0 6.553v3.75a2.25 2.25 0 01-1.632 2.163l-1.32.377a1.803 1.803 0 11-.99-3.467l2.31-.66a2.25 2.25 0 001.632-2.163zm0 0V2.25L9 5.25v10.303m0 0v3.75a2.25 2.25 0 01-1.632 2.163l-1.32.377a1.803 1.803 0 01-.99-3.467l2.31-.66A2.25 2.25 0 009 15.553z"/>'],
            'shield' => ['label' => 'Bảo hành', 'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>'],
            'sun' => ['label' => 'Mùa hè', 'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/>'],
            'moon' => ['label' => 'Ban đêm', 'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z"/>'],
            'bolt' => ['label' => 'Ưu đãi', 'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/>'],
            'bank' => ['label' => 'Tài chính', 'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21.75H2.25m0 0H1.5M9 9h.008v.008H9V9zm3 0h.008v.008H12V9zm3 0h.008v.008H15V9zm-6 5h.008v.008H9V14zm3 0h.008v.008H12V14zm3 0h.008v.008H15V14z"/>'],
            'wrench' => ['label' => 'Dịch vụ', 'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437l1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008z"/>'],
            'cube' => ['label' => 'Sản phẩm', 'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5L21 9l-9 4.5L3 9l9-4.5zM3 9v6l9 4.5 9-4.5V9M12 13.5V21"/>'],

        ];
    }
}

if (! function_exists('category_icon_svg')) {
    function category_icon_svg(?string $name): string
    {
        $icons = category_icons();
        $key = $name ?? 'tag';

        return $icons[$key]['svg'] ?? $icons['tag']['svg'];
    }
}

if (! function_exists('widget_enabled')) {
    function widget_enabled(string $key, bool $default = true): bool
    {
        $v = setting('widget_'.$key.'_enabled');

        return $v === null ? $default : (bool) $v;
    }
}

if (! function_exists('widget_limit')) {
    function widget_limit(string $key, int $default = 8): int
    {
        return max(1, (int) setting('widget_'.$key.'_limit', $default));
    }
}

if (! function_exists('widget_field')) {
    function widget_field(string $key, string $field, $default = ''): string
    {
        return (string) setting('widget_'.$key.'_'.$field, $default);
    }
}
if (! function_exists('studio_config')) {
    function studio_config(string $key, $default = null)
    {
        // Prefer a DB setting override (set via the Studio admin), fall back to config.
        $stored = setting('studio_'.$key);

        return $stored !== null ? (string) $stored : config('studio.'.$key, $default);
    }
}

if (! function_exists('studio_api_key')) {
    /**
     * Read a provider API key. Prefers the encrypted DB value managed from the
     * Studio API page; falls back to the env/config value.
     */
    function studio_api_keys_for(?string $provider = null, ?string $model_id = null, ?string $group = null)
    {
        $q = \App\Models\StudioApiKey::query()->where('enabled', true);
        if ($provider) $q->where('provider', $provider);
        $rows = $q->orderByDesc('priority')->orderBy('id')->get();
        if ($rows->isEmpty()) return collect();
        return $rows->filter(function ($k) use ($model_id, $group) {
            $sc = $k->scopes ?? ['*'];
            if (in_array('*', $sc, true)) return true;
            if ($model_id && in_array($model_id, $sc, true)) return true;
            if ($group && in_array($group, $sc, true)) return true;
            return false;
        })->values();
    }

    function studio_api_key_value($keyOrValue): ?string
    {
        $value = is_object($keyOrValue) ? $keyOrValue->value : $keyOrValue;
        if (! $value) return null;
        try { return \Illuminate\Support\Facades\Crypt::decryptString($value); }
        catch (\Throwable $e) { return $value; }
    }

    function studio_api_key(string $service): ?string
    {
        // Registered keys first (highest-priority enabled for this provider).
        $reg = \App\Models\StudioApiKey::where('provider', $service)->where('enabled', true)->orderByDesc('priority')->orderBy('id')->first();
        if ($reg) return studio_api_key_value($reg);

        $stored = setting('api_'.$service.'_key');

        if ($stored) {
            try {
                return \Illuminate\Support\Facades\Crypt::decryptString($stored);
            } catch (\Throwable $e) {
                return $stored;
            }
        }

        $configKeys = [
            'gemini' => 'gemini_key',
            'fal' => 'fal_key',
            'replicate' => 'replicate_token',
            'wan' => 'wan_key',
            'veo' => 'veo_key',
            'qwen' => 'qwen_key',
            'qwen_edit' => 'qwen_edit_key',
            'dashscope' => 'dashscope_key',
            'deepseek' => 'deepseek_key',
        ];

        $key = $configKeys[$service] ?? null;

        return $key ? config('studio.'.$key) ?: null : null;
    }
}

if (! function_exists('dashscope_base_url')) {
    /**
     * QwenCloud / DashScope hosts are separate per key type and must NOT be mixed.
     *  - sk-sp-…  (Token / Coding Plan) -> token-plan host (text/code models only)
     *  - sk-ws-… / sk-… (Pay-As-You-Go) -> dashscope-intl host (image/video generation)
     */
    function dashscope_base_url(string $key): string
    {
        if (str_starts_with($key, 'sk-sp-')) {
            return rtrim((string) studio_config('dashscope_token_plan_base', 'https://token-plan.ap-southeast-1.maas.aliyuncs.com'), '/');
        }

        $payGo = rtrim((string) studio_config('dashscope_base', 'https://dashscope-intl.aliyuncs.com'), '/');

        // A Pay-As-You-Go key (sk-… / sk-ws-…) must NEVER be sent to a Token/Coding Plan host.
        // If the admin left dashscope_base pointing at a plan host, correct it to the pay-go host.
        if (str_contains($payGo, 'token-plan.') || str_contains($payGo, 'coding-')) {
            $payGo = 'https://dashscope-intl.aliyuncs.com';
        }

        return $payGo;
    }
}

if (! function_exists('is_qwen_quota_error')) {
    /**
     * Whether a DashScope/QwenCloud message/body indicates quota exhaustion (Throttling.AllocationQuota).
     */
    function is_qwen_quota_error(?string $message): bool
    {
        if (! $message) {
            return false;
        }
        $lower = strtolower($message);

        return str_contains($lower, 'allocationquota') || str_contains($lower, 'throttling') || str_contains($message, '429');
    }
}

if (! function_exists('studio_qwen_credentials')) {
    /**
     * Ordered Qwen/DashScope API keys to try for a task, with automatic failover:
     *   - image / video / prompt / vision: Token Plan (sk-sp-…) first, then Pay-As-You-Go (sk-…/sk-ws-…)
     *   - edit (Inpaint): Pay-As-You-Go first (edit models usually live on the pay-go host), then Token Plan.
     * Keys are gathered from every Qwen/DashScope slot and ordered by their prefix.
     */
    if (! function_exists('deepseek_base_url')) {
    function deepseek_base_url(string $key): string
    {
        return (string) config('studio.deepseek_base', 'https://api.deepseek.com');
    }
}

if (! function_exists('deepseek_chat')) {
    /**
     * Simple DeepSeek chat-completion call (text). Returns the assistant reply or null.
     */
    function deepseek_chat(string $instruction, ?string $model = null): ?string
    {
        $key = studio_api_key('deepseek');
        if (! $key) { return null; }
        $model = $model ?: (string) config('studio.deepseek_model', 'deepseek-chat');
        try {
            $resp = \Illuminate\Support\Facades\Http::withToken($key)->timeout(60)
                ->post((string) config('studio.deepseek_base', 'https://api.deepseek.com').'/chat/completions', [
                    'model' => $model,
                    'messages' => [['role' => 'user', 'content' => $instruction]],
                    'stream' => false,
                ]);
            if ($resp->successful()) {
                $out = trim((string) data_get($resp->json(), 'choices.0.message.content'));
                return $out !== '' ? $out : null;
            }
            logger()->warning('DeepSeek chat failed: '.$resp->status().' '.substr((string) $resp->body(), 0, 180));
        } catch (\Throwable $e) { logger()->warning('DeepSeek chat error: '.$e->getMessage()); }
        return null;
    }
}

function studio_qwen_credentials(string $task = 'image', ?string $model_id = null): array
    {
        // Registered keys (multi per provider, scope-aware) — fall back to env/config slots.
        $keys = [];
        foreach (['qwen', 'dashscope', 'qwen_edit', 'wan'] as $p) {
            foreach (studio_api_keys_for($p, $model_id, $task === 'edit' ? 'edit' : ($task === 'video' ? 'video' : 'image')) as $k) {
                $v = studio_api_key_value($k);
                if ($v) $keys[] = $v;
            }
        }
        $keys = array_merge($keys, array_values(array_unique(array_filter([
            studio_api_key('qwen'), studio_api_key('dashscope'), studio_api_key('qwen_edit'), studio_api_key('wan'),
        ]))));
        $keys = array_values(array_unique(array_filter($keys)));

        $plan = [];
        $paygo = [];
        foreach ($keys as $k) {
            if (str_starts_with($k, 'sk-sp-')) {
                $plan[] = $k;
            } else {
                $paygo[] = $k;
            }
        }

        // GEN (image/video/edit) models live on the Pay-As-You-Go host; TEXT/Chat (prompt/vision) live on
        // the Token/Coding-Plan host. Order accordingly so a video/image request isn't sent to the plan host
        // (which has no image/video model -> "Model not exist") and doesn't burn the free-tier plan quota.
        return in_array($task, ['prompt', 'vision'], true)
            ? array_merge($plan, $paygo)   // text/vision: plan first
            : array_merge($paygo, $plan);  // image/video/edit: pay-go first
    }
}

if (! function_exists('capture_provider_quota_reset')) {
    /**
     * Extract the provider's "quota will reset at <time> UTC" from a quota error and store it
     * so the UI can show when the limit resets.
     */
    function capture_provider_quota_reset(?string $message): void
    {
        if (! $message) {
            return;
        }
        if (preg_match('/reset(?:s| will reset)? at ([0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2} UTC)/i', $message, $m)) {
            set_setting('studio_provider_quota_resets_at', $m[1]);
        }
    }
}

if (! function_exists('studio_usage')) {
    /**
     * Real token/credit usage summary (from the DB) + the provider's last-known quota reset time.
     */
    function studio_usage($user = null): array
    {
        $user = $user ?? auth()->user();
        $q = $user ? $user->generations()->where('status', 'completed') : null;

        return [
            'balance' => $user ? (int) $user->credits_balance : 0,
            'used_total' => $q ? (int) $q->sum('credits_cost') : 0,
            'used_today' => $q ? (int) $q->whereDate('created_at', today())->sum('credits_cost') : 0,
            'limit' => (int) studio_config('quota_limit', 0),
            'quota_resets_at' => (string) setting('studio_provider_quota_resets_at', ''),
        ];
    }
}

if (! function_exists('category_children_nodes')) {
    function category_children_nodes(\App\Models\Category $category): \Illuminate\Support\Collection
    {
        return $category->children()->orderBy('sort_order')->get()->map(function ($sub) {
            return (object) [
                'label' => $sub->name,
                'url' => route('shop.category', $sub->slug),
                'children' => category_children_nodes($sub),
            ];
        });
    }
}

if (! function_exists('menu_tree')) {
    function menu_tree(string $location = 'header'): \Illuminate\Support\Collection
    {
        $items = \App\Models\MenuItem::location($location)->active()
            ->with(['category', 'customPage'])->orderBy('sort_order')->orderBy('id')->get();

        $byParent = $items->groupBy('parent_id');

        $builder = function ($parentId) use ($byParent, &$builder) {
            $nodes = collect();

            foreach ($byParent->get($parentId, collect()) as $item) {
                // Category-type menu item: auto-render subcategories recursively.
                if ($item->type === 'category' && $item->category) {
                    $children = category_children_nodes($item->category);
                } else {
                    $children = $builder($item->id);
                }

                $nodes->push((object) [
                    'label' => $item->label,
                    'url' => $item->getUrl(),
                    'children' => $children,
                ]);
            }

            return $nodes;
        };

        return $builder(null);
    }
/**
 * Studio model registry — dynamic per group (image | video | inference).
 * Falls back to a built-in catalog when nothing is registered yet (so it works out-of-the-box).
 */
if (! function_exists('studio_model_catalog')) {
    function studio_model_catalog(): array
    {
        return [
            ['group' => 'image', 'name' => 'Flux Schnell (Fal)', 'provider' => 'fal', 'model_id' => 'flux-1.1-schnell', 'api_key_ref' => 'fal', 'priority' => 5, 'note' => 'Nhanh, rẻ — dùng cho stub/CRUD'],
            ['group' => 'image', 'name' => 'Qwen Image 3.0 Pro', 'provider' => 'qwen', 'model_id' => 'qwen-image-3.0-pro', 'api_key_ref' => 'qwen', 'priority' => 8, 'note' => 'Giàu chi tiết, ưu tiên cao'],
            ['group' => 'image', 'name' => 'Wan 2.7 Image Pro', 'provider' => 'wan', 'model_id' => 'wan2.7-image-pro', 'api_key_ref' => 'dashscope', 'priority' => 7, 'note' => 'DashScope'],
            ['group' => 'image', 'name' => 'Gemini Flash Image', 'provider' => 'gemini', 'model_id' => 'gemini-2.5-flash-image', 'api_key_ref' => 'gemini', 'priority' => 6, 'note' => 'Google'],
            ['group' => 'video', 'name' => 'Wan 2.2 i2v', 'provider' => 'wan', 'model_id' => 'wan2.2-i2v', 'api_key_ref' => 'wan', 'priority' => 9, 'note' => 'Chất lượng cao'],
            ['group' => 'video', 'name' => 'Wan 2.5 i2v', 'provider' => 'wan', 'model_id' => 'wan2.5-i2v', 'api_key_ref' => 'wan', 'priority' => 7, 'note' => 'Cân bằng'],
            ['group' => 'video', 'name' => 'Wan 2.1 i2v Turbo', 'provider' => 'wan', 'model_id' => 'wan2.1-i2v-turbo', 'api_key_ref' => 'wan', 'priority' => 5, 'note' => 'Nhanh'],
            ['group' => 'video', 'name' => 'Kling i2v', 'provider' => 'kling', 'model_id' => 'kling-v1-6-i2v', 'api_key_ref' => 'kling', 'priority' => 8, 'note' => 'Nếu có key Kling'],
            ['group' => 'inference', 'name' => 'Gemini (Giám đốc sáng tạo)', 'provider' => 'gemini', 'model_id' => 'gemini-2.5-flash', 'api_key_ref' => 'gemini', 'priority' => 9, 'note' => 'Suy luận prompt'],
            ['group' => 'inference', 'name' => 'Qwen Chat', 'provider' => 'qwen', 'model_id' => 'qwen-max', 'api_key_ref' => 'qwen', 'priority' => 7, 'note' => 'Suy luận prompt'],
        ];
    }
}

if (! function_exists('studio_models')) {
    function studio_models(?string $group = null)
    {
        $rows = App\Models\StudioModel::query()->get();
        if ($rows->isEmpty()) {
            $rows = collect(studio_model_catalog());
        }
        return $group ? $rows->where('group', $group)->values() : $rows;
    }
}

if (! function_exists('resolve_studio_model')) {
    // Pick the highest-priority ENABLED model for a group.
    function resolve_studio_model(string $group): ?array
    {
        $m = studio_models($group)
            ->where('enabled', true)
            ->sortByDesc('priority')
            ->first();
        if (! $m) return null;
        return ['provider' => $m['provider'], 'model' => $m['model_id'], 'api_key_ref' => $m['api_key_ref'] ?? null];
    }
}

}


/**
 * Resolve a robust VISION model for describing a face / analyzing a reference image.
 * Ignores clearly-wrong configs (e.g. qwen3.8-flash — a text-only model) so face sync
 * and style-suggest don't fail with "Model not found / not supported" on the vision call.
 */
function studio_vision_model(?string $provider = null): string
{
    $provider = $provider ?: (string) studio_config('vision_provider', 'gemini');

    if ($provider === 'qwen') {
        $m = (string) studio_config('qwen_vision_model', 'qwen-vl-plus');
        if (! preg_match('/vl|vision|minimax|wanx|owl/i', $m)) {
            return 'qwen-vl-plus'; // text-only Qwen model -> use a vision-capable one
        }
        return $m ?: 'qwen-vl-plus';
    }

    $m = (string) studio_config('vision_model', 'gemini-2.5-flash');
    if (str_contains(strtolower($m), 'qwen')) {
        return 'gemini-2.5-flash'; // wrong provider configured -> use a Gemini vision model
    }
    return $m ?: 'gemini-2.5-flash';
}

/**
 * Candidate Qwen (DashScope) VISION models to try in order — some accounts/hosts only expose a
 * subset (e.g. qwen-vl-max not qwen-vl-plus), so walking the list makes the vision call robust.
 */
function studio_qwen_vision_models(): array
{
    $primary = studio_vision_model('qwen');
    $candidates = [$primary, 'qwen-vl-max', 'qwen2.5-vl-72b-instruct', 'qwen-vl-plus', 'qwen2-vl-plus'];
    return array_values(array_unique(array_filter($candidates)));
}

