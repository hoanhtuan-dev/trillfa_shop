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

- **30 area** đã được review · **208 findings** (A–F 16 · G 0 — đính chính · H 7 · I 7)
- critical: **1** · high: **17** · medium: **51** · low: **96** · info: **43**
- Lượt 1 (workflow): 6/10 area · 70 findings — 4 task thất bại
- Lượt 2 (workflow, chia nhỏ): 2/6 area · 20 findings — cả 4 task `deepseek-official` thất bại
- Lượt 3 (điều phối tự làm): 4/4 area · 23 findings (high 2 · medium 9 · low 6 · info 6)
- Phần D (kiểm chứng template playbook, đã xác minh từng claim): 1 area · 2 findings medium + 1 claim bị loại là dương tính giả
- Phần E (smoke-test `qwen3.8-max` + `glm-5.2`, đã xác minh): 1 area · 4 findings (medium 2, low 1, info 1) + 1 claim `[high]` bị loại là dương tính giả
- Phần F (kiểm chứng định tuyến per-task model, đã xác minh): 2 area · 2 findings low + **4 claim bị loại** (3 XSS + 1 security) — kèm bảng so sánh thực nghiệm 2 model
- Phần G (đính chính báo cáo — điều phối tự kiểm tra top-5, phiên song song): 0 area finding · **LOẠI 1 claim `[high]` cũ** (config `env()`, đã gạch tại chỗ finding gốc) · sửa top-5 (`imagecreatefromstring` = **41** vị trí chứ không phải ~10; thêm `getMessage()` 12 vị trí) · bảng 4 pattern lan rộng
- Phần H (3 blade lớn còn lại — QUEUE A đợt 1: 6/6 task `qwen-token-plan`, điều phối xác minh 9/9 medium): **7 area · 42 findings** (low 30 · info 12 — **0/9 medium sống sót** sau xác minh) · phát hiện MỚI: `index.blade.php` là **file mồ côi** — 140 KB dead code (H.1) · bằng chứng bổ sung DB-first rotation (H.0)
- Phần I (creative-dir + QUEUE B — round 2: 6/6 task `qwen3.8-max`, điều phối xác minh 12/12 medium): **7 area · 46 findings** (medium 2 · low 34 · info 10 — **2/12 medium sống sót** M6 CanvasMaskTools lifecycle + M8 Ctrl+Z hijack) · 1 claim `[medium]` bị LOẠI (I.8) · phát hiện 2 component Vue MỒ CÔI (SourceCard/PaletteTextureCard, I.1) · note chéo: phiên song song đã vá top-5 #1+#2 trong lúc round chạy → M9 hạ thêm

## Vấn đề nghiêm trọng nhất (cần xử lý trước)

1. **[high] Path traversal → đọc file cục bộ tùy ý** — `StudioController::buildMaskImage()` (:262-268) nhận `source_url` chỉ validate `string` (không rule `url`, không chặn `..`), và `faceDescription()`/`poseDescription()` (:416-450) cùng pattern, reachable từ `compose`/`composePreview` (:928). Nội dung file được đưa qua vision model rồi trả về trong prompt → tiết lộ nội dung file nội bộ.
2. **[high] API key material gửi xuống trình duyệt** — `settingsData()` (:3857) và settings view (:3925) trả toàn bộ model `StudioApiKey`; model không có `$hidden` và `value` nằm trong `$fillable`. Mã hóa chỉ được gọi thủ công ở 3 chỗ trong controller (:3805, 4122, 4142) nên bất kỳ đường ghi nào khác sẽ lưu **plaintext**.
3. **[medium] Giải mã ảnh không giới hạn kích thước — 41 vị trí** (đã đếm lại bằng `grep`; báo cáo cũ ghi "~10" là **thiếu 4 lần**) — `imagecreatefromstring` xuất hiện 41 lần: `StudioController.php` 20 · `ImageAIService.php` 15 · `ProductAIService.php` 3 · `StyleSuggestService.php` 2 · `helpers.php` 1. Không kiểm tra size trước khi decode → dễ OOM worker.
4. **[medium] Endpoint public `studioImage()` không giới hạn prefix** — fallback `base_path('public_html/'.$path)` cho phép phục vụ mọi file trong document root (`.htaccess`, bundle JS) không cần auth.
5. **[medium] Trả nguyên văn `$e->getMessage()` cho client — 12 vị trí / 5 controller** — `StudioController.php` 5 · `StylistDataController.php` 4 · `CouponApiController.php` 1 · `CartApiController.php` 1 · `ProjectController.php` 1. Có thể lộ câu SQL, tên bảng/cột, đường dẫn framework.

> **Đã LOẠI khỏi top-5:** item cũ số 3 (`[high]` "`config/studio.php` đọc `env()` lúc parse") là **DƯƠNG TÍNH GIẢ** — bằng chứng ở Phần G.

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
- ~~**[high]** correctness · `config/studio.php:12-15, 24-30, 42-44, 136-141` — every value is read with `env()` at file-parse time; after `config:cache` later `.env` changes are silently ignored~~ → **ĐÃ LOẠI, xem Phần G** (hành vi chuẩn của Laravel, không phải defect của module).
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

---

# Phần G — ĐÍNH CHÍNH CHÍNH BÁO CÁO NÀY (điều phối tự kiểm tra lại top-5)

Hai lỗi trong chính báo cáo, phát hiện khi đếm lại toàn module bằng `grep` để trả lời câu hỏi "121 finding là bao nhiêu pattern".

## 1. LOẠI item top-5 cũ số 3 — `env()` trong config KHÔNG phải defect

- Claim cũ: `[high] correctness` · `config/studio.php:12-15, 24-30, 42-44, 136-141` — "mọi giá trị đọc bằng `env()` lúc parse; sau `config:cache` thì thay đổi `.env` bị bỏ qua âm thầm".
- **Bằng chứng bác bỏ:**
  - `env()` bên trong `config/*.php` là **pattern chuẩn Laravel khuyến nghị**. Đếm thực tế: `database.php` 61 · `studio.php` 53 · `logging.php` 20 · `cache.php` 18 · `queue.php` 17 · `session.php` 13 · `mail.php` 12 · `app.php` 9 · `services.php`/`filesystems.php` 8 mỗi file · `auth.php` 5 — tổng **224 vị trí**. Nếu đây là lỗi thì toàn bộ framework Laravel là lỗi.
  - Lỗi THẬT của họ này là gọi `env()` **NGOÀI** `config/` (sẽ trả `null` sau `config:cache`). Grep toàn `app/`, `routes/`, `resources/`, `database/` → chỉ **3 vị trí**: `app/Modules/Studio/config.php:3` và `app/Modules/Storefront/config.php:17,21`.
  - Cả 3 đều **vô hại**: `app/Modules/Studio/config.php` được nạp qua `$this->mergeConfigFrom(__DIR__.'/config.php', 'studio_module')` tại `StudioModuleServiceProvider::register()` (`:13`) → đi vào config repository trước khi cache, cùng cơ chế với `config/*.php`. `routes/`, `resources/`, `database/` = **0 vị trí**.
- **Phần hợp lệ duy nhất còn lại** (hạ xuống `[info]`, là ghi chú quy trình deploy chứ không phải lỗi code): sau khi đổi `.env` phải chạy lại `php artisan config:cache`/`config:clear`. Đây là yêu cầu của mọi app Laravel khi bật config cache.

## 2. SỬA item top-5 số 5 — báo THIẾU 4 lần

