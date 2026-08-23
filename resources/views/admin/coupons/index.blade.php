@extends('layouts.admin')

@section('title', 'Mã giảm giá')
@section('page_title', 'Mã giảm giá')

@section('content')
<div class="grid gap-6 lg:grid-cols-2">
    <div class="card overflow-hidden">
        <div class="border-b border-cream-200 p-5"><h2 class="font-display text-lg font-semibold text-ink-900">Danh sách mã</h2></div>
        <table class="w-full text-sm">
            <thead class="bg-cream-100 text-left text-xs font-semibold uppercase tracking-wide text-ink-500">
                <tr><th class="px-5 py-3">Mã</th><th class="px-5 py-3">Giảm</th><th class="px-5 py-3">Đã dùng</th><th class="px-5 py-3">Trạng thái</th><th class="px-5 py-3 text-right">Thao tác</th></tr>
            </thead>
            <tbody class="divide-y divide-cream-200">
                @forelse($coupons as $coupon)
                    <tr class="hover:bg-cream-50">
                        <td class="px-5 py-3 font-mono font-semibold text-brand-700">{{ $coupon->code }}</td>
                        <td class="px-5 py-3">{{ $coupon->type === 'percent' ? $coupon->value.'%' : format_price($coupon->value) }}</td>
                        <td class="px-5 py-3 text-ink-500">{{ $coupon->used_count }}/{{ $coupon->usage_limit ?? '∞' }}</td>
                        <td class="px-5 py-3">
                            <form method="POST" action="{{ route('admin.coupons.toggle', $coupon) }}">@csrf<button type="submit" class="badge {{ $coupon->is_active ? 'bg-brand-100 text-brand-700' : 'bg-red-100 text-red-600' }}">{{ $coupon->is_active ? 'Hoạt động' : 'Tắt' }}</button></form>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <form method="POST" action="{{ route('admin.coupons.destroy', $coupon) }}" onsubmit="return confirm('Xóa mã?')">@csrf @method('DELETE')<button type="submit" class="btn-ghost !p-2 text-red-500"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21"/></svg></button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-ink-500">Chưa có mã giảm giá.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card p-6">
        <h2 class="mb-4 font-display text-lg font-semibold text-ink-900">Thêm mã giảm giá</h2>
        <form method="POST" action="{{ route('admin.coupons.store') }}" class="grid gap-4 sm:grid-cols-2">
            @csrf
            <div><label class="label">Mã *</label><input type="text" name="code" class="input" required placeholder="WELCOME10"></div>
            <div>
                <label class="label">Loại</label>
                <select name="type" class="input"><option value="percent">Phần trăm (%)</option><option value="fixed">Số tiền cố định</option></select>
            </div>
            <div><label class="label">Giá trị *</label><input type="number" name="value" class="input" required step="0.01" min="0"></div>
            <div><label class="label">Đơn tối thiểu</label><input type="number" name="min_order" class="input" step="0.01" min="0"></div>
            <div><label class="label">Giảm tối đa</label><input type="number" name="max_discount" class="input" step="0.01" min="0"></div>
            <div><label class="label">Giới hạn dùng</label><input type="number" name="usage_limit" class="input" min="1"></div>
            <div><label class="label">Bắt đầu</label><input type="date" name="starts_at" class="input"></div>
            <div><label class="label">Kết thúc</label><input type="date" name="ends_at" class="input"></div>
            <div class="sm:col-span-2"><button type="submit" class="btn-brand">Tạo mã</button></div>
        </form>
    </div>
</div>
@endsection
