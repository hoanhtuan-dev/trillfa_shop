<?php

namespace Database\Seeders;

use App\Models\PosePreset;
use Illuminate\Database\Seeder;

class PosePresetSeeder extends Seeder
{
    public function run(): void
    {
        $physique = 'a stylish, edgy young Vietnamese fashion model with a slim figure, slim waist, long toned legs and a beautiful delicate face, ';

        $poses = [
            ['Đứng thẳng', $physique.'standing straight and elegant, arms relaxed at sides, one foot slightly forward, full body head to toe, not cropped', 'pose-01'],
            ['Tay chống hông', $physique.'standing with one hand on hip, weight on one leg, chin slightly up, confident, full body head to toe, not cropped', 'pose-02'],
            ['Sải bước catwalk', $physique.'walking mid-stride catwalk, one hand on hip, dynamic and confident, full body head to toe, not cropped', 'pose-03'],
            ['Nghiêng người', $physique.'standing at a slight angle, one shoulder forward, one hand lightly brushing hair, full body head to toe, not cropped', 'pose-04'],
            ['Ngồi ghế', $physique.'sitting gracefully on a high stool, back straight, one leg extended, full body head to toe, not cropped', 'pose-05'],
            ['Tay đút túi', $physique.'standing in relaxed side profile, one hand in pocket, full body head to toe, not cropped', 'pose-06'],
            ['Xoay lưng', $physique.'back view turned away, glancing over one shoulder, full body head to toe, not cropped', 'pose-07'],
            ['Tựa tường', $physique.'leaning lightly against a wall, one leg bent, arms relaxed, full body head to toe, not cropped', 'pose-08'],
        ];

        $names = array_column($poses, 0);
        foreach ($poses as $i => [$name, $desc, $file]) {
            PosePreset::updateOrCreate(
                ['name' => $name],
                ['description' => $desc, 'image' => '/storage/studio/dang-nguoi-mau/'.$file.'.png', 'sort' => ($i + 1) * 10, 'enabled' => true]
            );
        }

        // Tắt các tư thế cũ (không còn trong 8 tư thế mới) để catalog chỉ hiện 8.
        PosePreset::whereNotIn('name', $names)->update(['enabled' => false]);
    }
}
