<?php

namespace App\Services;

/**
 * StylistCatalog — dữ liệu xương sống của ✨ Thuật sỹ (module hóa, dễ kế thừa/mở rộng).
 * Một module khác có thể dùng chung hoặc kế thừa: garmentTypes(), questions(), nameOf().
 */
class StylistCatalog
{
    /** 18 loại trang phục dựng sẵn (không trùng lặp). */
    public function garmentTypes(): array
    {
        return [
            ['id' => 'dress',        'name' => 'Đầm',              'emoji' => '👗', 'color' => '#e8577d', 'img' => '/samples/garment-dress.png'],
            ['id' => 'top',          'name' => 'Áo sơ mi / Blouse','emoji' => '👚', 'color' => '#7aa7e0', 'img' => '/samples/garment-top.png'],
            ['id' => 'pants',        'name' => 'Quần',             'emoji' => '👖', 'color' => '#6a8f6a', 'img' => '/samples/garment-pants.png'],
            ['id' => 'skirt',        'name' => 'Chân váy',         'emoji' => '🩰', 'color' => '#b57bd0', 'img' => '/samples/garment-skirt.png'],
            ['id' => 'shorts',       'name' => 'Quần short',       'emoji' => '🩳', 'color' => '#e0a95a', 'img' => '/samples/garment-shorts.png'],
            ['id' => 'jacket',       'name' => 'Áo khoác',         'emoji' => '🧥', 'color' => '#8a6a4a', 'img' => '/samples/garment-jacket.png'],
            ['id' => 'aodai',        'name' => 'Áo dài',           'emoji' => '👘', 'color' => '#d04a4a', 'img' => '/samples/garment-aodai.png'],
            ['id' => 'set',          'name' => 'Set đồ',           'emoji' => '🧥', 'color' => '#4a7a90', 'img' => '/samples/garment-set.png'],
            ['id' => 'tshirt',       'name' => 'Áo thun',          'emoji' => '👕', 'color' => '#5aa0c8', 'img' => '/samples/garment-tshirt.png'],
            ['id' => 'hoodie',       'name' => 'Áo hoodie',        'emoji' => '🧶', 'color' => '#9a7ad0', 'img' => '/samples/garment-hoodie.png'],
            ['id' => 'denimjacket',  'name' => 'Áo khoác denim',   'emoji' => '🧥', 'color' => '#5a8a9a', 'img' => '/samples/garment-denimjacket.png'],
            ['id' => 'blazer',       'name' => 'Áo blazer',        'emoji' => '🕴️', 'color' => '#6a7a8a', 'img' => '/samples/garment-blazer.png'],
            ['id' => 'weddingdress', 'name' => 'Đầm cưới',         'emoji' => '💍', 'color' => '#e8e0d8', 'img' => '/samples/garment-weddingdress.png'],
            ['id' => 'bodysuit',     'name' => 'Áo bodysuit',      'emoji' => '🩱', 'color' => '#c88aa0', 'img' => '/samples/garment-bodysuit.png'],
            ['id' => 'jogger',       'name' => 'Quần jogger',      'emoji' => '👖', 'color' => '#8aa06a', 'img' => '/samples/garment-jogger.png'],
            ['id' => 'windbreaker',  'name' => 'Áo gió',           'emoji' => '🧥', 'color' => '#6aa0b0', 'img' => '/samples/garment-windbreaker.png'],
            ['id' => 'tanktop',      'name' => 'Áo ba lỗ',         'emoji' => '🎽', 'color' => '#e0b06a', 'img' => '/samples/garment-tanktop.png'],
            ['id' => 'bomber',       'name' => 'Áo khoác bomber',  'emoji' => '🧥', 'color' => '#7a6a5a', 'img' => '/samples/garment-bomber.png'],
        ];
    }

    public function nameOf(string $id): string
    {
        foreach ($this->garmentTypes() as $t) { if ($t['id'] === $id) { return $t['name']; } }
        return ucfirst($id);
    }

