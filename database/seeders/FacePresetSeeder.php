<?php

namespace Database\Seeders;

use App\Models\FacePreset;
use Illuminate\Database\Seeder;

class FacePresetSeeder extends Seeder
{
    public function run(): void
    {
        $presets = [
            ['name' => 'Nhẹ nhàng tự nhiên', 'ethnicity' => 'Vietnamese female', 'description' => 'young Vietnamese woman, 22, light natural everyday makeup, shoulder-length straight black hair, fair skin, gentle warm smile, soft feminine features'],
            ['name' => 'Tóc dài lượn sóng', 'ethnicity' => 'Vietnamese female', 'description' => 'young Vietnamese woman, 24, long wavy black hair with soft curls, radiant clear skin, subtle Korean-style makeup, elegant and graceful look'],
            ['name' => 'Cá tính tóc bob', 'ethnicity' => 'Vietnamese female', 'description' => 'young Vietnamese woman, 23, chic short black bob haircut, bold natural lipstick, confident modern look, sharp jawline, almond eyes'],
            ['name' => 'Thanh lịch tóc búi', 'ethnicity' => 'Vietnamese female', 'description' => 'young Vietnamese woman, 25, elegant low bun hairstyle, minimalist makeup, classic Vietnamese beauty, refined and sophisticated'],
            ['name' => 'Năng động tóc đuôi ngựa', 'ethnicity' => 'Vietnamese female', 'description' => 'young Vietnamese woman, 22, high ponytail, fresh sporty energetic look, clear glowing skin, bright happy smile, youthful'],
            ['name' => 'Ngọt ngào tóc xoăn', 'ethnicity' => 'Vietnamese female', 'description' => 'young Vietnamese woman, 21, soft loose curls, sweet innocent face, rosy cheeks, gentle dreamy eyes, cute and charming'],
            ['name' => 'Sang trọng mái lệch', 'ethnicity' => 'Vietnamese female', 'description' => 'young Vietnamese woman, 26, side-swept bangs, sophisticated editorial makeup, elegant high-fashion look, striking features'],
            ['name' => 'Thời trang mắt khói', 'ethnicity' => 'Vietnamese female', 'description' => 'young Vietnamese woman, 24, sleek center-parted straight hair, trendy smoky-eye makeup, fashion-forward street style, confident gaze'],
            ['name' => 'Dịu dàng tóc đen thẳng', 'ethnicity' => 'Vietnamese female', 'description' => 'young Vietnamese woman, 23, long straight jet-black hair, fresh natural no-makeup look, serene calm expression, classic beauty'],
            ['name' => 'Hiện đại mái ngố', 'ethnicity' => 'Vietnamese female', 'description' => 'young Vietnamese woman, 22, modern curtain bangs, fresh glass-skin makeup, trendy K-pop inspired look, sparkling eyes'],
        ];

        foreach ($presets as $i => $p) {
            FacePreset::updateOrCreate(
                ['name' => $p['name']],
                array_merge($p, ['sort' => ($i + 1) * 10, 'enabled' => true, 'image' => null])
            );
        }
    }
}
