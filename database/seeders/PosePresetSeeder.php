<?php

namespace Database\Seeders;

use App\Models\PosePreset;
use Illuminate\Database\Seeder;

class PosePresetSeeder extends Seeder
{
    public function run(): void
    {
        $poses = [
            ['Đứng thẳng', 'standing straight, arms relaxed, full body'],
            ['Tay chống hông', 'standing, one hand on hip, one leg crossed'],
            ['Hai tay chống hông', 'standing, both hands on hips'],
            ['Chống hông chéo chân', 'standing, hands on hips, legs crossed'],
            ['Chống hông (trắng-đen)', 'standing, both hands on hips'],
            ['Ngồi ghế', 'sitting on a high stool, one leg extended'],
            ['Tay đút túi', 'side view, hand in pocket, relaxed'],
            ['Ngồi xổm', 'stylish squat pose, knees apart'],
            ['Sải bước', 'walking mid-stride catwalk, hand on hip'],
            ['Xoay lưng', 'back view, turned away'],
            ['Tựa ghế', 'leaning on a stool, hand to head'],
            ['Bước ngang', 'walking, side profile, dynamic'],
        ];

        foreach ($poses as $i => [$name, $desc]) {
            PosePreset::updateOrCreate(
                ['name' => $name],
                ['description' => $desc, 'image' => null, 'sort' => ($i + 1) * 10, 'enabled' => true]
            );
        }
    }
}
