@extends('layouts.admin')

@section('title', 'Vận chuyển')
@section('page_title', 'Phương thức vận chuyển')

@section('content')
<div class="grid gap-6 lg:grid-cols-2">
    <div class="card overflow-hidden">
        <div class="border-b border-cream-200 p-5"><h2 class="font-display text-lg font-semibold text-ink-900">Danh sách</h2></div>
        <table class="w-full text-sm">
            <thead class="bg-cream-100 text-left text-xs font-semibold uppercase tracking-wide text-ink-500">
                <tr><th class="px-5 py-3">Tên</th><th class="px-5 py-3">Phí</th><th class="px-5 py-3">Ngày</th><th class="px-5 py-3">Trạng thái</th><th class="px-5 py-3 text-right">Thao tác</th></tr>
            </thead>
            <tbody class="divide-y divide-cream-200">
                @forelse($methods as $method)
                    <tr class="hover:bg-cream-50">
                        <td class="px-5 py-3">
                            <p class="font-medium text-ink-900">{{ $method->name }}</p>
                            @if($method->free_threshold !== null)<p class="text-xs text-ink-500">Miễn phí từ {{ format_price($method->free_threshold) }}</p>@endif
                        </td>
                        <td class="px-5 py-3 font-semibold">{{ format_price($method->fee) }}</td>
                        <td class="px-5 py-3 text-ink-500">{{ $method->estimated_days ?? '—' }}</td>
                        <td class="px-5 py-3">
                            <form method="POST" action="{{ route('admin.shipping.toggle', $method) }}">@csrf<button type="submit" class="badge {{ $method->is_active ? 'bg-brand-100 text-brand-700' : 'bg-red-100 text-red-600' }}">{{ $method->is_active ? 'Bật' : 'Tắt' }}</button></form>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <form method="POST" action="{{ route('admin.shipping.destroy', $method) }}" onsubmit="return confirm('Xóa?')">@csrf @method('DELETE')<button type="submit" class="btn-ghost !p-2 text-red-500"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9"/></svg></button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-ink-500">Chưa có phương thức.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card p-6">
        <h2 class="mb-4 font-display text-lg font-semibold text-ink-900">Thêm phương thức</h2>
        <form method="POST" action="{{ route('admin.shipping.store') }}" class="grid gap-4 sm:grid-cols-2">
            @csrf
            <div><label class="label">Tên *</label><input type="text" name="name" class="input" required placeholder="Giao tiêu chuẩn"></div>
            <div><label class="label">Phí (đ) *</label><input type="number" name="fee" class="input" required step="0.01" min="0"></div>
            <div class="sm:col-span-2"><label class="label">Mô tả</label><input type="text" name="description" class="input"></div>
            <div><label class="label">Miễn phí từ</label><input type="number" name="free_threshold" class="input" step="0.01" min="0"></div>
            <div><label class="label">Số ngày giao</label><input type="number" name="estimated_days" class="input" min="0"></div>
            <div><label class="label">Thứ tự</label><input type="number" name="sort_order" value="0" class="input"></div>
            <div class="sm:col-span-2"><button type="submit" class="btn-brand">Thêm</button></div>
        </form>
    </div>
</div>
@endsection
