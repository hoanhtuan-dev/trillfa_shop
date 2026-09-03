@extends('layouts.studio')
@section('title', 'Cài đặt Studio')
@section('content')
<div class="mx-auto max-w-2xl" x-data="{ tab: 'general' }">
    <h1 class="font-display text-2xl font-semibold text-ink-900">Cài đặt Studio</h1>
    <p class="mt-1 text-sm text-ink-500">Cấu hình nhà cung cấp AI, model &amp; API key dùng cho công cụ nội bộ.</p>

    {{-- Tabs --}}
    <div class="mt-4 flex flex-wrap gap-1.5 rounded-2xl border border-ink-700 bg-ink-800 p-1 text-xs font-semibold">
        <button @click="tab='general'" class="rounded-xl px-3 py-2 transition-colors" :class="tab==='general' ? 'bg-brand-600 text-white' : 'text-cream-200 hover:bg-ink-700'">⚙️ Cấu hình</button>
        <button @click="tab='models'" class="rounded-xl px-3 py-2 transition-colors" :class="tab==='models' ? 'bg-brand-600 text-white' : 'text-cream-200 hover:bg-ink-700'">🤖 Model</button>
        <button @click="tab='faces'" class="rounded-xl px-3 py-2 transition-colors" :class="tab==='faces' ? 'bg-brand-600 text-white' : 'text-cream-200 hover:bg-ink-700'">💃 Dáng & Khuôn mặt</button>
        <button @click="tab='suggest'" class="rounded-xl px-3 py-2 transition-colors" :class="tab==='suggest' ? 'bg-brand-600 text-white' : 'text-cream-200 hover:bg-ink-700'">💡 Gợi ý từ ảnh</button>
        <button @click="tab='keys'" class="rounded-xl px-3 py-2 transition-colors" :class="tab==='keys' ? 'bg-brand-600 text-white' : 'text-cream-200 hover:bg-ink-700'">🔑 API Keys</button>
    </div>

    <div x-show="tab==='general'">
    <form method="POST" action="{{ route('studio.settings.update') }}" enctype="multipart/form-data" class="card mt-6 space-y-5 p-6">
        @csrf
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
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="label">Pay-As-You-Go base URL <span class="text-ink-500">(key <code class="rounded bg-white px-1">sk-…</code>)</span></label>
                    <input type="url" name="dashscope_base" value="{{ old('dashscope_base', $dashscope_base) }}" class="input !py-2" placeholder="https://dashscope-intl.aliyuncs.com">
                </div>
                <div>
                    <label class="label">Token / Coding Plan base URL <span class="text-ink-500">(key <code class="rounded bg-white px-1">sk-sp-…</code>)</span></label>
                    <input type="url" name="dashscope_token_plan_base" value="{{ old('dashscope_token_plan_base', $dashscope_token_plan_base) }}" class="input !py-2" placeholder="https://token-plan.ap-southeast-1.maas.aliyuncs.com">
                </div>
            </div>
            <p class="mt-2 text-xs text-ink-500">Ứng dụng tự chọn base URL theo loại key: <code class="rounded bg-white px-1">sk-…</code> → Pay-As-You-Go, <code class="rounded bg-white px-1">sk-sp-…</code> → Token/Coding Plan. <strong>Hai loại key dùng host và chiến lược riêng, không dùng chung.</strong> Chỉ nhập <strong>host</strong> (không thêm <code class="rounded bg-white px-1">/apps/...</code>); mở trên trình duyệt sẽ <strong>404 (bình thường)</strong>. Kiểm tra bằng nút <strong>Test</strong> trong Quản lý API.</p>
            <div>
                <label class="label">Chế độ xử lý</label>
                <select name="processing" class="input !py-2">
                    <option value="sync" @selected(old('processing', $processing) === 'sync')>Đồng bộ (ra ảnh/video ngay, không cần worker)</option>
                    <option value="queue" @selected(old('processing', $processing) === 'queue')>Queue (nền — cần worker, phù hợp AI thật lâu)</option>
                </select>
                <p class="mt-1 text-xs text-ink-500">Queue: chạy <code class="rounded bg-white px-1">php artisan queue:work</code> hoặc cron <code class="rounded bg-white px-1">php artisan studio:process</code> mỗi phút. Nếu đã cấu hình key AI thật (QwenCloud/Wan), hệ thống tự chuyển sang Queue để tránh quá hạn PHP; nếu chưa có key (chế độ mô phỏng) vẫn chạy tức thì.</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="label">Prompt — nhà cung cấp</label>
                    <select name="prompt_provider" class="input !py-2">
                        <option value="gemini" @selected(old('prompt_provider', $prompt_provider) === 'gemini')>Gemini</option>
                        <option value="qwen" @selected(old('prompt_provider', $prompt_provider) === 'qwen')>Qwen</option>
                        <option value="deepseek" @selected(old('prompt_provider', $prompt_provider) === 'deepseek')>DeepSeek (deepseek-chat)</option>
                    </select>
                </div>
                <div><label class="label">Prompt model</label><input type="text" name="prompt_model" value="{{ old('prompt_model', $prompt_model) }}" class="input !py-2" placeholder="gemini-2.5-flash"></div>
                <div><label class="label">Qwen Prompt model</label><input type="text" name="qwen_prompt_model" value="{{ old('qwen_prompt_model', $qwen_prompt_model ?? 'qwen3.8-flash') }}" class="input !py-2" placeholder="qwen3.8-flash / qwen3.8-max / qwen-plus" list="qwen-chat-models"></div>
                <div><label class="label">Qwen Max model (cao cấp)</label><input type="text" name="qwen_max_model" value="{{ old('qwen_max_model', $qwen_max_model ?? 'qwen3.8-max') }}" class="input !py-2" placeholder="qwen3.8-max" list="qwen-chat-models"></div>
                <div class="sm:col-span-2">
                    <label class="label">Qwen Chat models — thứ tự ưu tiên (tùy biến)</label>
                    <input type="text" name="qwen_text_models" value="{{ old('qwen_text_models', $qwen_text_models ?? '') }}" class="input !py-2" placeholder="VD: qwen3.8-max, qwen3.8-flash, qwen-plus (để trống = dùng mặc định)">
                    <p class="mt-1 text-xs text-ink-500">Dùng cho <strong>Trợ lý thiết kế, Giám đốc sáng tạo, dịch prompt</strong> (endpoint chat). Model đầu = ưu tiên cao nhất; nhập model bất kỳ để nâng cấp mà không cần sửa code.</p>
                </div>
                <datalist id="qwen-chat-models">
                    <option value="qwen3.8-flash"></option>
                    <option value="qwen3.8-max"></option>
                    <option value="qwen-plus"></option>
                    <option value="qwen-turbo"></option>
                </datalist>
                <div><label class="label">Model dịch prompt (tiếng Việt)</label><input type="text" name="translate_model" value="{{ old('translate_model', $translate_model) }}" class="input !py-2" placeholder="gemini-3.6-flash-image"></div>
                <div><label class="label">Model Thay Đổi Người Mẫu</label><input type="text" name="swap_model" value="{{ old('swap_model', $swap_model) }}" class="input !py-2" placeholder="để trống = dùng model Inpaint (qwen-image-edit-max)"></div>
                <div><label class="label">Model Thuật sỹ ảo</label><input type="text" name="stylist_model" value="{{ old('stylist_model', $stylist_model) }}" class="input !py-2" placeholder="qwen3.8-flash" list="qwen-chat-models"></div>
                <div class="sm:col-span-2">
                    <label class="label">Model tạo ảnh</label>
                    <input type="text" id="default-image-model" class="input !py-2" placeholder="VD: qwen-image-3.0-pro / wan2.7-image-pro / flux-1.1-schnell" value="{{ old('image_model', $image_model) }}">
                    <input type="hidden" name="image_model" value="{{ old('image_model', $image_model) }}">
                    <input type="hidden" name="wan_model" value="{{ old('wan_model', $wan_model) }}">
                    <input type="hidden" name="qwen_model" value="{{ old('qwen_model', $qwen_model) }}">
                    <input type="hidden" name="gemini_image_model" value="{{ old('gemini_image_model', $gemini_image_model) }}">
                    <p class="mt-1 text-xs text-ink-500">Model mặc định cho <strong>nhà cung cấp sinh ảnh</strong> đang chọn (đổi nhà cung cấp để thấy model tương ứng). Thứ tự ưu tiên thực tế do <strong>Model Registry (tab Model)</strong> quyết định.</p>
                </div>
                <div>
                    <label class="label">Qwen Edit (chỉnh sửa ảnh / Inpaint)</label>
                    <input type="text" name="qwen_edit_model" value="{{ old('qwen_edit_model', $qwen_edit_model) }}" class="input !py-2" placeholder="qwen-image-edit" list="qwen-edit-models">
                    <datalist id="qwen-edit-models">
                        <option value="qwen-image-edit"></option>
                        <option value="qwen-image-edit-plus"></option>
                        <option value="wanx2.1-imageedit"></option>
                        <option value="wanx2.1-imageedit-plus"></option>
                    </datalist>
                    <p class="mt-1 text-xs text-ink-500">Có nhiều model edit ảnh chuyên dụng của Qwen (qwen-image-edit, qwen-image-edit-plus, wanx2.1-imageedit…). Nhập đúng model tài khoản bạn hỗ trợ; dùng chung khoá <strong>Qwen / Wan</strong> ở trang API.</p>
                </div>

                <div>
                    <label class="label">Video (Wan / Veo)</label>
                    <input type="text" name="video_model" list="video-model-list" value="{{ old('video_model', $video_model) }}" class="input !py-2" placeholder="wan2.5-t2v">
                    <datalist id="video-model-list">
                        <option value="wan2.5-t2v"><option value="wan2.2-i2v"><option value="happyhorse-1.1-i2v"><option value="wan2.1-i2v-turbo"><option value="veo-3.1">
                    </datalist>
                </div>
                <div>
                    <label class="label">Vision — nhà cung cấp</label>
                    <select name="vision_provider" class="input !py-2">
                        <option value="gemini" @selected(old('vision_provider', $vision_provider) === 'gemini')>Gemini</option>
                        <option value="qwen" @selected(old('vision_provider', $vision_provider) === 'qwen')>Qwen (multimodal 3.8)</option>
                        <option value="deepseek" @selected(old('vision_provider', $vision_provider) === 'deepseek')>DeepSeek</option>
                    </select>
                </div>
                <div><label class="label">Vision model (Gemini)</label><input type="text" name="vision_model" value="{{ old('vision_model', $vision_model) }}" class="input !py-2" placeholder="gemini-2.5-flash"></div>
                <div>
                    <label class="label">Qwen Vision model (đa phương thức)</label>
                    <input type="text" name="qwen_vision_model" value="{{ old('qwen_vision_model', $qwen_vision_model ?? 'qwen3.8-flash') }}" class="input !py-2" placeholder="qwen3.8-flash / qwen3.8-max" list="qwen-vision-models">
                    <datalist id="qwen-vision-models">
                        <option value="qwen3.8-flash"></option>
                        <option value="qwen3.8-max"></option>
                        <option value="qwen-vl-max"></option>
                        <option value="qwen-vl-plus"></option>
                    </datalist>
                    <p class="mt-1 text-xs text-ink-500">qwen3.8-flash / qwen3.8-max là model <strong>đa phương thức</strong> — tự đọc ảnh, video và text qua endpoint chat; dùng cho "Gợi ý từ ảnh" & phân tích khuôn mặt. qwen-vl-* giữ làm fallback.</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Qwen Vision models — thứ tự ưu tiên (tùy biến)</label>
                    <input type="text" name="qwen_vision_models" value="{{ old('qwen_vision_models', $qwen_vision_models ?? '') }}" class="input !py-2" placeholder="VD: qwen3.8-max, qwen3.8-flash, qwen-vl-max (để trống = dùng mặc định)">
                    <p class="mt-1 text-xs text-ink-500">Nhập danh sách model phân cách dấu phẩy — model <strong>đầu tiên được ưu tiên cao nhất</strong>; cho phép đổi/nâng cấp model bất kỳ (qwen3.8-max, qwen3.8-flash…) mà không cần sửa code. Để trống để dùng mặc định từ cấu hình.</p>
                </div>
            </div>
        </div>
        <div class="rounded-xl border border-cream-200 bg-cream-50 p-4">
            <h3 class="mb-3 font-display text-sm font-semibold text-ink-900">Prompt mặc định</h3>
            <p class="mb-2 text-xs text-ink-500">Các cài đặt này được dùng làm giá trị mặc định trong <b>Prompt Tạo Ảnh</b> — người dùng có thể ghi đè trong giao diện.</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="label">Mức sáng tạo mặc định (1-10)</label>
                    <input type="number" name="creative_level" min="1" max="10" value="{{ old('creative_level', $creative_level) }}" class="input !py-2">
                    <p class="mt-1 text-xs text-ink-500">1 = bám sát brief · 10 = sáng tạo tự do</p>
                </div>
                <div>
                    <label class="label">Texture mặc định (0-10)</label>
                    <input type="number" name="texture" min="0" max="10" value="{{ old('texture', $texture) }}" class="input !py-2">
                    <p class="mt-1 text-xs text-ink-500">0 = không có texture · 10 = siêu chi tiết sợi vải</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Prompt prefix (tự động thêm vào đầu)</label>
                    <input type="text" name="prompt_prefix" value="{{ old('prompt_prefix', $prompt_prefix) }}" class="input !py-2" placeholder="High-fashion editorial photograph, professional fashion photography">
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Prompt suffix (tự động thêm vào cuối)</label>
                    <input type="text" name="prompt_suffix" value="{{ old('prompt_suffix', $prompt_suffix) }}" class="input !py-2" placeholder="soft diffused studio lighting, clean minimal background, ultra detailed, 4k, sharp focus">
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Negative prompt (điều model KHÔNG được tạo)</label>
                    <textarea name="negative_prompt" rows="2" class="input !py-2" placeholder="blurry, low quality, distorted proportions...">{{ old('negative_prompt', $negative_prompt) }}</textarea>
                </div>
                <div>
                    <label class="flex items-center gap-2 text-ink-700">
                        <input type="checkbox" name="enrich_prompt" value="1" @if(old('enrich_prompt', $enrich_prompt)) checked @endif class="h-4 w-4 accent-brand-600">
                        Tự động làm giàu prompt (prefix + suffix + negative)
                    </label>
                    <p class="mt-1 text-xs text-ink-500">Bật: tự động thêm prefix/suffix/negative vào mọi prompt. Tắt: dùng prompt thô từ người dùng.</p>
                </div>
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
        <div class="flex items-center gap-3">
            <button type="submit" class="btn-brand">Lưu cài đặt</button>
            <a href="{{ route('studio.index') }}" class="btn-ghost">Quay lại</a>
        </div>
        @if($errors->any())<div class="rounded-xl bg-red-50 p-3 text-sm text-red-600">{{ $errors->first() }}</div>@endif
    </form>
    </div>

    <div x-show="tab==='suggest'">
    {{-- ===== Cấu hình RIÊNG "💡 Gợi ý từ ảnh" — độc lập với cấu hình chung ===== --}}
    <form method="POST" action="{{ route('studio.settings.suggest') }}" class="card mt-6 space-y-4 p-6">
        @csrf
        <h2 class="font-display text-base font-semibold text-ink-900">💡 Gợi ý từ ảnh (Image → Style / Prompt)</h2>
        <p class="text-xs text-ink-500">Cấu hình <b>provider + model + hành vi riêng</b> cho tính năng "Gợi ý từ ảnh". Không phụ thuộc cấu hình Vision chung, Model Registry hay API Keys của các tính năng khác.</p>

        <div class="rounded-xl border border-brand-100 bg-brand-900/40 p-4 text-xs text-brand-200">
            <label class="flex items-center gap-2 font-semibold text-brand-100">
                <input type="checkbox" name="suggest_enabled" value="1" @if(old('suggest_enabled', $suggest_enabled)) checked @endif class="h-4 w-4 accent-brand-600">
                Bật tính năng
            </label>
            <p class="mt-1">Khi tắt, thẻ "💡 Gợi ý từ ảnh" trong Studio sẽ không trả kết quả (báo lỗi thân thiện).</p>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label class="label">Vision provider</label>
                <select name="suggest_provider" class="input !py-2">
                    <option value="gemini" @selected(old('suggest_provider', $suggest_provider) === 'gemini')>Gemini (Vision)</option>
                    <option value="qwen" @selected(old('suggest_provider', $suggest_provider) === 'qwen')>Qwen (multimodal)</option>
                </select>
                <p class="mt-1 text-xs text-ink-500">Tự chuyển fallback sang provider còn lại khi lỗi; cuối cùng mới tới phân tích màu GD.</p>
            </div>
            <div>
                <label class="label">Gemini model (Vision)</label>
                <input type="text" name="suggest_gemini_model" value="{{ old('suggest_gemini_model', $suggest_gemini_model) }}" class="input !py-2" placeholder="gemini-2.5-flash">
            </div>
            <div>
                <label class="label">Qwen model chính (multimodal)</label>
                <input type="text" name="suggest_qwen_model" value="{{ old('suggest_qwen_model', $suggest_qwen_model) }}" class="input !py-2" placeholder="qwen3.8-flash" list="suggest-qwen-models">
                <datalist id="suggest-qwen-models">
                    <option value="qwen3.8-flash"></option>
                    <option value="qwen3.8-max"></option>
                    <option value="qwen-vl-max"></option>
                    <option value="qwen-vl-plus"></option>
                </datalist>
            </div>
            <div>
                <label class="label">Qwen Vision models (ưu tiên, phân cách dấu phẩy)</label>
                <input type="text" name="suggest_qwen_models" value="{{ old('suggest_qwen_models', $suggest_qwen_models) }}" class="input !py-2" placeholder="qwen3.8-max, qwen3.8-flash (để trống = mặc định)">
            </div>
        </div>

        <div class="rounded-xl border border-cream-200 bg-cream-50 p-4">
            <h3 class="mb-3 font-display text-sm font-semibold text-ink-900">Hành vi gợi ý</h3>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="label">Mức sáng tạo mặc định (1-10)</label>
                    <input type="number" name="suggest_creative_level" min="1" max="10" value="{{ old('suggest_creative_level', $suggest_creative_level) }}" class="input !py-2">
                    <p class="mt-1 text-xs text-ink-500">Dùng riêng cho gợi ý — không theo mức sáng tạo chung.</p>
                </div>
                <div>
                    <label class="label">Số phong cách tối đa (1-5)</label>
                    <input type="number" name="suggest_max_styles" min="1" max="5" value="{{ old('suggest_max_styles', $suggest_max_styles) }}" class="input !py-2">
                </div>
                <div>
                    <label class="label">Giới hạn downscale ảnh (px, 64-4096)</label>
                    <input type="number" name="suggest_downscale_max" min="64" max="4096" value="{{ old('suggest_downscale_max', $suggest_downscale_max) }}" class="input !py-2">
                    <p class="mt-1 text-xs text-ink-500">Ảnh lớn hơn sẽ được thu nhỏ trước khi gửi lên model để giảm chi phí/token.</p>
                </div>
                <div>
                    <label class="label">Ngôn ngữ hiển thị mặc định</label>
                    <select name="suggest_default_lang" class="input !py-2">
                        <option value="en" @selected(old('suggest_default_lang', $suggest_default_lang) === 'en')>🇬🇧 English</option>
                        <option value="vi" @selected(old('suggest_default_lang', $suggest_default_lang) === 'vi')>🇻🇳 Tiếng Việt</option>
                    </select>
                </div>
            </div>
            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                <label class="flex items-center gap-2 text-ink-700">
                    <input type="checkbox" name="suggest_fallback" value="1" @if(old('suggest_fallback', $suggest_fallback)) checked @endif class="h-4 w-4 accent-brand-600">
                    Dùng fallback màu (GD) khi không có key
                </label>
                <label class="flex items-center gap-2 text-ink-700">
                    <input type="checkbox" name="suggest_include_video" value="1" @if(old('suggest_include_video', $suggest_include_video)) checked @endif class="h-4 w-4 accent-brand-600">
                    Kèm prompt video catwalk
                </label>
            </div>
        </div>

        <button type="submit" class="btn-brand">💾 Lưu cấu hình "Gợi ý từ ảnh"</button>
        @if($errors->any())<div class="rounded-xl bg-red-50 p-3 text-sm text-red-600">{{ $errors->first() }}</div>@endif
    </form>
    </div>

    <div x-show="tab==='models'">
    {{-- ===== Cấu hình model Thay Đổi Người Mẫu (Try-on) ===== --}}
    <form method="POST" action="{{ url('/studio/settings/models') }}" class="card mt-6 space-y-4 p-6">
        @csrf
        <h2 class="font-display text-base font-semibold text-ink-900">🪄 Model Thay Đổi Người Mẫu (Try-on)</h2>
        <p class="text-xs text-ink-500">Cấu hình model riêng cho tính năng "Thay Đổi Người Mẫu" — tách khỏi cấu hình chung.</p>
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label class="label">Model Edit (Thay Đổi Người Mẫu)</label>
                <input type="text" name="swap_model" value="{{ old('swap_model', $swap_model) }}" class="input !py-2" placeholder="để trống = dùng qwen_edit_model">
            </div>
            <div>
                <label class="label">Model Qwen Edit (Inpaint)</label>
                <input type="text" name="qwen_edit_model" value="{{ old('qwen_edit_model', $qwen_edit_model) }}" class="input !py-2" placeholder="qwen-image-edit-max">
            </div>
            <div>
                <label class="label">Model Đọc ảnh (Qwen Vision)</label>
                <input type="text" name="qwen_vision_model" value="{{ old('qwen_vision_model', $qwen_vision_model) }}" class="input !py-2" placeholder="qwen3.8-flash">
            </div>
            <div>
                <label class="label">Qwen Vision models (ưu tiên)</label>
                <input type="text" name="qwen_vision_models" value="{{ old('qwen_vision_models', $qwen_vision_models) }}" class="input !py-2" placeholder="qwen3.8-flash, qwen-vl-max (để trống = mặc định)">
            </div>
        </div>
        <button type="submit" class="btn-brand">💾 Lưu model try-on</button>
    </form>

    {{-- ===== Model Registry manager ===== --}}
    <div class="card mt-6 p-6">
        <p class="mb-3 text-xs text-ink-500">⚙️ <b>{{ $api_keys->pluck('provider')->unique()->count() }}</b> nhóm API · <b>{{ $api_keys->count() }}</b> key đã đăng ký. Chọn <b>API key</b> → tự nhận provider → gán <b>vai trò</b> (Ảnh/Video/Suy luận/Ngôn ngữ) + <b>Ưu tiên</b>.</p>
        <h2 class="flex items-center justify-between font-display text-base font-semibold text-ink-900">🤖 Model Registry <span class="text-xs font-normal text-ink-500">quản lý model theo nhóm (image / video / inference)</span></h2>
        <p class="mt-1 text-xs text-ink-500">Chọn nhiều model/nhóm, gán <b>ưu tiên</b> (cao = dùng trước). Khi một model hết hạn mức/API lỗi, hệ thống tự chuyển sang model kế tiếp theo độ ưu tiên. Model hiển thị trong Studio theo nhóm tương ứng (Model video = nhóm video).</p>

        @foreach(['image'=>'Ảnh','video'=>'Video','inference'=>'Suy luận','text'=>'Ngôn ngữ (text/prompt)'] as $grp=>$grpLabel)
            <div class="mt-5">
                <h3 class="text-sm font-semibold text-ink-900">{{ $grpLabel }}</h3>
                <div class="mt-2 space-y-2">
                    @forelse($models->where('group', $grp) as $m)
                        @php $id = data_get($m, 'id'); $name = data_get($m, 'name'); $provider = data_get($m, 'provider'); $modelId = data_get($m, 'model_id'); $priority = data_get($m, 'priority', 0); $enabled = (bool) data_get($m, 'enabled', true); @endphp
                        <div class="rounded-xl border border-cream-200 p-2 text-xs" x-data="{ editing:false }">
                            <div x-show="!editing" class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-ink-900">{{ $name }}</span>
                                <span class="text-ink-500">{{ $provider }} · {{ $modelId }}@if(data_get($m,'api_key_ref')) <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] text-indigo-700">🔑 {{ data_get($m,'api_key_ref') }}</span>@endif</span>
                                <span class="rounded-full bg-cream-200 px-2 py-0.5 text-[10px] text-ink-700">Ưu tiên {{ $priority }}</span>
                                <span class="rounded-full px-2 py-0.5 text-[10px] {{ $enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-600' }}">{{ $enabled ? 'Bật' : 'Tắt' }}</span>
                                @if($id)<button type="button" class="btn-outline btn-sm" onclick="studioTestModel(this, {{ $id }})">🔍 Kiểm tra</button><span class="test-result block w-full text-[10px]"></span>@endif
                                @if($id)
                                    <div class="ml-auto flex items-center gap-1.5">
                                        <button type="button" @click="editing=true" class="btn-outline btn-sm">✏️ Sửa</button>
                                        <form method="POST" action="{{ route('studio.models.delete', $m) }}" onsubmit="return confirm('Xóa model «{{ $name }}»?')">@csrf @method('DELETE')<button class="btn-outline btn-sm text-red-600">Xóa</button></form>
                                    </div>
                                @else
                                    <span class="ml-auto text-[10px] text-ink-500">(mặc định — thêm lại để chỉnh sửa)</span>
                                @endif
                            </div>
                            @if($id)
                            <form x-show="editing" method="POST" action="{{ route('studio.models.update', $m) }}" class="mt-2 space-y-2 border-t border-cream-200 pt-2">
                                @csrf @method('PUT')
                                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                    <div><label class="label">Tên</label><input name="name" value="{{ $name }}" class="input !py-1"></div>
                                    <div><label class="label">Vai trò</label><select name="group" class="input !py-1"><option value="image" @selected($m->group==='image')>Ảnh</option><option value="video" @selected($m->group==='video')>Video</option><option value="inference" @selected($m->group==='inference')>Suy luận</option><option value="text" @selected($m->group==='text')>Ngôn ngữ</option></select></div>
                                    <div><label class="label">Provider</label><input name="provider" value="{{ $provider }}" class="input !py-1"></div>
                                    <div><label class="label">Model ID</label><input name="model_id" value="{{ $modelId }}" class="input !py-1"></div>
                                    <div><label class="label">Khóa (API key)</label><select name="api_key_ref" class="input !py-1"><option value="">—</option>@foreach($api_keys as $ak)<option value="{{ $ak->provider }}" @selected(data_get($m,'api_key_ref')===$ak->provider)>{{ $ak->provider }} · {{ $ak->label ?: ($ak->kind ?: '') }}</option>@endforeach</select></div>
                                    <div><label class="label">Ưu tiên</label><input type="number" name="priority" value="{{ $priority }}" min="0" max="100" class="input !py-1"></div>
                                    <div class="col-span-2"><label class="flex items-center gap-1 text-ink-700"><input type="checkbox" name="enabled" value="1" @if($enabled) checked @endif class="h-4 w-4 accent-brand-600"> Bật</label></div>
                                </div>
                                <div class="flex gap-2"><button class="btn-brand btn-sm">💾 Lưu</button><button type="button" @click="editing=false" class="btn-ghost btn-sm">Hủy</button></div>
                            </form>
                            @endif
                        </div>
                    @empty
                        <p class="text-xs text-ink-500">Chưa có model nhóm {{ $grpLabel }}.</p>
                    @endforelse
                </div>
            </div>
        @endforeach


        {{-- Add / edit model --}}
        <form method="POST" action="{{ route('studio.models.store') }}" class="mt-6 space-y-3 rounded-xl border border-dashed border-cream-300 p-4">
            @csrf
            <h3 class="text-sm font-semibold text-ink-900">➕ Thêm model</h3>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <div><label class="label">Vai trò</label><select name="group" class="input !py-2"><option value="image">Ảnh (image)</option><option value="video">Video</option><option value="inference">Suy luận (inference)</option><option value="text">Ngôn ngữ (text/prompt)</option></select></div>
                <div><label class="label">Tên hiển thị</label><input name="name" class="input !py-2" placeholder="VD: Wan 2.2 i2v" required></div>
                <div><label class="label">Provider (tự nhận từ key)</label><input name="provider" id="model-provider" class="input !py-2" placeholder="auto từ key" readonly required></div>
                <div><label class="label">Model ID</label><input name="model_id" class="input !py-2" placeholder="VD: wan2.2-i2v" required></div>
                <div><label class="label">Khóa (API key)</label><select name="api_key_ref" class="input !py-2" onchange="document.getElementById('model-provider').value = this.value; this.closest('form').querySelector('[name=name]').placeholder = this.options[this.selectedIndex].text || 'VD: Wan 2.2 i2v'">
                    <option value="">— Chọn API key —</option>
                    @php $seen = []; @endphp
                    @foreach($api_keys as $ak)
                        @if(in_array($ak->provider, $seen)) @continue @endif
                        @php $seen[] = $ak->provider; @endphp
                        <option value="{{ $ak->provider }}" @selected(old('api_key_ref') === $ak->provider)>{{ $ak->provider }} · {{ $ak->label ?: ($ak->kind ?: '') }}</option>
                    @endforeach
                    @foreach(['qwen','qwen_edit','wan','dashscope','gemini','deepseek','fal','replicate'] as $p)
                        @if(!in_array($p, $seen))<option value="{{ $p }}" @selected(old('api_key_ref') === $p)>{{ $p }}</option>@endif
                    @endforeach
                </select></div>
                <div><label class="label">Ưu tiên</label><input type="number" name="priority" value="5" min="0" max="100" class="input !py-2"></div>
                <div class="col-span-2 sm:col-span-3"><label class="label">Ghi chú</label><input name="note" class="input !py-2" placeholder="(tùy chọn)"></div>
            </div>
            <button class="btn-brand btn-sm">➕ Thêm model</button>
        </form>
    </div>
    </div>

    <div x-show="tab==='faces'" class="mt-6 space-y-8">
        {{-- Khuôn mặt mẫu --}}
        <div>
            <h3 class="text-sm font-semibold text-ink-900">👩 Khuôn mặt mẫu <span class="text-ink-500">(dùng trong 🪄 Thay Đổi Người Mẫu)</span></h3>
            <p class="mt-1 text-xs text-ink-500">Preset là <b>mô tả khuôn mặt</b> (không cần tải ảnh) — nhập mô tả tiếng Anh rõ ràng để model dựng đúng. Nếu có ảnh kèm, hệ thống dùng ảnh làm tham chiếu (độ giống cao hơn).</p>
            <div class="mt-2 space-y-2">
                @forelse($face_presets as $fp)
                    <div class="rounded-xl border border-cream-200 p-2 text-xs" x-data="{ editf:false }">
                        <div x-show="!editf" class="flex flex-wrap items-center gap-2">
                            @if($fp->image)<img src="{{ $fp->image }}" class="h-10 w-8 rounded bg-ink-900 object-cover">@else<span class="grid h-10 w-8 place-items-center rounded bg-ink-900 text-sm">👩</span>@endif
                            <span class="font-semibold text-ink-900">{{ $fp->name }}</span>
                            <span class="text-ink-500">{{ $fp->ethnicity ?: 'Vietnamese female' }} · Ưu tiên {{ $fp->sort }}</span>
                            <span class="rounded-full px-2 py-0.5 text-[10px] {{ $fp->enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-600' }}">{{ $fp->enabled ? 'Bật' : 'Tắt' }}</span>
                            <div class="ml-auto flex items-center gap-1.5">
                                <button type="button" @click="editf=true" class="btn-outline btn-sm">✏️ Sửa</button>
                                <form method="POST" action="{{ route('studio.face-presets.destroy', $fp) }}" onsubmit="return confirm('Xóa khuôn mặt «{{ $fp->name }}»?')">@csrf @method('DELETE')<button class="btn-outline btn-sm text-red-600">Xóa</button></form>
                            </div>
                        </div>
                        <form x-show="editf" method="POST" action="{{ route('studio.face-presets.update', $fp) }}" enctype="multipart/form-data" class="mt-2 space-y-2 border-t border-cream-200 pt-2">
                            @csrf @method('PUT')
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                <div><label class="label">Tên</label><input name="name" value="{{ $fp->name }}" class="input !py-1" required></div>
                                <div><label class="label">Dân tộc/Phong cách</label><input name="ethnicity" value="{{ $fp->ethnicity }}" class="input !py-1" placeholder="Vietnamese female"></div>
                                <div><label class="label">Ưu tiên</label><input type="number" name="sort" value="{{ $fp->sort }}" min="0" class="input !py-1"></div>
                                <div class="col-span-2"><label class="label">Mô tả khuôn mặt (tiếng Anh)</label><textarea name="description" rows="2" class="input !py-1" required>{{ $fp->description }}</textarea></div>
                                <div class="col-span-2"><label class="label">Ảnh tham chiếu (tuỳ chọn — bỏ trống giữ ảnh cũ)</label><input type="file" name="image" accept="image/*" class="input !py-1"></div>
                                <div class="col-span-2"><label class="flex items-center gap-1 text-ink-700"><input type="checkbox" name="enabled" value="1" @if($fp->enabled) checked @endif class="h-4 w-4 accent-brand-600"> Bật</label></div>
                            </div>
                            <div class="flex gap-2"><button class="btn-brand btn-sm">💾 Lưu</button><button type="button" @click="editf=false" class="btn-ghost btn-sm">Hủy</button></div>
                        </form>
                    </div>
                @empty
                    <p class="text-xs text-ink-500">Chưa có khuôn mặt mẫu — thêm bên dưới.</p>
                @endforelse
            </div>
            <form method="POST" action="{{ route('studio.face-presets.store') }}" enctype="multipart/form-data" class="mt-3 space-y-3 rounded-xl border border-dashed border-cream-300 p-4">
                @csrf
                <h4 class="text-sm font-semibold text-ink-900">➕ Thêm khuôn mặt mẫu</h4>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <div><label class="label">Tên</label><input name="name" class="input !py-2" placeholder="VD: Nhẹ nhàng tự nhiên" required></div>
                    <div><label class="label">Dân tộc/Phong cách</label><input name="ethnicity" class="input !py-2" placeholder="Vietnamese female"></div>
                    <div><label class="label">Ưu tiên</label><input type="number" name="sort" value="0" min="0" class="input !py-2"></div>
                    <div class="col-span-3"><label class="label">Mô tả khuôn mặt (tiếng Anh — model dùng mô tả này để dựng)</label><textarea name="description" rows="2" class="input !py-2" placeholder="VD: young Vietnamese woman, 22, light natural makeup, shoulder-length straight black hair, fair skin, gentle smile" required></textarea></div>
                    <div class="col-span-3"><label class="label">Ảnh tham chiếu (tuỳ chọn)</label><input type="file" name="image" accept="image/*" class="input !py-2"></div>
                </div>
                <button class="btn-brand btn-sm">Thêm khuôn mặt mẫu</button>
            </form>
        </div>

        {{-- Dáng mẫu --}}
        <div>
            <h3 class="text-sm font-semibold text-ink-900">🧍 Dáng mẫu <span class="text-ink-500">(pose — dùng trong 🪄 Thay Đổi Người Mẫu)</span></h3>
            <p class="mt-1 text-xs text-ink-500">Mô tả dáng (tiếng Anh) để model dựng đúng tư thế; ảnh kèm là tuỳ chọn.</p>
            <div class="mt-2 space-y-2">
                @forelse($pose_presets as $pp)
                    <div class="rounded-xl border border-cream-200 p-2 text-xs" x-data="{ editp:false }">
                        <div x-show="!editp" class="flex flex-wrap items-center gap-2">
                            @if($pp->image)<img src="{{ $pp->image }}" class="h-10 w-8 rounded bg-ink-900 object-cover">@else<span class="grid h-10 w-8 place-items-center rounded bg-ink-900 text-sm">🧍</span>@endif
                            <span class="font-semibold text-ink-900">{{ $pp->name }}</span>
                            <span class="text-ink-500">Ưu tiên {{ $pp->sort }}</span>
                            <span class="rounded-full px-2 py-0.5 text-[10px] {{ $pp->enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-600' }}">{{ $pp->enabled ? 'Bật' : 'Tắt' }}</span>
                            <div class="ml-auto flex items-center gap-1.5">
                                <button type="button" @click="editp=true" class="btn-outline btn-sm">✏️ Sửa</button>
                                <form method="POST" action="{{ route('studio.pose-presets.destroy', $pp) }}" onsubmit="return confirm('Xóa dáng «{{ $pp->name }}»?')">@csrf @method('DELETE')<button class="btn-outline btn-sm text-red-600">Xóa</button></form>
                            </div>
                        </div>
                        <form x-show="editp" method="POST" action="{{ route('studio.pose-presets.update', $pp) }}" enctype="multipart/form-data" class="mt-2 space-y-2 border-t border-cream-200 pt-2">
                            @csrf @method('PUT')
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                <div><label class="label">Tên</label><input name="name" value="{{ $pp->name }}" class="input !py-1" required></div>
                                <div><label class="label">Ưu tiên</label><input type="number" name="sort" value="{{ $pp->sort }}" min="0" class="input !py-1"></div>
                                <div class="col-span-2"><label class="label">Mô tả dáng (tiếng Anh)</label><textarea name="description" rows="2" class="input !py-1" required>{{ $pp->description }}</textarea></div>
                                <div class="col-span-2"><label class="label">Ảnh tham chiếu (tuỳ chọn — bỏ trống giữ ảnh cũ)</label><input type="file" name="image" accept="image/*" class="input !py-1"></div>
                                <div class="col-span-2"><label class="flex items-center gap-1 text-ink-700"><input type="checkbox" name="enabled" value="1" @if($pp->enabled) checked @endif class="h-4 w-4 accent-brand-600"> Bật</label></div>
                            </div>
                            <div class="flex gap-2"><button class="btn-brand btn-sm">💾 Lưu</button><button type="button" @click="editp=false" class="btn-ghost btn-sm">Hủy</button></div>
                        </form>
                    </div>
                @empty
                    <p class="text-xs text-ink-500">Chưa có dáng mẫu — thêm bên dưới.</p>
                @endforelse
            </div>
            <form method="POST" action="{{ route('studio.pose-presets.store') }}" enctype="multipart/form-data" class="mt-3 space-y-3 rounded-xl border border-dashed border-cream-300 p-4">
                @csrf
                <h4 class="text-sm font-semibold text-ink-900">➕ Thêm dáng mẫu</h4>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <div><label class="label">Tên</label><input name="name" class="input !py-2" placeholder="VD: Tay chống hông" required></div>
                    <div><label class="label">Ưu tiên</label><input type="number" name="sort" value="0" min="0" class="input !py-2"></div>
                    <div class="col-span-3"><label class="label">Mô tả dáng (tiếng Anh — model dùng mô tả này để dựng tư thế)</label><textarea name="description" rows="2" class="input !py-2" placeholder="VD: standing, one hand on hip, one leg crossed" required></textarea></div>
                    <div class="col-span-3"><label class="label">Ảnh tham chiếu (tuỳ chọn)</label><input type="file" name="image" accept="image/*" class="input !py-2"></div>
                </div>
                <button class="btn-brand btn-sm">Thêm dáng mẫu</button>
            </form>
        </div>
    </div>

    <div x-show="tab==='keys'">
    {{-- ===== API Keys Registry ===== --}}
    <div class="card mt-6 p-6">
        <h2 class="flex items-center justify-between font-display text-base font-semibold text-ink-900">🔑 API Keys Registry <span class="text-xs font-normal text-ink-500">đăng ký key độc lập — model chỉ cần chọn key</span></h2>
        <p class="mt-1 text-xs text-ink-500">⚙️ <b>{{ $api_keys->pluck('provider')->unique()->count() }}</b> nhóm API · <b>{{ $api_keys->count() }}</b> key. Chỉ cần đăng ký <b>provider + key</b> (không phụ thuộc model); <b>Model Registry</b> sẽ chọn key theo <b>vai trò + ưu tiên</b>. Mỗi provider có thể có nhiều key (Qwen: Token-Plan + Pay-As-You-Go…).</p>
        <div class="mt-3 rounded-xl border border-brand-100 bg-brand-900/40 p-4 text-xs text-brand-200">
            <p class="font-semibold">💡 Gợi ý lấy key</p>
            <ul class="mt-1 list-inside list-disc space-y-0.5">
                <li>Gemini: <code class="rounded bg-ink-700 px-1 text-cream-100">aistudio.google.com</code> → API Keys.</li>
                <li>Qwen / Wan (ảnh, chỉnh sửa ảnh & video): <code class="rounded bg-ink-700 px-1 text-cream-100">home.qwencloud.com/api-keys</code> (Token-Plan) hoặc <code class="rounded bg-ink-700 px-1 text-cream-100">dashscope.aliyuncs.com</code> (Pay-As-You-Go).</li>
                <li>Replicate: <code class="rounded bg-ink-700 px-1 text-cream-100">replicate.com/account/api-tokens</code>.</li>
            </ul>
            <p class="mt-2">Khi có key, service tự chuyển từ <b>stub</b> sang gọi API thật. Key trong <b>API Keys Registry</b> ưu tiên hơn env trong <code class="rounded bg-ink-700 px-1 text-cream-100">.env</code>.</p>
        </div>

        @foreach($api_keys->groupBy('provider') as $provider=>$keys)
            <div class="mt-5">
                <h3 class="flex items-center gap-2 text-sm font-semibold text-ink-900">
                    <span>{{ ($providers[$provider]['label'] ?? $provider) }}</span>
                    <span class="badge {{ count($keys) ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">{{ count($keys) ? 'Có key × '.count($keys) : 'Chưa có' }}</span>
                    <button type="button" class="btn-outline btn-sm ml-auto" onclick="document.getElementById('apikey-provider').value='{{ $provider }}'; document.getElementById('apikey-provider').focus(); document.querySelector('#apikey-add').scrollIntoView({behavior:'smooth'})">➕ Thêm key</button>
                </h3>
                <div class="mt-2 space-y-2">
                    @foreach($keys as $k)
                        <div class="rounded-xl border border-cream-200 p-2 text-xs" x-data="{ editk:false }">
                            <div x-show="!editk" class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-ink-900">{{ $k->label }}</span>
                                <span class="text-ink-500">{{ $k->kind ?: $provider }}</span>
                                <span class="rounded-full bg-cream-200 px-2 py-0.5 text-[10px] text-ink-700">Ưu tiên {{ $k->priority }}</span>
                                <span class="rounded-full px-2 py-0.5 text-[10px] {{ $k->enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-600' }}">{{ $k->enabled ? 'Bật' : 'Tắt' }}</span>
                                <span class="ml-auto flex flex-wrap items-center gap-1.5">
                                    <button type="button" @click="editk=true" class="btn-outline btn-sm">✏️ Sửa</button>
                                    <form method="POST" action="{{ route('studio.keys.delete', $k) }}" onsubmit="return confirm('Xóa key «{{ $k->label }}»?')">@csrf @method('DELETE')<button class="btn-outline btn-sm text-red-600">Xóa</button></form>
                                </span>
                            </div>
                            <form x-show="editk" method="POST" action="{{ route('studio.keys.update', $k) }}" class="mt-2 space-y-2 border-t border-cream-200 pt-2">
                                @csrf @method('PUT')
                                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                    <div><label class="label">Provider</label><input name="provider" value="{{ $k->provider }}" class="input !py-1"></div>
                                    <div><label class="label">Nhãn</label><input name="label" value="{{ $k->label }}" class="input !py-1"></div>
                                    <div><label class="label">Loại (kind)</label><input name="kind" value="{{ $k->kind }}" class="input !py-1" placeholder="plan / paygo / ..."></div>
                                    <div class="col-span-2"><label class="label">Key (chỉ nhập nếu đổi)</label><input name="value" class="input !py-1" placeholder="để trống để giữ nguyên key hiện tại"></div>
                                    <div><label class="label">Ưu tiên</label><input type="number" name="priority" value="{{ $k->priority }}" min="0" max="100" class="input !py-1"></div>
                                    <div class="col-span-2"><label class="flex items-center gap-1 text-ink-700"><input type="checkbox" name="enabled" value="1" @if($k->enabled) checked @endif class="h-4 w-4 accent-brand-600"> Bật</label></div>
                                </div>
                                <div class="flex gap-2"><button class="btn-brand btn-sm">💾 Lưu</button><button type="button" @click="editk=false" class="btn-ghost btn-sm">Hủy</button></div>
                            </form>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        {{-- Add API key --}}
        <form id="apikey-add" method="POST" action="{{ route('studio.keys.store') }}" class="mt-6 space-y-3 rounded-xl border border-dashed border-cream-300 p-4">
            @csrf
            <h3 class="text-sm font-semibold text-ink-900">➕ Thêm API key</h3>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <div><label class="label">Provider</label><select id="apikey-provider" name="provider" class="input !py-2" required>
                    <option value="">— Chọn provider —</option>
                    <option value="qwen">Qwen (ảnh/chỉnh sửa)</option><option value="qwen_edit">Qwen Edit (inpaint)</option>
                    <option value="wan">Wan AI (video)</option><option value="dashscope">DashScope (Wan/Qwen)</option>
                    <option value="gemini">Gemini</option><option value="deepseek">DeepSeek</option>
                    <option value="fal">Fal.ai (Flux)</option><option value="replicate">Replicate (Flux)</option><option value="veo">Google Veo</option>
                </select></div>
                <div><label class="label">Nhãn</label><input name="label" class="input !py-2" placeholder="VD: Qwen Token-Plan" required></div>
                <div><label class="label">Key</label><input name="value" class="input !py-2" placeholder="sk-..." required></div>
                <div><label class="label">Loại (kind)</label><input name="kind" class="input !py-2" placeholder="plan / paygo / ..."></div>
                <div><label class="label">Ưu tiên</label><input type="number" name="priority" value="5" min="0" max="100" class="input !py-2"></div>
                <div class="col-span-2 sm:col-span-3"><label class="label">Ghi chú</label><input name="note" class="input !py-2" placeholder="(tùy chọn)"></div>
            </div>
            <button class="btn-brand btn-sm">➕ Thêm API key</button>
        </form>
    </div>
    </div>
