# STUDIO REVIEW — LEDGER TIẾN ĐỘ (bộ nhớ bền bỉ)

> **File này là trạng thái, không phải quy trình.** Quy trình ở `DELEGATION_PLAYBOOK.md`.
> Mỗi goal round / session mới: đọc file này TRƯỚC (chỉ ~4 KB), làm việc, rồi cập nhật lại nó.
> Lý do phải có: goal round cộng dồn trong CÙNG session và compaction có thể shadow round cũ
> (`dsh-goal-round-driver/README.md`: "no fresh agent or copied conversation prefix").

## Trạng thái hiện tại

- Kết quả: `STUDIO_REVIEW.md` — **23 area**, **162 findings** (critical 1 · high 17 · medium 49 · low 63 · info 32), 7 claim dương tính giả đã gạch bỏ kèm bằng chứng (mới nhất: `[high]` config `env()` — bằng chứng ở Phần G + H.0). Đối chiếu `grep -cE '^- \*\*\[(critical|high|medium|low|info)\]\*\*'` = **162** ✓ (đo sau khi gộp Phần H).
- Footprint module studio: **1.338.134 bytes** = PHP 532.648 (24 file) + JS/Vue 562.602 (34 file) + blade 242.884 (11 file) ≈ 405k token → bắt buộc fan-out.
- Provider dùng: **chỉ `qwen-token-plan`**. Model mặc định `qwen3.8-max`; `glm-5.2` khi cần suy luận liên file.
- Đã phủ: `StudioController.php` (toàn bộ 4.732 dòng) · `store.js` · `ImageAIService` · `ProductAIService` · `GeminiService` · `VirtualTryOnService` · `StylistService` · `StyleSuggestService` · `StudioLibraryService` · `ProjectWorkflowService` · `StylistDataController` · `StylistCatalog` · models+config+migrations+wiring · 5 component lớn (StudioApp, ConceptCard, ComposeCard, RefImageCard, InpaintCard) · 13 component nhỏ + SettingsApp · 6 blade nhỏ · **TOÀN BỘ 11 file blade còn lại** (Phần H: `index.blade.php` — phát hiện **MỒ CÔI/dead code**, `settings.blade.php` ×2 chunk, `library.blade.php` + 2 vue shell) → **lớp blade phủ 100%**.

## ĐÃ VÁ (phase 2 — điều phối trực tiếp, có test)

