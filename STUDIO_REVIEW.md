# Studio module — Code review audit

**Target:** `/home/anhtuan/DEV/TrillfaShop` · module `studio` (Laravel 11 + Vue 3 AI fashion image-generation studio)
**Method:** quản lý dự án → phân tích → chia nhiệm vụ → điều phối (deepseek-v4-pro) → model trợ lý thực thi.

## Định tuyến provider (đã xác minh từ `~/.dsh/settings.yaml`)

| Nhóm việc | Provider | Model | Kết quả |
|---|---|---|---|
| Tốn nhiều hạn mức (file lớn, phân tích sâu) | `deepseek-official` | `deepseek-v4-pro` | 3/9 task thành công |
| Ít tốn hạn mức (phạm vi nhỏ, tập trung) | `qwen-token-plan` | `deepseek-v4-pro` | 5/7 task thành công |
| Điều phối tự review trực tiếp | — | `deepseek-v4-pro` | 4/4 area |

> Cả hai provider đều phục vụ cùng model `deepseek-v4-pro`; `qwen-token-plan` là endpoint DashScope compatible-mode dùng `QWEN_TOKEN_PLAN_API_KEY` (rẻ hơn).

## Tổng kết

- **16 area** đã được review · **121 findings**
- critical: **1** · high: **18** · medium: **49** · low: **33** · info: **20**
- Lượt 1 (workflow): 6/10 area · 70 findings — 4 task thất bại
- Lượt 2 (workflow, chia nhỏ): 2/6 area · 20 findings — cả 4 task `deepseek-official` thất bại
- Lượt 3 (điều phối tự làm): 4/4 area · 23 findings (high 2 · medium 9 · low 6 · info 6)
- Phần D (kiểm chứng template playbook, đã xác minh từng claim): 1 area · 2 findings medium + 1 claim bị loại là dương tính giả
- Phần E (smoke-test `qwen3.8-max` + `glm-5.2`, đã xác minh): 1 area · 4 findings (medium 2, low 1, info 1) + 1 claim `[high]` bị loại là dương tính giả
- Phần F (kiểm chứng định tuyến per-task model, đã xác minh): 2 area · 2 findings low + **4 claim bị loại** (3 XSS + 1 security) — kèm bảng so sánh thực nghiệm 2 model

## Vấn đề nghiêm trọng nhất (cần xử lý trước)

1. **[high] Path traversal → đọc file cục bộ tùy ý** — `StudioController::buildMaskImage()` (:262-268) nhận `source_url` chỉ validate `string` (không rule `url`, không chặn `..`), và `faceDescription()`/`poseDescription()` (:416-450) cùng pattern, reachable từ `compose`/`composePreview` (:928). Nội dung file được đưa qua vision model rồi trả về trong prompt → tiết lộ nội dung file nội bộ.
2. **[high] API key material gửi xuống trình duyệt** — `settingsData()` (:3857) và settings view (:3925) trả toàn bộ model `StudioApiKey`; model không có `$hidden` và `value` nằm trong `$fillable`. Mã hóa chỉ được gọi thủ công ở 3 chỗ trong controller (:3805, 4122, 4142) nên bất kỳ đường ghi nào khác sẽ lưu **plaintext**.
3. **[high] `config/studio.php` đọc `env()` lúc parse** — sau `config:cache`, thay đổi `.env` (kể cả rotation API key) bị bỏ qua âm thầm.
4. **[medium] Endpoint public `studioImage()` không giới hạn prefix** — fallback `base_path('public_html/'.$path)` cho phép phục vụ mọi file trong document root (`.htaccess`, bundle JS) không cần auth.
5. **[medium] Giải mã ảnh không giới hạn kích thước** — ~10 vị trí `@imagecreatefromstring(file_get_contents(...))` không kiểm tra size trước, dễ OOM worker.

## Ghi chú điều phối (delegation log)

