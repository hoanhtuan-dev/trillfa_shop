@extends('layouts.admin')

@section('title', 'Thanh toán')
@section('page_title', 'Phương thức thanh toán')

@section('content')
    <div class="card overflow-hidden">
        <div class="border-b border-cream-200 p-5"><h2 class="font-display text-lg font-semibold text-ink-900">Phương thức thanh toán</h2></div>
        <div class="divide-y divide-cream-200">
            @foreach($methods as $method)
                <form method="POST" action="{{ route('admin.payments.update', $method) }}" class="grid grid-cols-12 items-center gap-4 p-5 hover:bg-cream-50">
                    @csrf
                    @method('PUT')
                    <div class="col-span-12 sm:col-span-3">
                        <p class="font-medium text-ink-900">{{ $method->name }}</p>
                        <p class="text-xs text-ink-500">{{ $method->code }}</p>
                    </div>
                    <div class="col-span-6 sm:col-span-3"><label class="label">Tên hiển thị</label><input type="text" name="name" value="{{ $method->name }}" class="input !py-2"></div>
                    <div class="col-span-6 sm:col-span-3"><label class="label">Mô tả</label><input type="text" name="description" value="{{ $method->description }}" class="input !py-2"></div>
                    <div class="col-span-6 sm:col-span-2"><label class="label">Thứ tự</label><input type="number" name="sort_order" value="{{ $method->sort_order }}" class="input !py-2"></div>
                    <div class="col-span-6 flex items-center justify-end gap-2 sm:col-span-1">
                        <button type="submit" class="btn-outline btn-sm">Lưu</button>
                    </div>
                </form>
            @endforeach
        </div>
    </div>
@endsection