</div>
@push('scripts')
<script>
function studioTestModel(btn, id) {
    const row = btn.closest('div'); const el = row ? row.querySelector('.test-result') : null;
    if (el) { el.textContent = 'Đang kiểm tra…'; el.className = 'block w-full text-[10px] text-ink-500'; }
    fetch('/studio/models/' + id + '/test', { headers: { Accept: 'application/json' } })
      .then(r => r.json()).then(d => {
          if (el) {
              const ok = d.key_exists;
              el.className = 'block w-full text-[10px] ' + (ok ? 'text-emerald-400' : 'text-red-400');
              el.textContent = 'Provider: ' + d.provider + ' · Model: ' + d.model_id + (ok ? ' · Key: ' + d.key_prefix : ' · CHƯA CÓ KEY') + (d.base_url ? ' · ' + d.base_url : '');
              el.title = d.note || '';
          }
          if (d.note && d.note.indexOf('OK') !== 0) { alert(d.note); }
      }).catch(e => { if (el) { el.textContent = 'Lỗi kiểm tra: ' + e.message; el.className = 'block w-full text-[10px] text-red-400'; } });
}

// Single "Model tạo ảnh" field <-> the selected provider's model (default config).
(function () {
    const field = document.getElementById('default-image-model');
    if (!field) return;
    const providerSelect = document.querySelector('[name=image_provider]');
    const hiddenMap = {
        flux: document.querySelector('[name=image_model]'),
        wan: document.querySelector('[name=wan_model]'),
        qwen: document.querySelector('[name=qwen_model]'),
        gemini: document.querySelector('[name=gemini_image_model]'),
    };
    const activeHidden = function () { return hiddenMap[providerSelect ? providerSelect.value : 'flux']; };
    function sync() { const h = activeHidden(); if (h) field.value = h.value; }
    if (providerSelect) { providerSelect.addEventListener('change', sync); }
    field.addEventListener('input', function () { const h = activeHidden(); if (h) h.value = field.value; });
    sync();
})();
</script>
@endpush
@endsection