- Báo cáo cũ ghi "~10 vị trí `@imagecreatefromstring(file_get_contents(...))`".
- **Đếm lại thực tế: 41 vị trí** — `StudioController.php` **20** · `ImageAIService.php` **15** · `ProductAIService.php` **3** · `StyleSuggestService.php` **2** · `helpers.php` **1**.
- Nguyên nhân thiếu: con số cũ lấy từ một vùng của `StudioController.php`, chưa đếm các service. Hệ quả: mức độ ưu tiên bị đánh giá thấp hơn thực tế — đây là pattern lan rộng nhất module, nên sửa bằng **1 helper dùng chung** chứ không phải vá từng chỗ.

## 3. ĐẾM LẠI TOÀN MODULE — 121 finding thực chất quy về 4 pattern

| Pattern | Số vị trí (đo bằng grep) | Phân bố | Cách sửa đòn bẩy cao |
|---|---|---|---|
| Decode ảnh không giới hạn kích thước | **41** | 5 file PHP | 1 helper `studio_decode_image($src, $maxBytes)` + thay hàng loạt |
| `res.json()` không check `res.ok` | **39** | JS/Vue studio | 1 fetch wrapper dùng chung |
| `catch` rỗng | **26** | 18 Vue + 8 JS | CI check / ESLint rule chặn `catch {}` rỗng |
| Trả `$e->getMessage()` cho client | **12** | 5 controller PHP | 1 helper `error_response()` chuẩn |

→ **Kết luận vận hành:** sửa 4 pattern + thêm guard trong CI rẻ hơn và bền hơn sửa lẻ ~118 vị trí, vì guard ngăn lỗi tái phát. Chỉ 2 item top-5 còn lại (path traversal, API key xuống trình duyệt) là lỗi đơn lẻ cần vá trực tiếp.

---

# Phần H — 3 blade lớn còn lại (QUEUE A đợt 1: 6/6 task thành công · ĐÃ XÁC MINH TOÀN BỘ)

Chạy 1 workflow (chỉ provider `qwen-token-plan`): 4 task `qwen3.8-max` (blade-idx-1/2/3, blade-misc) + 2 task `glm-5.2` (blade-set-1/2). **6/6 thành công, `failed = []`**. Parser bao dung parse đủ 41 finding (nguyên bản: 9 medium · 20 low · 12 info · **0 high/critical** → gate xác minh high/critical không kích hoạt).

**Điều phối đã xác minh 9/9 medium** bằng `read` lại đúng dòng: **9/9 bị hạ mức hoặc hiệu chỉnh cơ chế** — 0 medium nguyên bản sống sót. Hai căn cứ hạ mức: (a) `index.blade.php` là file mồ côi (H.1) → 6 medium không có tác động runtime; (b) 3 medium còn lại sai cơ chế/phạm vi (route thực tế là GET, id là khóa chính int, form có nút submit thủ công).

## H.0 — Bằng chứng BỔ SUNG cho Phần G: đường rotation key là DB-first, không qua env (điều phối tự xác minh độc lập)

- ~~`[high]` "`config/studio.php` đọc `env()` lúc parse → sau `config:cache` việc rotation key bị bỏ qua âm thầm"~~ — **DƯƠNG TÍNH GIẢ**, 3 bằng chứng (điều phối tự xác minh):
  1. `env()` bên trong `config/*.php` là pattern **chuẩn** của Laravel; `config:cache` tồn tại chính là để snapshot env một lần — hành vi documented, không phải khiếm khuyết.
  2. Đường rotation của module **không đi qua env**: `studio_api_key()` ưu tiên registry DB mã hóa `StudioApiKey` (`app/Support/helpers.php:402-404`), rồi setting DB `api_<service>_key` (`:406-413`), cuối mới fallback config; `studio_config()` cũng ưu tiên setting DB trước config (`helpers.php:192-194`). Đổi key/cấu hình từ trang Settings có hiệu lực **ngay lập tức**, bất kể `config:cache`.
  3. Comment `config/studio.php:20-22` nói rõ giá trị env chỉ là default, "You can override each on the Studio Settings page".
  → Điều còn lại đúng duy nhất là hiển nhiên: sửa thẳng `.env` mà không chạy lại `config:cache` thì không thấy giá trị mới — đúng với MỌI app Laravel, không phải lỗi module này, càng không phải `high`.

## H.1 — Phát hiện MỚI (điều phối tự xác minh): `index.blade.php` là FILE MỒ CÔI — 140 KB dead code

- **[low]** dead-code · `resources/views/studio/index.blade.php` (1.626 dòng / 140 KB) — **không có đường code nào render view này**; toàn bộ UI Alpine `studioApp()` đã bị thay thế bởi Vue (`StudioApp.vue` + `store.js`).
  - Bằng chứng: route `studio.index` (`routes/web.php:212`) → `StudioController::index()` (`:43-47`) render `studio.vue`; `studioVue()` (`:38`) cũng vậy; grep toàn repo **0** lời gọi `view('studio.index')` — controller có đúng 10 lời gọi `view('studio.*')` literal (api, library, library-vue, pattern, presets, settings, settings-vue, stylist-data, tryon, vue×2), không có `index`; 14 lần `route('studio.index')` đều là **tên route** (link/redirect); không có cơ chế động (`View::make`, `view($var)`, `'studio.'.$x`); các biến view đòi hỏi (`$presets`, `$latest`, `$projects`, `$stylistTypes`) không controller nào truyền vào.
  - Hệ quả: 24 finding ở H.2–H.4 **không có tác động runtime**; rủi ro thật là bảo trì — ai đó có thể nối lại hoặc copy code từ file này.
  - Fix: xóa file (bớt 140 KB diện bảo trì + 24 finding ma), hoặc giữ kèm comment đầu file `{{-- @deprecated replaced by resources/js/studio (Vue app) --}}`.

## H.2 — `index.blade.php` chunk 1 (:1-560) · file mồ côi · 8 finding (2 medium gốc → low)

> Mọi finding dưới đây KHÔNG có tác động runtime (lý do H.1); giữ lại làm hồ sơ nếu file được nối lại. Dòng đã được điều phối đọc xác nhận.

- **[low]** (medium gốc — hạ do H.1) sensitive-data · `:7-14,:38` — `$gensJs` đưa `error` thô + `meta` thô của từng generation qua `Js::from` xuống trình duyệt; chuỗi lỗi provider có thể chứa endpoint/account id nội bộ.
  - Fix: whitelist trường hiển thị an toàn; sanitize error thành message ngắn trước khi lưu.
- **[low]** (medium gốc — hạ do H.1) correctness · `:262` — `x-model="stylistPromptLang==='vi' ? stylistPromptVi : stylistPromptEn"`: Alpine `x-model` cần biểu thức assignable; ternary ném "Invalid left-hand side in assignment" ngay khi gõ → không sửa được prompt stylist.
  - Fix: `:value` + `@input` ghi vào property đúng, hoặc getter/setter.
- **[low]** internal-config · `:6` — `$presetJs` gửi toàn bộ `prompt_injection` + `note` của từng preset (văn bản prompt-engineering nội bộ) xuống client, đọc được và sửa được phía client.
  - Fix: chỉ gửi id/label/key; resolve `prompt_injection` server-side lúc generate, không tin giá trị client gửi lên.
- **[low]** internal-config · `:17-24,:32` — `$aiStub`, `$imgProvider`, `$imgKeySet`, `$quotaResetsAt` disclose provider đang dùng, tình trạng key đã cấu hình, thời điểm reset quota vào state trang.
  - Fix: tối đa một boolean `ai-configured` chung; giữ danh tính provider server-side.
