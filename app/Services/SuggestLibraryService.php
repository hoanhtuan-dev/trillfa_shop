<?php

namespace App\Services;

use App\Models\SuggestResult;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * SuggestLibraryService — quản lý Thư viện Prompt phân tích từ "Gợi ý từ ảnh".
 *
 * Kế thừa pattern từ StudioLibraryService:
 *   - list() — danh sách có filter + phân trang + stats
 *   - save() — lưu kết quả sau khi suggestStyle() thành công
 *   - apply() — đánh dấu đã áp dụng (tăng apply_count)
 *   - bulkDelete() — xóa hàng loạt
 */
class SuggestLibraryService
{
    /**
     * Lọc + phân trang danh sách prompt đã lưu của người dùng, kèm thống kê.
     */
    public function list(User $user, array $filters = []): array
    {
        $query = $user->suggestResults()->with('project');

        if (! empty($filters['garment_type'])) {
            $query->where('garment_type', 'like', '%' . $filters['garment_type'] . '%');
        }
        if (! empty($filters['project_id'])) {
            if ((string) $filters['project_id'] === 'none') {
                $query->whereNull('project_id');
            } else {
                $query->where('project_id', (int) $filters['project_id']);
            }
        }
        if (! empty($filters['q'])) {
            $q = trim((string) $filters['q']);
            $query->where(function ($sub) use ($q) {
                $sub->where('image_prompt_en', 'like', '%' . $q . '%')
                    ->orWhere('prompt_vi', 'like', '%' . $q . '%')
                    ->orWhere('garment_type', 'like', '%' . $q . '%')
                    ->orWhere('detail_notes', 'like', '%' . $q . '%');
            });
        }

        $perPage = max(12, min(100, (int) ($filters['per_page'] ?? 48)));
        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->latest()->paginate($perPage);

        $items = $paginator->getCollection()->map(fn (SuggestResult $r) => $this->serialize($r))->values();

        return [
            'items' => $items,
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'has_more' => $paginator->hasMorePages(),
            'stats' => $this->counts($user),
        ];
    }

    /**
     * Đếm nhanh: tổng, đã áp dụng, chưa dùng, theo garment_type.
     */
    public function counts(User $user): array
    {
        $base = $user->suggestResults();

        return [
            'total' => (clone $base)->count(),
            'applied' => (clone $base)->where('apply_count', '>', 0)->count(),
            'unused' => (clone $base)->where('apply_count', 0)->count(),
            'with_image' => (clone $base)->whereNotNull('reference_url')->count(),
        ];
    }

    /**
     * Lưu kết quả phân tích vào DB. Trả về SuggestResult đã tạo.
     */
    public function save(User $user, array $data, string $referenceUrl): SuggestResult
    {
        // Tạo thumbnail 160px WebP từ ảnh nguồn (nếu có file cục bộ)
        $thumb = $this->makeThumb($referenceUrl);

        return $user->suggestResults()->create([
            'project_id' => $data['project_id'] ?? null,
            'reference_url' => $referenceUrl,
            'reference_thumb' => $thumb,

            'styles' => $data['styles'] ?? [],
            'background' => $data['background'] ?? null,
            'pose' => $data['pose'] ?? null,
            'fabric' => $data['fabric'] ?? null,
            'silhouette' => $data['silhouette'] ?? null,
            'camera' => $data['camera'] ?? null,
            'garment_type' => $data['garment_type'] ?? null,
            'embellishment' => $data['embellishment'] ?? null,
            'detail_notes' => $data['detail_notes'] ?? null,
            'color_palette' => $data['color_palette'] ?? [],

            'image_prompt_en' => $data['image_prompt_en'] ?? null,
            'prompt_vi' => $data['prompt_vi'] ?? null,
            'video_prompt_en' => $data['video_prompt_en'] ?? null,
            'negative_prompt' => $data['negative_prompt'] ?? null,
            'keywords' => $data['keywords'] ?? [],

            'creative_level' => (int) ($data['creative_level'] ?? 6),
            'adherence' => (int) ($data['adherence'] ?? 8),
            'detail_level' => (int) ($data['detail_level'] ?? 8),
            'category' => $data['category'] ?? null,
        ]);
    }

