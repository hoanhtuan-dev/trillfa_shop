@extends('layouts.admin')

@section('title', 'Thanh toán')
@section('page_title', 'Phương thức thanh toán')

@section('content')
@php
    $paymentItems = $methods->map(fn($m) => [
        'id' => $m->id, 'name' => $m->name, 'code' => $m->code,
        'description' => $m->description, 'fee' => $m->fee,
        'sort_order' => $m->sort_order, 'is_active' => $m->is_active,
    ])->values();
@endphp

<div class="grid gap-6 lg:grid-cols-2" x-data="paymentForm({{ Js::from($paymentItems) }}, '{{ route('admin.payments.store') }}')">
    <!-- List -->
    <div class="space-y-4">
        @forelse($methods as $method)
            <div class="card flex gap-4 p-4">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/></svg>
                </span>
                <div class="flex-1">
                    <p class="font-medium text-ink-900">{{ $method->name }} <span class="text-xs text-ink-500">({{ $method->code }})</span></p>
                    <p class="text-xs text-ink-500">{{ $method->description }}</p>
                    <p class="mt-1 text-xs text-ink-500">Phí: <strong>{{ $method->fee > 0 ? format_price($method->fee) : 'Miễn phí' }}</strong> · Thứ tự {{ $method->sort_order }}</p>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <button @click="edit({{ $method->id }})" class="badge bg-brand-50 text-brand-700 hover:bg-brand-100">Sửa</button>
                        <form method="POST" action="{{ route('admin.payments.toggle', $method) }}">@csrf<button type="submit" class="badge {{ $method->is_active ? 'bg-brand-100 text-brand-700' : 'bg-red-100 text-red-600' }}">{{ $method->is_active ? 'Bật' : 'Tắt' }}</button></form>
                        <form method="POST" action="{{ route('admin.payments.destroy', $method) }}" onsubmit="return confirm('Xóa phương thức này?')">@csrf @method('DELETE')<button type="submit" class="badge bg-red-50 text-red-600 hover:bg-red-100">Xóa</button></form>
                    </div>
                </div>
            </div>
        @empty
            <div class="card p-10 text-center text-ink-500">Chưa có phương thức thanh toán.</div>
        @endforelse
    </div>

    <!-- Create/edit form -->
    <div class="card h-fit p-6">
        <h2 class="mb-4 font-display text-lg font-semibold text-ink-900" x-text="editing ? 'Sửa phương thức' : 'Thêm phương thức'"></h2>
        <form :action="formAction" method="POST" class="space-y-4">
            @csrf
            <input type="hidden" name="_method" :value="formMethod">
            <input type="hidden" name="is_active" :value="form.is_active ? '1' : '0'">
            <div><label class="label">Tên hiển thị *</label><input type="text" name="name" x-model="form.name" class="input" required></div>
            <div>
                <label class="label">Mã (code) <span x-show="editing" class="text-xs text-ink-500">(không đổi)</span></label>
                <input type="text" name="code" x-model="form.code" class="input" :disabled="editing" :required="!editing" placeholder="cod, vnpay, momo, bank...">
            </div>
            <div><label class="label">Mô tả</label><input type="text" name="description" x-model="form.description" class="input"></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label">Phí (đ)</label><input type="number" name="fee" x-model="form.fee" class="input" step="0.01" min="0"></div>
                <div><label class="label">Thứ tự</label><input type="number" name="sort_order" x-model="form.sort_order" class="input"></div>
            </div>
            <label class="flex items-center justify-between text-sm text-ink-700"><span>Kích hoạt</span><input type="checkbox" x-model="form.is_active" class="h-5 w-5 accent-brand-600"></label>
            <div class="flex items-center gap-2 pt-1">
                <button type="submit" class="btn-brand flex-1" x-text="editing ? 'Cập nhật' : 'Thêm'"></button>
                <button type="button" @click="resetForm()" class="btn-ghost" x-show="editing">Hủy</button>
            </div>
        </form>
        @if($errors->any())<div class="mt-3 rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ $errors->first() }}</div>@endif
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('paymentForm', (items, createUrl) => ({
        items, createUrl,
        editing: null,
        form: { id: null, name: '', code: '', description: '', fee: 0, sort_order: 0, is_active: true },
        get formAction() { return this.editing ? '/admin/payments/' + this.editing : this.createUrl; },
        get formMethod() { return this.editing ? 'PUT' : 'POST'; },
        edit(id) {
            const m = this.items.find(x => x.id === id);
            if (!m) return;
            this.editing = m.id;
            this.form = { id: m.id, name: m.name, code: m.code, description: m.description || '', fee: m.fee || 0, sort_order: m.sort_order || 0, is_active: !!m.is_active };
        },
        resetForm() {
            this.editing = null;
            this.form = { id: null, name: '', code: '', description: '', fee: 0, sort_order: 0, is_active: true };
        },
    }));
});
</script>
@endpush
@endsection
