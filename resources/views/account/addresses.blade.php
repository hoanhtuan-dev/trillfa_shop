@extends('layouts.account')

@section('title', 'Địa chỉ')

@section('account_content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="font-display text-2xl font-semibold text-ink-900">Sổ địa chỉ</h1>
            <p class="mt-1 text-sm text-ink-500">Quản lý địa chỉ giao hàng của bạn.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-xl bg-brand-50 p-4 text-sm text-brand-800">{{ session('success') }}</div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2" x-data="{ form: false, edit: null }">
        @foreach($addresses as $address)
            <div class="card relative p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-medium text-ink-900">{{ $address->name }}</h3>
                            @if($address->is_default)<span class="badge bg-brand-600 text-white">Mặc định</span>@endif
                        </div>
                        <p class="mt-1 text-sm text-ink-500">{{ $address->phone }}</p>
                        <p class="mt-1 text-sm text-ink-500">{{ $address->address }}</p>
                        <p class="text-sm text-ink-500">{{ implode(', ', array_filter([$address->ward, $address->district, $address->province])) }}</p>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2 border-t border-cream-200 pt-3 text-sm">
                    <button @click="form = true; edit = {{ $address->id }}" class="link">Sửa</button>
                    <form method="POST" action="{{ route('account.addresses.delete', $address) }}" onsubmit="return confirm('Xóa địa chỉ này?')" class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-red-500 hover:text-red-700">Xóa</button>
                    </form>
                </div>
            </div>
        @endforeach

        <button @click="form = !form" class="card flex min-h-[180px] flex-col items-center justify-center border-dashed text-ink-500 transition hover:border-brand-500 hover:text-brand-700">
            <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-6-6h12"/></svg>
            <span class="mt-2 text-sm font-medium">Thêm địa chỉ mới</span>
        </button>

        <!-- New address form (modal-ish inline) -->
        <div x-show="form" x-collapse class="card col-span-full p-6">
            <h3 class="mb-4 font-display text-lg font-semibold text-ink-900">Thêm địa chỉ mới</h3>
            <form method="POST" action="{{ route('account.addresses.store') }}" class="grid gap-4 sm:grid-cols-2">
                @csrf
                <div><label class="label">Họ và tên</label><input type="text" name="name" class="input" required></div>
                <div><label class="label">Số điện thoại</label><input type="text" name="phone" class="input" required></div>
                <div class="sm:col-span-2"><label class="label">Địa chỉ</label><input type="text" name="address" class="input" required></div>
                <div><label class="label">Phường / Xã</label><input type="text" name="ward" class="input"></div>
                <div><label class="label">Quận / Huyện</label><input type="text" name="district" class="input"></div>
                <div><label class="label">Tỉnh / Thành phố</label><input type="text" name="province" class="input"></div>
                <div class="flex items-end"><label class="flex items-center gap-2 text-sm text-ink-700"><input type="checkbox" name="is_default" value="1" class="h-4 w-4 accent-brand-600"> Đặt làm mặc định</label></div>
                <div class="sm:col-span-2 flex justify-end gap-2">
                    <button type="button" @click="form = false" class="btn-outline btn-sm">Hủy</button>
                    <button type="submit" class="btn-brand btn-sm">Lưu địa chỉ</button>
                </div>
            </form>
        </div>
        @if($errors->any())
            <div class="col-span-full rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ $errors->first() }}</div>
        @endif
    </div>
@endsection