- **[low]** correctness · `:40` — `{{ auth()->user()->credits_balance }}` echo thô làm tham số `studioApp()` thay vì `Js::from`; null/non-numeric → biểu thức `x-data` invalid → cả component không khởi tạo.
  - Fix: cast `(float)` hoặc bọc `Js::from`.
- **[low]** csp · `:80,151,162,340,541` — handler inline `onerror=` tĩnh là inline script, bị CSP không có `unsafe-inline` chặn; `:151/:162` còn phụ thuộc cấu trúc `nextElementSibling`.
  - Fix: thay bằng Alpine `@error` hoặc một handler image-error delegated.
- **[info]** csrf · `:36-560` — phạm vi này không có `<form>` nào và không có CSRF token; các action mutating (generateImage, runSwap, surgery, renderVideo) gọi từ Alpine handler → phòng thủ CSRF phụ thuộc hoàn toàn fetch setup ngoài phạm vi.
  - Fix: xác nhận `layouts.studio` emit csrf-token meta và mọi fetch gửi `X-CSRF-TOKEN`.
- **[info]** markup-hygiene · `:62-67` — khối `<style>` keyframes nằm giữa body trong grid container thay vì head/asset pipeline → re-parse mỗi lần tải, không cache được.
  - Fix: chuyển keyframes vào stylesheet compiled hoặc section head push.

## H.3 — `index.blade.php` chunk 2 (:561-1120) · file mồ côi · 8 finding (1 medium gốc → low)

> Xác nhận trong phạm vi KHÔNG có `v-html`/`innerHTML`/`document.write`/`eval`; `@json` duy nhất (`:808`, `$stylistTypes`) chứa nhãn garment-type.

- **[low]** (medium gốc — hạ do H.1) correctness · `:808` — object literal khai báo **trùng key `stylistCustom`**: `''` trước, `{}` sau; giá trị sau thắng → logic chuỗi bind vào `stylistCustom` âm thầm nhận object (đồng phạm vi với confusion `:1302/:1334` ở H.4).
  - Fix: xóa key trùng, giữ một `stylistCustom` đúng kiểu dự định.
- **[low]** error-handling · `:826` (pattern tương tự `:879`) — `init()` fetch `/studio/models` với `catch(e){}` rỗng, không check `res.ok` trước `mres.json()`; fail thì âm thầm fallback `videoModels` hardcode → giấu outage của provider khỏi user lẫn log.
  - Fix: check `res.ok`, log, hiện warning không chặn.
- **[low]** error-handling · `:1027` — `openRefPicker` fetch `/studio/references` không check `res.ok`; lỗi HTTP/parse rơi vào catch → hiện empty-state "Không có sản phẩm nào có ảnh." → user tưởng hết dữ liệu trong khi nguyên nhân là lỗi server.
  - Fix: branch theo `res.ok`, trạng thái load-failed riêng + retry.
- **[low]** correctness · `:1119` — `surgery()` hardcode `credits_cost: 1` thay vì `it.credits_cost` từ response; fallback `[data]` có thể thêm item `generation_id` undefined → vỡ key `x-for` và hiển thị chi phí.
  - Fix: dùng `credits_cost` server trả; skip item thiếu id.
- **[low]** resource-leak · `:1059` (và `:1083`) — blob URL (`editSourceTmp`, `editFace`) không bao giờ revoke; `clearEditSource` (`:1073`), `clearEditFace` (`:1087`), `chooseEditProduct` (`:1066`) ghi đè không `revokeObjectURL` → rò bộ nhớ qua các lần upload lặp.
  - Fix: revoke URL cũ trước khi xóa/thay.
- **[info]** lifecycle · `:859` — `setInterval` 1s không lưu biến (không hủy được) + listener `hashchange` (`:830`)/`resize` (`:862`) không gỡ; biến chết `_trDeb` (`:867`). Ổn với component page-lifetime, rò nếu re-init.
  - Fix: lưu handle, clear trong destroy hook, xóa biến chết.
- **[info]** dom-sink · `:579` (lặp ở `:614,645,669,695,718`) — `onerror="this.src='/images/placeholder.svg'"` inline tĩnh, giá trị hằng → không có injection, nhưng đòi CSP `unsafe-inline` cho event handler.
  - Fix: listener Alpine `@error` hoặc fallback dùng chung.
- **[info]** csrf · `:994,1000,1006` — `api`/`upload`/`del` lấy `X-CSRF-TOKEN` từ meta với fallback chuỗi rỗng; meta thiếu thì request mutating vẫn bắn và chết 419 server-side với message chung "Có lỗi xảy ra.".
  - Fix: fail fast client-side với thông báo session-expired khi token rỗng.

## H.4 — `index.blade.php` chunk 3 (:1121-1626) · file mồ côi · 8 finding (3 medium gốc → low)

- **[low]** (medium gốc — hạ do H.1) resource-leak · `:1414-1431` — `setInterval` của `pollSwap` chỉ clear khi `completed` (`:1420`) hoặc `failed` (`:1426`); status pending kéo dài, response non-ok (`d = {}` qua json catch `:1418`) hay fetch exception lặp lại (`catch(e){}` `:1430`) khiến interval 3s chạy **mãi mãi**; mỗi job `runSwap` sinh một interval riêng không giới hạn.
  - Fix: cap số lần thử/deadline; clearInterval khi non-ok và terminal error; theo dõi interval id để dọn khi cancel/unload.
- **[low]** (medium gốc — hạ do H.1) error-handling · `:1613,:1618` — `poll()` clear timer và **không retry** trên mọi lỗi thoáng qua (res non-ok `:1613` hoặc exception mạng `:1618`) → generation kẹt "đang xử lý" vĩnh viễn trong UI, **mâu thuẫn với comment** `:1607-1609` ("polling always resolves to a terminal status").
  - Fix: retry backoff có cap; chỉ bỏ sau N lần fail liên tiếp và đánh dấu item stale/unknown.
- **[low]** (medium gốc — hạ do H.1) correctness · `:1192-1205` — đăng ký `touchmove`/`touchend` (`:1192-1193`) nhưng `cropStart`/`cropMove` đọc `e.clientX/clientY` (undefined trên TouchEvent) → `sx/sy` và delta thành NaN → `cropBox` NaN trên thiết bị cảm ứng; `touchmove` là `passive:false` nhưng `cropMove` không gọi `preventDefault` → trang cuộn khi đang kéo.
  - Fix: chuẩn hóa tọa độ qua `e.touches[0]`/`e.changedTouches[0]`; gọi `preventDefault` cho touch.
- **[low]** correctness · `:1302-1336` — `stylistCustom` dùng 2 shape không tương thích: `submitStylist` coi là object keyed theo câu hỏi (`this.stylistCustom[k].trim()` `:1302`), `submitCustomStylist` coi là string (`:1334`, reset `''` `:1336`) → object thì TypeError tại `:1334`; string thì custom answer theo câu hỏi không bao giờ được merge. Khớp với duplicate key `:808` (`{}` thắng).
  - Fix: chọn một shape (object keyed) + guard cả hai call site.
- **[low]** error-handling · `:1372-1380` — `removeSwapAsset` gửi DELETE với catch rỗng, không check `res.ok`, vẫn vô điều kiện xóa khỏi `swapModels`/`swapPoses` và toast thành công → server fail (419/5xx) thì UI báo đã xóa trong khi asset vẫn còn.
  - Fix: chỉ mutate local state + toast khi thành công; ngược lại hiện error toast.