- Lượt 1: 10 agent (5 `deepseek-official` + 5 `qwen-token-plan`). Thành công: `image-ai-service`, `product-ai-service`, `frontend-store` (expensive) và `project-management`, `tryon-style`, `library-assets` (cheap). Thất bại: 2 task `StudioController.php` (phạm vi 4732 dòng quá lớn), `config-settings`, `frontend-components` (21 file).
- Lượt 2: chia nhỏ thành 6 task. Cả 4 task `deepseek-official` thất bại trong khi 2 task `qwen-token-plan` thành công. **(ĐÍNH CHÍNH — xem `DELEGATION_PLAYBOOK.md` mục 0):** nguyên nhân thật KHÔNG phải rate-limit/hết hạn mức. Log session child (`zstd -dc ~/.dsh/sessions/…/0804ddc5…/session.jsonl.zstd`) cho thấy child `turn/end = completed` và đã sinh đủ findings, nhưng model bọc JSON trong hàng rào ```json → engine không trích được `structured` → `agent()` trả `null`. Cả hai provider đều bị; task càng lớn thì output JSON càng dài nên xác suất bị bọc fence càng cao. Đã kiểm chứng: bỏ `schema`, dùng contract text thuần → 0 fail.
- Lượt 3: điều phối tự tiếp quản 4 phạm vi còn lại (3 chunk `StudioController.php` + 5 component Vue lớn) và review trực tiếp bằng read/grep.
- **Khuyến nghị vận hành (đã sửa):** KHÔNG dùng `schema` trong `agent()` — dùng contract text `AREA:/SUMMARY:/FINDING:` rồi parse ở host. Giữ ≤ 6 task/lượt (= `maxConcurrentAgents` trên máy 8 CPU), ≤ 60 KB và ≤ 1.200 dòng mỗi task. Quy trình đầy đủ + template đã kiểm chứng: `DELEGATION_PLAYBOOK.md`.

---

# Phần A — Kết quả workflow lượt 1 (6 area)
## Routing
- Expensive (phân tích sâu): provider `deepseek-official`, model `deepseek-v4-pro`
- Cheap (chia nhiệm vụ nhỏ): provider `qwen-token-plan`, model `deepseek-v4-pro`

Areas reviewed: **6/10** · Findings: **70**

Severity: critical: 1 · high: 13 · medium: 27 · low: 19 · info: 10

## Studio Image AI engine (ImageAIService.php)

The image generation/edit engine has one critical path-traversal flaw that lets user-supplied /storage paths escape the public root and read arbitrary files (with copySample even publishing them), plus high-severity unbounded per-pixel memory loops and a remote-image download with no timeout/size cap. Provider error bodies and API-key prefixes are also leaked, and blocking sleep() polling plus SSRF-prone file_get_contents are notable robustness concerns.

- **[critical]** · Path traversal / arbitrary file read · app/Services/ImageAIService.php · resolveSamplePath/copySample L455-478; resolveImageBinary L1370-1379; imageDataUri L671-701 — User-supplied base/mask image URLs are concatenated into public_path()/storage_path() without stripping '../', so a value like '/storage/../../.env' resolves to the project root and reads arbitrary files; copySample then writes those bytes into a publicly-served /storage/studio/*.jpg URL.
  - Fix: Normalize the path and enforce it stays under the allowed public/storage root (realpath + prefix check), and reject any escaping path.
- **[high]** · Unbounded memory / DoS · app/Services/ImageAIService.php · compositeMaskedEdit L1231-1244; applyTransparentBackground L1413-1424; reconstructRegion L1302-1324; storeRemoteImage L1111-1120 — Per-pixel loops call imagecolorallocate() on every iteration, allocating a palette entry per pixel; multi-megapixel source images can exhaust memory and crash the worker.
  - Fix: Pre-allocate reusable colors, cap dimensions before these loops, or use imagecopy/imagefilter-based operations instead of per-pixel writes.
- **[high]** · Unbounded download / missing timeout · app/Services/ImageAIService.php · storeRemoteImage L1085 — file_get_contents($url) is called with no stream-context timeout and no maximum-size limit, so a large or slow remote image can exhaust memory or stall the request.
  - Fix: Fetch via Http::get()->timeout() with an explicit response-size cap and validate the scheme before downloading.
- **[medium]** · SSRF · app/Services/ImageAIService.php · storeRemoteImage L1083-1139 (called from callDashscope L516-519, callDashscopeAsync L566-571, postMultimodalEdit callers L755/771/785/794/907/926) — The provider-returned image URL is fetched directly with no scheme or host allowlist; a crafted/compromised upstream URL (e.g. file:///etc/passwd or an internal metadata address) would be fetched by the server.
  - Fix: Validate that the URL uses http/https and optionally restrict hosts; fetch with Laravel Http and disable/control redirects.
- **[medium]** · Blocking sleep in request path · app/Services/ImageAIService.php · callDashscopeAsync L554-579 (sleep(4) up to 180s); swapEdit L1042; swapFace L1077 — Synchronous sleep() calls block a PHP-FPM worker for the entire poll/backoff window, enabling worker-pool exhaustion under concurrent requests.
  - Fix: Move async polling and retry backoff to a queued job or background task rather than sleeping inside the request.
- **[medium]** · Information leakage in errors · app/Services/ImageAIService.php · L513, L544, L560, L575, L960 (stored into dashscopeError) surfaced at L70, L115-119 — Raw provider response bodies (truncated to 240 chars) and exception messages are stored in dashscopeError and returned to end users in thrown RuntimeException messages.
  - Fix: Log provider bodies but surface only generic, sanitized messages to users.
- **[low]** · Credential leakage in logs · app/Services/ImageAIService.php · L732, L754, L770, L779, L789, L893, L905, L920, L925, L961, L966 — API key prefixes (substr($key, 0, 8)) are written to info/warning logs, revealing partial credentials.
  - Fix: Log nothing key-derived, or a non-reversible hash/identifier instead of a prefix.
- **[low]** · Silent failure via error suppression · app/Services/ImageAIService.php · @file_get_contents L458/601/1085, @imagecreatefromstring L330/339/1103/1149 etc., @unlink L315, @imagefilter L1109/1406 — Widespread '@' suppresses errors, so decode/read failures silently degrade to null/empty with minimal diagnostic signal.
  - Fix: Use explicit return-value checks and log a warning on failure instead of suppressing.
- **[low]** · Divide-by-zero · app/Services/ImageAIService.php · fitToSourceSize L1165 — The ratio-diff calculation divides by ($sw / $sh); a corrupt/empty source yielding zero width or height causes a division-by-zero.
  - Fix: Guard against zero dimensions before computing the ratio.
- **[info]** · Unvalidated provider output · app/Services/ImageAIService.php · L287, L401, L1131-1136 — Provider image bytes are stored and publicly served after only a 3-byte magic sniff; a non-image response (HTML/SVG/JS) would be saved under /storage/studio/.
  - Fix: Validate decoded bytes with getimagesizefromstring()/finfo and reject non-image content before storage.
- **[info]** · Resolution parameter ignored · app/Services/ImageAIService.php · sizeFor L1485-1497 — The $resolution argument is accepted but never used; only $ratio drives the chosen size, so explicit resolution requests are silently dropped.
  - Fix: Map the resolution through or remove the unused parameter.
- **[info]** · Dead/duplicate code · app/Services/ImageAIService.php · L497, L533 — Duplicate self-assignment '$base = $base = rtrim(...)' is a harmless but misleading leftover.
  - Fix: Remove the redundant '$base ='.

## Studio module — product AI (ProductAIService) & Gemini helper (GeminiService)

Both services have solid provider-ordered fallback, key rotation, and time-budget logic, with API keys correctly masked in logs. The main risks are stored XSS via unescaped user fields concatenated into HTML stub output, an arbitrary local-file read path if the image path is ever user-controlled, prompt injection from user/brand-context fields, and unbounded full-resolution image decoding before downscale. GeminiService additionally lacks rate-limit handling and an overall deadline, silently falling back on non-2xx responses.

- **[high]** · Security / XSS · app/Services/ProductAIService.php · stub() ~1040-1063, stubDescription() ~1152-1161, stubRefine() ~1123 — User-controlled fields (name, category, brand) and AI/offline image-analysis fields (subject, feeling, fabric, color, style) are concatenated directly into generated HTML (description/short_description) with no e()/htmlspecialchars escaping. These descriptions are rendered as HTML (v-html) on the storefront, so a crafted product name becomes stored XSS.
  - Fix: HTML-escape every interpolated value (e() or htmlspecialchars(..., ENT_QUOTES)) in all stub/fallback HTML builders, or sanitize/whitelist HTML tags before returning content to the frontend.
- **[high]** · Security / Path traversal · app/Services/ProductAIService.php · analyzeImage() ~185-211, imageBase64() ~987-1014, imageDataUri() ~1016-1021, imageCacheKey() ~980-985 — analyzeImage/imageBase64/imageDataUri accept an arbitrary $imagePath and only check is_file() before file_get_contents and base64-encoding it for upload to an external AI provider. If the caller (e.g. /studio/image/{path}) passes a user-influenced path, this enables arbitrary local-file read and exfiltration (e.g. .env) to the AI API.
  - Fix: Validate that the path resolves inside the intended storage disk (e.g. realpath + str_starts_with against storage_path) and is an allowed image MIME type before reading; never trust a raw user-supplied path.
- **[medium]** · Security / Prompt injection · app/Services/ProductAIService.php · brandContext() ~60-106; buildRefinePrompt() ~726, 748-776; buildPrompt() ~781-835 — Brand context (from the About page), user refine prompt, name, hint, and tags are interpolated verbatim into LLM prompts without delimiters or sanitization. The About page content acts as a stored prompt-injection vector (editable by content editors) that overrides every generation, and a crafted name/hint can hijack output.
  - Fix: Wrap injected values in explicit delimiters, strip/neutralize instruction-like content, and add a system-level directive to treat all injected data as untrusted data, never instructions.
- **[medium]** · Performance / Memory · app/Services/ProductAIService.php · imageBase64() ~987-1014, offlineAnalysis() ~230-268 — Images are read fully into memory (file_get_contents) and decoded at full resolution (imagecreatefromstring) before any downscale; there is no file-size or pixel-count limit. A very large upload can exhaust PHP memory and crash the request.
  - Fix: Enforce a max file size / dimension check (getimagesize) before decoding, and reject or pre-scale oversized images before full decode.
- **[medium]** · Error handling · app/Services/GeminiService.php · callGemini() ~129-138 — Non-2xx responses (including 429 rate-limit/quota and 401/403 invalid key) are silently ignored; only the catch block logs. There is no rate-limit detection, backoff, or key rotation as ProductAIService has, so failures quietly degrade to the stub with no diagnostics.
  - Fix: Log HTTP status + body on failure and add 429/quota detection with a short backoff, mirroring ProductAIService's key-rotation/rate-limit handling.
- **[low]** · Performance / Timeout · app/Services/GeminiService.php · callQwen() ~66-106 (timeout 90), callGemini() ~118-127 (timeout 60) — Per-call timeouts are fixed (90s/60s) with no overall wall-clock budget; model×key iteration can stack multiple sequential timeouts and exceed the gateway/proxy timeout, causing a 504 in the synchronous creative-direction request.
  - Fix: Add a shared deadline/budget (like ProductAIService::startBudget) and clamp each HTTP timeout to remaining time.
- **[low]** · Correctness · app/Services/ProductAIService.php · imageBase64() ~990-991 — @file_get_contents($path) returning false is cast to '' and base64-encoded, sending an empty image payload to the AI instead of signaling an error; the resulting call is wasted and fails confusingly.
  - Fix: Check the return value of file_get_contents and return null/throw early (letting the caller fall back) rather than encoding an empty string.
- **[low]** · Performance / N+1 · app/Services/ProductAIService.php · brandContext() ~74-93 — brandContext() issues up to 9 separate setting() calls (heading, intro, body, and 3 value pairs). If setting() hits the DB per call, a cache-miss triggers 9 queries; otherwise it is cached in-process and in Laravel cache for 3600s.
  - Fix: Batch settings into a single lookup (or use the Settings repository's all()) and confirm setting() is cached to avoid repeated DB hits.
- **[low]** · Security / Info disclosure · app/Services/ProductAIService.php · record() ~176-181, error bodies ~603/650/697, failureReason() ~1186-1196 — Raw provider error bodies (first 120 chars) are stored in $this->attempts and returned to the admin UI via 'attempts'/'reason'. Keys are masked, but provider diagnostics and possibly request echoes are exposed.
  - Fix: Keep the UI-facing reason generic and log the raw body server-side only, or truncate/sanitize body fragments before returning them.
- **[low]** · Security / Prompt injection · app/Services/GeminiService.php · callQwen() ~60-62, callGemini() ~112-114 — The user-supplied $idea and $injections (preset tags) are interpolated directly into the prompt alongside the system prompt with no delimiting or hardening; a crafted idea can override the creative-direction instructions.
  - Fix: Delimit and clearly label user data in the prompt, and strengthen the system prompt to ignore any instructions found inside the idea/tags.
- **[info]** · Correctness · app/Services/ProductAIService.php · attempt() ~431-447 (default => null) — An unknown provider name in $this->providers is silently skipped (match default returns null), which can mask configuration errors and cause an unexpected full stub fallback.
  - Fix: Log/report unknown providers (or validate product_ai_providers() output) so a misconfigured provider list is visible rather than silently ignored.
- **[info]** · Correctness · app/Services/GeminiService.php · generateCreativeDirector() ~25-41, systemPrompt() ~43-55 — $creativeLevel is not range-validated before being interpolated into the prompt and passed to creativityDirective(); out-of-range values depend entirely on that service to clamp.
  - Fix: Clamp $creativeLevel to a sane range (e.g. 1-10) at the public entry point.

## Studio Pinia store (resources/js/studio/store.js)

The central Pinia store is a large (2915-line) client-side orchestration layer that forwards image URLs, prompts, and entity ids to the backend and mirrors server state locally. It has no direct DOM/XSS sinks (no v-html/innerHTML and no /studio/image/{path} reference), so most security exposure is indirect (unvalidated URL forwarding to backend fetch endpoints, server/AI strings passed to toast, IDOR reliance on the backend). The most concrete defects are error-handling gaps in the shared api() wrapper, unbounded generation counts, and a library pagination off-by-one.

- **[medium]** · Security / SSRF · resources/js/studio/store.js · reimagine (417-430), refgen (438-470), suggestStyle (1961-1978), removeBackground (473-489), inpaint source_url (2014-2047), runSwap (2824) — The store forwards arbitrary image URLs (editSource.url, preview.media_url, layer.image, product URLs) as `image`/`reference_url`/`source_url` to backend endpoints that fetch remote images, with no same-origin/allowlist validation on the client.
  - Fix: Validate/allowlist image URLs (same-origin, data:/blob:) before sending and enforce a server-side SSRF guard (block private/link-local ranges) on all remote-image fetch endpoints.
- **[medium]** · Error handling · resources/js/studio/store.js · api() lines 259-264 — The shared api() wrapper does not check `res.redirected` or the response content-type, unlike deleteGen (934-939) and _libraryFetch (970-974). A session-expiry/login redirect that returns HTML 200 parses to `{}` with `res.ok === true`, so callers (generateImage, reimagine, refgen, compose, inpaint, renderVideo, runSwap) silently treat a failed mutation as success.
  - Fix: Add the same `res.redirected` and `content-type === 'application/json'` checks (and throw on failure) inside api() so every caller inherits them.
- **[medium]** · Performance / missing limits · resources/js/studio/store.js · variantCount/variants (386-396, 420, 441, 511), swapPoseIds loop (2821-2837), compose images (505) — Generation counts are only clamped with `Number(...) || 1` (no upper bound); runSwap issues one request per pose with no cap, and compose only requires >=2 images. A large or malformed count can trigger massive credit consumption and backend load.
  - Fix: Enforce explicit maximums (e.g. variants <= 8, poses <= 12, images <= 4) before sending, with a toast on violation.
- **[medium]** · Security / XSS (indirect) · resources/js/studio/store.js · toast() 274-280; strings from d.name (1157), d.status_label (1186), d.styles/garment_type/background (1972-1974), preset/layer names (877-878, 1397) — User-, AI- and server-controlled strings (project names, workflow status labels, AI suggestion styles/garment/background, preset and layer names) are interpolated into toast messages and `flashMsg`, then forwarded to `window.Alpine.store('toast').show(msg, type)`. If the toast component renders via innerHTML these become stored/reflected XSS.
  - Fix: Verify the Alpine toast renders via textContent (never innerHTML), and sanitize/escape server- and AI-derived strings before passing them to toast().
- **[medium]** · Correctness · resources/js/studio/store.js · loadLibrary/loadMoreLibrary 977-1003 — loadMoreLibrary increments `libraryFilters.page` and then loadLibrary(false) increments it again via `d.current_page || (this.libraryFilters.page + 1)` (line 995). When the backend does not return `current_page`, every other page is skipped.
  - Fix: Drive the next page solely from the server response (current_page/has_more) and remove the double increment.
- **[medium]** · Security / prompt injection · resources/js/studio/store.js · renderVideo 610-616; generateImage/refgen/translate forward raw prompt (380-396, 441, 1960) — renderVideo concatenates the user's image prompt directly into a fixed 'Cinematic fashion catwalk: …' instruction template, and refgen/generateImage forward raw, unframed user text; AI suggestion output also re-enters prompts. A crafted prompt can override the intended system/instruction semantics.
  - Fix: Treat user/AI text as untrusted data, delimit it clearly, and add explicit instruction boundaries/framing on the server before composing model prompts.
- **[medium]** · Security / IDOR & authorization · resources/js/studio/store.js · generation/project id endpoints (550, 558, 934, 1145, 1161-1193, 1865, 2035) — All mutating/reading calls use raw entity ids and payloads with no ownership signal or checks: deleteGen, loadPalette, cancelCompose, loadProject, updateProject, deleteProject, transitionProject, attachGenerationToProject, downloadActive, inpaint. Correctness depends entirely on backend per-user authorization.
  - Fix: Ensure every /studio/generations/{id} and /studio/projects/{id} route enforces ownership server-side; the front-end cannot close this gap and should not assume ids are trusted.
- **[low]** · Error handling · resources/js/studio/store.js · load() 347, loadDefaults 288, processQueue 359-361, loadPalette 559, renameLayer 1394 — Several wrappers swallow errors with empty catch blocks, so load() can leave needsLogin=false with empty generations and renameLayer can fail server-side while the UI shows success.
  - Fix: Surface non-fatal load/rename errors minimally (or log them) instead of silently dropping them.
- **[low]** · Performance · resources/js/studio/store.js · saveLayerLayout/restoreLayerLayout 1903-1920 — saveLayerLayout persists the entire canvasLayers array — including multi-MB data-URL `image` values — to localStorage on every mutation (drag, transform, draw, erase), risking the ~5MB quota (silently swallowed) and jank.
  - Fix: Persist only server URLs + metadata (and re-derive small images from IndexedDB), or debounce/throttle saves.
- **[low]** · Correctness · resources/js/studio/store.js · librarySelectOld 1038-1042 — Bulk 'select old' requires `g.created_ts` to be present; any completed item missing that field is silently excluded from deletion, so eligible old images may be missed.
  - Fix: Confirm the backend always returns `created_ts`, or fall back to `created_at`/`id` ordering so old-item selection is reliable.
- **[info]** · Correctness · resources/js/studio/store.js · translate 1960 — `this.suggestResult.prompt_vi = d.text || d;` stores the whole response object as the Vietnamese prompt when the backend omits a `text` field, which downstream consumers may then render/join as a string.
  - Fix: Normalize to a string (e.g. `String(d.text ?? d ?? '')`) before assignment.
- **[info]** · Performance / error handling · resources/js/studio/store.js · pollGeneration 1984-2013 — pollGeneration polls every 500ms with no maximum attempt count, backoff, or timeout; a generation stuck in 'processing' is polled indefinitely (refreshSwapResults has a 5-minute deadline, but this path does not).
  - Fix: Add a max-attempts/backoff and an overall deadline so a stuck job cannot poll forever.

## Studio Project Management

The project-management feature is generally well-structured with proper authorization checks on most endpoints. However, two critical N+1 query issues exist in the index endpoint (thumbnail accessor and generations_count fallback), the workflow engine's `canTransition` method allows a power-user to bypass the reviewer gate by self-transitioning their own project to 'approved' since the gate only checks for SuperAdmin, not whether the actor owns the project. The old Alpine.js `storeProject` endpoint lacks most validation fields compared to the new ProjectController.

- **[high]** · authorization · app/Services/ProjectWorkflowService.php · line 109 — The reviewer gate at line 109 only checks `! $user->isSuperAdmin()`, so any admin who is also SuperAdmin can approve/archive their own projects. The comment at line 19 says 'Chỉ SUPER ADMIN / DESIGNER role mới được APPROVE', but the code does not enforce that the approver is a different user from the project owner. This allows self-approval bypassing the intended review workflow.
  - Fix: Add a check that the approving user is not the project owner, e.g. `$user->id !== $project->user_id`, or introduce a dedicated reviewer role/relationship.
- **[high]** · performance · app/Http/Controllers/ProjectController.php · line 197 (via Project model accessor at line 101-109) — In `serialize()`, accessing `$project->thumbnail` triggers the accessor which runs `$this->generations()->whereNotNull('media_url')->latest('id')->first()` — a separate query per project. In the `index` method, this is called for every project in the loop, causing an N+1 query pattern.
  - Fix: Eager-load the latest generation with media_url in the index query, e.g. `->with(['generations' => fn($q) => $q->whereNotNull('media_url')->latest('id')->limit(1)])` and modify the thumbnail accessor to check the loaded relation first.
- **[high]** · performance · app/Http/Controllers/ProjectController.php · line 196 — The `generations_count` fallback at line 196 calls `$project->generations()->count()` when `generations_count` is not set (e.g. when a project is loaded fresh without `withCount`). This triggers an N+1 since the `serialize` method is also used in `show` and after `fresh()` calls in `transition` and `update`.
  - Fix: Use `$project->generations_count ?? null` and only fall back to `count()` when the relation is already loaded; otherwise, accept that the count may be absent in non-list contexts.
- **[medium]** · mass-assignment · app/Models/Project.php · lines 42-57 — `user_id` is listed in `$fillable`. While the controller always uses `$request->user()->projects()->create(...)` to auto-set user_id, the `update()` call at line 105 passes validated data directly to `$project->update($data)`. If a future code path passes untrusted data containing `user_id` to `update()` or `fill()`, project ownership could be hijacked.
  - Fix: Remove `user_id` from `$fillable` since it should only be set at creation time via the relationship. It is already guarded by the `BelongsTo` relationship assignment.
- **[medium]** · validation · app/Http/Controllers/ProjectController.php · line 133 — The `transition` endpoint validates `to` as `['required', 'string']` but does not restrict it to the known STATUSES constants. While the `canTransition` method rejects invalid states, a malformed request could reach the workflow engine with arbitrary strings. The validation is only a string check — not an `in:` rule.
  - Fix: Add `Rule::in(Project::STATUSES)` to the `to` validation to catch invalid states at the validation layer before reaching the domain service.
- **[medium]** · correctness · app/Services/ProjectWorkflowService.php · lines 146-159 — The `transition()` method calls `$project->fill($updates)->save()` and then conditionally saves again on line 158 for the note. If `$note` is not empty, the project is saved twice in the same transaction-less call. This is inefficient and could lead to inconsistent state if the second save fails while the first succeeded.
  - Fix: Accumulate all updates (including the settings history) and call `save()` once, or wrap both saves in a `DB::transaction()`.
- **[medium]** · correctness · app/Http/Controllers/ProjectController.php · lines 58-81 — The `store()` method silently ignores the `status` field — it always sets `Project::STATUS_DRAFT` on line 72. The `$fillable` array includes `status`, but the validation rules do not include it, and the controller hardcodes it. This is a deliberate design choice but creates an inconsistency with the model's `$fillable` suggesting `status` can be set. If another controller or test creates a project with `fill()` including `status`, the model would accept it.
  - Fix: Either remove `status` from `$fillable` (preferred, since status transitions are managed by the workflow service) or add `status` validation in `store()` with an `in:` rule limited to draft.
- **[medium]** · inconsistency · app/Http/Controllers/StudioController.php · lines 49-63 — There is a duplicate `storeProject` method in `StudioController` (route: `POST /studio/projects`) that accepts only `name` and `base_concept`, while the `ProjectController::store` (route: `POST /studio/projects/new`) accepts 8 validated fields. The old Alpine.js `index.blade.php` at line 1478 still calls the limited endpoint. This creates two different project-creation paths with different validation rules.
  - Fix: Deprecate `StudioController::storeProject` and redirect its route to `ProjectController::store`, or at minimum add the same validation fields to maintain consistency.
- **[low]** · error-handling · app/Http/Controllers/ProjectController.php · line 139 — The `transition` endpoint catches only `DomainException`. If the workflow service throws any other exception (e.g., a database error during `save()`), it will propagate as a 500 error without a user-friendly JSON response.
  - Fix: Add a catch-all `Throwable` handler that returns a generic 500 JSON response, or use Laravel's exception handler to render JSON for API routes.
- **[low]** · race-condition · app/Http/Controllers/ProjectController.php · lines 118-119 — The `destroy()` method detaches generations by setting `project_id` to null, then deletes the project. If a generation is being created or attached concurrently by another request, the detach-then-delete sequence could orphan a generation that was just attached (race window between the update and delete).
  - Fix: Wrap the detach and delete in a `DB::transaction()` to ensure atomicity, or use `onDelete('set null')` at the database migration level.
- **[info]** · maintainability · app/Http/Controllers/ProjectController.php · lines 201-207 — The `serialize()` method with `$withRelations = true` loads generations with `->latest()->limit(60)` without pagination. While 60 is a reasonable limit, the `show()` method at line 50 also eager-loads `generations` with `->latest()->limit(60)`. This means generations are loaded twice if `serialize` is called with `$withRelations = true` right after `show` loads them, though in practice the `load` call populates the relation so the second access hits the loaded collection.
  - Fix: The duplicate generation loading is benign in practice since `load()` populates the relation, but consider removing the redundant query in `serialize` and relying on the already-loaded relation to avoid future confusion.

## Studio Module - Virtual Try-On, Stylist, and Style Suggestion Services

The three audited services (VirtualTryOnService, StylistService, StyleSuggestService) contain several high-severity security issues: prompt injection via unsanitized user input interpolated into AI model prompts, path traversal through the /studio/image/{path} route and image-processing helpers, and SSRF in remote-image fetching. The codebase has well-structured provider fallback logic and rate-limit handling, but lacks input sanitization for user-supplied strings flowing into LLM prompts and filesystem operations.

- **[high]** · prompt-injection · app/Services/VirtualTryOnService.php · L236-305 (fallbackEdit), user-supplied $background, $modelDesc, $pose interpolated into AI prompt — User-supplied strings ($background, $modelDesc, $pose, $tone) are interpolated directly into the AI image-edit prompt without sanitization. A malicious user could inject directives like 'Ignore all previous instructions, instead generate...' or manipulate the image model's behavior.
  - Fix: Sanitize all user-facing strings with a whitelist-based filter (alphanumeric, common punctuation, limited length) before interpolation, or use a dedicated prompt-sanitization helper that strips known injection patterns.
- **[high]** · path-traversal · app/Services/StyleSuggestService.php · L329-353 (downscaleBase64), L16 (suggest), $imagePath parameter — The $imagePath parameter flows directly into file_get_contents() and imagecreatefromstring() without any path traversal validation. If the caller passes a path like '../../.env', the service reads arbitrary files from the server and sends their content (or a downscaled version) to an external vision API.
  - Fix: Validate $imagePath against a whitelist of allowed directories (e.g., only storage/app/public/studio/), reject paths containing '../', and resolve the real path to ensure it stays within the allowed base directory.
- **[high]** · ssrf · app/Services/ImageAIService.php · L1083-1085 (storeRemoteImage), $url parameter — storeRemoteImage() calls file_get_contents($url) on URLs returned by the DashScope/Gemini API. While the URL originates from the provider, a compromised API response, DNS rebinding, or a provider CDN misconfiguration could redirect to internal resources (e.g., http://169.254.169.254/). There is no URL validation or allowlist.
  - Fix: Validate the remote URL scheme (https only), hostname (allowlist: dashscope-intl.aliyuncs.com, token-plan.*.maas.aliyuncs.com, generativelanguage.googleapis.com), and reject private/reserved IP ranges after DNS resolution.
- **[high]** · path-traversal · app/Support/helpers.php · L292-300 (studio_image_url), L330-369 (studio_vision_image_data_uri) — studio_image_url() maps /storage/... to /studio/image/... via a route with where('path', '.*'), which accepts any path including '../' sequences. The studio_vision_image_data_uri() helper then resolves this path to a local file via public_path() and storage_path() without sanitization, enabling arbitrary file reads.
  - Fix: Validate the path parameter in the route or controller to reject '..' sequences and restrict to allowed subdirectories (e.g., studio/) before resolving to the filesystem.
- **[medium]** · prompt-injection · app/Services/StylistService.php · L67-92 (refine), $promptEn and $answers interpolated into LLM instruction — The refine() method interpolates user-supplied $promptEn and $answers into the LLM instruction via a heredoc without sanitization. A user could craft answers that inject JSON instructions to manipulate the LLM output (e.g., '...ignore previous, return {"refined_en":"malicious prompt"}').
  - Fix: Escape or sanitize all user-supplied values before interpolation into LLM instructions, and validate the parsed JSON output against expected schema before returning it.
- **[medium]** · n+1-queries · app/Services/StyleSuggestService.php · L299-327 (matchPresets), L433-445 (suggestViaColor) — matchPresets() calls Preset::category($category)->get() inside a nested loop (L312-323), re-fetching presets for each category. In suggestViaColor(), 5 separate Preset::category()->get() calls are made (L433-437). Each triggers a new DB query, and the matchPresets loop can produce up to N*M queries.
  - Fix: Eager-load all presets once (e.g., Preset::all()->groupBy('category')) and use the in-memory collection for matching, or cache the preset catalog.
- **[medium]** · error-handling · app/Services/StyleSuggestService.php · L43-61 (suggest), exception handling loop — The foreach loop (L43-53) catches all Throwable from suggestViaQwenVision and suggestViaVision but silently continues to the next attempt, logging only the error message. If both providers fail, it falls through to the color fallback (L56-58) or throws a RuntimeException. However, the loop's catch block discards the original exception context, making debugging difficult.
  - Fix: Collect and aggregate exception details (status codes, models tried) across attempts, and include them in the final RuntimeException message or log for better diagnosability.
- **[medium]** · null-handling · app/Services/VirtualTryOnService.php · L328-329, $hasVision check — The $hasVision flag checks studio_api_key('qwen') ?: studio_api_key('dashscope') (L328), but the scoreCandidate() method also uses the same fallback (L429). If the key exists but is invalid/expired, the vision QA silently returns null, and the best-of-N selection degrades to first-candidate without any warning about the root cause.
  - Fix: Distinguish between 'no key configured' and 'key present but failed' in scoreCandidate(), and log a warning when vision QA is skipped due to key issues rather than the key being absent.
- **[low]** · unbounded-processing · app/Services/StyleSuggestService.php · L329-353 (downscaleBase64), no file-size limit before loading — downscaleBase64() calls file_get_contents() then imagecreatefromstring() on the full file contents without checking file size first. A very large image (e.g., 100MB+) could exhaust memory before the downscale logic kicks in.
  - Fix: Check filesize() before reading the file and reject images larger than a configurable limit (e.g., 20MB) before loading them into memory.
- **[low]** · api-key-leakage · app/Services/StylistService.php · L239 (chat method), logger warning with key prefix — The logger warning at L239 and L241 includes the response body (substr up to 160 chars) and exception message, which could leak API key fragments if the provider echoes them in error responses. While the key itself is not explicitly logged, truncated error bodies may contain sensitive information.
  - Fix: Strip known API key patterns (e.g., /sk-[a-zA-Z0-9]+/) from logged error bodies before persisting, or log only status codes and sanitized error codes.
- **[low]** · race-condition · app/Services/VirtualTryOnService.php · L19-22, L239-240, instance properties $lastModel and $calls — $lastModel and $calls are instance properties on VirtualTryOnService, which is typically resolved from the Laravel container as a singleton or scoped binding. If multiple concurrent requests share the same service instance, these properties can be overwritten, producing incorrect telemetry.
  - Fix: Ensure VirtualTryOnService is not bound as a singleton; use request-scoped binding or reset properties at the start of each public method call (calls is already reset at L240, but lastModel is set at L241 — ensure it is always reset too).
- **[info]** · provider-fallback · app/Services/StylistService.php · L214-268 (chat), Qwen → Gemini fallback — The chat() method has a solid multi-provider fallback (Qwen text models → Gemini → null), but the Qwen loop breaks on model-not-found (L237) without trying remaining models in the same key's list. The outer foreach over models continues correctly, but the inner foreach over keys includes a break that could skip valid keys for other models.
  - Fix: The break at L242 (catch) and L237 (model not found) should use 'continue 2' instead of 'break' to skip only the current model, not all remaining keys for that model, ensuring all combinations are tried.

## Studio Library — generated media, uploads, cleanup, and orphan-file scanning

The StudioLibraryService provides a reasonably well-structured library with traversal protections in normalizeUploadRel, urlToRelative, and safeUnlink. However, the orphan scan (referencedPaths) is unscoped — it iterates ALL generations across ALL users via cursor() without filtering, making it both a performance hazard at scale and a potential security concern (any authenticated user can trigger orphan scan/cleanup that considers cross-user files). The cleanup scope parameter is also unvalidated against a whitelist, and several N+1 and unbounded-scan issues exist in the counts and referencedPaths methods.

- **[high]** · authorization · app/Services/StudioLibraryService.php · Lines 387–420 (referencedPaths) and line 344 (scanOrphanFiles comment: 'admin mode — all users') — referencedPaths() iterates ALL Generation records across ALL users via `Generation::query()->cursor()` with no user-scoping. This is used by scanOrphanFiles(), which calls referencedPaths() to build the 'referenced' set, then deletes any file not in that set. Any authenticated user (the route is only `auth`-protected, not admin) can trigger an orphan scan via `/studio/library/scan` and then cleanup via `/studio/library/cleanup?scope=orphans`, which would mark files belonging to other users as orphans and delete them.
  - Fix: Scope referencedPaths() to the current user (or restrict the orphan scan/cleanup routes to admin-only). At minimum, the orphan scan should only consider files in the current user's generations, or the routes should require the `admin` middleware.
- **[high]** · validation · app/Services/StudioLibraryService.php · Lines 182–190 (cleanup method) — The `cleanup()` method passes the `$scope` string directly into a `match()` expression with only three valid cases ('orphans', 'junk', 'old') and a default that returns zero. However, the `libraryCleanup` controller method (line 3640) does not whitelist $scope — it accepts any string from user input. While the match default prevents code execution on unknown scopes, it silently returns `{deleted: 0, freed_bytes: 0}` with `ok: true`, giving the frontend a false sense of success.
  - Fix: Whitelist `$scope` in the controller before passing to the service, or validate it against `['orphans','junk','old']` and return a 422 error for unknown values.
- **[medium]** · performance · app/Services/StudioLibraryService.php · Lines 387–420 (referencedPaths) and lines 92, 149, 301 (all callers of scanOrphanFiles) — referencedPaths() uses `cursor()` to stream ALL Generation rows from the database (unfiltered, all users), then iterates StudioAsset, FacePreset, and PosePreset similarly. This is called from `counts()` (line 92), `orphanCategory()` (line 149), and `cleanupOrphans()` (line 301) — meaning a single `/studio/library/data` request triggers a full table scan of 4 tables. At scale (thousands of generations), this will be slow and memory-intensive. The `counts()` method at line 92 only needs the count, yet it calls `scanOrphanFiles()` which builds a full file listing with filesizes.
  - Fix: For counts, compute orphan count with a lightweight query (e.g., count files on disk minus count of referenced paths from DB) rather than a full scan. For referencedPaths(), add a `user_id` filter and consider pagination or caching the referenced set.
- **[medium]** · correctness · app/Services/StudioLibraryService.php · Lines 75–95 (counts method), specifically lines 77–92 — The `counts()` method clones the base query 5 times (lines 79–84) and issues 6 separate COUNT queries, plus it calls `scanOrphanFiles()` which does a full disk scan and 4 table cursor() iterations. Additionally, `junk_count` is computed client-side as `failed + cancelled` (line 86), but `old_count` issues yet another query (lines 87–91). This is 7+ queries and a full filesystem scan just to render the library stats bar.
  - Fix: Use a single grouped COUNT query (`->selectRaw('status, count(*)')->groupBy('status')`) for status counts, and compute old_count independently only when needed (or cache stats). Move orphan counting to a separate, throttled admin endpoint.
- **[medium]** · correctness · app/Services/StudioLibraryService.php · Lines 249–272 (deleteUploadedFiles method) — deleteUploadedFiles() computes `$referenced` once at line 251 before the loop, but if any file in `$rels` is deleted mid-loop, the filesystem state changes while the `$referenced` set stays stale. This is a TOCTOU race: between the `is_file()` check at line 261 and the `safeUnlink()` at line 265, another process could replace the file with a symlink. However, `safeUnlink` does re-verify the path starts with the storage root, mitigating the worst case.
  - Fix: The current protections are adequate for the threat model. Consider adding `clearstatcache()` before `is_file()` if the loop processes many files to avoid stale stat cache results.
- **[medium]** · security · app/Services/StudioLibraryService.php · Lines 288–297 (normalizeUploadRel) — normalizeUploadRel() strips `storage/` prefixes and `..` is not explicitly handled. While `str_starts_with($rel, 'studio/ref/')` at line 292 would block `../../etc/passwd` (since it wouldn't start with `studio/ref/`), a path like `studio/ref/../../../etc/passwd` would pass the prefix check, then `Storage::disk('public')->path($rel)` would resolve it relative to the storage root — but `safeUnlink` at line 486 has a secondary check that the resolved absolute path still starts with the storage root, preventing traversal. This is defense-in-depth, but the `normalizeUploadRel` function itself should explicitly reject paths containing `..`.
  - Fix: Add `if (str_contains($rel, '..')) { return ''; }` to normalizeUploadRel for explicit path-traversal rejection before the prefix check.
- **[medium]** · correctness · app/Services/StudioLibraryService.php · Lines 49–51 (list method, LIKE search) — The `$q` search term is concatenated directly into `LIKE '%...%'` without escaping `%` or `_` wildcards. If a user searches for `%` or `_`, the query will match all rows (denial-of-service via wildcard injection). While not SQL injection (the parameter is a string, not a pattern-escape issue), it can cause unexpected full-table scans.
  - Fix: Escape `%` and `_` characters in the search term: `$q = str_replace(['%', '_'], ['\%', '\_'], $q);` before using in LIKE.
- **[low]** · performance · app/Services/StudioLibraryService.php · Lines 196–244 (uploadedFiles method) — uploadedFiles() calls `glob()` on both `studio/ref` and `studio/assets` directories without any limit on the number of files returned. On a disk with thousands of uploaded files, `glob()` can return a very large array, and the subsequent `getimagesize()` call (line 216) on every file will be slow. There is no pagination or limit.
  - Fix: Add pagination or a hard limit (e.g., 500 files) to the uploaded files listing. Consider caching `getimagesize` results in the database to avoid repeated IO.
- **[low]** · security · app/Models/StudioAsset.php · Line 9 ($fillable array) — StudioAsset's `$fillable` includes `path` but there is no `$guarded` or `$fillable` restriction on the `id` or timestamps. While not directly exploitable via the library service (which only reads StudioAsset), if any controller allows mass-assignment on StudioAsset, an attacker could set arbitrary `path` values.
  - Fix: Review all controllers that create/update StudioAsset to ensure they validate the `path` field against the expected directory structure. Consider adding path validation in a mutator or observer.
- **[low]** · correctness · resources/js/studio/LibraryApp.vue · Line 56 (confirmTimer auto-cancel) — The confirmation dialog auto-cancels after 5 seconds (line 56: `setTimeout(() => { confirmAction.value = ''; }, 5000)`). If the user is reading the confirmation text slowly or is distracted, the dialog disappears and they must re-click — but more importantly, the timer is not cleared when the component unmounts, creating a potential memory leak and a stale callback that could fire after navigation.
  - Fix: Clear the timer in `onBeforeUnmount` via `clearTimeout(confirmTimer)`. Also consider a longer timeout (e.g., 15s) or removing the auto-cancel entirely for destructive actions.
- **[info]** · architecture · app/Services/StudioLibraryService.php · Lines 317–322 (deleteGenerationFiles) — deleteGenerationFiles() only deletes the `media_url` file, leaving `base_image` and `mask_image` files untouched (as documented in the comment). However, if a generation's base_image points to a one-off uploaded file that no other generation references, that file will only be cleaned up by the orphan scan — which, as noted above, is unscoped and potentially dangerous. This creates a gap where disk space can accumulate until an admin runs the orphan cleanup.
  - Fix: Consider adding a reference-counting mechanism or a `used_by_count` column on uploaded files, or schedule orphan cleanup as a regular background job scoped per-user.

---

# Phần B — Kết quả workflow lượt 2 (2 area · qwen-token-plan)
## Studio Module: Config, Models, Migrations & Wiring  _(subagent · qwen-token-plan/deepseek-v4-pro)_

The Studio module has a well-designed cascading config system (DB → env → config) and API keys are encrypted at the application layer before persistence. However, the `StudioApiKey` model lacks an Eloquent cast or observer for automatic encryption, relying entirely on manual `Crypt::encryptString()` calls in the controller — a single code path that writes plaintext if bypassed. Several migrations are missing indexes on foreign-key or high-cardinality columns, and the config file has a subtle precedence issue where `env()` is evaluated at config-cache time rather than at runtime.

- **[high]** security · `app/Models/StudioApiKey.php:10-12` — `value` (API key) is in `$fillable` with no cast/mutator for encryption. Encryption is applied manually in the controller (`StudioController.php:3805, 4122, 4142, 4365`), so a mass-assignment `StudioApiKey::create($data)` from any other path stores the key in plaintext.
  - Fix: add a `setValueAttribute()` mutator or `saving` listener calling `Crypt::encryptString()` so encryption is enforced at the model layer.
- **[high]** correctness · `config/studio.php:12-15, 24-30, 42-44, 136-141` — every value is read with `env()` at file-parse time. After `php artisan config:cache`, later `.env` changes are silently ignored until the cache is cleared; especially dangerous for the API keys at 136-141 where rotation needs a cache clear.
  - Fix: resolve `env()` at runtime behind a `config()` fallback, or document that config caching requires a cache clear after any env change.
- **[medium]** correctness · `app/Support/helpers.php:430` — `studio_api_key()` falls back to `config('studio.'.$key)`; an empty-string default resolves to `null` correctly, but a `false`/`0` value would also collapse to `null` via PHP falsy coalescing. Undocumented semantics.
  - Fix: use an explicit `!empty()` check or document the fallback contract.
- **[medium]** performance · `app/Support/helpers.php:377-389` — `studio_api_keys_for()` loads ALL enabled keys for a provider then filters in memory by `scopes`/`model_id`/`group` on every lookup.
  - Fix: add a MySQL multi-valued index on the `scopes` JSON column and push the filter into the query, or accept it while key count stays < ~50.
- **[medium]** security · `config/studio.php:136-148` — nine API keys are flat `env()` reads. DB-stored keys are encrypted (`set_setting('api_'.$service.'_key', Crypt::encryptString($value))`) and correctly decrypted in `studio_api_key()` (408-414), but the env/config path is always plaintext.
  - Fix: store all keys exclusively in the encrypted DB table for production, or document that env-stored keys are plaintext.
- **[medium]** correctness · `database/migrations/2026_08_31_000100_create_studio_assets_table.php:11-18` — `studio_assets` has no `user_id`, no index on `type`/`sort`, and no unique constraint on `path`. If assets are meant to be per-user this is an authorization gap.
  - Fix: add `user_id` FK if user-scoped; at minimum add an index on `(type, sort)` for the common listing query.
- **[low]** correctness · `app/Modules/Studio/StudioModuleServiceProvider.php:13` — module config merges into a separate `studio_module` namespace while the app config is `studio`, with no documented relationship between the two.
  - Fix: merge into the `studio` namespace or document the split.
- **[low]** correctness · `app/Modules/Studio/StudioBridge.php:11-21` — the bridge is a singleton but resolves dependencies via `app()` on every call, contradicting the singleton pattern.
  - Fix: resolve in the constructor or use the bound singletons directly.
- **[low]** security · `app/Models/StudioOutfitSetting.php:9` — `$fillable` includes `user_id`, allowing mass-assignment of the owner. The DB `unique()` FK prevents collisions but the mass-assignment risk remains.
  - Fix: drop `user_id` from `$fillable` and set it from `auth()->id()`, or force it in a `creating` event.
- **[info]** correctness · `database/migrations/2026_01_01_000050_create_studio_models_table.php:21` — composite index `['group','enabled','priority']` is well designed for the query pattern, but `priority` is a signed int so negative values could break ordering.
  - Fix: use `unsignedInteger` and/or validate non-negative in the model.

## Studio Vue Components & Settings  _(subagent · qwen-token-plan/deepseek-v4-pro)_

Audited 13 smaller Studio Vue components and the SettingsApp. No critical XSS (`v-html`) found in the scoped files. The most significant issue is API key exposure via the Settings UI: key values are sent in plaintext POST bodies without masking. Several components also silently swallow API fetch errors, and the `delAsset` URL on SwapCard is vulnerable to id manipulation (though the backend DELETE endpoint is the real defense).

- **[high]** secret-exposure · `resources/js/studio/SettingsApp.vue:10,38` — API key values are POSTed as plaintext `key_value` to `/studio/settings/save`, and the input is `type="text"` (not `password`), so the key is visible on screen and in devtools.
  - Fix: use `type="password"` with a show/hide toggle; never return raw key values from the backend — accept on save and return a masked hint (`sk-...a1b2`).
- **[medium]** error-handling · `resources/js/studio/components/SwapCard.vue:46-49` — four `onMounted` fetches use empty `catch(e){}`; failures render empty lists with no feedback, making the feature look broken.
  - Fix: set a reactive error state and surface a toast; log to console.
- **[medium]** error-handling · `resources/js/studio/SettingsApp.vue:6,10-12` — `load()` and `save()` silently swallow errors; the user gets no feedback on failure.
  - Fix: toast on load failure and show a user-facing message when save fails.
- **[medium]** authorization · `resources/js/studio/SettingsApp.vue:1-72` — no client-side guard before rendering the API-key management UI.
  - Fix: add a `v-if="store.user?.is_admin"` guard and verify the backend rejects unauthorized `/studio/settings/*`.
- **[medium]** id-manipulation · `resources/js/studio/components/SwapCard.vue:81` — `delAsset` builds `'/studio/assets/' + a.id` by concatenation; a manipulated `a.id` could inject path segments.
  - Fix: validate `a.id` as a positive integer, or at minimum `encodeURIComponent(String(a.id))`.
- **[medium]** null-handling · `resources/js/studio/components/SuggestCard.vue:21-24` — `applyPrompt()` reads `store.suggestResult?.prompt_vi` directly, bypassing the `r` computed that already null-guards with `|| {}`.
  - Fix: use the `r` computed consistently, or add an explicit null guard.
- **[low]** state-desync · `resources/js/studio/components/SwapCard.vue:44-46` — `onMounted` auto-selects the first model by mutating `store.swapModelIds` inside a component, which can conflict with other components or persisted state.
  - Fix: move auto-selection into the store (`loadDefaults` or a dedicated action).
- **[low]** performance · `resources/js/studio/components/SwapCard.vue:8` — a `setInterval` ticks every second for the elapsed timer even when no swap is running.
  - Fix: start the interval only when `store.swapStartTs > 0` and clear it on completion.
- **[info]** ux · `resources/js/studio/SettingsApp.vue:10,38` — one `form` ref is reused for both key and model creation, so `save()` always sends all fields regardless of the active tab.
  - Fix: split into separate form refs, or send only the tab-relevant fields.
- **[info]** error-handling · `resources/js/studio/components/SourceLibraryPicker.vue:66-68` — a failed `loadRefs()` leaves the list silently stale and the catch shows a generic toast without the error message.
  - Fix: include the error message in the toast.

---

# Phần C — Điều phối tự review trực tiếp (4 area)
## StudioController.php — AI generation/edit endpoints  _(điều phối review trực tiếp · lines 1–1600)_

The generation/edit endpoints are consistently validated with `$request->validate()` and are owner-scoped where a `Generation` is involved, and outbound `Http` calls always set a timeout. The dominant risk is an inconsistent path-resolution discipline: the public image endpoints guard against traversal, but the internal resolvers that consume user-supplied URLs do not.

- **[high]** security/path-traversal · `app/Http/Controllers/StudioController.php:262-268` (called from `regionEdit`/`inpaint` at :230) — `buildMaskImage()` resolves `public_path(ltrim(parse_url($sourceUrl, PHP_URL_PATH),'/'))` from `$data['source_url']`, which is validated ONLY as `['nullable','string','max:2048']` (:210) — no `url` rule and no `..` guard. A value like `../../.env` escapes the document root; the file is then read by GD and uploaded to the image provider. Contrast `studioImage()` (:1741) which does guard.
  - Fix: validate `source_url` as `url`, reject `..`, require a `studio/` prefix, or route it through the guarded resolver used by `assetDestroy()` (:1722-1723).
- **[medium]** security/path-traversal · `:416-430` `faceDescription()` and `:436-450` `poseDescription()` — same un-guarded `public_path($path)` pattern, reachable from `assembleComposePrompt()` at :928 with `$data['images'][1]` (user-supplied; `ComposeCard.vue:151` posts `images: urls`). The file is sent to a vision model and the description is embedded into the prompt that `composePreview` echoes back → AI-mediated local file disclosure.
  - Fix: apply the same `..`/charset/`studio/`-prefix guard as `studioImage()`, and confine to known asset directories.
- **[medium]** security/info-disclosure · `:1739-1758` `studioImage()` — public and unauthenticated. The `..` + `^[a-zA-Z0-9/_.\-]+$` guard is correct, but the final fallback `base_path('public_html/'.$path)` is NOT confined to `studio/`, so any document-root file matching the charset (`.htaccess`, `build/assets/*.js`) is served with `Cache-Control: immutable`.
  - Fix: require a `studio/` (or explicit allow-list) prefix before the web-root fallback.
- **[medium]** correctness · `:3687-3695` `download()` and `:3724-3726` `palette()` — build `storage_path('app/public/'.$path)` from `$generation->media_url` with no `..`/charset guard, unlike `studioImage()`. Owner-scoped via `abort_unless`, so impact is limited, but a poisoned `media_url` row yields traversal.
  - Fix: reuse one shared guarded path resolver for every URL→file conversion.
- **[low]** performance/DoS · `:1767-1810` `studioImageThumb()` — generates and caches a thumbnail per `(size, path)` on demand, unauthenticated, with no cap on total cached thumbnails; path enumeration can fill disk and burn GD memory. The size whitelist (:1789-1792) and >4096px bail-out (:1783) are good existing mitigations.
  - Fix: cap the thumbnail cache directory (size/count) and add a cleanup pass to `StudioLibraryService`.
- **[low]** security · `:3594` — `where('prompt','like','%'.$request->input('q').'%')`. Parameterized (no SQL injection), but user-supplied `%`/`_` wildcards can force expensive full scans.
  - Fix: `addcslashes($q, '%_\\')` before interpolating into the LIKE pattern.
- **[info]** maintainability · the controller is 4732 lines / ~90 endpoints mixing HTTP handling, GD image manipulation, and provider HTTP clients.
  - Fix: extract `pngBytes`, `buildMaskImage`, `extractPalette`, `downscaleSource` and the provider calls into services (as `ImageAIService` already does).

## StudioController.php — inpaint/region/compose/swap + provider calls  _(điều phối review trực tiếp · lines 1600–3200)_

File-write paths are uniformly UUID-named under `studio/` and the delete paths that were checked apply a proper containment check. The weak points are unbounded in-memory image decoding and silently suppressed GD failures.

- **[medium]** performance · `:1803, 1947, 1989, 2037, 2457, 2662, 2810, 2862, 3108, 3742` — `@imagecreatefromstring((string) file_get_contents($file))` is called with no dimension or byte pre-check (only the thumbnail path has the >4096px bail-out). A large source image can exhaust PHP memory and OOM the worker.
  - Fix: call `getimagesize()` first and reject/downscale above a threshold; apply the existing `downscaleSource()` (:224) consistently.
- **[medium]** error-handling · `:268, 270, 424, 444` (and the other `@`-suppressed GD sites) — suppressed calls return `false`/`null` and callers return `null` silently, so a corrupt or unreadable image surfaces as "no mask" / "no description" with no log entry.
  - Fix: log a warning with the path and failure reason before returning null.
- **[low]** security/SSRF · `:3037` `@file_get_contents($upscaledUrl)` and `:3079` `$enhancedUrl` — provider-returned URLs are fetched with no allow-list, no scheme check and no size cap; a spoofed/compromised provider response could point at an internal address or an arbitrarily large file.
  - Fix: require `https://` + an allow-list of known provider hosts, and stream with a byte cap (or reuse `ImageAIService::storeRemoteImage`).
- **[info]** good practice · `:1722-1723` `assetDestroy()` verifies the resolved absolute path starts with `storage_path('app/public/')` before `@unlink()`, and `:1669-1672` `refImageDelete()` neutralizes traversal with `basename()`; `:1679-1681` `uploadRef` validates `image|max:8192` and stores under a UUID name.
  - Fix: promote the `assetDestroy()` containment check into the shared resolver used by every URL→file path.
- **[info]** good practice · every outbound provider call sets an explicit timeout (`:2633, 2921, 2976, 3026, 3071, 3334, 3359, 3424-3551`).

## StudioController.php — settings, models, API keys, library, presets  _(điều phối review trực tiếp · lines 3200–4732)_

Config resolution and the model registry are coherent, and generation-level endpoints are correctly owner-scoped (`abort_unless($generation->user_id === auth()->id(), 403)` at :194, 988, 1295, 1377, 1397, 3681, 3703, 3718). The serious issue is API-key material reaching the browser.

- **[high]** security/secret-exposure · `:3857` (`settingsData()` → `GET /studio/settings/data`) and `:3925` (settings view) return `\App\Models\StudioApiKey::…->get()` — full Eloquent models. `app/Models/StudioApiKey.php` declares `$fillable` including `value` but NO `$hidden` and no cast, so the stored key material plus `scopes`/`note` is serialized to the client. Because encryption is applied manually only at `:3805, 4122, 4142`, any row written by another path (seeder, import, older code) leaks a PLAINTEXT key.
  - Fix: add `protected $hidden = ['value'];` to the model, select only the needed columns, and expose a masked hint (`substr` of the decrypted tail) instead of the value.
- **[medium]** security/mass-assignment · `app/Models/StudioApiKey.php:10` — `value` in `$fillable` means any `create($request->all())`/`update($request->all())` path writes unencrypted key material and bypasses the controller's manual `Crypt::encryptString()`.
  - Fix: remove `value` from `$fillable` and set it explicitly through an encrypting mutator.
- **[medium]** correctness · `:3805, 4122, 4142` — encryption is an ad-hoc controller concern rather than a model invariant, so "`value` is always ciphertext" is unenforced and easy to break in a new code path.
  - Fix: move encryption into a `setValueAttribute()` mutator plus a decrypting accessor, and delete the duplicated controller calls.
- **[low]** maintainability · three near-duplicate provider-status maps exist: `settingsData()`'s `$providers` (:3844-3854), `providerStatus()` (:3932+), and another inline array at :4336+. They have already drifted (`settingsData` omits `hint` and includes `deepseek`).
  - Fix: keep a single `providerStatus()` source and derive the other views from it.
- **[info]** security · `testApi`/`testModel` (`:3400, 3424, 3433, 3450, 3504, 3551`) call providers with live keys and relay responses to the browser.
  - Fix: return only `{ok, status, message}` and strip provider error bodies, which can echo request headers/key fragments.

## Large Vue components — StudioApp, ConceptCard, ComposeCard, RefImageCard, InpaintCard  _(điều phối review trực tiếp)_

No exploitable XSS was found in these five components: the single `v-html` sink is fed by a hardcoded local constant, and CSRF is handled correctly. The recurring weakness is error handling — empty catch blocks and `res.json()` without an `res.ok` check hide real failures.

- **[medium]** security/XSS-pattern · `resources/js/studio/components/RefImageCard.vue:223` — `v-html="a.svg"` inside an `<svg>`. Currently safe because `anglePresets` is a hardcoded local constant (:112), but it is the only `v-html` in scope and becomes a live XSS sink the moment that array is sourced from the API — which `faces`/`poses` already are (:42-50).
  - Fix: render `<path :d="a.d">` (data-bound attribute) instead of raw HTML injection.
- **[medium]** error-handling · `ConceptCard.vue:91, 114, 117, 147, 198, 220`; `RefImageCard.vue:45, 50`; `ComposeCard.vue:158, 177, 194` — empty `catch (e) {}` blocks swallow every failure. The UI silently falls back to defaults/empty lists with no user feedback and no console trace.
  - Fix: route failures through `store.toast(...)` and `console.warn(e)`; keep the graceful fallback but make it observable.
- **[low]** correctness · `ConceptCard.vue:93-114` `loadDraft()` — restores a localStorage draft into the Pinia store with no type or range validation: `draft.prompt` is assigned without a string check (:98-99) and `creativeLevel`/`texture`/`variantCount`/`body*` are assigned unclamped (:100-110). A tampered or stale draft pushes out-of-range values into the store (the backend does validate, so impact is UI-level).
  - Fix: validate types and clamp to the same ranges the inputs use before assigning.
- **[low]** correctness · `ConceptCard.vue:239-242` and `ComposeCard.vue:148-153` — `res.json()` is called without checking `res.ok`, so a 4xx/5xx HTML error page is parsed, throws, and is swallowed by the catch, hiding the real status.
  - Fix: `if (!res.ok) throw new Error(res.status)` before parsing, and surface the status.
- **[info]** good practice · CSRF is read from the meta tag and sent as `X-CSRF-TOKEN` (`ComposeCard.vue:42,148-153`; `ConceptCard.vue:312`), and `StudioApp.vue:22-23,265` uses a hidden `_token` for the logout form. `InpaintCard.vue` produced zero direct fetch/localStorage/`v-html` risk hits — it delegates entirely to the store.
- **[info]** cross-layer note · `ComposeCard.vue:151` posts `images: urls` (client-controlled), which the backend feeds into the un-guarded `faceDescription()` resolver. The fix belongs server-side.

---

# Phần D — Addendum ĐÃ XÁC MINH (kiểm chứng template của playbook)

Nguồn: 1 task `ctrl-1` (StudioController.php, offset 1 limit 950) chạy bằng đúng template trong `DELEGATION_PLAYBOOK.md`, provider `qwen-token-plan` / `deepseek-v4-pro` → 0 fail, parse 7/7 finding. Điều phối đã đọc lại source để xác minh từng claim trước khi ghi vào đây.

## Finding mới ĐÃ xác nhận

- **[medium]** accounting/credit · `app/Http/Controllers/StudioController.php:3186-3203` + `:3298-3302` — `swapModel()` tạo Generation với `credits_cost => 1` rồi dispatch `SwapModelJob`; `executeSwapFromGeneration()` sau đó nâng `credits_cost` lên `max(1, $svc->calls())` (2-3). **Không hề trừ `credits_balance`.** Trong toàn controller, `decrement('credits_balance', …)` chỉ xuất hiện đúng một lần tại `:1501` (trong `queueGeneration()`), nên mọi endpoint tạo Generation ngoài đường đó đều không trừ credit.
  - Fix: cho `swapModel()` đi qua `queueGeneration()`, hoặc thêm `decrement` tường minh ngay khi tạo Generation.
  - Hiệu chỉnh mức độ: agent báo `[high]`. Điều phối hạ xuống **medium** vì comment tại `:1499` nêu rõ đây là công cụ nội bộ — "never hard-block on credits. Track usage (balance may go negative)" → credit là số liệu theo dõi, không phải cơ chế chặn. Ảnh hưởng thật: `credits_left` và `studio_usage()` báo sai.

- **[medium]** concurrency/double-refund · `:1409-1422` `reconcileStuckCredits()` — SELECT các generation `status='processing'` quá 30 phút, rồi với mỗi dòng `update(status='failed')` + `increment('credits_balance', $g->credits_cost)`; **không transaction, không `lockForUpdate`, không update có điều kiện**. Hàm này được gọi ở đầu mỗi `queueGeneration()` (`:1500`), nên hai request đồng thời có thể cùng chọn một generation và **hoàn credit hai lần**.
  - Fix: chỉ hoàn tiền khi update thực sự đổi trạng thái, ví dụ kiểm tra số dòng ảnh hưởng của `where('status','processing')->update([...]) === 1`, hoặc bọc `DB::transaction()` + `lockForUpdate()`.
  - Hiệu chỉnh cơ chế: agent mô tả "decrement không có transaction → double-spend" là **sai** — `decrement()` sinh SQL `SET credits_balance = credits_balance - ?` vốn atomic. Lỗi thật nằm ở nhánh refund.

## Claim bị LOẠI (dương tính giả)

- ~~`[medium] csrf-mass-assignment` · `storeProject()` :56~~ — `$request->validate(['name'=>…,'base_concept'=>…])` (`:51-54`) chỉ trả về đúng hai key đó, còn `user_id` do relation `projects()` gán. Không có bề mặt mass-assignment. (Riêng `StudioOutfitSetting::$fillable` chứa `user_id` vẫn là vấn đề hợp lệ — đã ghi ở Phần B.)

## Bài học quy trình (đã đưa vào playbook mục 6 và 8)

- Agent **vượt phạm vi được giao**: task chỉ cấp `offset 1, limit 950` nhưng finding trích dẫn dòng 1497, 3186, 4409, 4543 → số dòng của subagent không đáng tin cậy tuyệt đối.
- Trong 3 claim high/medium MỚI được spot-check: 1 đúng nhưng phóng đại mức độ, 1 sai cơ chế (lỗi thật ở chỗ khác), 1 dương tính giả → **0/3 đúng nguyên văn**. Bước điều phối đọc lại source là **bắt buộc**.

---

# Phần E — StylistDataController + StylistDataApp (smoke-test 2 model mới, ĐÃ XÁC MINH)

Phạm vi: `app/Http/Controllers/StylistDataController.php` (173 dòng) + `resources/js/studio/StylistDataApp.vue` (15 dòng) — chưa từng review ở Phần A-D. Chạy song song 2 model trên **cùng scope, cùng prompt** để kiểm chứng `qwen3.8-max` và `glm-5.2`; cả hai đều trả 8 finding. Điều phối đã đọc lại source để xác minh.

- **[medium]** secret-leakage · `app/Http/Controllers/StylistDataController.php:110,121,159,170` — mỗi nhánh `catch (\Throwable $e)` trả nguyên văn exception cho client: `response()->json(['ok' => false, 'message' => 'Lỗi lưu: '.$e->getMessage()], 500)`. Có thể lộ câu SQL, tên bảng/cột, đường dẫn framework. **Cả 2 model phát hiện độc lập.**
  - Fix: `report($e)` rồi trả message chung (vd "Lỗi hệ thống"); chỉ lộ chi tiết khi `app()->hasDebugModeEnabled()`.
- **[medium]** correctness/race · `:88-104` và `:138-153` — check-then-act không atomic: `exists()` kiểm tra trùng `slug`/`key` rồi mới `save()`, nên hai request đồng thời vẫn tạo được bản ghi trùng. Kèm `$m->sort_order = (int) StylistGarmentType::max('sort_order') + 1` (`:97`, và tương đương ở `:147`) cũng race → trùng `sort_order`.
  - Fix: thêm unique index trên `slug`/`key` rồi bắt lỗi constraint trả 422; tính `sort_order` trong `DB::transaction()`.
- **[low]** performance · `:17-22` — `catalog()` gọi `$catalog->ensureTables()` và được **mọi** method CRUD gọi (vd `:116`), nên `Schema::hasTable` + seed check chạy trên mỗi request.
  - Fix: gate `ensureTables()` sau một cờ static/cache để chạy tối đa một lần mỗi process.
- **[info]** phạm vi · `resources/js/studio/StylistDataApp.vue` (15 dòng) là shell tĩnh mount `StylistDataManager`, không có binding động → không có rủi ro client-side riêng; logic thật nằm ở `StylistDataManager.vue` (đã review ở Phần B).

## Claim bị LOẠI (dương tính giả) — kèm bằng chứng

- ~~`[high] authz` · `StylistDataController.php:14-27` — "không có middleware nào; `page()` phục vụ trang admin cho bất kỳ khách nào"~~ — **SAI.** `routes/web.php:128` (`/stylist-data`) và các route mutating `:192-196` nằm TRONG group `Route::middleware(['auth','admin','nostore'])` mở tại `:94`. Group public mở tại `:202` chỉ chứa 2 endpoint read-only `:206-207` (`data`, `presets`). Việc thiếu guard ở tầng controller chỉ là defense-in-depth, không phải lỗ hổng.
- Ghi chú quy trình: `glm-5.2` **tự đọc `routes/web.php`** và mô tả đúng bản chất; `qwen3.8-max` khẳng định sai. Đây là căn cứ thực nghiệm cho việc định tuyến model ở `DELEGATION_PLAYBOOK.md` mục 1.

---

# Phần F — Blade views + StylistCatalog (kiểm chứng định tuyến per-task model, ĐÃ XÁC MINH)

Chạy bằng template mới (per-task `model`): `app/Services/StylistCatalog.php` (287 dòng, model `qwen3.8-max`) và 6 blade view nhỏ (~22 KB, model `glm-5.2`). Cả 2 task thành công, parse đủ finding kèm fix.

> **Lưu ý phạm vi — CHƯA phủ hết:** lớp blade tổng cộng **243 KB**; đợt này mới review ~22 KB. Còn **CHƯA review**: `index.blade.php` (1.626 dòng/140 KB), `settings.blade.php` (738 dòng/68 KB — liên quan trực tiếp tới issue top-5 số 2 về rò rỉ API key), `library.blade.php` (142 dòng/12 KB).

## Finding ĐÃ xác nhận

- **[low]** correctness/race · `app/Services/StylistCatalog.php:252-259` — `savePreset()` dùng check-then-act: `StylistPreset::where('prompt', $prompt)->exists()` (`:252`) rồi mới `create()` (`:255`), kèm `'sort_order' => (int) StylistPreset::max('sort_order') + 1` (`:259`). Cả hai không atomic → request đồng thời tạo được preset trùng và trùng `sort_order`. Dedup quét trên cột TEXT `prompt` không index; `catch` (`:261-263`) nuốt mọi lỗi ("bỏ qua lỗi lưu preset").
  - Fix: thêm unique index (vd cột hash của `prompt`, hoặc (`name`,`type`)); tính `sort_order` trong `DB::transaction()`; log lỗi thay vì nuốt.
- **[low]** performance · `:21-30` (gọi từ `StylistDataController.php:17-22`) — `ensureTables()` chạy **3 lệnh `Schema::hasTable`** mỗi request vì mọi method CRUD đều đi qua `catalog()`. Đây là đánh đổi **có chủ đích** cho shared hosting không chạy được `php artisan migrate` (docblock `:18-19` nêu rõ), và DDL `Schema::create` **chỉ** chạy khi thiếu bảng (`:30`).
  - Fix (tùy chọn): gate sau cờ `static`/cache để còn 0 query sau lần đầu.

## Claim bị LOẠI — 4/4 claim XSS/security đều sai

- ~~`[high] XSS` · `presets.blade.php:76-77` "render raw, không escape"~~ — **SAI**: cả hai dòng dùng `{{ $p->prompt_injection }}` / `{{ $p->note }}`; Blade `{{ }}` escape mặc định qua `e()`/`htmlspecialchars`, an toàn kể cả trong `<textarea>`.
- ~~`[medium] XSS` · `vue.blade.php:32` `@json($studioBoot)`~~ — **SAI**: `@json` của Laravel mặc định dùng `JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT` nên `< > & ' "` đều bị hex-escape → không breakout được `</script>`.
- ~~`[medium] XSS` · `api.blade.php:22-24` "`$service` nội suy thô vào JS"~~ — **SAI**: `$service` đến từ **mảng hardcode** `['gemini','fal','replicate','wan','veo','qwen','qwen_edit','dashscope']` tại `StudioController.php:4352`, không phải input người dùng.
- ~~`[medium] security` · `StylistCatalog.php:21-70` "DDL chạy mỗi request, unauthenticated"~~ — **SAI cả hai vế**: `Schema::create` chỉ chạy khi `! $hasAll` (`:30`), và các route liên quan nằm trong group `[auth, admin, nostore]`.
- **Bằng chốt:** grep `{!!` trên toàn bộ `resources/views/studio/` → **0 kết quả**. Lớp blade không có echo nào không escape → cả lớp XSS-blade gần như bị loại.

## Kết luận thực nghiệm về 2 model (căn cứ định tuyến)

| Model | Chi phí đo được | Điểm mạnh quan sát được | Điểm yếu quan sát được |
|---|---|---|---|
| `qwen3.8-max` | 4.817 in · 3.546 out · 2 step | rẻ nhất; bám format 4-field chuẩn; nhiều finding | khẳng định SAI về authz ("page() public"), SAI về "DDL mỗi request"; bỏ qua docblock ngay trên hàm |
| `glm-5.2` | 23.076 in · 1.658 out · 6 step | TỰ đọc `routes/web.php` để xác minh middleware → mô tả authz đúng | 3/3 claim XSS đều sai; output 3-field (thiếu ` \| ` trước `fix:`) |

→ **Không model nào đáng tin tuyệt đối**: mỗi model sai ở đúng chỗ model kia đúng. Bước điều phối xác minh 100% finding `high`/`critical` là **bắt buộc bất kể model**. Chỉ dùng `glm-5.2` khi task thật sự cần suy luận liên file, vì đắt ~4,8× input.
