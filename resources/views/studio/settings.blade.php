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
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label class="label">Độ phân giải Ảnh</label>
                <select name="image_resolution" class="input !py-2">
                    <option value="1K" @selected(old('image_resolution', $image_resolution) === '1K')>1K (1024×1024)</option>
                    <option value="2K" @selected(old('image_resolution', $image_resolution) === '2K')>2K (2048×2048)</option>
                </select>
                <p class="mt-1 text-xs text-ink-500">Chất lượng ảnh đầu ra khi render với model AI thật.</p>
            </div>
            <div>
                <label class="label">Độ phân giải Video</label>
                <select name="video_resolution" class="input !py-2">
                    <option value="480" @selected(old('video_resolution', $video_resolution) === '480')>480p</option>
                    <option value="720" @selected(old('video_resolution', $video_resolution) === '720')>720p</option>
                    <option value="1080" @selected(old('video_resolution', $video_resolution) === '1080')>1080p</option>
                </select>
                <p class="mt-1 text-xs text-ink-500">Độ phân giải video catwalk khi render với model AI thật.</p>
            </div>
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label class="label">Tỉ lệ khung hình Ảnh</label>
                <select name="image_ratio" class="input !py-2">
                    <option value="1:1" @selected(old('image_ratio', $image_ratio) === '1:1')>1:1 (vuông)</option>
                    <option value="4:3" @selected(old('image_ratio', $image_ratio) === '4:3')>4:3</option>
                    <option value="3:4" @selected(old('image_ratio', $image_ratio) === '3:4')>3:4</option>
                    <option value="9:16" @selected(old('image_ratio', $image_ratio) === '9:16')>9:16 (dọc)</option>
                    <option value="16:9" @selected(old('image_ratio', $image_ratio) === '16:9')>16:9 (ngang)</option>
                    <option value="4:5" @selected(old('image_ratio', $image_ratio) === '4:5')>4:5</option>
                    <option value="21:9" @selected(old('image_ratio', $image_ratio) === '21:9')>21:9 (rạp phim)</option>
                    <option value="19:6" @selected(old('image_ratio', $image_ratio) === '19:6')>19:6</option>
                </select>
            </div>
            <div>
                <label class="label">Độ dài Video</label>
                <select name="video_duration" class="input !py-2">
                    <option value="5" @selected(old('video_duration', $video_duration) === '5')>5 giây</option>
                    <option value="8" @selected(old('video_duration', $video_duration) === '8')>8 giây</option>
                    <option value="10" @selected(old('video_duration', $video_duration) === '10')>10 giây</option>
                    <option value="15" @selected(old('video_duration', $video_duration) === '15')>15 giây</option>
                    <option value="20" @selected(old('video_duration', $video_duration) === '20')>20 giây</option>
                </select>
            </div>
        </div>
        <div class="rounded-xl border border-cream-200 bg-cream-50 p-4">
            <h3 class="mb-3 font-display text-sm font-semibold text-ink-900">Model AI theo tác vụ</h3>
            <div>
                <label class="label">DashScope base URL</label>
                <input type="url" name="dashscope_base" value="{{ old('dashscope_base', $dashscope_base) }}" class="input !py-2" placeholder="https://dashscope-intl.aliyuncs.com">
                <p class="mt-1 text-xs text-ink-500">Chỉ nhập <strong>host</strong> (không thêm <code class="rounded bg-white px-1">/apps/...</code>). Đây là <strong>API endpoint</strong> — mở trực tiếp trong trình duyệt sẽ <strong>404 (bình thường)</strong>, đừng dùng trình duyệt để kiểm tra. Quốc tế: <code class="rounded bg-white px-1">https://dashscope-intl.aliyuncs.com</code> · Trung Quốc: <code class="rounded bg-white px-1">https://dashscope.aliyuncs.com</code>. Kiểm tra bằng nút <strong>Test</strong> trong Quản lý API.</p>
            </div>
            <div>
                <label class="label">Chế độ xử lý</label>
                <select name="processing" class="input !py-2">
                    <option value="sync" @selected(old('processing', $processing) === 'sync')>Đồng bộ (ra ảnh/video ngay, không cần worker)</option>
                    <option value="queue" @selected(old('processing', $processing) === 'queue')>Queue (nền — cần worker, phù hợp AI thật lâu)</option>
                </select>
                <p class="mt-1 text-xs text-ink-500">Queue: chạy <code class="rounded bg-white px-1">php artisan queue:work</code> hoặc cron <code class="rounded bg-white px-1">php artisan studio:process</code> mỗi phút. Nếu đã cấu hình key AI thật (QwenCloud/Wan), hệ thống tự chuyển sang Queue để tránh quá hạn PHP; nếu chưa có key (chế độ mô phỏng) vẫn chạy tức thì.</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div><label class="label">Prompt (Gemini)</label><input type="text" name="prompt_model" value="{{ old('prompt_model', $prompt_model) }}" class="input !py-2" placeholder="gemini-1.5-flash"></div>
                <div><label class="label">Ảnh Flux</label><input type="text" name="image_model" value="{{ old('image_model', $image_model) }}" class="input !py-2" placeholder="flux-1.1-schnell"></div>
                <div><label class="label">Ảnh Wan</label><input type="text" name="wan_model" value="{{ old('wan_model', $wan_model) }}" class="input !py-2" placeholder="wan2.7-image-pro"></div>
                <div><label class="label">Ảnh Qwen</label><input type="text" name="qwen_model" value="{{ old('qwen_model', $qwen_model) }}" class="input !py-2" placeholder="qwen-image-3.0-pro"></div>
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