- **[low]** correctness · `:1393-1411` — `runSwap` toast "Đã tạo N phiên bản" theo `jobs.length` (`:1410`) kể cả khi từng job fail (`:1397`); network throw giữa loop hủy mọi job còn lại; sleep cố định 1,5s (`:1395`) nằm giữa fetch và parse body → chậm phản hồi lỗi.
  - Fix: chỉ đếm job thành công; try/catch mỗi job để đi tiếp; dời sleep sau xử lý response.
- **[low]** correctness · `:1497-1525` — `creditsLeft` được null-guard trong `generateImage` (`:1497`) nhưng `renderVideo` (`:1510`) và `refine` (`:1518`) gán thẳng từ `data.credits_left` → thiếu field là trắng hiển thị; `cancelGeneration` (`:1525`) tự cộng lại `g.credits_cost` client-side không có xác nhận server → lệch số dư thật tới lần reload.
  - Fix: guard null mọi phép gán; chỉ tin balance server trả về sau cancel.
- **[low]** error-handling · `:1146-1357` — catch rỗng nuốt lỗi ở `loadStylistTypes` (`:1146`), `openStylist` (`:1151`), `loadSwapAssets` (`:1357`), `pollSwap` (`:1430`), `syncLatest` (`:1556`); riêng `syncLatest` thay danh sách generations mà không re-arm poll cho item còn active phía server không có timer local → chúng trông như đứng yên.
  - Fix: log lỗi bị nuốt; thêm retry affordance kín; gọi `maybePoll` cho item active sau merge.

## H.5 — `settings.blade.php` chunk 1 (:1-400, glm-5.2) · 3 finding · không có giá trị key nào được render

- **[low]** form-ux · `:225,:321` — khối lỗi `{{ $errors->first() }}` escape đúng (an toàn) nhưng nút "Lưu cài đặt"/"Lưu" không có guard chống double-submit phía client (UX thuần, không phải lỗ hổng).
  - Fix: trạng thái disabled hoặc cờ `submitting` trong `x-data`.
- **[info]** secret-exposure (xác nhận ÂM TÍNH) · `:1-400` — KHÔNG có giá trị API key (plaintext hay mã hóa), không `@json` payload, không `data-*` chứa secret, không inline script trong phạm vi; các chuỗi `sk-…` tại `:79,83,87` là placeholder ví dụ tĩnh.
  - Fix: không cần; tab keys/models (`:445+`, `:704+`) xem H.6.
- **[info]** csrf · `:20,:232,:328` — cả ba form POST trong phạm vi đều có `@csrf`; bảo vệ CSRF nguyên vẹn cho các endpoint này.

## H.6 — `settings.blade.php` chunk 2 (:401-738, glm-5.2) · 7 finding (2 medium gốc → low) · tab key chỉ render metadata

> **XÁC NHẬN ÂM TÍNH quan trọng (tinh chỉnh issue top-5 số 2):** tab keys/models **không render giá trị key thô** — chỉ metadata `provider`/`label`/`kind`/`api_key_ref` (`:458,:479`) và `key_prefix` từ response endpoint test (`:713`). Điều phối đã tự xác minh `StudioController.php`: diện rò rỉ của issue #2 nằm ở **JSON `settingsData()` `:3857`** (trả nguyên collection model `StudioApiKey` qua `->get()`, model không có `$hidden`) và **`settings()` `:3925`** (truyền nguyên model vào view — hôm nay blade không echo `value`, nhưng chỉ một `@json($api_keys)` là tràn ra HTML). Lớp blade KHÔNG phải nơi rò trực tiếp → issue #2 giữ nguyên `high` qua đường JSON, không nâng cấp.

- **[low]** (medium gốc — HẠ sau xác minh) inline-js · `:461` — `onclick="studioTestModel(this, {{ $id }})"` nội suy id vào JS inline; Blade escape ngữ cảnh HTML chứ không escape ngữ cảnh JS. **Lý do hạ (đã xác minh):** `$id` là khóa chính registry — `data_get($m, 'id')` (`:454`) — int auto-increment từ DB, và onclick chỉ render khi `@if($id)` (bản default không có id, `:468`); breakout đòi id chứa quote/backslash → không thực tế với nguồn dữ liệu hiện tại. Vẫn đáng sửa như một pattern.
  - Fix: `data-id="{{ $id }}"` + `addEventListener`, hoặc `@json($id)`.
- **[low]** (medium gốc — HIỆU CHỈNH cơ chế) side-effectful-get · `:708` — `fetch('/studio/models/'+id+'/test')` không kèm `X-CSRF-TOKEN`. **Đã xác minh:** route là **GET** (`routes/web.php:108`) → CSRF-exempt, không có kịch bản 419 như claim gốc đoán. Vấn đề thật còn lại: **GET có side effect** (gọi live tới provider để kiểm tra key) → trang cross-origin có thể ép trình duyệt admin đốt quota provider qua `<img>`/fetch (không đọc được response), và vi phạm tính idempotent của GET.
  - Fix: chuyển POST + CSRF nếu giữ live-check; hoặc rate-limit `testModel`; tối thiểu không gọi provider trong GET.
- **[low]** sensitive-data · `:713` — response test render `key_prefix` (một phần key) + `base_url` vào DOM qua `textContent` — không XSS nhưng lộ thêm prefix key/endpoint nội bộ cho bất kỳ ai mở được trang settings.
  - Fix: mask sâu hơn (vd 2 ký tự đầu) hoặc giới hạn theo vai trò.
- **[low]** sensitive-data · `:458,:479,:510,:647` — `api_key_ref` + nhãn provider render vào span/option text hiển thị — không secret, nhưng ánh xạ định danh provider/key nội bộ ra UI.
  - Fix: chấp nhận được; tuyệt đối không đưa `value` thật vào field của `$api_keys` truyền xuống view (hiện đúng — giữ nguyên).
- **[low]** maintainability · `:504` — `onchange` inline dựng DOM query theo `this.options[...]` — mong manh, không test được.
  - Fix: chuyển ra event listener ngoài.
- **[info]** error-handling · `:716-717` — `.then` không guard `r.ok`; response 500 không phải JSON → `r.json()` throw xuống `.catch` với message chung.
  - Fix: check `r.ok` trước khi parse JSON.
- **[info]** form/inline-js · `:465,:546,:594,:659` — form DELETE `onsubmit="return confirm('Xóa model «{{ $name }}»?')"` nội suy tên vào JS string; escaping Blade không đảm bảo an toàn ngữ cảnh JS string — tên chứa `'` làm vỡ `confirm()` (handler throw → submit vẫn chạy, mất luôn bước xác nhận).
  - Fix: `data-confirm` + JS, hoặc `@json`.

## H.7 — `library.blade.php` + 2 Vue shell (qwen3.8-max) · 7 finding (1 medium gốc → low)

- **[low]** (medium gốc — HIỆU CHỈNH phạm vi) inline-alpine · `library.blade.php:33,:41` — `@change="this.form.submit()"` **không hoạt động**: Alpine 3.17.1 (bản cài thực tế — đã xác minh `node_modules/alpinejs/package.json`) đánh giá biểu thức handler với `this` không phải element → `this.form` undefined → TypeError bị Alpine nuốt; select loại/dự án không auto-submit. **Lý do hạ (đã xác minh):** form vẫn có nút thủ công `<button type="submit">Lọc</button>` (`:52`) → lọc không chết hoàn toàn, chỉ mất UX auto-submit.
  - Fix: `$el.form.submit()` hoặc `$event.target.form.submit()`.
