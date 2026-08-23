@extends('layouts.admin')

@section('title', 'Đánh giá')
@section('page_title', 'Đánh giá')

@section('content')
    <div class="card overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-cream-100 text-left text-xs font-semibold uppercase tracking-wide text-ink-500">
                <tr><th class="px-5 py-3">Sản phẩm</th><th class="px-5 py-3">Người dùng</th><th class="px-5 py-3">Điểm</th><th class="px-5 py-3">Nội dung</th><th class="px-5 py-3">Trạng thái</th><th class="px-5 py-3 text-right">Thao tác</th></tr>
            </thead>
            <tbody class="divide-y divide-cream-200">
                @forelse($reviews as $review)
                    <tr class="hover:bg-cream-50">
                        <td class="px-5 py-3 font-medium text-ink-900">{{ $review->product?->name }}</td>
                        <td class="px-5 py-3 text-ink-700">{{ $review->user?->name }}</td>
                        <td class="px-5 py-3"><span class="text-amber-400">★{{ $review->rating }}</span></td>
                        <td class="px-5 py-3"><p class="max-w-xs truncate text-ink-500">{{ $review->body }}</p></td>
                        <td class="px-5 py-3">
                            <form method="POST" action="{{ route('admin.reviews.toggle', $review) }}">@csrf<button type="submit" class="badge {{ $review->is_active ? 'bg-brand-100 text-brand-700' : 'bg-red-100 text-red-600' }}">{{ $review->is_active ? 'Hiện' : 'Ẩn' }}</button></form>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <form method="POST" action="{{ route('admin.reviews.destroy', $review) }}" onsubmit="return confirm('Xóa đánh giá?')">@csrf @method('DELETE')<button type="submit" class="btn-ghost !p-2 text-red-500"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21"/></svg></button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center text-ink-500">Chưa có đánh giá.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="border-t border-cream-200 p-4">{{ $reviews->links() }}</div>
    </div>
@endsection
