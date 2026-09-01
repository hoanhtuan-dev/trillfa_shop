<?php

namespace Database\Seeders;

use App\Models\PosePreset;
use Illuminate\Database\Seeder;

class PosePresetSeeder extends Seeder
{
    public function run(): void
    {
        $poses = [
            ['Đứng thẳng', 'standing straight, arms relaxed, full body', 'pose-01'],
            ['Tay chống hông', 'standing, one hand on hip, one leg crossed', 'pose-02'],
            ['Hai tay chống hông', 'standing, both hands on hips', 'pose-03'],
            ['Chống hông chéo chân', 'standing, hands on hips, legs crossed', 'pose-04'],
            ['Chống hông (trắng-đen)', 'standing, both hands on hips', 'pose-05'],
            ['Ngồi ghế', 'sitting on a high stool, one leg extended', 'pose-06'],
            ['Tay đút túi', 'side view, hand in pocket, relaxed', 'pose-07'],
            ['Ngồi xổm', 'stylish squat pose, knees apart', 'pose-08'],
            ['Sải bước', 'walking mid-stride catwalk, hand on hip', 'pose-09'],
            ['Xoay lưng', 'back view, turned away', 'pose-10'],
            ['Tựa ghế', 'leaning on a stool, hand to head', 'pose-11'],
            ['Bước ngang', 'walking, side profile, dynamic', 'pose-12'],
        ];

        foreach ($poses as $i => [$name, $desc, $file]) {
            PosePreset::updateOrCreate(
                ['name' => $name],
                ['description' => $desc, 'image' => '/storage/studio/'.$file.'.png', 'sort' => ($i + 1) * 10, 'enabled' => true]
            );
        }
    }
}