    /**
     * Ma trận câu hỏi xương sườn (khảo sát nhanh) — điều chỉnh theo loại trang phục.
     */
    public function questions(string $type): array
    {
        $g = $this->nameOf($type);
        return [
            ['key' => 'model', 'q' => 'Người mẫu (Việt):', 'opts' => [
                'Trẻ trung 18-25, thanh mảnh, tóc dài đen, da sáng',
                'Thanh xuân 25-32, cao 1m68+, tóc xoăn, da nâu vàng',
                'Trưởng thành 32-40, đầy đặn, tóc ngắn cá tính, da ngăm',
                'Cận trung niên 40-50, quyến rũ, tóc búi, da sáng',
            ]],
            ['key' => 'silhouette', 'q' => 'Phom/ silhouette '.$g.' (kỹ thuật dựng):', 'opts' => [
                'Ôm sát (fitted): may co giãn 2-4%, tôn dáng, đường may princess tách eo',
                'Suông (straight): cắt đơn giản, rộng vừa, không chiết ly, phom H',
                'A-line: xòe dần từ eo, độ rộng gấu +8-12cm, phom A mềm mại',
                'Xòe cong (flare): chân váy xòe rộng, dún hoặc chéo sợi 45°',
                'Oversized/boxy: rộng rộng, tay rộng, phong cách street',
            ]],
            ['key' => 'fabric', 'q' => 'Chất liệu (kỹ thuật dệt):', 'opts' => [
                'Lụa satin mềm (độ bóng cao, rũ đẹp, 12-16 momme)',
                'Chiffon mỏng nhẹ (xếp lớp 2-3 lớp, bay, không bóng)',
                'Cotton thoáng (GSM 180-240, ít nhăn, dễ bảo quản)',
                'Dệt kim co giãn (lycra 5-8%, không nhăn, mềm)',
                'Voan lưới (polyester, xòe cứng cáp, giữ phom)',
                'Denim (cotton 12-14oz, chà xát, bền)',
                'Da / faux leather (mềm, bóng nhẹ, có độ cứng)',
            ]],
            ['key' => 'color', 'q' => 'Màu nhuộm (xu hướng VN):', 'opts' => [
                'Đen huyền bí', 'Đỏ rượu vang (đô)', 'Pastel hồng/be (nhãn)', 'Xanh navy thanh lịch', 'Trắng/cream sạch sẽ', 'Xanh rêu/military',
            ]],
            ['key' => 'details', 'q' => 'Chi tiết may (kỹ thuật):', 'opts' => [
                'Cổ chữ V sâu + gọng ngực ẩn', 'Tay phồng bồng (xếp ly cánh tay, độ phồng 2 lần vai)', 'Corset thắt eo (xương mềm + ren)', 'Viền ren + dún lưng (độ rũ 1.5 lần)', 'Khuy thắt + phối màu tương phản', 'Xếp tầng 3-4 lớp (layer)', 'Túi hộp may nổi + nẹp viền',
            ]],
            ['key' => 'occasion', 'q' => 'Dịp & bối cảnh (thị trường VN):', 'opts' => [
                'Tiệc tối sang trọng', 'Đi làm thanh lịch', 'Dạo phố trẻ trung', 'Sự kiện/catwalk', 'Hẹn hò lãng mạn', 'Lễ cưới / dự lễ',
            ]],
            ['key' => 'setting', 'q' => 'Bối cảnh & ánh sáng (chụp):', 'opts' => [
                'Studio tối giản, ánh sáng mềm (softbox)', 'Phố cổ / kiến trúc, nắng vàng (golden hour)', 'Thiên nhiên xanh, ánh sáng tự nhiên', 'Catwalk / sàn diễn, đèn neon', 'Trong nhà sang trọng, ánh đèn chùm',
            ]],
            ['key' => 'style', 'q' => 'Phong cách / cảm hứng (trend):', 'opts' => [
                'Minimal thanh lịch', 'Luxury couture', 'Boho tự do', 'Streetwear hiện đại', 'Retro/cổ điển', 'Futuristic avant-garde',
            ]],
        ];
    }
}
