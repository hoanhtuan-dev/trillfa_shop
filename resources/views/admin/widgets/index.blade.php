@extends('layouts.admin')

@section('title', 'Widget')
@section('page_title', 'Widget & khối nội dung')

@section('content')
    <div class="mb-5 rounded-xl bg-brand-50 p-4 text-sm text-brand-800">
        Bật/tắt, đặt giới hạn hiển thị và biên tập nội dung từng khối trên trang chủ, footer và thanh thông báo. Cài đặt được lưu và áp dụng ngay.
    </div>

    <form method="POST" action="{{ route('admin.widgets.update') }}" class="space-y-4">
        @csrf
        @foreach($widgets as $widget)
            <div class="card overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-cream-200 p-5">
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

                @if(isset($widget['fields']) && count($widget['fields']))
                    <div class="grid gap-4 p-5 sm:grid-cols-2">
                        @foreach($widget['fields'] as $field)
                            <div class="{{ $field['type'] === 'textarea' ? 'sm:col-span-2' : '' }}">
                                <label class="label">{{ $field['label'] }}</label>
                                @if($field['type'] === 'textarea')
                                    <textarea name="widget_{{ $widget['key'] }}_{{ $field['key'] }}" rows="2" class="input">{{ $widget['values'][$field['key']] ?? '' }}</textarea>
                                @else
                                    <input type="text" name="widget_{{ $widget['key'] }}_{{ $field['key'] }}" value="{{ $widget['values'][$field['key']] ?? '' }}" class="input">
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach

        <div class="flex justify-end">
            <button type="submit" class="btn-brand">Lưu cài đặt</button>
        </div>
    </form>

    @if(session('success'))
        <div class="mt-4 rounded-xl bg-brand-50 p-4 text-sm text-brand-800">{{ session('success') }}</div>
    @endif
@endsection