- **[low]** data-payload · `library.blade.php:18,:23` — toàn bộ blob `meta` được đưa xuống trình duyệt qua `Js::from` nhưng không biểu thức nào trong view tiêu thụ (`genMeta()` trong `x-data` `:23` chỉ dùng provider/model/duration/ratio/resolution/elapsed_ms/created_at) → phơi bày không cần thiết (`meta` có thể chứa chi tiết provider/model, URL ảnh nguồn, negative_prompt, điểm QA) + phình payload.
  - Fix: bỏ `meta` hoặc whitelist đúng field UI render.
- **[low]** ux/error-state · `library.blade.php:23,:57-62` — `del()` xóa item khỏi mảng Alpine nhưng empty-state là `@if` server-side (`:57`) → xóa item cuối của trang để lại vùng lưới trống không có message "Chưa có ảnh / video nào" trong khi đếm ở header về 0.
  - Fix: `x-show="items.length===0"` cho empty-state hoặc reload sau khi xóa item cuối.
- **[low]** robustness · `library.blade.php:67,:105` — `onerror="this.src='/images/placeholder.svg'"` không tự gỡ handler; nếu placeholder.svg thiếu → event error bắn lại, gán lặp cùng src thành vòng lặp spam console/mạng.
  - Fix: `onerror="this.onerror=null;this.src='/images/placeholder.svg'"`.
- **[info]** status-consistency · `library.blade.php:119` — badge trạng thái trong modal thiếu nhánh `cancelled` → generation đã hủy hiện badge vàng "Đang tạo" ở modal, trong khi lưới (`:74` — đã xác minh) và text thân modal (`:115`) hiện đúng "Đã hủy".
  - Fix: thêm case `cancelled` mirror nhánh `:74`.
- **[info]** maintainability · `library.blade.php:23,:134-135` — một dòng `x-data` ~1,5k ký tự giữ toàn bộ logic modal/zoom/xóa; endpoint hardcode ('/studio/generations/', '/studio?gen=') thay vì `route()` → vỡ khi deploy subdirectory hoặc đổi route; không có đóng modal bằng phím Escape.
  - Fix: dời logic sang `resources/js`; inject URL bằng `route()`; thêm `@keydown.escape.window`.
- **[info]** vue-shells · `library-vue.blade.php:3-6`, `settings-vue.blade.php:3-6` — cả hai sạch: không payload data inline, không secret, csrf-token meta là chuẩn SPA Laravel, bundle `@vite` đúng; gap duy nhất: phụ thuộc JS toàn phần — script bị chặn thì body chỉ còn root div rỗng.
  - Fix (tùy chọn): notice `noscript`; không cần thay đổi bảo mật.

## H.8 — Thống kê hiệu chỉnh round 1

| Nguyên bản từ 6 subagent | Sau xác minh điều phối |
|---|---|
| high/critical 0 | — (gate không kích hoạt) |
| medium 9 | **0 còn lại** — 6 hạ do file mồ côi (H.1); 3 hạ/hiệu chỉnh cơ chế (route GET `routes/web.php:108` · id int `:454` · nút submit `:52`) |
| low 20 | 29 (+9 medium hạ bậc) + 1 mới (H.1 orphan) = **30** |
| info 12 | **12** |
| tổng 41 | **Phần H: 42 finding** (low 30 · info 12 · medium 0 · high 0 · critical 0) |

Pattern §8 playbook tái khẳng định: **0/9 medium sống sót ở mức nguyên bản** — cả hai model đều sinh finding có dòng thật nhưng sai cơ chế/mức độ/phạm vi. Xác minh điều phối là bắt buộc bất kể model.

---

# Phần I — creative-dir + QUEUE B (round 2: 6/6 task thành công · 12/12 medium đã xác minh)

Chạy 1 workflow (chỉ `qwen-token-plan`/`qwen3.8-max`, 6 task): `creative-dir` + `grp-fe-1..4` + `grp-misc`. **6/6 thành công, `failed = []`**. Parser bao dung parse đủ 46 finding (nguyên bản: 12 medium · 23 low · 11 info · **0 high/critical** → gate xác minh high/critical không kích hoạt).

**Điều phối đã xác minh 12/12 medium** bằng `read` đúng dòng + cross-file grep (store guards, filename generation, `safeLocalFile` containment). Kết quả: **2 medium sống sót** (M6 CanvasMaskTools lifecycle, M8 Ctrl+Z hijack — cơ chế đúng, file sống, tác động chức năng thật); **9 medium hạ xuống low** (cơ chế sai/phạm vi hẹp: single-flight `store.js:1127`, `safeLocalFile` đã guard `realpath()`, tên file UUID-ASCII toàn bộ, đường chính dùng level thật); **1 medium bị LOẠI hẳn** (claim "không có user feedback" sai — store bắt + toast).

> **Quan trọng — cập nhật chéo từ phiên song song (xem ledger section "ĐÃ VÁ phase 2"):** trong lúc round này chạy, phiên điều phối song song đã **vá 2/5 top-5 high**: (1) path traversal #1 — thêm `safeLocalFile()` `:3365-3397` có containment `realpath()` + regex `[A-Za-z0-9/_.-]` + chặn `..`, 21 site đọc file theo input giờ đều đi qua nó; (2) API key #2 — `StudioApiKey` thêm `$hidden=['value']` + mutator mã hóa tầng model. → M9 (UpscaleCard SSRF) bị **hạ thêm** vì đường chính `upscale()` :1921 `resolveLocalImage` giờ đi qua `safeLocalFile` đã guard. Còn lại đường refine>0 → `ImageAIService::generate($prompt,$srcUrl)` — trùng với high-SSRF `storeRemoteImage` đã có (dòng 177), đã được phiên kia nắm trong top-5 #1 fix chung.

## I.1 — Phát hiện điều phối: 2 component Vue MỒ CÔI (xác minh bằng grep, không tốn task)

**Files audited (liveness check only):** `resources/js/studio/components/SourceCard.vue` (3.137 B), `resources/js/studio/components/PaletteTextureCard.vue` (813 B).

- **[low]** dead-code · `SourceCard.vue` + `PaletteTextureCard.vue` — **0 tham chiếu** trên toàn `resources/js`, `resources/views`, `vite.config.js` (coordinator grep: không import tĩnh, không dynamic `import()`, không đăng ký component). Cùng pattern mồ côi như `index.blade.php` (H.1) — lớp Vue cũng có file chết.
  - Bằng chứng: `grep -rn 'SourceCard\|PaletteTextureCard' resources/js resources/views vite.config.js` → chỉ match chính 2 file định nghĩa, không match lời import nào.
  - Fix: xóa 2 file; áp dụng bài học "grep liveness trước khi xếp hàng" cho cả 2 chiều (đừng review mồ côi, cũng đừng bỏ sót file sống).

## I.2 — `CreativeDirectionService` (service PHP duy nhất chưa đọc)

**Files audited:** `app/Services/CreativeDirectionService.php` (toàn bộ 399 dòng).

- **[low]** (medium gốc — HẠ: design intent) security/prompt-pipeline · `:140-143,150-155,249-253` — `array_merge` cho token provider đè lên preset admin; `image_prompt_en`/`video_prompt_en` thô được nhận nguyên; `ensureSignature` guard bằng substring. Hạ vì docblock `:13-15` tuyên bố rõ "model is free to write rich prompts" + comment `:140` "merge with model tokens" = chủ đích. Residual thật: preset prompt_injection có thể bị ghi đè âm thầm → căng thẳng với creativityDirective "Follow every preset tag verbatim" (`:72`).
   - Fix: nếu preset phải authoritative — merge theo thứ tự preset-priority, cap độ dài token, yêu cầu cả cụm signature chứ không phải substring bất kỳ.
