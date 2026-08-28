@extends('layouts.studio')
@section('title', 'Cài đặt Studio')
@section('content')
<div class="mx-auto max-w-2xl">
    <h1 class="font-display text-2xl font-semibold text-ink-900">Cài đặt Studio</h1>
    <p class="mt-1 text-sm text-ink-500">Tinh chỉnh tín dụng và giới hạn cho công cụ AI nội bộ.</p>

    <form method="POST" action="{{ route('studio.settings.update') }}" class="card mt-6 space-y-5 p-6">
        @csrf
        <div>
            <label class="label">Tín dụng / ảnh</label>
            <input type="number" name="image_credits" value="{{ old('image_credits', $image_credits) }}" min="0" max="1000" class="input">
            <p class="mt-1 text-xs text-ink-500">Số tín dụng trừ khi tạo 1 ảnh.</p>
        </div>
        <div>
            <label class="label">Tín dụng / video</label>
            <input type="number" name="video_credits" value="{{ old('video_credits', $video_credits) }}" min="0" max="1000" class="input">
            <p class="mt-1 text-xs text-ink-500">Số tín dụng trừ khi render 1 video.</p>
        </div>
        <div>
            <label class="label">Giới hạn số generation mỗi project</label>
            <input type="number" name="max_generations" value="{{ old('max_generations', $max_generations) }}" min="1" max="500" class="input">
        </div>
        <div>
            <label class="label">Nhà cung cấp sinh ảnh</label>
            <select name="image_provider" class="input">
                <option value="flux" @selected(old('image_provider', $image_provider) === 'flux')>Flux (Fal.ai / Replicate)</option>
                <option value="wan" @selected(old('image_provider', $image_provider) === 'wan')>Wan AI</option>
                <option value="qwen" @selected(old('image_provider', $image_provider) === 'qwen')>Qwen AI</option>
            </select>
            <p class="mt-1 text-xs text-ink-500">Chọn mô hình sinh ảnh; bỏ trống key thì vẫn dùng stub.</p>
        </div>
        <div class="rounded-xl border border-cream-200 bg-cream-50 p-4">
            <h3 class="mb-3 font-display text-sm font-semibold text-ink-900">Model AI theo tác vụ</h3>
            <div>
                <label class="label">DashScope base URL</label>
                <input type="url" name="dashscope_base" value="{{ old('dashscope_base', $dashscope_base) }}" class="input !py-2" placeholder="https://dashscope-intl.aliyuncs.com">
                <p class="mt-1 text-xs text-ink-500">Quốc tế: <code class="rounded bg-white px-1">dashscope-intl.aliyuncs.com</code> · Trung Quốc: <code class="rounded bg-white px-1">dashscope.aliyuncs.com</code>. Nếu key bị 401, thử đổi vùng này.</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div><label class="label">Prompt (Gemini)</label><input type="text" name="prompt_model" value="{{ old('prompt_model', $prompt_model) }}" class="input !py-2" placeholder="gemini-1.5-flash"></div>
                <div><label class="label">Ảnh Flux</label><input type="text" name="image_model" value="{{ old('image_model', $image_model) }}" class="input !py-2" placeholder="flux-1.1-schnell"></div>
                <div><label class="label">Ảnh Wan</label><input type="text" name="wan_model" value="{{ old('wan_model', $wan_model) }}" class="input !py-2" placeholder="wan2.7-image-pro"></div>
                <div><label class="label">Ảnh Qwen</label><input type="text" name="qwen_model" value="{{ old('qwen_model', $qwen_model) }}" class="input !py-2" placeholder="qwen-image"></div>
                <div><label class="label">Video (Wan/Veo)</label><input type="text" name="video_model" value="{{ old('video_model', $video_model) }}" class="input !py-2" placeholder="wan2.5-t2v"></div>
                <div><label class="label">Vision (gợi ý từ ảnh)</label><input type="text" name="vision_model" value="{{ old('vision_model', $vision_model) }}" class="input !py-2" placeholder="gemini-1.5-flash"></div>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <button type="submit" class="btn-brand">Lưu cài đặt</button>
            <a href="{{ route('studio.index') }}" class="btn-ghost">Quay lại</a>
        </div>
        @if($errors->any())<div class="rounded-xl bg-red-50 p-3 text-sm text-red-600">{{ $errors->first() }}</div>@endif
    </form>
</div>
@endsection