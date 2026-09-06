# STUDIO REVIEW — LEDGER TIẾN ĐỘ (bộ nhớ bền bỉ)

> **File này là trạng thái, không phải quy trình.** Quy trình ở `DELEGATION_PLAYBOOK.md`.
> Mỗi goal round / session mới: đọc file này TRƯỚC (chỉ ~4 KB), làm việc, rồi cập nhật lại nó.
> Lý do phải có: goal round cộng dồn trong CÙNG session và compaction có thể shadow round cũ
> (`dsh-goal-round-driver/README.md`: "no fresh agent or copied conversation prefix").

## Trạng thái hiện tại

- Kết quả: `STUDIO_REVIEW.md` — **30 area**, **208 findings** (critical 1 · high 17 · medium 51 · low 96 · info 43), 8 claim dương tính giả đã gạch bỏ kèm bằng chứng (mới nhất: `[medium]` "ProjectWorkspace no user feedback" — Phần I.8, store.js bắt+toast). Đối chiếu `grep -cE '^- \*\*\[(critical|high|medium|low|info)\]\*\*'` = **208** ✓ (đo sau khi gộp Phần I).
- Footprint module studio: **1.338.134 bytes** = PHP 532.648 (24 file) + JS/Vue 562.602 (34 file) + blade 242.884 (11 file) ≈ 405k token → bắt buộc fan-out.
- Provider dùng: **chỉ `qwen-token-plan`**. Model mặc định `qwen3.8-max`; `glm-5.2` khi cần suy luận liên file.
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
- **[test]** Unit: 52 passed (126 assertions). Studio feature tests: all pass (StylistData 4/4, Inpaint 7/7). Full suite gặp `ProcessSignaledException` (OOM/timeout hạ tầng, không liên quan đến vá).
- **[done]** P5/P6 (26 catch rỗng frontend) — 22 site nuốt lỗi fetch/parse/localStorage đã thay bằng catch + console.error('studio ... failed', e); giữ nguyên 4 site catch (err) {} của setPointerCapture (pattern chuẩn, cố ý im lặng). Vite build OK.
- **[done]** P7 (fetch không check res.ok) — thêm if (!r.ok) throw trước 17 site .json() nằm trong try/catch (SettingsApp, RefImageCardx2, SourceCard, StylistCard, SourcePanel, SourceLibraryPicker, SwapCardx4, ConceptCardx3, store.js palette); site duy nhất không guard (SwapCard reload assets sau upload) bọc riêng bằng toast lỗi + return. Không đổi hành vi OK-path; lỗi server giờ rơi vào catch hiện có thay vì nuốt thành dữ liệu rỗng. Vite build OK 3.9s.
- **[note]** Session song song đang sửa Project/* (ProjectController, ProjectWorkflowService, ProjectWorkspace.vue, seeder, ProjectControllerTest, ProjectWorkflowServiceTest) — KHÔNG đụng vào; git diff hiện gộp cả công việc của họ.

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
3. Ghi finding vào `STUDIO_REVIEW.md` Phần kế tiếp (**J**, …); gạch bỏ dương tính giả kèm bằng chứng.
5. **Chống race phiên song song (bài học đợt Phần G/H):** trước khi ghi, `stat` mtime + đọc lại vùng sắp sửa; sau khi ghi, recount bằng `grep -c` và đối chiếu ledger. Không chạy 2 đợt workflow đồng thời trên cùng artifact.
4. Task mới phát sinh → thêm vào QUEUE, kèm offset/limit đo bằng `wc -lc`.