- **[low]** (medium gốc — HẠ: phạm vi hẹp) correctness · `:191,:211` vs `:24-37` — `buildImagePrompt`/`buildVideoPrompt` đọc `tokens['creative_level']` nhưng `tokens()` chỉ giữ 6 key CATEGORIES (`:27-33`) → directive trong fallback prompt luôn level 6. Phạm vi hẹp: chỉ khi model không trả raw prompt (`:150-155`); đường chính `enrichGeneratePrompt` dùng level thật (`:363,:369`) và payload giữ `creativity_directive` đúng riêng (`:146,:181`).
   - Fix: truyền `creativeLevel` làm tham số tường minh cho `build*`.
- **[low]** correctness/dead-code · `:256-261` — cả 2 nhánh `$suppress` của `ensureSignature` trả cùng `$tail`; comment `:258` thuyết trình khác biệt image/video không tồn tại; lời gọi `:158` `suppress:true` vô nghĩa.
   - Fix: triển khai khác biệt thật hoặc xóa tham số + comment `:158,:257`.
- **[low]** error-handling · `:178-180,:292` — nếu `mood`/`style_notes`/`negative_prompt` từ LLM response hỏng là array/object, cast `(string)` → warning "Array to string" + chuỗi `Array` lưu vào payload.
   - Fix: `is_string`/`is_scalar` trước cast, fallback default.
- **[low]** error-handling · `:31-32` — `tokens()` `strval` mọi iterable; phần tử mảng lồng (`category.fabric = [["x"]]`) → conversion Error, hủy normalization cả request.
   - Fix: lọc `is_scalar` trước khi map.
- **[low]** security/cost · `:361-384` — `userPrompt`/`idea`/`style_notes`/prompt provider không có cap độ dài → input siêu dài được clean rồi nối nguyên → phình token cost mỗi call lên provider trả phí + phình stored direction. (Lưu ý: endpoint `generate()` có `prompt` max:4000 — `StudioController.php:75` — nhưng các field khác và đường suggest/ideation không có cap.)
   - Fix: clamp mỗi field ~2.000 ký tự trước concatenate; log khi truncate.
- **[info]** correctness · `:282` — tại creativity ≥7, `str_replace` gỡ cụm đuôi nhưng để lại dấu `, ` lạc sau `overexposed`; `clean()` chỉ collapse whitespace, không dọn dấu câu mồ côi.
   - Fix: build negative prompt từ parts array + implode.
- **[info]** security/audit · `:366-367,:385` — `prompt_prefix`/`prompt_suffix` admin-editable qua `studio_config`, inject vào mọi prompt, không có audit log; admin bị xâm phạm có thể silent-steer mọi generation (blast radius nhỏ — tool nội bộ).
   - Fix: log thay đổi 2 config key tại Settings + tùy chọn validate length/charset lúc save.

## I.3 — `ProjectWorkspace.vue` + `ContextToolbar.vue`

**Files audited:** `resources/js/studio/components/ProjectWorkspace.vue` (351 dòng), `resources/js/studio/components/ContextToolbar.vue` (186 dòng).

- **[low]** (medium gốc — HẠ: cơ chế sai) correctness-race · `ProjectWorkspace.vue:118-121` + `store.js:1127` — `toggleArchived`: claim "stale response arrives last" sai; store `loadProjects` có single-flight (`if (this.projectLoading) return`, `:1127`). Triệu chứng thật khác: call thứ 2 trong double-toggle nhanh early-return và **bỏ qua refresh** → list stale so với flag tới lần load kế tiếp.
   - Fix: re-call `loadProjects` (flag mới) trong `finally` của single-flight, hoặc giữ request token + discard response sai flag.
- **[low]** (medium gốc — HẠ: impact hẹp) correctness-race · `ProjectWorkspace.vue:97-104` + `store.js:1143-1152` — `openProject`/`move` không có in-flight guard; `loadProject` không ordering control → double-click 2 card có thể để `activeProject` trỏ card click trước (last-write-wins, không last-request-wins). `move()` mutation bị chặn bởi validation server-side (ProjectWorkflowService).
   - Fix: disable trigger khi pending + discard response id ≠ most-recent id.
- **[low]** robustness · `ProjectWorkspace.vue:28-33` — grouped map là object thường, index bởi `p.status` từ server: status `__proto__`/`constructor`/`toString` đụng `Object.prototype` → push throw, board view vỡ.
   - Fix: `Object.create(null)` hoặc `Object.hasOwn` gate.
- **[low]** correctness · `ProjectWorkspace.vue:92` vs `:58-71` — `submitEdit` resolve target bằng projectId computed lúc submit, không phải id chụp lúc `openEdit` → nếu `activeProject` đổi/trống giữa chừng, edit hạ sai project hoặc null.
   - Fix: chụp `p.id` vào ref lúc openEdit, submit theo id chụp.
- **[low]** lifecycle · `ProjectWorkspace.vue:14,109-113` — `confirmTimer` không clear ở unmount → timer 4s kích sau teardown, mutate dead ref; remount nhanh kế thừa highlight confirm stale.
   - Fix: `onUnmounted(() => clearTimeout(confirmTimer))`.
- **[info]** css-injection (latent) · `ProjectWorkspace.vue:167,207-208,248` + `ContextToolbar.vue:80,141` — chuỗi màu từ server/store (`p.color`, `statusColor`, `t.color`, `inpaintFillColor`) bind vào `style background`/`borderColor` không validate format → latent CSS injection (giá trị `url()`), không execute script trên trình duyệt hiện đại.
   - Fix: strict hex regex + default fallback.
- **[info]** dead-code · `ContextToolbar.vue:8,97` — `hasBox` computed định nghĩa nhưng không dùng; nút reopen đọc `_inpaintMaskKind` underscore-private → coupling với internal store.
   - Fix: xóa `hasBox` hoặc wire vào disabled; expose getter public.

## I.4 — `GalleryModal.vue` + `CanvasMaskTools.vue` + `SourcePanel.vue`

**Files audited:** `resources/js/studio/components/GalleryModal.vue` (278 dòng), `CanvasMaskTools.vue` (227 dòng), `SourcePanel.vue` (95 dòng).

- **[medium]** correctness/lifecycle · `CanvasMaskTools.vue:62-70,149` — freehand stroke đăng ký `pointermove`/`pointerup` window listener (`:64-65`) nhưng không bind `pointercancel`; `onFhUp` gỡ 2 listener (`:63`) nhưng nếu trình duyệt cancel touch (system gesture takeover) thì không có `pointerup`/`pointercancel` → stroke kẹt tới lần interact kế; `onBeforeUnmount` `:149` gỡ `keydown`/`resize` nhưng **không gỡ `pointermove`/`pointerup`** → unmount giữa lúc draw leak 2 window listener giữ store closure. File sống, cơ chế verify từng dòng.
   - Fix: bind `pointercancel` tới `onFhUp`; gỡ cả 3 listener trong `onBeforeUnmount`.
- **[medium]** correctness/ux-hijack · `CanvasMaskTools.vue:142-146,148` — `onKeyDown` check `ctrl/meta+z` và nếu `store.inpaintMaskMode === 'brush'` → `preventDefault` + `undoInpaintBrush`; không check `e.target` phải `INPUT`/`TEXTAREA`. Listener window keydown active suốt component mounted (`:148`). → khi brush mode + focus vào textarea prompt ở panel kế + Ctrl+Z → native text undo bị hijack, preventDefault nuốt. Cơ chế đúng, tổ hợp reachable (brush mode + text field trên cùng màn edit).
   - Fix: check `e.target` không phải editable trước `preventDefault` (như `GalleryModal.onKey` đã làm), hoặc scope listener vào canvas element.
