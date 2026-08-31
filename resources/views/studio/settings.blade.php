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
                        <option value="qwen" @selected(old('prompt_provider', $prompt_provider) === 'qwen')>Qwen (qwen3.8-flash)</option>
                    </select>
                </div>
                <div><label class="label">Prompt model</label><input type="text" name="prompt_model" value="{{ old('prompt_model', $prompt_model) }}" class="input !py-2" placeholder="gemini-1.5-flash / qwen3.8-flash"></div>
                <div><label class="label">Model dịch prompt (tiếng Việt)</label><input type="text" name="translate_model" value="{{ old('translate_model', $translate_model) }}" class="input !py-2" placeholder="gemini-3.6-flash-image"></div>
                <div><label class="label">Model Thay Đổi Người Mẫu</label><input type="text" name="swap_model" value="{{ old('swap_model', $swap_model) }}" class="input !py-2" placeholder="qwen-image-edit-plus-2025-12-15"></div>
                <div><label class="label">Model Thuật sỹ ảo</label><input type="text" name="stylist_model" value="{{ old('stylist_model', $stylist_model) }}" class="input !py-2" placeholder="qwen3.8-flash"></div>
                <div><label class="label">Ảnh Flux</label><input type="text" name="image_model" value="{{ old('image_model', $image_model) }}" class="input !py-2" placeholder="flux-1.1-schnell"></div>
                <div><label class="label">Ảnh Wan</label><input type="text" name="wan_model" value="{{ old('wan_model', $wan_model) }}" class="input !py-2" placeholder="wan2.7-image-pro"></div>
                <div><label class="label">Ảnh Qwen</label><input type="text" name="qwen_model" value="{{ old('qwen_model', $qwen_model) }}" class="input !py-2" placeholder="qwen-image-3.0-pro"></div>
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
                <div><label class="label">Ảnh Gemini</label><input type="text" name="gemini_image_model" value="{{ old('gemini_image_model', $gemini_image_model) }}" class="input !py-2" placeholder="gemini-2.5-flash-image"><p class="mt-1 text-xs text-ink-500">Model hợp lệ: <code class="rounded bg-white px-1">gemini-2.5-flash-image</code> · <code class="rounded bg-white px-1">gemini-2.0-flash-preview-image-generation</code> · <code class="rounded bg-white px-1">imagen-4.0-generate-001</code>. Key dùng <code class="rounded bg-white px-1">x-goog-api-key</code> (lấy tại <code class="rounded bg-white px-1">aistudio.google.com/apikey</code>).</p></div>
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
                        <option value="qwen" @selected(old('vision_provider', $vision_provider) === 'qwen')>Qwen (VL)</option>
                    </select>
                </div>
                <div><label class="label">Vision model</label><input type="text" name="vision_model" value="{{ old('vision_model', $vision_model) }}" class="input !py-2" placeholder="gemini-2.5-flash / qwen-vl-max"></div>
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

    <div x-show="tab==='models'">
    {{-- ===== Model Registry manager ===== --}}
    <div class="card mt-6 p-6">
        <h2 class="flex items-center justify-between font-display text-base font-semibold text-ink-900">🤖 Model Registry <span class="text-xs font-normal text-ink-500">quản lý model theo nhóm (image / video / inference)</span></h2>
        <p class="mt-1 text-xs text-ink-500">Chọn nhiều model/nhóm, gán <b>ưu tiên</b> (cao = dùng trước). Khi một model hết hạn mức/API lỗi, hệ thống tự chuyển sang model kế tiếp theo độ ưu tiên. Model hiển thị trong Studio theo nhóm tương ứng (Model video = nhóm video).</p>

        @foreach(['image'=>'Ảnh','video'=>'Video','inference'=>'Suy luận'] as $grp=>$grpLabel)
            <div class="mt-5">
                <h3 class="text-sm font-semibold text-ink-900">{{ $grpLabel }}</h3>
                <div class="mt-2 space-y-2">
                    @forelse($models->where('group', $grp) as $m)
                        @php $id = data_get($m, 'id'); $name = data_get($m, 'name'); $provider = data_get($m, 'provider'); $modelId = data_get($m, 'model_id'); $priority = data_get($m, 'priority', 0); $enabled = (bool) data_get($m, 'enabled', true); @endphp
                        <div class="flex flex-wrap items-center gap-2 rounded-xl border border-cream-200 p-2 text-xs">
                            <span class="font-semibold text-ink-900">{{ $name }}</span>
                            <span class="text-ink-500">{{ $provider }} · {{ $modelId }}</span>
                            <span class="rounded-full bg-cream-200 px-2 py-0.5 text-[10px] text-ink-700">Ưu tiên {{ $priority }}</span>
                            <span class="rounded-full px-2 py-0.5 text-[10px] {{ $enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-600' }}">{{ $enabled ? 'Bật' : 'Tắt' }}</span>
                            @if($id)<button type="button" class="btn-outline btn-sm" onclick="studioTestModel(this, {{ $id }})" :title="'Kiểm tra model này'">🔍 Kiểm tra</button><span class="test-result block w-full text-[10px]"></span>@endif
                            @if($id)
                                <form method="POST" action="{{ route('studio.models.update', $m) }}" class="ml-auto flex flex-wrap items-center gap-1.5">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="group" value="{{ data_get($m,'group') }}"><input type="hidden" name="name" value="{{ $name }}"><input type="hidden" name="provider" value="{{ $provider }}"><input type="hidden" name="model_id" value="{{ $modelId }}">
                                    <label class="flex items-center gap-1 text-ink-700"><input type="checkbox" name="enabled" value="1" @if($enabled) checked @endif class="h-4 w-4 accent-brand-600"> Bật</label>
                                    <input type="number" name="priority" value="{{ $priority }}" min="0" max="100" class="input !w-16 !py-1">
                                    <button class="btn-outline btn-sm">Lưu</button>
                                </form>
                                <form method="POST" action="{{ route('studio.models.delete', $m) }}" onsubmit="return confirm('Xóa model «{{ $name }}»?')">
                                    @csrf @method('DELETE')
                                    <button class="btn-outline btn-sm text-red-600">Xóa</button>
                                </form>
                            @else
                                <span class="ml-auto text-[10px] text-ink-500">(mặc định — thêm lại để chỉnh sửa)</span>
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
                <div><label class="label">Nhóm</label><select name="group" class="input !py-2"><option value="image">Ảnh</option><option value="video">Video</option><option value="inference">Suy luận</option></select></div>
                <div><label class="label">Tên hiển thị</label><input name="name" class="input !py-2" placeholder="VD: Wan 2.2 i2v" required></div>
                <div><label class="label">Provider</label><input name="provider" class="input !py-2" placeholder="wan / qwen / gemini / fal / ..." required></div>
                <div><label class="label">Model ID</label><input name="model_id" class="input !py-2" placeholder="VD: wan2.2-i2v" required></div>
                <div><label class="label">API key ref</label><input name="api_key_ref" class="input !py-2" placeholder="wan / dashscope / ..."></div>
                <div><label class="label">Ưu tiên</label><input type="number" name="priority" value="5" min="0" max="100" class="input !py-2"></div>
                <div class="col-span-2 sm:col-span-3"><label class="label">Ghi chú</label><input name="note" class="input !py-2" placeholder="(tùy chọn)"></div>
            </div>
            <button class="btn-brand btn-sm">➕ Thêm model</button>
        </form>
    </div>
    </div>

    <div x-show="tab==='keys'">
    {{-- ===== API Keys Registry ===== --}}
    <div class="card mt-6 p-6">
        <h2 class="flex items-center justify-between font-display text-base font-semibold text-ink-900">🔑 API Keys Registry <span class="text-xs font-normal text-ink-500">nhiều key/provider · scope theo model/nhóm · ưu tiên · tránh trùng lặp</span></h2>
        <p class="mt-1 text-xs text-ink-500">Mỗi provider có thể đăng ký <b>nhiều key</b> (vd Qwen: Token-Plan + Pay-As-You-Go). <b>Scope</b> giới hạn key chỉ dùng cho model/nhóm cụ thể (để trống = dùng chung, hoặc gõ model_id/nhóm cách dấu phẩy). Key dùng trước = ưu tiên cao nhất.</p>
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
                        <div class="flex flex-wrap items-center gap-2 rounded-xl border border-cream-200 p-2 text-xs">
                            <span class="font-semibold text-ink-900">{{ $k->label }}</span>
                            <span class="text-ink-500">{{ $k->kind ?: $provider }}</span>
                            <span class="rounded-full bg-cream-200 px-2 py-0.5 text-[10px] text-ink-700">Ưu tiên {{ $k->priority }}</span>
                            <span class="text-[10px] text-ink-500">Scope: {{ ($k->scopes && !in_array('*',$k->scopes)) ? implode(', ',$k->scopes) : 'Tất cả' }}</span>
                            <span class="rounded-full px-2 py-0.5 text-[10px] {{ $k->enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-600' }}">{{ $k->enabled ? 'Bật' : 'Tắt' }}</span>
                            <span class="ml-auto flex flex-wrap items-center gap-1.5">
                                <form method="POST" action="{{ route('studio.keys.update', $k) }}" class="ml-auto flex flex-wrap items-center gap-1.5">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="provider" value="{{ $k->provider }}"><input type="hidden" name="label" value="{{ $k->label }}"><input type="hidden" name="kind" value="{{ $k->kind }}">
                                    <label class="flex items-center gap-1 text-ink-700"><input type="checkbox" name="enabled" value="1" @if($k->enabled) checked @endif class="h-4 w-4 accent-brand-600"> Bật</label>
                                    <input type="number" name="priority" value="{{ $k->priority }}" min="0" max="100" class="input !w-16 !py-1">
                                    <button class="btn-outline btn-sm">Lưu</button>
                                </form>
                                <form method="POST" action="{{ route('studio.keys.delete', $k) }}" onsubmit="return confirm('Xóa key «{{ $k->label }}»?')">
                                    @csrf @method('DELETE')
                                    <button class="btn-outline btn-sm text-red-600">Xóa</button>
                                </form>
                            </span>
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
                <div><label class="label">Provider</label><input id="apikey-provider" name="provider" class="input !py-2" placeholder="qwen / wan / gemini / fal / ..." required></div>
                <div><label class="label">Nhãn</label><input name="label" class="input !py-2" placeholder="VD: Qwen Token-Plan" required></div>
                <div><label class="label">Key</label><input name="value" class="input !py-2" placeholder="sk-..." required></div>
                <div><label class="label">Loại (kind)</label><input name="kind" class="input !py-2" placeholder="plan / paygo / ..."></div>
                <div><label class="label">Scope (mặc định *)</label><input name="scopes" class="input !py-2" placeholder="model_id, group hoặc để trống"></div>
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
</script>
@endpush
@endsection