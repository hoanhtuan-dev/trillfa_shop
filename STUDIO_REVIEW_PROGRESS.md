# STUDIO REVIEW — LEDGER TIẾN ĐỘ (bộ nhớ bền bỉ)

> **File này là trạng thái, không phải quy trình.** Quy trình ở `DELEGATION_PLAYBOOK.md`.
> Mỗi goal round / session mới: đọc file này TRƯỚC (chỉ ~4 KB), làm việc, rồi cập nhật lại nó.
> Lý do phải có: goal round cộng dồn trong CÙNG session và compaction có thể shadow round cũ
> (`dsh-goal-round-driver/README.md`: "no fresh agent or copied conversation prefix").

## Trạng thái hiện tại

- **Brush tool nâng cấp (UI-4)**: Hardness · Flow · Spacing · Smoothing · Blend mode · con trỏ cọ — ContextToolbar GUI chuyên nghiệp (pill) + thu gọn + fix banding nét vẽ. Fix shift chọn đa layer ổn định. **Bezier curve selection (Krita Pen tool)**: click=neo/kéo=handle, **kéo node để di chuyển**, **kéo 2 tay điều khiển out(in xanh/in hồng) riêng** (symmetric + Alt phá đối xứng), **right-click node = xóa**, click khoảng trống = thêm neo; pathClose 2-handle cubic bézier. Đã commit + deploy live.
- PHẦN I (UI/UX Redesign) — ĐÃ GỘP bên dưới (37 phiên UI-1 → UI-3aj); mọi commit đã push + deploy live.
- Audit code: 214 findings · đã vá ~60 · full suite 210 pass / 0 FAIL (phiên N).

## PHẦN I — Studio UI/UX Redesign (gộp 37 phiên UI-1 → UI-3aj)

> Spec: STUDIO_UI_REDESIGN.md (PHẦN I + II). Provider CHỈ deepseek-official (v4-pro/v4-flash) — workflow fan-out, text contract, điều phối xác minh 100%. Deploy: scripts/deploy.sh → SSH ssh -p 65002 u310846799@145.79.25.57 (app ~/domains/trillfa.shop).

- **Chrome & layout** (UI-1/2/3): vá CSS production thiếu ink-600/ink-950/cream-400; StudioIcon 90+ Lucide; class chrome .seg/.tool-btn/.icon-btn/.panel-head/.panel-title; right bar 1 cột w-44→w-[115px] (đính chính người dùng: là right dock, không phải left); header tool-btn/icon-btn; chips → .seg; mobile top bar + badge đếm; mobile tối giản (ContextToolbar desktop-only + floating bottom-12 khi tool active; outputs drawer chỉ đóng bằng nút close; RegionTools luôn nổi); addBlankLayer(bg,ratio) — ưu tiên kích thước ảnh đang chọn, trống theo preset tỷ lệ + 7 preset + màu tùy chỉnh + thoát popup khi bấm ngoài (backdrop).
- **Nguồn + Thư viện** (UI-3b/3c/3f): card Nguồn = 1 slot → SourcePickerPopup.vue gộp 2 nguồn 2 tab; LibraryCard.vue icon thư viện cuối dock; header /studio/library redesign.
- **Toolbar canvas** (UI-3g→3j/3p/3r): RegionTools + ContextToolbar đơn sắc theme (cream/ink, bỏ brand), icon/cỡ nhất quán, nhóm chuẩn UX, dải phân cách ngang; tool Lựa chọn (Select) đầu dock (icon cursor) — marquee/quét chỉ khi bật Select (hết đua pan); snap xuống status bar (mặc định BẬT 8px, 8/16/24/32); exitCanvasTools thoát tool khi chuyển tác vụ/thoát ảnh tiêu điểm.
- **Viewer GalleryModal** (UI-3d/3e/3s/3t): chuyển ảnh mượt không chớp (probe + cache loadedUrls + crossfade); info thu gọn; nút Sử dụng → popup Prompt Tạo Ảnh; dải thumbnail cuộn/chọn được (fix pointer-capture); lưu/khôi phục layer+nhóm+lựa chọn sau refresh; cài đặt status bar tự lưu (saveBarSettings/restoreBarSettings).
- **Đa chọn & GROUP = 1 đối tượng** (UI-3n→3aj): shift+click toggle nguyên nhóm; marquee chọn nhiều; MultiSelectBar.vue (căn lề 6 hướng · chia đều X/Y · nhóm/tách · tải hàng loạt · xóa) icon Lucide; selectionUnitCount đếm đối tượng; khung bbox TÍM quanh nhóm + ring layer XANH SKY inner (outline offset âm); layerGroups + groupId + rotation/scale cấp group (reset rotation hoàn nguyên vị trí nội bộ, z-order cả nhóm bringUnitTo, thu gọn folder ẩn hết member mở mặc định, Alt+click edit-in-group); kéo-thả ảnh Outputs → canvas.
- **Fix gốc lỗi dai dẳng**: chọn layer panel = ROOT CAUSE truyền nhầm object thay vì id vào selectLayerWithGroup (normalize + truyền l.id) · isSelected từ getter → action (fix TypeError màn hình trắng) · bbox lệch (viewport vs local) · ring-inset bị che sau ảnh → outline.
- **Verify**: vite build ✓ mỗi phiên · grep/diff 100% · deploy SSH + verify live asset HTTP 200.