- **[done]** Xóa `resources/views/studio/index.blade.php` (140.236 B dead code) — đã xác minh 0 lời gọi `view('studio.index')`; route `studio.index` render `studio.vue`. Khôi phục: `git checkout HEAD -- <path>`.
- **[done]** Path traversal (top-5 #1) — thêm `resolveLocalImage()`/`safeLocalFile()` có containment bằng `realpath()`; **21** site đọc file theo input người dùng đều đi qua nó (buildMaskImage, faceDescription, poseDescription, buildBackgroundMask, downscaleSource, regionEdit, download, palette, assetDestroy, +13 `$rel`). `parse_url` còn 2 (chính helper + assetDestroy đã có containment).
- **[done]** API key rò rỉ (top-5 #2) — `StudioApiKey` thêm `$hidden=['value']` + `setValueAttribute()` mutator mã hóa ở tầng model; bỏ 3 chỗ mã hóa thủ công (settingsSave/storeApiKey/updateApiKey). Đường settings-DB fallback (`:4349`) giữ nguyên.
- **[test]** `tests/Unit/StudioLocalFileContainmentTest.php` (17 test/24 assert) + `tests/Unit/StudioApiKeyEncryptionTest.php` (5 test/13 assert).
- **[verify]** Test: baseline 9 fail / 145 pass → sau vá 9 fail / 167 pass. **0 regression**, +22 test pass.

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

**Ưu tiên cao hơn cả việc review thêm:** tái xác minh **18 finding `high`/`critical`** đang có (1 critical + 17 high — đếm bằng grep sau Phần H). Lý do: điều phối vừa tự phát hiện **1 dương tính giả và 1 báo thiếu 4 lần ngay trong top-5** (Phần G) → danh sách ưu tiên hiện tại chưa đủ tin cậy để dùng làm backlog khắc phục.

## QUEUE A — việc CHƯA phủ chắc chắn

Mỗi dòng là 1 task, đã chia theo ngân sách (≤60 KB / ≤1.200 dòng / ≤10 file). Chạy ≤6 task mỗi round.

- [x] `blade-idx-1` · qwen3.8-max · `resources/views/studio/index.blade.php` offset 1 limit 560  ✅ xong (Phần H)
- [x] `blade-idx-2` · qwen3.8-max · `resources/views/studio/index.blade.php` offset 561 limit 560  ✅ xong (Phần H)
- [x] `blade-idx-3` · qwen3.8-max · `resources/views/studio/index.blade.php` offset 1121 limit 506  ✅ xong (Phần H)
- [x] `blade-set-1` · **glm-5.2** · `resources/views/studio/settings.blade.php` offset 1 limit 400 — ưu tiên CAO: liên quan issue top-5 số 2 (API key xuống trình duyệt)  ✅ xong (Phần H)
- [x] `blade-set-2` · **glm-5.2** · `resources/views/studio/settings.blade.php` offset 401 limit 338  ✅ xong (Phần H)
- [x] `blade-misc` · qwen3.8-max · `library.blade.php` (142) + `library-vue.blade.php` (7) + `settings-vue.blade.php` (7)  ✅ xong (Phần H)
- [ ] `creative-dir` · qwen3.8-max · `app/Services/CreativeDirectionService.php` (17.710 B / 399 dòng)

→ **7 task** ≈ 245.045 B. (`LibraryApp.vue` ĐÃ được phủ — Phần A dòng 218 cite finding tại line 56 — nên đã loại khỏi queue.)

## QUEUE B — ĐÃ ĐO, chia sẵn theo ngân sách

Căn cứ: `grep` tên file trong `STUDIO_REVIEW.md`. **CẢNH BÁO**: đây là proxy yếu — Phần B ghi "đã audit 13 component nhỏ" nhưng **không liệt kê** là file nào, nên một số file dưới đây CÓ THỂ đã được audit mà không bị nêu tên (chỉ file có lỗi mới được cite). Chấp nhận rủi ro review trùng còn hơn bỏ sót.

- [ ] `grp-fe-1` · qwen3.8-max · `ContextToolbar.vue` (22.992 B/186 d) + `ProjectWorkspace.vue` (20.910 B/351 d) = 43.902 B
- [ ] `grp-fe-2` · qwen3.8-max · `GalleryModal.vue` (16.634/278) + `CanvasMaskTools.vue` (14.114/227) + `SourcePanel.vue` (10.436/95) = 41.184 B
- [ ] `grp-fe-3` · qwen3.8-max · `StylistCard.vue` (8.594/118) + `RegionTools.vue` (8.311/53) + `StudioIcon.vue` (7.344/54) + `UpscaleCard.vue` (6.261/62) + `OutputModule.vue` (4.014/61) = 34.524 B
- [ ] `grp-fe-4` · qwen3.8-max · `useStudioThumb.js` (3.374/75) + `SourceCard.vue` (3.137/34) + `DirectorCard.vue` (2.535/28) + `CompareSlider.vue` (2.259/38) + `LoadingSpinner.vue` (2.227/49) + `BaseModal.vue` (930/15) + `PaletteTextureCard.vue` (813/12) = 15.275 B
- [ ] `grp-misc` · qwen3.8-max · `CleanStudioStorage.php` (3.390) + `ProcessStudioGenerations.php` (952) + `StylistQuestion.php` (231) + `app.js` (713) + `library.js` (462) + `settings.js` (182) + `stylist-data.js` (195) = 6.125 B

→ **5 task** ≈ 141.010 B. `StylistGarmentType.php`/`StylistPreset.php` đã được nêu trong báo cáo → không vào queue.

## Tổng khối lượng còn lại (đã đo)

| Lớp | Chưa xác minh | Ghi chú |
|---|---|---|
| blade views | **0 B** | ✅ phủ 100% (Phần F + Phần H) — `index` 140 KB là **dead code mồ côi**, đề xuất XÓA |
| JS/Vue | **136.437 B** / 21 file | `store.js` 167 KB đã phủ ở Phần A (đã trừ ra) |
| PHP | **22.283 B** / 4 file | `CreativeDirectionService` chiếm 17,7 KB |
| **Tổng** | **158.720 B ≈ 48k token ≈ 12% module** | QUEUE A (1) + QUEUE B (5) = **6 task = 1 round** |

## Việc KHÔNG cần làm lại (đã kết luận)

- XSS ở lớp blade: grep `{!!` = **0 kết quả** → cả lớp bị loại. Đừng để subagent báo lại.
- `deepseek-official` "bị rate-limit": **đã bác bỏ**. Thủ phạm thật là `schema` + model bọc fence JSON.
- Route studio: đã xác nhận group `[auth, admin, nostore]` tại `routes/web.php:94`, ngoại lệ public tại `:210-221`.
- **`env()` trong `config/*.php` KHÔNG phải lỗi** — đó là pattern chuẩn Laravel (224 vị trí trên toàn bộ config). Lỗi thật của họ này là `env()` NGOÀI config → grep toàn `app/`, `routes/`, `resources/`, `database/` chỉ ra **3 vị trí**, cả 3 vô hại vì nạp qua `mergeConfigFrom()` (`StudioModuleServiceProvider.php:13`). Đã loại khỏi top-5, bằng chứng ở Phần G.
- **`imagecreatefromstring` = 41 vị trí**, KHÔNG phải con số ~10 như báo cáo cũ (`StudioController` 20 · `ImageAIService` 15 · `ProductAIService` 3 · `StyleSuggestService` 2 · `helpers` 1). Pattern lan rộng nhất module → sửa bằng 1 helper dùng chung, không vá lẻ.

## Cập nhật sau mỗi round (bắt buộc)

1. Đánh `[x]` task đã xong, kèm số finding đã xác minh.
2. Cộng số liệu vào "Trạng thái hiện tại" và đối chiếu bằng `grep -c`.
3. Ghi finding vào `STUDIO_REVIEW.md` Phần kế tiếp (**I**, …); gạch bỏ dương tính giả kèm bằng chứng.
5. **Chống race phiên song song (bài học đợt Phần G/H):** trước khi ghi, `stat` mtime + đọc lại vùng sắp sửa; sau khi ghi, recount bằng `grep -c` và đối chiếu ledger. Không chạy 2 đợt workflow đồng thời trên cùng artifact.
4. Task mới phát sinh → thêm vào QUEUE, kèm offset/limit đo bằng `wc -lc`.
