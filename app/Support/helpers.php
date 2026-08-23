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