## Kết quả audit code (tóm tắt)
- Kết quả: `STUDIO_REVIEW.md` — **30 area**, **214 findings** (critical 2 · high 18 · medium 53 · low 98 · info 43), 8 claim dương tính giả đã gạch bỏ kèm bằng chứng. Đối chiếu `grep -cE '^- \*\*\[(critical|high|medium|low|info)\]\*\*'` = **214** ✓ (đo sau khi gộp Phần L). Cấu trúc file hiện: Phần A–L (K = hoàn thiện Quản lý dự án + K.8 current-project/hardening; **L = đợt vá production-refgen: chẩn đoán Hostinger còn code trước 234d405 + refgen 422 `be3cfe7` + fix warning CSS color**, thêm 2026-09-07).
- Footprint module studio: **1.338.134 bytes** = PHP 532.648 (24 file) + JS/Vue 562.602 (34 file) + blade 242.884 (11 file) ≈ 405k token → bắt buộc fan-out.
- Provider dùng: **chỉ `qwen-token-plan`**. Model mặc định `qwen3.8-max`; `glm-5.2` khi cần suy luận liên file; **`kimi-k3`** cho frontend chiều sâu (Vue lifecycle/listener/race/a11y) — smoke đo 2026-09-07: 6/8 đúng · 0/5 bẫy "đã fix" · phồng severity · chi tiết playbook §1.
- Đã phủ: `StudioController.php` (toàn bộ 4.732 dòng) · `store.js` · `ImageAIService` · `ProductAIService` · `GeminiService` · `VirtualTryOnService` · `StylistService` · `StyleSuggestService` · `StudioLibraryService` · `ProjectWorkflowService` · `StylistDataController` · `StylistCatalog` · models+config+migrations+wiring · 5 component lớn (StudioApp, ConceptCard, ComposeCard, RefImageCard, InpaintCard) · 13 component nhỏ + SettingsApp · 6 blade nhỏ · **TOÀN BỘ 11 file blade** (Phần H) → lớp blade 100% · **`CreativeDirectionService`** (Phần I, service cuối) · **19 component JS/Vue còn lại + 4 entry Vite + 2 console command + StylistQuestion** (Phần I) → **module phủ ~100% diện tích** (trừ 2 component mồ côi `SourceCard`/`PaletteTextureCard`, I.1).
## ĐÃ VÁ (phase 2 — điều phối trực tiếp, có test)

