<?php
// Tạo 8 ảnh minh hoạ tư thế (croquis) khớp mô tả mới — nét mực tối trên nền sáng, kiểu fashion sketch.
$out = __DIR__.'/../storage/app/public/studio/dang-nguoi-mau';
if (! is_dir($out)) { mkdir($out, 0755, true); }

const W = 240, H = 360;

function newCanvas(): array {
    $im = imagecreatetruecolor(W, H);
    $white = imagecolorallocate($im, 252, 250, 246);
    imagefilledrectangle($im, 0, 0, W - 1, H - 1, $white);
    $ink = imagecolorallocate($im, 38, 42, 54);
    $soft = imagecolorallocate($im, 160, 168, 180);
    return [$im, $ink, $soft];
}

function thickline($im, $c, $x1, $y1, $x2, $y2, $w = 4) {
    imageline($im, $x1, $y1, $x2, $y2, $c);
    if ($w > 1) {
        imageline($im, $x1 + 1, $y1, $x2 + 1, $y2, $c);
        imageline($im, $x1, $y1 + 1, $x2, $y2 + 1, $c);
    }
}

function head($im, $c, $cx, $cy, $r = 26, $filled = false) {
    if ($filled) { imagefilledellipse($im, $cx, $cy, $r * 2, $r * 2, $c); }
    else {
        imageellipse($im, $cx, $cy, $r * 2, $r * 2, $c);
        imageellipse($im, $cx, $cy, $r * 2 + 1, $r * 2 + 1, $c);
    }
}

// Mỗi pose: callback vẽ, nhận ($im, $ink, $soft)
$poses = [];

$poses['pose-01'] = function ($im, $ink, $soft) {
    head($im, $ink, 120, 55);
    thickline($im, $ink, 120, 82, 120, 165);            // neck+torso
    thickline($im, $ink, 100, 95, 88, 165);             // arm L
    thickline($im, $ink, 140, 95, 152, 165);            // arm R
    thickline($im, $ink, 104, 168, 98, 330);            // leg L
    thickline($im, $ink, 136, 168, 142, 330);           // leg R
    thickline($im, $ink, 100, 95, 140, 95);             // shoulder
    thickline($im, $ink, 104, 168, 136, 168);           // hip
};

$poses['pose-02'] = function ($im, $ink, $soft) {
    head($im, $ink, 120, 55);
    thickline($im, $ink, 120, 82, 120, 165);
    thickline($im, $ink, 100, 95, 92, 135); thickline($im, $ink, 92, 135, 106, 150); // arm L on hip
    thickline($im, $ink, 140, 95, 148, 135); thickline($im, $ink, 148, 135, 134, 150); // arm R on hip
    thickline($im, $ink, 104, 168, 98, 330);
    thickline($im, $ink, 136, 168, 142, 330);
    thickline($im, $ink, 100, 95, 140, 95);
    thickline($im, $ink, 104, 168, 136, 168);
};

$poses['pose-03'] = function ($im, $ink, $soft) {
    head($im, $ink, 120, 55);
    thickline($im, $ink, 120, 82, 120, 168);
    thickline($im, $ink, 100, 95, 80, 150);              // arm L swing
    thickline($im, $ink, 140, 95, 136, 152);             // arm R on hip
    thickline($im, $ink, 104, 168, 84, 330);             // leg L forward
    thickline($im, $ink, 136, 168, 158, 330);            // leg R back
    thickline($im, $ink, 100, 95, 140, 95);
    thickline($im, $ink, 104, 168, 136, 168);
};

$poses['pose-04'] = function ($im, $ink, $soft) {
    head($im, $ink, 130, 55);
    thickline($im, $ink, 128, 82, 122, 165);
    thickline($im, $ink, 102, 95, 86, 160);              // arm L relaxed
    thickline($im, $ink, 142, 95, 134, 58);              // arm R brushing hair
    thickline($im, $ink, 106, 168, 100, 330);
    thickline($im, $ink, 138, 168, 142, 330);
    thickline($im, $ink, 102, 95, 142, 95);
    thickline($im, $ink, 106, 168, 138, 168);
};

$poses['pose-05'] = function ($im, $ink, $soft) {
    // stool
    thickline($im, $soft, 85, 196, 155, 196, 3);
    thickline($im, $soft, 90, 196, 88, 332, 2);
    thickline($im, $soft, 150, 196, 152, 332, 2);
    head($im, $ink, 120, 72);
    thickline($im, $ink, 120, 98, 120, 196);             // torso
    thickline($im, $ink, 100, 118, 88, 180);             // arm L
    thickline($im, $ink, 140, 118, 152, 180);            // arm R
    thickline($im, $ink, 108, 196, 84, 232); thickline($im, $ink, 84, 232, 80, 332); // leg L thigh+shin
    thickline($im, $ink, 132, 196, 158, 232); thickline($im, $ink, 158, 232, 162, 332); // leg R
    thickline($im, $ink, 100, 118, 140, 118);
};

$poses['pose-06'] = function ($im, $ink, $soft) {
    head($im, $ink, 126, 55);
    thickline($im, $ink, 124, 82, 120, 168);
    thickline($im, $ink, 102, 95, 100, 162);             // arm L hand in pocket
    thickline($im, $ink, 142, 95, 150, 162);             // arm R
    thickline($im, $ink, 106, 168, 102, 330);
    thickline($im, $ink, 138, 168, 140, 330);
    thickline($im, $ink, 102, 95, 142, 95);
    thickline($im, $ink, 106, 168, 138, 168);
};

$poses['pose-07'] = function ($im, $ink, $soft) {
    head($im, $ink, 120, 55, 26, true);                  // filled = hair (back view)
    thickline($im, $ink, 120, 82, 120, 165);
    thickline($im, $ink, 100, 95, 88, 165);
    thickline($im, $ink, 140, 95, 152, 165);
    thickline($im, $ink, 104, 168, 98, 330);
    thickline($im, $ink, 136, 168, 142, 330);
    thickline($im, $ink, 100, 95, 140, 95);
    thickline($im, $ink, 104, 168, 136, 168);
};

$poses['pose-08'] = function ($im, $ink, $soft) {
    thickline($im, $soft, 40, 20, 40, 340, 3);           // wall
    head($im, $ink, 72, 58);
    thickline($im, $ink, 72, 84, 80, 168);               // torso (lean)
    thickline($im, $ink, 62, 100, 42, 124);              // arm L on wall
    thickline($im, $ink, 82, 100, 96, 156);              // arm R
    thickline($im, $ink, 82, 168, 68, 330);              // leg L straight
    thickline($im, $ink, 90, 168, 122, 230); thickline($im, $ink, 122, 230, 120, 300); // leg R bent
    thickline($im, $ink, 62, 100, 82, 100);
};

foreach ($poses as $file => $draw) {
    [$im, $ink, $soft] = newCanvas();
    $draw($im, $ink, $soft);
    imagepng($im, $out.'/'.$file.'.png');
    imagedestroy($im);
    echo "generated $file.png
";
}
echo "done
";
