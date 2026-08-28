@extends('layouts.studio')
@section('title', 'Cài đặt Studio')
@section('content')
<div class="mx-auto max-w-2xl">
    <h1 class="font-display text-2xl font-semibold text-ink-900">Cài đặt Studio</h1>
    <p class="mt-1 text-sm text-ink-500">Tinh chỉnh tín dụng và giới hạn cho công cụ AI nội bộ.</p>

    <form method="POST" action="{{ route('studio.settings.update') }}" enctype="multipart/form-data" class="card mt-6 space-y-5 p-6">
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
                <option value="gemini" @selected(old('image_provider', $image_provider) === 'gemini')>Gemini (Flash Image)</option>
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
                <p class="mt-1 text-xs text-ink-500">Chỉ nhập <strong>host</strong> (không thêm <code class="rounded bg-white px-1">/apps/...</code>); đây là API endpoint nên mở trực tiếp trên trình duyệt sẽ <strong>404 (bình thường)</strong>. Base URL <strong>phụ thuộc loại key QwenCloud</strong>:
                    <span class="mt-1 block">• Key <code class="rounded bg-white px-1">sk-xxxxx</code> (pay-as-you-go) → <code class="rounded bg-white px-1">https://dashscope-intl.aliyuncs.com</code></span>
                    <span class="block">• Key <code class="rounded bg-white px-1">sk-sp-…</code> (Token Plan, dùng Credits) → <code class="rounded bg-white px-1">https://token-plan.ap-southeast-1.maas.aliyuncs.com</code></span>
                    <span class="block">• Key <code class="rounded bg-white px-1">sk-sp-…</code> (Coding Plan) → <code class="rounded bg-white px-1">https://coding-intl.dashscope.aliyuncs.com</code></span>
                    Nếu để mặc định và dùng key <code class="rounded bg-white px-1">sk-sp-</code>, hệ thống tự chuyển sang Token Plan. Kiểm tra bằng nút <strong>Test</strong> trong Quản lý API.</p>
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
                <div><label class="label">Ảnh Gemini</label><input type="text" name="gemini_image_model" value="{{ old('gemini_image_model', $gemini_image_model) }}" class="input !py-2" placeholder="gemini-2.5-flash-image"><p class="mt-1 text-xs text-ink-500">Model hợp lệ: <code class="rounded bg-white px-1">gemini-2.5-flash-image</code> · <code class="rounded bg-white px-1">gemini-2.0-flash-preview-image-generation</code> · <code class="rounded bg-white px-1">imagen-4.0-generate-001</code>. Key dùng <code class="rounded bg-white px-1">x-goog-api-key</code> (lấy tại <code class="rounded bg-white px-1">aistudio.google.com/apikey</code>).</p></div>
                <div>
                    <label class="label">Video (Wan / Veo)</label>
                    <input type="text" name="video_model" list="video-model-list" value="{{ old('video_model', $video_model) }}" class="input !py-2" placeholder="wan2.5-t2v">
                    <datalist id="video-model-list">
                        <option value="wan2.5-t2v"><option value="wan2.2-i2v"><option value="happyhorse-1.1-i2v"><option value="wan2.1-i2v-turbo"><option value="veo-3.1">
                    </datalist>
                </div>
                <div><label class="label">Vision (gợi ý từ ảnh)</label><input type="text" name="vision_model" value="{{ old('vision_model', $vision_model) }}" class="input !py-2" placeholder="gemini-1.5-flash"></div>
            </div>
        </div>
        <div class="rounded-xl border border-cream-200 bg-cream-50 p-4">
            <h3 class="mb-2 font-display text-sm font-semibold text-ink-900">Worker tự động (Hàng đợi)</h3>
            <p class="text-xs text-ink-500">Studio <strong>tự động chạy job ngay</strong> khi bạn bấm Tạo ảnh / Video — <strong>không cần SSH, không cần cron, không cần <code class="rounded bg-white px-1">php artisan queue:work</code></strong>. Kết quả cập nhật ngay trên Canvas / Outputs.</p>
            <p class="mt-2 text-xs text-ink-500">Các việc <strong>rất dài</strong> (video AI thật) có thể đưa sang worker nền để không chờ trong trang — chỉ dành cho người dùng nâng cao:</p>
            <div class="mt-2 break-all rounded-xl bg-white p-3 font-mono text-xs text-ink-900">php artisan queue:work --stop-when-empty --timeout=200</div>
            <p class="mt-2 text-xs text-ink-500">Đang chờ (nâng cao): <b class="text-ink-900">{{ $pending_count }}</b> · Driver: <b class="text-ink-900">{{ $queue_driver }}</b></p>
            <p class="mt-1 text-xs text-ink-500">Nếu chỉ muốn chạy thủ công: <code class="rounded bg-white px-1">php artisan studio:process</code>.</p>
        </div>
        <div class="rounded-xl border border-cream-200 bg-cream-50 p-4">
            <h3 class="mb-2 font-display text-sm font-semibold text-ink-900">Thương hiệu & Khuôn mặt mẫu</h3>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="label">Logo thương hiệu (chèn vào hậu cảnh ảnh)</label>
                    <input type="file" name="brand_logo" accept="image/*" class="input !py-2">
                    @if($brand_logo)<img src="{{ $brand_logo }}" class="mt-2 h-10 w-40 rounded bg-white object-contain" alt="Logo">@endif
                </div>
                <div>
                    <label class="label">Khuôn mặt mẫu (đồng bộ nhân vật)</label>
                    <input type="file" name="face_ref" accept="image/*" class="input !py-2">
                    @if($face_ref)<img src="{{ $face_ref }}" class="mt-2 h-16 w-16 rounded-full bg-white object-cover" alt="Mặt mẫu">@endif
                </div>
            </div>
            <p class="mt-2 text-xs text-ink-500">Logo được dán vào góc ảnh khi tạo ảnh nền shop; khuôn mặt mẫu dùng làm tham chiếu cho nhất quán nhân vật (nếu model ảnh hỗ trợ).</p>
        </div>
        <div class="flex items-center gap-3">
            <button type="submit" class="btn-brand">Lưu cài đặt</button>
            <a href="{{ route('studio.index') }}" class="btn-ghost">Quay lại</a>
        </div>
        @if($errors->any())<div class="rounded-xl bg-red-50 p-3 text-sm text-red-600">{{ $errors->first() }}</div>@endif
    </form>
</div>
@endsection