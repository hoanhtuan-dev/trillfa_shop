@extends('layouts.admin')

@section('title', 'Widget')
@section('page_title', 'Widget trang chủ')

@section('content')
    <div class="mb-5 rounded-xl bg-brand-50 p-4 text-sm text-brand-800">
        Bật/tắt và đặt giới hạn hiển thị cho từng khối trên trang chủ. Cài đặt được lưu và áp dụng ngay.
    </div>

    <form method="POST" action="{{ route('admin.widgets.update') }}" class="space-y-4">
        @csrf
        <div class="card overflow-hidden">
            <div class="border-b border-cream-200 p-5"><h2 class="font-display text-lg font-semibold text-ink-900">Trang chủ — Widgets</h2></div>
            <div class="divide-y divide-cream-200">
                @foreach($widgets as $widget)
                    <div class="flex flex-wrap items-center justify-between gap-3 p-5">
                        <div>
                            <p class="font-medium text-ink-900">{{ $widget['label'] }}</p>
                            <p class="text-xs text-ink-500">key: widget_{{ $widget['key'] }}</p>
                        </div>
                        <div class="flex items-center gap-4">
                            @if($widget['has_limit'])
                                <label class="flex items-center gap-2 text-sm text-ink-700">
                                    <span>Số lượng:</span>
                                    <input type="number" name="limit_{{ $widget['key'] }}" value="{{ $widget['limit'] }}" min="1" max="24" class="input !w-20 !py-2">
                                </label>
                            @endif
                            <label class="flex items-center gap-2 text-sm text-ink-700">
                                <span>Bật</span>
                                <input type="hidden" name="enabled_{{ $widget['key'] }}" value="0">
                                <input type="checkbox" name="enabled_{{ $widget['key'] }}" value="1" @checked($widget['enabled']) class="h-5 w-5 accent-brand-600">
                            </label>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="flex justify-end border-t border-cream-200 p-5">
                <button type="submit" class="btn-brand">Lưu cài đặt</button>
            </div>
        </div>
    </form>

    @if(session('success'))
        <div class="mt-4 rounded-xl bg-brand-50 p-4 text-sm text-brand-800">{{ session('success') }}</div>
    @endif
@endsection