    /**
     * Đánh dấu prompt đã được áp dụng vào Tạo ảnh.
     */
    public function apply(SuggestResult $result): void
    {
        $result->update([
            'applied_at' => now(),
            'apply_count' => $result->apply_count + 1,
        ]);
    }

    /**
     * Xóa hàng loạt prompt đã lưu.
     */
    public function bulkDelete(User $user, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (! $ids) {
            return ['deleted' => 0];
        }

        $count = $user->suggestResults()->whereIn('id', $ids)->delete();

        return ['deleted' => $count];
    }

    /**
     * Tạo thumbnail 160px WebP từ ảnh nguồn (nếu là file cục bộ).
     */
    private function makeThumb(string $url): ?string
    {
        $file = $this->resolveLocalImage($url);
        if (! $file) {
            return null;
        }

        $img = studio_image_decode($file);
        if (! $img) {
            return null;
        }

        $w = imagesx($img);
        $h = imagesy($img);
        $long = max($w, $h);
        if ($long <= 160) {
            imagedestroy($img);
            return $url;
        }

        $scale = 160 / $long;
        $nw = (int) max(1, round($w * $scale));
        $nh = (int) max(1, round($h * $scale));
        $out = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);

        $name = 'studio/suggest-thumb-' . \Illuminate\Support\Str::uuid() . '.webp';
        ob_start();
        imagewebp($out, null, 80);
        $data = ob_get_clean();
        imagedestroy($out);

        \Illuminate\Support\Facades\Storage::disk('public')->put($name, $data);

        return '/storage/' . $name;
    }

    /**
     * Resolve URL ảnh thành đường dẫn file cục bộ.
     */
    private function resolveLocalImage(string $url): ?string
    {
        $url = trim($url);
        if ($url === '' || str_starts_with($url, 'data:') || str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return null;
        }
        $rel = ltrim(str_replace('\\', '/', parse_url($url, PHP_URL_PATH) ?: $url), '/');
        $rel = preg_replace('#^(storage/)+#', '', $rel) ?? $rel;

        $path = \Illuminate\Support\Facades\Storage::disk('public')->path($rel);
        return is_file($path) ? $path : null;
    }

    /**
     * Serialize SuggestResult về shape frontend.
     */
    private function serialize(SuggestResult $r): array
    {
        return [
            'id' => $r->id,
            'project_id' => $r->project_id,
            'project' => $r->project?->name,
            'reference_url' => $r->reference_url,
            'reference_thumb' => $r->reference_thumb,
            'styles' => $r->styles ?? [],
            'background' => $r->background,
            'pose' => $r->pose,
            'fabric' => $r->fabric,
            'silhouette' => $r->silhouette,
            'camera' => $r->camera,
            'garment_type' => $r->garment_type,
            'embellishment' => $r->embellishment,
            'detail_notes' => $r->detail_notes,
            'color_palette' => $r->color_palette ?? [],
            'image_prompt_en' => $r->image_prompt_en,
            'prompt_vi' => $r->prompt_vi,
            'video_prompt_en' => $r->video_prompt_en,
            'negative_prompt' => $r->negative_prompt,
            'keywords' => $r->keywords ?? [],
            'creative_level' => $r->creative_level,
            'adherence' => $r->adherence,
            'detail_level' => $r->detail_level,
            'category' => $r->category,
            'applied_at' => $r->applied_at?->format('d/m/Y H:i'),
            'apply_count' => $r->apply_count,
            'created_at' => $r->created_at?->format('d/m/Y H:i'),
            'created_ts' => $r->created_at?->getTimestamp(),
        ];
    }
}