- **[done]** Xóa `resources/views/studio/index.blade.php` (140.236 B dead code) — đã xác minh 0 lời gọi `view('studio.index')`; route `studio.index` render `studio.vue`. Khôi phục: `git checkout HEAD -- <path>`.
- **[done]** Path traversal (top-5 #1) — thêm `resolveLocalImage()`/`safeLocalFile()` có containment bằng `realpath()`; **21** site đọc file theo input người dùng đều đi qua nó (buildMaskImage, faceDescription, poseDescription, buildBackgroundMask, downscaleSource, regionEdit, download, palette, assetDestroy, +13 `$rel`). `parse_url` còn 2 (chính helper + assetDestroy đã có containment).
- **[done]** API key rò rỉ (top-5 #2) — `StudioApiKey` thêm `$hidden=['value']` + `setValueAttribute()` mutator mã hóa ở tầng model; bỏ 3 chỗ mã hóa thủ công (settingsSave/storeApiKey/updateApiKey). Đường settings-DB fallback (`:4349`) giữ nguyên.
- **[test]** `tests/Unit/StudioLocalFileContainmentTest.php` (17 test/24 assert) + `tests/Unit/StudioApiKeyEncryptionTest.php` (5 test/13 assert).
- **[verify]** Test: baseline 9 fail / 145 pass → sau vá 9 fail / 167 pass. **0 regression**, +22 test pass.
- **[done]** studioImage() fallback (top-5 #4) — thêm `studioServePath()` với containment `realpath()` + whitelist image/video extension + từ chối dotfile; `studioImage()`/:1748 `studioImageThumb()` giờ gọi helper này. Public endpoint không còn phục vụ `.htaccess`/`.env`/JS bundle.
- **[done]** 41 `imagecreatefromstring` (top-5 #5) — thêm `studio_image_decode()` trong `helpers.php` (giới hạn 64 MiB, auto-detect file path hoặc raw bytes); thay thế cơ học toàn bộ 41 site (StudioController 20 · ImageAIService 15 · ProductAIService 3 · StyleSuggestService 2 · helpers 1).
- **[done]** 6 `getMessage()` leak trong 500 response (P2) — thêm `studio_fail()` helper (ghi log chi tiết, trả message chung, debug-aware); thay thế 6 site (StudioController 2 · StylistDataController 4). Các site 422 (3 site CartApi/CouponApi/ProjectController) giữ nguyên — exception validation có thể chứa thông điệp thân thiện với người dùng.
- **[test]** Unit: 52 passed (126 assertions). Studio feature tests: all pass (StylistData 4/4, Inpaint 7/7). ~~Full suite gặp `ProcessSignaledException` (OOM/timeout hạ tầng, không liên quan đến vá)~~ **ĐÍNH CHÍNH (Phần K.4): thủ phạm là `studio_image_decode()` đệ quy vô hạn do chính đợt vá J #23 gây ra — đã fix 1 dòng, full suite chạy trọn 9 fail baseline / 183 pass.**
- **[done]** P5/P6 (26 catch rỗng frontend) — 22 site nuốt lỗi fetch/parse/localStorage đã thay bằng catch + console.error('studio ... failed', e); giữ nguyên 4 site catch (err) {} của setPointerCapture (pattern chuẩn, cố ý im lặng). Vite build OK.
- **[done]** P7 (fetch không check res.ok) — thêm if (!r.ok) throw trước 17 site .json() nằm trong try/catch (SettingsApp, RefImageCardx2, SourceCard, StylistCard, SourcePanel, SourceLibraryPicker, SwapCardx4, ConceptCardx3, store.js palette); site duy nhất không guard (SwapCard reload assets sau upload) bọc riêng bằng toast lỗi + return. Không đổi hành vi OK-path; lỗi server giờ rơi vào catch hiện có thay vì nuốt thành dữ liệu rỗng. Vite build OK 3.9s.
- **[done]** Project/* (ProjectController, ProjectWorkflowService, Project model, ProjectWorkspace.vue, store.js vùng dự án, seeder, routes, 2 file test) — **HOÀN TẤT bởi phiên kia, kết quả + verdict độc lập ở Phần K** (11/11 finding fixed, 53 test Project pass, 0 regression, UX/UI 9 hạng mục, vite build ✓).
- **[done]** Toàn bộ đợt vá phase 2 đã ghi vào STUDIO_REVIEW.md **Phần J** (J.1–J.8): checklist rà soát không bỏ sót, chi tiết từng vá (#4 studioImage, #3 imagecreatefromstring, P2 getMessage, P5/P6 catch rỗng, P7 res.ok), bảng kiểm chứng, và mục việc còn lại trung thực (15 high/critical chờ tái xác minh + 9 lỗi ShopFlowTest baseline + 2 medium M6/M8 + full-suite ProcessSignaledException + chưa commit).
- **[note]** QUEUE A (7/7) + QUEUE B (5/5) ĐÃ HOÀN TẤT (Phần H + I) — không còn task review tồn đọng; findings tổng = 208; module phủ ~100% diện tích.
- **[done]** `studio_image_decode()` đệ quy vô hạn (helpers.php:246) — **[critical]**, root cause của mọi vụ SIGKILL test suite từ sau J; fix 1 dòng `@imagecreatefromstring`; 5 file test từng chết nay pass (22 test sống lại). Chi tiết + bài học: Phần K.4.
- **[done]** `latestGeneration()` dùng `latestOfMany()` rơi constraint `whereNotNull` — **[high]** regression tự phát hiện+tự vá trong Phần K (ofMany + closure); test regression assert GIÁ TRỊ thumbnail (eager/lazy parity).
- **[note]** Race phiên song song: các commit 85b9f91/af5a86f/f35fd00/**234d405** quét toàn tree trong lúc phiên Project/* đang làm việc → phần lớn công việc Phần K đã nằm trong commit của phiên kia (kể cả fix helpers.php — 234d405, hai phiên phát hiện độc lập cùng bug, fix trùng khớp). ~~Chưa commit: Project.php (ofMany) + ProjectControllerTest.php (test parity) + 2 file báo cáo~~ → **ĐÃ commit hết qua f90c5e9 (05:55:09)** — working tree chỉ còn 2 sửa đổi báo cáo: đồng bộ Tổng kết (Phần J/K + số liệu 210) và chính ghi chú này. Bài học mới: worktree baseline symlink vendor KHÔNG sạch (`__DIR__` resolve về tree chính) — xem K.6.
- **[note]** 2026-09-07 — Đồng bộ Tổng kết `STUDIO_REVIEW.md` (phiên xử lý 500 refgen): danh sách lượt còn thiếu Phần J + Phần K → đã thêm; số liệu đầu file 208→**210 findings** (critical 1→**2** · high 17→**18**, nguồn +2 = K.4). Xác nhận Phần I **đã ghi đầy đủ từ phiên song song** (I.1–I.9, gồm I.8 claim LOẠI + I.9 thống kê hiệu chỉnh) — không ghi đè. Đối chiếu `grep -cE` sau ghi = **210** ✓.
- **[done]** `refgen()` không pre-validate ảnh nguồn (production gen #3 fail 0.04s "Không tạo được ảnh mới từ ảnh tham chiếu.") — **[low]** vá bởi phiên xử lý 500 refgen: pre-validate `resolveLocalImage()` → 422 không tạo generation mồ côi + hoist `downscaleSource()` khỏi vòng lặp variants; commit **`be3cfe7`**; test fixture ảnh thật + `test_refgen_rejects_unresolvable_image` — 8/8 pass. Chi tiết: Phần L.2.
- **[done]** `input[type=color]` bind `''` → Chrome warning "must be a valid CSS color" (`ProjectWorkspace.vue:432`) — **[low]**, fix `:value="form.color || '#4a7a90'"`; nằm trong commit quét tree **`8817f85`** của phiên song song. Chi tiết: Phần L.2.
- **[note]** **Production Hostinger chưa deploy `234d405`** — bằng chứng: stack trace production tới frame **#47290** (đệ quy `studio_image_decode()` K.4 vẫn live trên host). Việc bắt buộc: deploy `234d405` + `be3cfe7` + build assets. Đã ghi Phần L.1.
- **[note]** 2026-09-07 — **Phiên song song đã GỘP `STUDIO_REVIEW.md` thành bản v2 (195 dòng, §0–§7)** — chi tiết Phần A–L nén thành bảng tra cứu; nguyên bản: `git show 8817f85:STUDIO_REVIEW.md` + backup `/tmp/STUDIO_REVIEW_pre-merge_2026-09-07.md`. Việc CÒN MỞ giờ theo dõi ở §1 (T1–T7). Ledger này giữ nguyên format cũ.
- **[done]** **Phiên M — Đồng bộ Quản lý dự án × Outputs + /studio/library + cơ chế áp dụng dự án + icon đồng bộ** (điều phối 4 subagent: A LibraryApp ✓ · B GalleryModal ✓ · C ProjectWorkspace ✓ · D OutputModule+StudioApp ✓; 3/4 fail giữa chừng do provider → resume OK): backend additive (`latest()` + project-detail generations kèm tên dự án · `project_linked_count` · filter `project_id=none`) · store tách `appliedProject` khỏi `activeProject` + `applyProject`/`unapplyProject`/`openViewer(list)`/`viewerList` + attach/detach đồng bộ state · StudioIcon +20 icon · 6 file Vue · **đóng nửa đầu T6** (GalleryModal gắn/gỡ dự án) · vá bug tích hợp: GalleryModal z-70<z-95 bị workspace che · vá test refgen lỗi từ `be3cfe7` (fixture ảnh thật — ShopFlowTest giờ pass). Test mới `tests/Feature/StudioLibraryProjectTest.php` **5/5** · Project 54/54 · full suite **190 pass / 9 fail = baseline** · vite ✓ 781ms · **0 regression**. Toàn bộ code + test + build assets của phiên M đã được phiên song song quét commit vào **`be01615`** (cùng đợt gộp báo cáo v2) — chỉ còn 2 hàng §2/§7 trong STUDIO_REVIEW.md + 2 dòng ledger này chưa commit. Còn lại: move-1-chạm giữa 2 dự án (hiện = gỡ + gắn) · pattern/tryon blade chưa gửi project_id (T6 nửa sau).
- **[note]** 2026-09-07 — **Smoke-test `kimi-k3` (đưa vào quy trình)**: 1 task template mục 5, provider `qwen-token-plan`, scope = 3 file Phần I.4 trên tree hiện tại (GalleryModal 386d/21,9 KB + CanvasMaskTools 227d/14,1 KB + SourcePanel 95d/10,5 KB = 46,5 KB — cài sẵn 5 bẫy "đã fix": res.ok J.6 · toDataURL đã xóa · watch refactor · listener gỡ đủ · catch pointer-capture cố ý). Chi phí: **3 step · 24.772 input · 6.280 output** · 8 finding parse sạch, đúng format 4-field (có dạo đầu trước `AREA:` như glm — parser bao dung phủ). Chấm theo ground truth đã xác minh từng dòng: **6/8 đúng** (M6+M8 đúng cơ chế+dòng · a11y · doDelete không pending · SourcePanel empty-state · **bug MỚI thật trong code phiên M**: `clipboard.writeText` không await `GalleryModal:126`) · **1/8 SAI** ("unhandled rejection" — không đọc `store.deleteGen` catch nội bộ `store.js:963`) · **1/8 bỏ qua comment chủ đích** `:194` · **0/5 bẫy**. Severity phồng (high×2 cho thứ thật medium/low). Đã ghi vào: playbook §1 (bảng + bullet) · §5 (`const KIMI`) · §8 (bẫy cross-file mới + mở rộng bẫy comment) · STUDIO_REVIEW.md §5.2 (+2 low mới, −2 vô hiệu do M refactor) · §6#3,8 · §0 baseline 190/9 · T3/T4/T6/T7 đồng bộ trạng thái phiên M (`be01615`).

## CẬP NHẬT SAU ĐỢT CHẠY CỦA NGƯỜI DÙNG (Phần H)

6/6 task QUEUE A đợt 1 **đã chạy xong và đã xác minh** — kết quả ở `STUDIO_REVIEW.md` Phần H. Hai hệ quả làm thay đổi kế hoạch:

1. **`index.blade.php` là FILE MỒ CÔI — 140 KB dead code.** Route `studio.index` (`routes/web.php:212`) → `StudioController::index()` (`:43-47`) render `studio.vue`, KHÔNG render `studio.index`; grep toàn repo = **0** lời gọi `view('studio.index')`. → **24/41 finding ở Phần H không có tác động runtime.** Việc đáng làm là XÓA file (hoặc đánh dấu `@deprecated`), không phải sửa 24 finding đó.
2. **Claim `env()`/`config:cache` được chứng minh là dương tính giả lần thứ hai, với bằng chứng mạnh hơn**: đường rotation key KHÔNG đi qua env — `studio_api_key()` ưu tiên registry DB mã hóa (`helpers.php:402-404`) → setting DB `api_<service>_key` (`:406-413`) → mới fallback config; `studio_config()` cũng ưu tiên setting DB (`helpers.php:192-194`). Đổi key từ trang Settings có hiệu lực ngay, bất kể `config:cache`.

**BÀI HỌC MỚI (đã đưa vào playbook):** trước khi xếp hàng đợi một file blade/view, phải grep xem nó CÓ ĐƯỢC RENDER không. Đợt này đã tốn 3 task cho 140 KB dead code vì chỉ xếp hạng theo kích thước.

## KHUYẾN NGHỊ — phạm vi tối thiểu (~90% giá trị còn lại với ~25% chi phí)

Bề mặt chưa phủ **rủi ro thấp về cấu trúc**: blade đã phủ 100% (Phần F + H; lớp XSS bị loại, `{!!` = 0), 21 file JS/Vue còn lại là client-side, blast radius thấp. Đáng hạn mức còn lại:

1. ~~`blade-set-1` + `blade-set-2`~~ ✅ **ĐÃ XONG (Phần H)** — xác nhận blade KHÔNG render giá trị key thô; diện rò issue top-5 số 2 là JSON `settingsData()` `:3857` (giữ `high` qua đường đó)
2. `creative-dir` — `CreativeDirectionService.php` 17,7 KB, service duy nhất chưa đọc (task cuối QUEUE A)
3. QUEUE B (5 task đã đo) — chỉ chạy nếu muốn phủ 100% diện tích

→ Round 2: `creative-dir` + 5 task QUEUE B = **vừa đúng 6 task / 1 round**; hoặc chỉ `creative-dir` và dành thời gian round cho việc tái xác minh ở dòng dưới (khuyến nghị cao hơn).

**Ưu tiên cao hơn cả việc review thêm:** tái xác minh **~15 finding `high`/`critical` còn lại** (1 critical + 17 high ban đầu — **2 high đã VÁ** bởi phiên song song: path traversal #1 + API key #2, có test 17+5 assert, 0 regression). Lý do: điều phối vừa tự phát hiện **1 dương tính giả và 1 báo thiếu 4 lần ngay trong top-5** (Phần G) → danh sách ưu tiên hiện tại chưa đủ tin cậy để dùng làm backlog khắc phục. Round 3 (cuối) = host-side re-verify ~15 high/critical bằng `read`.

## QUEUE A — việc CHƯA phủ chắc chắn

Mỗi dòng là 1 task, đã chia theo ngân sách (≤60 KB / ≤1.200 dòng / ≤10 file). Chạy ≤6 task mỗi round.

- [x] `blade-idx-1` · qwen3.8-max · `resources/views/studio/index.blade.php` offset 1 limit 560  ✅ xong (Phần H)
- [x] `blade-idx-2` · qwen3.8-max · `resources/views/studio/index.blade.php` offset 561 limit 560  ✅ xong (Phần H)
- [x] `blade-idx-3` · qwen3.8-max · `resources/views/studio/index.blade.php` offset 1121 limit 506  ✅ xong (Phần H)
- [x] `blade-set-1` · **glm-5.2** · `resources/views/studio/settings.blade.php` offset 1 limit 400 — ưu tiên CAO: liên quan issue top-5 số 2 (API key xuống trình duyệt)  ✅ xong (Phần H)
- [x] `blade-set-2` · **glm-5.2** · `resources/views/studio/settings.blade.php` offset 401 limit 338  ✅ xong (Phần H)
- [x] `blade-misc` · qwen3.8-max · `library.blade.php` (142) + `library-vue.blade.php` (7) + `settings-vue.blade.php` (7)  ✅ xong (Phần H)
- [x] `creative-dir` · qwen3.8-max · `app/Services/CreativeDirectionService.php` (17.710 B / 399 dòng)  ✅ xong (Phần I.2) — 8 finding (low 6 · info 2; 2 medium hạ low)

→ **QUEUE A ✅ HOÀN TẤT** (7/7 task, Phần H + Phần I.2). `LibraryApp.vue` đã phủ Phần A nên loại khỏi queue.

## QUEUE B — ĐÃ ĐO, chia sẵn theo ngân sách

Căn cứ: `grep` tên file trong `STUDIO_REVIEW.md`. **CẢNH BÁO**: đây là proxy yếu — Phần B ghi "đã audit 13 component nhỏ" nhưng **không liệt kê** là file nào, nên một số file dưới đây CÓ THỂ đã được audit mà không bị nêu tên (chỉ file có lỗi mới được cite). Chấp nhận rủi ro review trùng còn hơn bỏ sót.

- [x] `grp-fe-1` · qwen3.8-max · `ContextToolbar.vue` + `ProjectWorkspace.vue`  ✅ xong (Phần I.3) — 7 finding (low 5 · info 2; 2 medium hạ low, 1 medium LOẠI)
- [x] `grp-fe-2` · qwen3.8-max · `GalleryModal.vue` + `CanvasMaskTools.vue` + `SourcePanel.vue`  ✅ xong (Phần I.4) — 8 finding (**medium 2** M6 lifecycle + M8 hijack · low 5 · info 1)
- [x] `grp-fe-3` · qwen3.8-max · `StylistCard` + `RegionTools` + `StudioIcon` + `UpscaleCard` + `OutputModule`  ✅ xong (Phần I.5) — 8 finding (low 5 · info 3; 1 medium hạ low vì `safeLocalFile` đã vá)
- [x] `grp-fe-4` · qwen3.8-max · `useStudioThumb.js` + `DirectorCard` + `CompareSlider` + `LoadingSpinner` + `BaseModal`  ✅ xong (Phần I.6) — 6 finding (low 4 · info 2). **LOẠI khỏi review:** `SourceCard.vue` + `PaletteTextureCard.vue` — coordinator xác minh **MỒ CÔI** (0 import, I.1).
- [x] `grp-misc` · qwen3.8-max · `CleanStudioStorage.php` + `ProcessStudioGenerations.php` + `StylistQuestion.php` + 4 Vite entry  ✅ xong (Phần I.7) — 8 finding (low 7 · info 1; 3 medium hạ low)

→ **QUEUE B ✅ HOÀN TẤT** (5/5 task, Phần I.3–I.7). 2 component mồ côi (`SourceCard`/`PaletteTextureCard`) phát hiện + loại. → **cả QUEUE A + B đều hết [ ]**.

## Tổng khối lượng còn lại (đã đo)

| Lớp | Chưa xác minh | Ghi chú |
|---|---|---|
| blade views | **0 B** | ✅ 100% (Phần F+H; `index` mồ côi đã XÓA bởi phiên song song) |
| JS/Vue | **0 B** | ✅ 100% (Phần A store.js + Phần I 19 component/4 entry; 2 mồ côi loại) |
| PHP | **0 B** | ✅ 100% (Phần I.2 CreativeDirectionService + commands) |
| **Tổng** | **0 B** | ✅ **module phủ ~100% diện tích** — QUEUE A+B hết [ ] |

## Việc KHÔNG cần làm lại (đã kết luận)

- XSS ở lớp blade: grep `{!!` = **0 kết quả** → cả lớp bị loại. Đừng để subagent báo lại.
- `deepseek-official` "bị rate-limit": **đã bác bỏ**. Thủ phạm thật là `schema` + model bọc fence JSON.
- Route studio: đã xác nhận group `[auth, admin, nostore]` tại `routes/web.php:94`, ngoại lệ public tại `:210-221`.
- **`env()` trong `config/*.php` KHÔNG phải lỗi** — đó là pattern chuẩn Laravel (224 vị trí trên toàn bộ config). Lỗi thật của họ này là `env()` NGOÀI config → grep toàn `app/`, `routes/`, `resources/`, `database/` chỉ ra **3 vị trí**, cả 3 vô hại vì nạp qua `mergeConfigFrom()` (`StudioModuleServiceProvider.php:13`). Đã loại khỏi top-5, bằng chứng ở Phần G.
- **`imagecreatefromstring` = 41 vị trí**, KHÔNG phải con số ~10 như báo cáo cũ (`StudioController` 20 · `ImageAIService` 15 · `ProductAIService` 3 · `StyleSuggestService` 2 · `helpers` 1). Pattern lan rộng nhất module → sửa bằng 1 helper dùng chung, không vá lẻ.

## Cập nhật sau mỗi round (bắt buộc)

1. Đánh `[x]` task đã xong, kèm số finding đã xác minh.
2. Cộng số liệu vào "Trạng thái hiện tại" và đối chiếu bằng `grep -c`.
3. Ghi finding vào `STUDIO_REVIEW.md` Phần kế tiếp (**L**, …); gạch bỏ dương tính giả kèm bằng chứng.
4. **K.8 addendum** (current-project chip + backend project_id ownership hardening): đã build + test (54/54 Project pass, vite 739ms). 2 finding mới [medium] DA VA. `attachGenerationToProject` vẫn orphan UI (documented gap).
5. **Chống race phiên song song (bài học đợt Phần G/H):** trước khi ghi, `stat` mtime + đọc lại vùng sắp sửa; sau khi ghi, recount bằng `grep -c` và đối chiếu ledger. Không chạy 2 đợt workflow đồng thời trên cùng artifact.
4. Task mới phát sinh → thêm vào QUEUE, kèm offset/limit đo bằng `wc -lc`.
---

## Phiên N (2026-09-07) — T2 tái xác minh + vá 6 nhóm high/critical · T3–T6 đóng · full suite XANH 210/0

**Điều phối:** workflow 11 agent tái xác minh §3 — LUÂN PHIÊN model (qwen3.8-max → glm-5.2 → deepseek-v4-pro, retry tối đa 2 khi agent trả null; 12 agent start cho 11 item, đúng 1 retry) + 3 subagent nền (T4 · T6 · T3). Điều phối đối chứng 100% verdict high/critical bằng cách đọc lại đúng dòng → **bắt 2 lỗi agent**: S5 verdict SAI (thực CÒN — resolveImagePath ở controller, không containment) · G (T3) gỡ 2 assertion LD+JSON giấu regression SEO thật → khôi phục assertion + vá code (seo()->product() trong StorefrontController::product). G cũng làm mất HEX flags trong @json → thêm lại (chống script-breakout).

**Vá bảo mật (phiên N):** S1 critical → studio_safe_public_file() dùng chung 5 call-site · S3 SSRF → Http client scheme/timeout/cap 50MiB/allowlist host tùy chọn · S4 XSS → e() trong stub HTML · S5 → resolveImagePath qua helper · S8 → scope whitelist 422 · S9 → type=password · S6 residual → vision data-uri qua helper · **mới phát hiện**: cap pixel studio.image_max_pixels (30MP) chống decompression bomb trong studio_image_decode.

**Kết quả:** StudioSecurityFixesTest mới 11/11 · ShopFlowTest 64/64 (từ 9 fail baseline) · ImageFallback 8/8 (cập nhật fake https — file:// bị chặn đúng sau vá S3) · **full suite 210 pass / 0 FAIL — xanh toàn bộ lần đầu** · vite build ✓ · 0 regression.

**Còn mở:** T1 deploy production (việc người dùng) · T7 commit báo cáo · ~18 medium + ~45 low/info (§5) — backlog ưu tiên thấp.

## Phiên O (2026-09-07) — Bezier draw: hiện ngay ở lần nhấp đầu + preview khi kéo · sạch console warning

**Yêu cầu người dùng:** "Điều khiển chưa hoạt động" + flood `getImageData willReadFrequently` từ `_finalizeInpaintBrush` + "hiển thị ngay khi nhấp lần đầu tiên, preview khi nhấp và kéo".

**Gốc rễ (2):**
1. SVG preview path có `v-if="... length > 1"` → **node đầu tiên không render**; neo chỉ được push ở `pathUp` (pointerup) — nên click đầu không thấy gì. Đồng thời `_pathDrag` là property KHÔNG reactive → kéo tay điều khiển không preview sống.
2. `_pathHit` kiểm tra HANDLE trước NODE; handle suy biến (độ dài 0) nằm đúng trên node → click node bắt phải HANDLE lệch, node không kéo được.
3. `_finalizeInpaintBrush` gọi `getImageData` trên ctx không có `willReadFrequently` → cảnh báo lặp.

**Đã sửa (commit `5f8d7bf`):**
- `pathDown`: push node NGAY vào `inpaintPathPoints` (reactivity) → node hiện ở lần nhấp đầu; `pathMove` chỉnh ox/oy/ix/iy của node đó IN-PLACE → tay điều khiển + đường cong preview sống khi kéo. `pathUp` chỉ kết thúc kéo (bỏ push cũ).
- SVG container `> 1` → `> 0`; ẩn tay điều khiển suy biến (`Math.hypot(...)*anchor.w > 2`) để lần nhấp đầu hiện node sạch.
- `_pathHit`: bỏ qua handle có độ dài màn hình ≤4px → kéo node hoạt động đúng (control "hoạt động" trở lại).
- `getContext('2d', { willReadFrequently: true })` ở `_initInpaintBrush` / `attachBrushCanvas` / `_finalizeInpaintBrush` (mask canvas) → hết cảnh báo + đọc mask nhanh hơn.

**Xác minh:** vite build ✓ (789ms) · push `5f8d7bf` · SSH pull + `php artisan optimize:clear` ✓ · asset `app-By6qjKLN.js` + `GalleryModal-CUY1aRgV.js` + manifest = HTTP 200 trên `trillfa.shop`.


## Phiên P (2026-09-07) — Path vẽ theo kiểu Krita: mở đường bao · snap-đóng tự động · bỏ nút "Đóng" · hint ở status bar

**Yêu cầu:** "cải tiến giống krita: không tạo đường bao đóng ngay từ đầu · snap ở điểm bắt đầu để đóng kín · tự đóng hoàn thành vùng chọn rồi bắt đầu vùng khác · thiết kế lại ContextToolbar bỏ nút Đóng (gây hiểu nhầm) · thêm hướng dẫn ở status bar".

**Đã sửa (commit `116fa42`):**
- `pathSmooth(pts, closed=true)`: đang vẽ `closed=false` → **ĐƯỜNG MỞ** (chỉ nối các điểm kế tiếp, KHÔNG quay về đầu); vùng đã đóng vẫn preview kín. Bỏ polygon fill đang vẽ (trước đây hiện vùng đóng sẵn ngay từ đầu).
- **Snap-đóng Krita:** `pathDown` khi ≥3 điểm & nhấp gần node 0 (<22px) → `type:'close'`; `pathUp` nếu KHÔNG kéo → gọi `pathClose()` (bake mask, push region, clear points, chuyển sang 'add' → vẽ vùng mới). Kéo >4px → chuyển thành di chuyển node đầu (vẫn chỉnh được).
- **Hover snap:** thêm `pathHover` (container `@pointermove`) cập nhật `inpaintPathCloseHover` khi gần điểm đầu (<20px). SVG: node 0 phóng to + đổi xanh (#34d399) khi hover, + guide line nét đứt nối điểm cuối → điểm đầu.
- **Bỏ nút "Đóng"** trong ContextToolbar (path) — vì đóng tự động khi quay lại điểm đầu.
- **Hướng dẫn ở status bar:** `CanvasStatusBar` thêm `toolHint` theo `inpaintMaskMode` (path/freehand/rect/brush/magic) — dùng icon `info` mới thêm vào StudioIcon.

**Xác minh:** vite build ✓ (775ms) · push `116fa42` · SSH pull + `optimize:clear` ✓ · asset `app-cGbGBF60.js` + `GalleryModal-C_JwzfFG.js` + manifest = HTTP 200 trên `trillfa.shop`.


## Phiên Q (2026-09-07) — Ctrl+click node → đổi kiểu node (smooth/cusp/sharp)

**Yêu cầu:** "thêm hành động ctrl + click node -> thay đổi kiểu".

**Đã sửa (commit `d0628f6`):**
- `_pathNodeHit(p)`: hit theo TÂM node (không xét tay điều khiển) — dùng cho Ctrl+click.
- `pathDown`: nếu `e.ctrlKey || e.metaKey` → tìm node, gọi `pathSetNodeKind(ni)` rồi return. **Ctrl+click khoảng trống = no-op** (không thêm điểm/không đóng). Ctrl+click node 0 đổi kiểu (không đóng); đóng vẫn là click thường ở node 0.
- `pathSetNodeKind`: xoay vòng **smooth (mượt, `sym:true`, mirror) → cusp (góc cong lệch, `sym:false`) → sharp (góc nhọn, xóa cả 2 tay) → smooth**. Toast xác nhận kiểu.
- Node mới có `kind:'smooth'`.
- `nodeFill`/màu node theo kiểu: smooth=tím #a78bfa · cusp=cam #fb923c · sharp=hồng #f43f5e; node 0 vẫn xanh khi hover snap. Thêm `:title` cho biết kiểu + phím tắt.
- Status bar hint path bổ sung "Ctrl+click node = đổi kiểu (mượt/cusp/nhọn)".

**Xác minh:** vite build ✓ (786ms) · push `d0628f6` · SSH pull + `optimize:clear` ✓ · asset `app-RERzE918.js` + `GalleryModal-BpLe8ZY9.js` + manifest = HTTP 200 trên `trillfa.shop`.


## Phiên R (2026-09-07) — Chỉnh sửa sau khi đóng + tay cầm theo kiểu node

**Yêu cầu:** "đổi kiểu nhưng không xuất hiện tay cầm tương ứng · nên cho người dùng chỉnh sửa khi đã đóng đường bao, đảm bảo vẫn tạo đường bao mới được".

**Đã sửa (commit `4373efa`):**
- **Đổi kiểu → hiện tay cầm:** `pathSetNodeKind` khi chuyển KHỎI 'sharp' (node đang không có tay) gọi `_initHandlesForKind(i, kind)` sinh tay điều khiển MẶC ĐỊNH trỏ về node kế/trước (out hướng next, in hướng prev; smooth mirror, cusp lệch). Chuyển sang 'sharp' vẫn xóa tay.
- **Chỉnh sửa vùng ĐÃ ĐÓNG:** thêm `_pathEditingRegion`, `_regionNodeHit`, `_loadEditRegion`, `_rebakePathRegions`. Khi không đang vẽ (`inpaintPathPoints` rỗng), bấm node của vùng đã đóng → nạp points vào `inpaintPathPoints` để kéo/đổi kiểu/xóa; đóng bởi snap-close → `pathClose` thay thế đúng vùng đi (giữ `_mode` gốc) và **re-bake toàn bộ vùng** (`_rebakePathRegions` clear canvas + fill từng region theo add/subtract). Bấm khoảng trống vẫn tạo đường bao mới.
- Vùng đã đóng giờ hiện **node chấm nhỏ/mờ** (chỉ khi không đang vẽ) để bấm mở lại; mỗi region lưu `_mode` (add/subtract) để re-bake đúng.
- Hint status bar: "…bấm node vùng đã đóng để sửa lại…".

**Xác minh:** vite build ✓ (737ms) · push `4373efa` · SSH pull + `optimize:clear` ✓ · asset `app-I6rNsxwG.js` + `GalleryModal-Dx0-eGGc.js` + manifest = HTTP 200 trên `trillfa.shop`.


## Phiên S (2026-09-07) — Nút "Sửa" khi hover đường bao + ContextToolbar tô sáng nút chọn

**Yêu cầu:** "nên hiện nút sửa khi hover vào vị trí đường bao · làm cho contexToollbar tô sáng các nút khi được chọn".

**Đã sửa (commit `1b00426`):**
- **Nút "Sửa" khi hover đường bao:** store thêm `_pathHoverRegion`; `pathHover` tính vùng đã đóng gần con trỏ (`_regionHoverHit` — khoảng cách con trỏ → từng đoạn nối node, ngưỡng 18px, chỉ khi không đang vẽ điểm mới). CanvasMaskTools render **nút "Sửa" SVG** đặt ở trọng tâm vùng hover, giữ kích thước ổn định trên màn hình (`scale(invScale)`); bấm gọi `enterEditRegion` (nạp vùng để chỉnh). Kích thước nút không phóng to khi zoom.
- **ContextToolbar tô sáng nút chọn:** hằng `on` = `!bg-cream-100 !text-ink-900 ring-2 ring-brand-300 shadow` (dùng `!` để thắng bg cũ, tránh xung đột Tailwind) → mọi toggle/mode đang chọn (Vẽ/Tẩy, add/subtract) nền sáng + vòng brand + bóng, phân biệt rõ với nút chưa chọn (`btn`).

**Xác minh:** vite build ✓ (762ms) · push `1b00426` · SSH pull + `optimize:clear` ✓ · asset `app-PwMHRIGd.js` + `GalleryModal-CFpBKWkc.js` + manifest = HTTP 200 trên `trillfa.shop`.


## Phiên T (2026-09-07) — Nút "Sửa" có icon + độ trễ ẩn · icon +/− vùng chọn chuẩn ngành

**Yêu cầu:** "đã nổi nút sửa nhưng thiếu icon · không thể chọn vì không có độ trễ khi ẩn nút · tạo 2 icon đúng chuẩn ngành cho + vùng chọn | − vùng chọn, dùng cho các tool tương tự kể cả tô sáng khi chọn".

**Đã sửa (commit `69e0bab`):**
- **Nút "Sửa" thêm icon bút chì** (pencil SVG) + giữ chữ "Sửa", đặt cạnh nút.
- **Độ trễ khi ẩn:** `pathHover` giờ dùng `_regionHoverHit` trả `{region, x, y}` (điểm GẦN NHẤT trên đường bao); khi rời vùng → `setTimeout 450ms` rồi mới ẩn (`_pathHoverTimer`), anchor nút tại đúng điểm đang hover → di tới bấm luôn kịp, không biến mất.
- **+ / − vùng chọn:** thêm icon `selectAdd` (square+plus) và `selectSubtract` (square+minus) vào StudioIcon; ContextToolbar dùng cho add/subtract (freehand/path/magic) — các nút này vẫn **tô sáng (ring brand) khi chọn** như trước.

**Xác minh:** vite build ✓ (721ms) · push `69e0bab` · SSH pull + `optimize:clear` ✓ · asset `app-ApSJmrui.js` + `GalleryModal-cWpBsaW-.js` + manifest = HTTP 200 trên `trillfa.shop`.


## Phiên U (2026-09-07) — Nút "Xong" khi chỉnh sửa vùng + sửa tỷ lệ vùng chọn/tô màu

**Yêu cầu:** "khi vào chỉnh sửa nổi thêm nút xông để hoàn thành · tỷ lệ vùng chọn bây giờ không đúng · khi tô màu bị nhỏ hơn".

**Đã sửa (commit `12333b1`):**
- **Nút "Xong" khi chỉnh sửa:** ContextToolbar branch path — khi `_pathEditingRegion >= 0` (đang sửa vùng đã đóng) hiện nút **Xong** (primary, check) gọi `store.pathClose()` để hoàn thành chỉnh sửa (cập nhật vùng + re-bake); ẩn nút "Xong" thoát (confirmInpaintMask) trong lúc sửa để tránh 2 nút "Xong".
- **Sửa tỷ lệ vùng chọn / tô màu nhỏ hơn:** gốc rễ — mask canvas (`_initInpaintBrush`) dùng tỷ lệ `cvImg` (ảnh nguồn) trong khi con trỏ vùng chọn chuẩn hoá theo `frameLayout` (baseW/baseH của LAYER đang active). Khi layer tỷ lệ khác ảnh nguồn → mask bị ép méo → preview/tô màu lệch. Sửa: `_initInpaintBrush` + `brushCanvasSize` ưu tiên tỷ lệ KHUNG LAYER (`frameLayout`) → mask & overlay cùng hệ toạ độ, tô màu đúng kích thước.

**Xác minh:** vite build ✓ (716ms) · push `12333b1` · SSH pull + `optimize:clear` ✓ · asset `app-CKEvp2pM.js` + `GalleryModal-BuP0n266.js` + manifest = HTTP 200 trên `trillfa.shop`.


## Phiên V (2026-09-07) — Đổi tên nút "Xong" (2 mục đích) + mask canvas khớp khung layer 1:1

**Yêu cầu:** "nút xong đang gây hiểu nhầm vì cùng tên nút nhưng 2 mục đích khác nhau · tỷ lệ vùng chọn chưa đúng".

**Đã sửa (commit `f1613a1`):**
- **Đổi tên nút:** nút hoàn thành CHỈNH SỬA vùng đã đóng đổi từ "Xong" → **"Hoàn thành"** (gọi `pathClose` cập nhật vùng + re-bake); nút "Xong" thoát toàn bộ (confirmInpaintMask) giữ nguyên — hết nhầm lẫn 2 mục đích.
- **Tỷ lệ vùng chọn:** `_initInpaintBrush` + `brushCanvasSize` giờ dùng **đúng kích thước khung layer** (`frameLayout` = baseW/baseH, cap 1024) thay vì chỉ lấy tỷ lệ rồi ép 512. Kết quả: **1 px mask = 1 px khung vẽ** → vùng chọn (preview) & màu tô (`inpaintBrushData`) khớp 100% với vùng đã vẽ, không còn lệch/nhỏ hơn.

**Xác minh:** vite build ✓ (774ms) · push `f1613a1` · SSH pull + `optimize:clear` ✓ · asset `app-CtK06bRF.js` + `GalleryModal-W6ypwzMD.js` + manifest = HTTP 200 trên `trillfa.shop`.


## Phiên W (2026-09-07) — Nút "Hoàn thành" xanh + icon · sửa viền mờ khi tô màu

**Yêu cầu:** "đổi nút Xong chỉnh sửa -> 'Hoàn thành' và có icon, tô màu khác · sửa lỗi vùng chọn không che khuất hết khi tô màu (vẫn bị ảnh hưởng nhẹ)".

**Đã sửa (commit `b0ba642`):**
- **Nút "Hoàn thành" (chỉnh sửa vùng):** đổi nhãn thành "Hoàn thành", có icon **check**, và dùng màu **xanh lục** (`confirm` = emerald-600/trắng) — KHÁC hẳn nút "Xong" thoát (kem) → hết nhầm lẫn.
- **Sửa viền mờ khi tô màu:** gốc rễ — mép vùng chọn bị anti-alias (alpha bán trong suốt) → `_buildSelectionAlpha` chuyển thành alpha bán trong suốt, khi tô/xóa màu chỉ phủ ~50% ở mép → lộ ảnh nền một ít. Sửa: **cứng hoá mép (ngưỡng sel≥128)** trong `_buildSelectionAlpha` → alpha nhị phân 0/255, màu tô/xóa/nhân đôi/nâng **che khuất trọn** vùng chọn, không còn viền mờ.

**Xác minh:** vite build ✓ (763ms) · push `b0ba642` · SSH pull + `optimize:clear` ✓ · asset `app-DOIWj1aF.js` + `GalleryModal-Chgr3H0O.js` + manifest = HTTP 200 trên `trillfa.shop`.


## Phiên X (2026-09-07) — Nâng cấp card Sửa ảnh: 1 công cụ Vẽ Mask (path) + nhận mọi ảnh canvas

**Yêu cầu:** "loại bỏ Render đa góc|Model chỉnh sửa (clean) · loại bỏ Chọn vùng, Vẽ tự do, Vẽ mask · tạo Nút Vẽ Mask mới chỉ gọi đúng 1 công cụ 'Vùng chọn bằng đường cong' → vẽ xong & đóng đồng thời lấy vùng chọn làm mask · card nhận tất cả ảnh từ nhiều nguồn/chọn trong canvas".

**Đã sửa (commit `3e056d0`):**
- **Backend:** tách `inpaint(Request, Generation)` → `inpaint()` + `inpaintSource(Request)` + `handleInpaint(Request, ?Generation)`; thêm route `POST /studio/inpaint` (name `studio.inpaint.source`) — source-agnostic, chỉ cần `source_url`, không cần generation cha.
- **Store:** `inpaint()` nguồn = `upscaleSrc` (ảnh đang chọn trên canvas), gọi `/studio/inpaint`; bỏ yêu cầu `previewId`. `pathClose()` khi `inpaintMaskSource==='inpaint'` → **tự finalize mask** (`_inpaintMaskKind='brush'`, `inpaintMaskDone=true`, `inpaintMaskMode='none'`) ngay khi đóng.
- **InpaintCard:** XÓA "Render đa góc" (toàn bộ script + modal), "Model chỉnh sửa" (select), 3 nút "Chọn vùng/Vẽ tự do/Vẽ mask". Thêm **1 nút "Vẽ mask"** (icon penTool) gọi `toggleInpaintMask('path')` duy nhất. "Chỉnh lại" cũng mở lại path. Hiển thị ảnh đang chọn = `activeImg` (`upscaleSrc`), chấp nhận mọi nguồn.
- `canSubmit` giờ chỉ cần `activeImg` + prompt (không cần previewId).

**Xác minh:** php -l ✓ (controller + routes) · vite build ✓ (732ms) · push `3e056d0` · SSH pull + `optimize:clear` ✓ · asset `app-DMqx4PoW.js` + `GalleryModal-DqbqwFah.js` + manifest + `/studio` = HTTP 200 trên `trillfa.shop`.


## Phiên Y (2026-09-07) — Nút "Vẽ mask" đẹp mắt

**Yêu cầu:** "tạo button đẹp mắt cho nút vẽ mask".

**Đã sửa (commit `df118ee`):**
- Nút **Vẽ mask** trong card Sửa ảnh đổi từ seg-btn nhỏ → **nút full-width**:
  - Icon badge (penTool) trong khung bo góc + label đậm + sub-label "Vùng chọn bằng đường cong (Bezier)".
  - Trạng thái **đang vẽ** (path active): **gradient emerald→teal** + chữ trắng + bóng xanh.
  - Trạng thái nghỉ: viền xanh mờ + nền xanh nhạt + hover sáng + bóng + active scale.
  - Label động: "Đang vẽ mask — đóng kín để hoàn tất" khi active.
  - Nút "Bỏ mask" chuyển thành chip nhỏ căn giữa phía dưới.

**Xác minh:** vite build ✓ (813ms) · push `df118ee` · SSH pull + `optimize:clear` ✓ · asset `app--S4b4qMS.js` + manifest = HTTP 200 trên `trillfa.shop`.