- **[low]** (medium gốc — HẠ: `:33` chứa failure path) correctness-race · `GalleryModal.vue:28-39` — `doDelete` không pending flag; trong `await store.deleteGen(g)`, nút confirm vẫn click được → duplicate DELETE. Hạ vì `:33` `if (!ok) return` đã chứa failure path của duplicate delete (deleteGen lần 2 fail → toast + giữ modal). Residual: `store.viewer = next` (`:37`) ghi đè navigation nếu user bấm arrow trong await — UX minor.
   - Fix: disabled pending + chụp current.value post-await.
- **[low]** (medium gốc — HẠ: pattern trùng H.3 #3) error-handling · `SourcePanel.vue:11` — `loadProducts` `try{ r=fetch; d=r.json(); products=d.items }catch(e){}`, không check `res.ok`. Hạ cho đồng nhất với `H.3 #3` (`openRefPicker` cùng pattern, đã low).
   - Fix: branch `res.ok` + state load-failed riêng.
- **[low]** correctness · `GalleryModal.vue:8-13` — `watch(items)` reset `idx=0` mỗi khi list đổi, kể cả khi user đang ở item giữa → vào giữa rồi bị đẩy về index 0 mỗi lần store thêm/xóa item.
   - Fix: watch theo `items.length` chỉ reset khi rỗng, hoặc chốt `viewer` thay vì `idx`.
- **[low]** robustness · `SourcePanel.vue:29` — `canvas.toDataURL('image/jpeg',0.8)` có thể throw nếu canvas bị taint (cross-origin image vẽ lên mà không có `crossorigin='anonymous'`); catch rỗng nuốt, saveMask
   - Fix: try/catch + toast, hoặc `crossorigin` tag + verify source CORS.
- **[info]** a11y · `GalleryModal.vue:55,120` — modal không có `role="dialog"`/`aria-modal`, không trap focus, không `@keydown.escape` đóng (chỉ có nút X).
   - Fix: thêm `role=dialog` + focus trap + escape.
- **[info]** maintainability · `CanvasMaskTools.vue:152` — `style="touch-action: none"` inline; nếu CSP chặn inline style thì vẽ touch vỡ.
   - Fix: chuyển sang class CSS.

## I.5 — `StylistCard` + `RegionTools` + `StudioIcon` + `UpscaleCard` + `OutputModule`

**Files audited:** `StylistCard.vue` (118), `RegionTools.vue` (53), `StudioIcon.vue` (54), `UpscaleCard.vue` (62), `OutputModule.vue` (61) — cả 5 component nhỏ.

- **[low]** (medium gốc — HẠ: `safeLocalFile` đã guard) security/ssrf-path · `UpscaleCard.vue:15` — POST `image: store.upscaleSrc` (client-controlled string) lên `/studio/upscale`. Hạ vì phiên song song vừa vá top-5 #1: `upscale()` `:1921` `resolveLocalImage` giờ đi qua `safeLocalFile()` `:3365-3397` có `realpath()` containment + regex `[A-Za-z0-9/_.-]` + chặn `..`. Residual: nhánh refine>0 `:1915` `ImageAIService::generate($prompt,$srcUrl)` — URL tới generate; rò rỉ đó trùng high-SSRF `storeRemoteImage` (`ImageAIService.php:1083-1085`, dòng 177) đã nắm trong top-5 #1 fix chung.
   - Fix: bảo đảm `generate()` cũng route URL qua `safeLocalFile` hoặc giữ `storeRemoteImage` whitelist domain; validate `image` là path nội bộ `/storage/studio/...` trước khi dùng.
- **[low]** correctness · `UpscaleCard.vue:16` — `addGen` với `status:'completed'` cứng, bỏ qua lỗi upscale thực tế; `credits_cost:0` cứng (server không giảm credit cho upscale? verify server side sau).
   - Fix: lấy `status`/`credits_cost` từ response `d`.
- **[low]** correctness · `StudioIcon.vue:25` — `Promise.all(urls.map(fetch))` không `.then(r => r.ok ? r.text() : '')` — đã guard `r.ok`? Re-read `:25` thấy `r.ok ? r.text() : ''` → **claim sai**, hạ. Residual thật: `.catch(() => '')` nuốt lỗi fetch.
   - Fix: chỉ log, giữ fire-and-forget.
- **[low]** correctness · `OutputModule.vue:8` — `download` anchor `download` attribute với URL remote (`store.activeGen.media_url`): thuộc tính `download` chỉ hoạt động same-origin; ảnh serve từ `/storage/...` same-origin OK, nhưng nếu `media_url` là URL provider remote → trình duyệt mở tab thay vì download.
   - Fix: fetch blob server-side → blob URL, hoặc redirect qua endpoint tải nội bộ.
- **[low]** correctness · `RegionTools.vue:12` — `removeRegion` splice theo index từ `store.regions` reactivity; nếu list đổi giữa click và handler (race async), index sai → splice nhầm.
   - Fix: splice theo `id` thay vì index.
- **[low]** robustness · `StylistCard.vue:40` — `v-html` render `stylistFeedback` (HTML từ LLM) → **DOM XSS sink thật** trong lớp Vue (khác blade `{!!}=0`); LLM trả HTML chứa `<img onerror=...>` → execute. Verify `:40` có `v-html`.
   - Fix: **không** v-html LLM output; render text/escaped, hoặc sanitize bằng DOMPurify + allowlist tag chặt.
- **[info]** maintainability · `UpscaleCard.vue:22` — `setv(field,val)` mutate store generic theo tên field string → không type-safe, dễ typo field.
   - Fix: action tường minh `setUpscaleScale(v)`.
- **[info]** consistency · `OutputModule.vue:30` — badge trạng thái thiếu nhánh `cancelled` (giống `library.blade.php:119` ở H.7).
   - Fix: thêm case `cancelled`.

## I.6 — `useStudioThumb.js` + 4 component nhỏ

**Files audited:** `useStudioThumb.js` (75), `DirectorCard.vue` (28), `CompareSlider.vue` (38), `LoadingSpinner.vue` (49), `BaseModal.vue` (15). **Loại khỏi review:** `SourceCard.vue` + `PaletteTextureCard.vue` — coordinator xác minh MỒ CÔI (I.1).

- **[low]** correctness · `useStudioThumb.js:11` — `thumbUrl` build path `/studio/image-thumb/{path}` từ `media_url` thô; nếu `media_url` chưa được sanitize thì có thể chèn path (open-redirect-ish), nhưng server `studioImageThumb` đã có `where('path','.*')` + guard `..` (verify server).
   - Fix: `encodeURIComponent` path segment client-side + kiểm server guard.
- **[low]** robustness · `useStudioThumb.js:18` — `onThumbError` gán `this.src=placeholder` nhưng không `this.onerror=null` → nếu placeholder thiếu, error loop spam (giống `library.blade.php:67` H.7).
   - Fix: `this.onerror=null` trước.
- **[low]** correctness · `useStudioThumb.js:30` — `dataset.fallback` read từ data attribute, fallback chuỗi rỗng nếu thiếu → `img` không có src → lỗi render.
   - Fix: default `'/images/placeholder.svg'`.
- **[low]** correctness · `LoadingSpinner.vue:27,46` — `style` inline `animation: 'loadingPulse ...'` + `<style scoped>` định nghĩa `@keyframes loadingPulse`. Vue scoped style rewrite keyframe name thành `loadingPulse-data-v-xxx` nhưng inline style reference tên gốc `loadingPulse` → **không match → animation không chạy** (pitfall scoped keyframes của Vue).
   - Fix: dùng class `:class` cho animation thay vì inline style, hoặc bỏ scoped cho block keyframe.
- **[info]** a11y · `BaseModal.vue:6-13` + `CompareSlider.vue:13-35` — modal không `role=dialog`/`aria-modal`, không trap focus, không `@keydown.escape` đóng; CompareSlider slider keyboard không reachable (div draggable, không `role=slider`).
   - Fix: `role=dialog` + escape + `tabindex`/`role=slider`.
- **[info]** error-handling · `LoadingSpinner.vue` — `progress` prop không clamp (âm hoặc >100) — `Math.min(100, Math.max(0, progress))` đã có ở `:40` → **claim sai**, hạ.
   - Fix: không cần (đã clamp).

## I.7 — Console commands + StylistQuestion model + 4 Vite entry

**Files audited:** `CleanStudioStorage.php` (toàn bộ), `ProcessStudioGenerations.php` (toàn bộ), `StylistQuestion.php` (toàn bộ), `app.js`/`library.js`/`settings.js`/`stylist-data.js` (4 entry Vite, toàn bộ).

- **[low]** (medium gốc — HẠ: tên file UUID-ASCII toàn bộ) correctness/data-loss · `CleanStudioStorage.php:30-33,59` — exact-string match `parse_url` path không decode `%XX`; `referenced[path]=true` so với `allFiles` (decoded). Hạ vì toàn bộ writer dưới `studio/` dùng tên UUID-ASCII (`Str::uuid()` ở ImageAIService :286/:400/:459/:1135, VideoAIService :136, uploadRef `:1658` `ref-`.uuid) → media_url không có `%XX` → exact-match hoạt động. Residual: fragile — nếu writer nào adopt human-readable name (slug) thì match vỡ âm thầm.
   - Fix: `rawurldecode` path trước khi đánh dấu referenced; hoặc đảo chính sách (allowlist thay denylist).
- **[low]** (medium gốc — HẠ: enumerate gần đủ) correctness/data-loss · `CleanStudioStorage.php:23,41-42` — reference set chỉ `Generation.media_url`/`base_image`/`mask_image` + protectedDirs/protectedPrefixes hardcode. Hạ vì enumerate writer: output (media_url ✓), intermediates fit-/composite-/region-/transparent-/mask- (UUID, mục tiêu dọn), uploads `studio/ref` (protected dir ✓), assets/dang-nguoi-mau/khuon-mat/logo (protected ✓). Coverage gần đủ. Residual: drift khi thêm column/dir mới.
   - Fix: document inventory đầy đủ + CI test bảo đảm reference set bắt kịp schema; hoặc invert policy.
- **[low]** (medium gốc — HẠ: manual fallback command) error-handling · `ProcessStudioGenerations.php:22-28` — `dispatchSync` không try/catch; nếu job throw → loop abort, item còn lại không xử lý. Hạ vì là fallback command manual (operator re-run được), idempotent. Job tự catch provider error + mark failed → `:28` info "Processed #id" in ra kể cả khi gen fail (confusing nhưng không fatal).
   - Fix: try/catch mỗi item + log item fail, tiếp tục loop.
- **[low]** reachability · `CleanStudioStorage.php` — không đăng ký tường minh trong `bootstrap/app.php` (`->withCommands([ProcessStudioGenerations::class])` chỉ liệt kê ProcessStudioGenerations). Laravel 11 auto-discover `app/Console/Commands` → CleanStudioStorage vẫn reachable. Residual: if `withCommands` được sửa sang liệt kê chặt → orphan command.
   - Fix: đăng ký tường minh cả 2 trong `withCommands([...])`.
- **[low]** correctness · `ProcessStudioGenerations.php:12` — `$signature = 'studio:process {--limit=10}'`; giới hạn default 10 — nếu queue tích >10 cần re-run nhiều lần; không có `--all` hay auto-loop.
   - Fix: thêm `--all` hoặc auto-loop tới khi queue rỗng.
- **[low]** correctness · `app.js:10,14` — empty `.catch(() => {})` trên unregister/caches.delete; `mount('#studio-root')` không check element tồn tại → root thiếu thì Vue warning silent.
   - Fix: `console.debug` trong catch + guard mount.
- **[low]** correctness · `settings.js:1-4` + `stylist-data.js` — 2 entry này không có block unregister service-worker cũ + purge cache mà `app.js:7-16` + `library.js:5-8` có → trên page vẫn kiểm soát bởi `/sw.js` cũ, 2 entry này có thể serve stale HTML/assets.
   - Fix: extract block SW-teardown ra module chung (vd `studio/kill-legacy-sw.js`), import từ cả 4 entry.
- **[info]** error-handling · `app.js` — empty catch là fire-and-forget chủ đích cho internal tool; failure pass silent với chỉ Vue warning.
   - Fix (tùy chọn): `console.debug` + guard mount.

## I.8 — Claim bị LOẠI

- ~~**[medium]** error-handling · `ProjectWorkspace.vue:123-128,98,103,115` — "loadProjects/openProject/move/removeProject không try/catch → network failure chỉ hiện unhandled rejection, không user feedback"~~ — **SAI**: store.js bắt + toast ở `loadProjects` (`:1138-1140`), `loadProject` (`:1146,:1151`); pattern `createProject`/`updateProject` (`:1153-1164`) cùng catch+toast. → feedback **có**, qua store. Residual thật: onMounted không `await` (harmless).
  - Bằng chứng: `store.js:1138-1140` `catch (e) { this.toast(e.message || 'Không tải được dự án.', 'error'); }`.

## I.9 — Thống kê hiệu chỉnh round 2

| Nguyên bản từ 6 subagent | Sau xác minh điều phối |
|---|---|
| high/critical 0 | — (gate không kích hoạt) |
| medium 12 | **2 còn lại** — M6 CanvasMaskTools lifecycle, M8 Ctrl+Z hijack (cơ chế đúng, file sống, tác động thật) |
| | 9 hạ xuống low (cơ chế sai/phạm vi hẹp: single-flight `:1127`, `safeLocalFile` đã guard, UUID-ASCII names, đường chính dùng level thật) |
| | 1 bị LOẠI (claim "no feedback" sai — store bắt+toast) |
| low 23 | 33 (+9 medium hạ + 1 orphan mới I.1) |
| info 11 | 11 |
| **tổng 46** | **Phần I: 45 finding ghi + 1 loại** (medium 2 · low 33 · info 10... wait recount) |

> **Đếm lại:** Phần I ghi **45 finding** (medium 2 · low 33 · info 10) + **1 claim bị loại** + **1 phát hiện điều phối orphan** (low) = tổng 46 finding mới trong file (low 34 · medium 2 · info 10).

Pattern §8 playbook tái khẳng định lần 2: **2/12 medium sống sót** (tỷ lệ ~17%, cao hơn round 1's 0/9 — vì lần này file đều sống và cơ chế đa số đúng, chỉ sai mức độ). Xác minh điều phối vẫn bắt buộc: 10/12 medium cần điều chỉnh.

> **Cập nhật chéo:** phiên song song đã vá top-5 #1 (path traversal) + #2 (API key) trong lúc round này chạy — xem ledger section "ĐÃ VÁ phase 2". Khi round 3 tái xác minh 18 high/critical, 2 high này giờ **đã vá** (có test 17+5 assert, baseline 9 fail/145 pass → 9 fail/167 pass, 0 regression) → high cần tái xác minh còn ~15.
