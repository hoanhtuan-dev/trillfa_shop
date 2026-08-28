@extends('layouts.studio')
@section('title', 'Quản lý API')
@section('content')
<div class="mx-auto max-w-2xl">
    <h1 class="font-display text-2xl font-semibold text-ink-900">Quản lý API</h1>
    <p class="mt-1 text-sm text-ink-500">Nhập các khoá AI (mã hoá khi lưu). Để trống để giữ khoá hiện có; tick "Xoá" để xoá khoá.</p>

    <form method="POST" action="{{ route('studio.api.update') }}" class="mt-6 space-y-4" x-data="apiTester">> ({})); } catch (e) { this.results[service] = { ok: false, message: e.message }; } finally { this.testing = ''; } } }">
        @csrf
        @foreach($providers as $service => $p)
            <div class="card p-5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p class="font-medium text-ink-900">{{ $p['label'] }}</p>
                        <p class="text-xs text-ink-500">env: {{ $p['hint'] }}</p>
                    </div>
                    <span class="badge {{ $p['configured'] ? 'bg-brand-600 text-white' : 'bg-cream-200 text-ink-500' }}">{{ $p['configured'] ? 'Đã cấu hình' : 'Chưa cấu hình' }}</span>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <input type="password" name="key_{{ $service }}" class="input flex-1" placeholder="{{ $p['configured'] ? '•••••••••••••• (giữ nguyên nếu bỏ trống)' : 'Nhập khoá ' . $p['hint'] }}" autocomplete="off">
                    <label class="flex items-center gap-2 text-sm text-ink-600"><input type="checkbox" name="clear_{{ $service }}" value="1" class="h-4 w-4 accent-red-600"> Xoá</label>
                    <button type="button" @click="test('{{ $service }}')" :disabled="testing === '{{ $service }}'" class="btn-outline btn-sm shrink-0">{{ $p['configured'] ? 'Test' : 'Test' }}</button>
                </div>
                <div x-show="results['{{ $service }}']" x-text="results['{{ $service }}'].message" :class="results['{{ $service }}'].ok ? 'mt-2 text-xs text-brand-700' : 'mt-2 text-xs text-red-600'"></div>
            </div>
        @endforeach

        <div class="flex items-center gap-3">
            <button type="submit" class="btn-brand">Lưu cấu hình API</button>
            <a href="{{ route('studio.index') }}" class="btn-ghost">Quay lại</a>
        </div>
        @if($errors->any())<div class="rounded-xl bg-red-50 p-3 text-sm text-red-600">{{ $errors->first() }}</div>@endif
    </form>

    <div class="mt-6 rounded-xl border border-brand-100 bg-brand-50 p-4 text-sm text-brand-800">
        Khi nhập khoá, các service tự chuyển từ <strong>stub</strong> sang gọi API thật (Gemini tạo prompt; Fal/Replicate sinh ảnh; Wan/Veo render video).
    </div>
</div>
@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('apiTester', () => ({
        testing: '',
        results: {},
        async test(service) {
            this.testing = service;
            try {
                const res = await fetch('/studio/api/test/' + service, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || '', Accept: 'application/json' },
                });
                this.results[service] = await res.json().catch(() => ({}));
            } catch (e) {
                this.results[service] = { ok: false, message: e.message };
            } finally {
                this.testing = '';
            }
        },
    }));
});
</script>
@endpush

@endsection
