# DELEGATION PLAYBOOK — quy trình giao việc đa-agent (DSH workflow)

> **Đọc file này trước khi điều phối. Đừng khảo sát lại từ đầu.**
> Mọi con số dưới đây đã được đo/xác minh trực tiếp trên máy này (không phải ước đoán).
> Sinh ra từ đợt review module `studio` ngày 2026-09 — kết quả ở `STUDIO_REVIEW.md`.

---

## 1. Chính sách provider HIỆN HÀNH

| | Provider | Model | Context | Trạng thái |
|---|---|---|---|---|
| **Mặc định cho MỌI task** | `qwen-token-plan` | `deepseek-v4-pro` | 262.144 token | ✅ DÙNG |
| Task nặng (đã tạm dừng) | `deepseek-official` | `deepseek-v4-pro` | 1.000.000 token | ⛔ TẠM DỪNG |

**Chỉ giao việc cho `qwen-token-plan`.** Lý do (bằng chứng thực tế):
- Cả hai route đều phục vụ cùng model `deepseek-v4-pro`, khác nhau ở jalur thanh toán:
  `qwen-token-plan` = DashScope compatible-mode (`https://dashscope-intl.aliyuncs.com/compatible-mode/v1`, key `QWEN_TOKEN_PLAN_API_KEY`) → rẻ hơn.
- Lượt 2 của đợt trước: **4/4 task `deepseek-official` thất bại, 2/2 task `qwen-token-plan` thành công**.
- Máy có 8 CPU → workflow chạy **6 agent đồng thời**. Lượt 2 bắn 4 request `deepseek-official` cùng lúc → burst rate-limit / chạm trần hạn mức.
- Toàn đợt: `qwen-token-plan` thành công 5/7 task; `deepseek-official` chỉ 3/9.

Nguồn xác minh: `~/.dsh/settings.yaml` (`llm-pi-ai.providers`, `agent-default-model`).
Context 262.144 là `DEFAULT_CONTEXT_WINDOW` của pi-ai — settings.yaml **không** khai `contextWindow` cho route này.

## 2. Trần của workflow engine (đã xác minh trong code)

| Giới hạn | Giá trị | Ghi chú |
|---|---|---|
| `maxConcurrentAgents` | **6** trên máy này | `min(16, max(1, CPU-2))`, CPU=8 |
| `maxTotalAgents` | 1000 | không phải rào cản thực tế |
| `maxItemsPerCall` | 4096 | số item mỗi `parallel()`/`pipeline()` |

→ **6 agent chạy song song**. Muốn tránh burst: giữ tổng số task mỗi lượt ≤ 6, hoặc chấp nhận xếp hàng.

## 3. Ngân sách ngữ cảnh & quy tắc chia task

Quy đổi: **token ≈ bytes / 3.3** (code đặc, đã kiểm chứng trên các file của module).

Mỗi child agent có 262.144 token, phải trừ đi:
- system prompt + schema tool của child: ~15–25k
- kết quả grep/read trung gian + reasoning + output JSON: ~20–40k

**QUY TẮC (bám sát để không bao giờ tràn ngữ cảnh):**
- 🎯 Mục tiêu: **≤ 60 KB nguồn mỗi task (~18k token)**, tương đương **≤ 1.200 dòng**.
- 🛑 Trần cứng: **≤ 150 KB (~45k token)** — chỉ khi thật cần, vẫn còn >200k headroom.
- File > 60 KB → **BẮT BUỘC chia chunk theo `offset`/`limit`**, không giao nguyên file.
- Không gộp quá 10 file vào một task dù tổng nhỏ (agent dễ lạc phạm vi).
- Nếu chưa chắc kích thước: `stat -c%s <file>` và `wc -l <file>`.

### Bytes/dòng đã đo (dùng để ước lượng nhanh)

| File | Dòng | Bytes | B/dòng |
|---|---|---|---|
| `app/Http/Controllers/StudioController.php` | 4.732 | 244.538 | 51 |
| `resources/js/studio/store.js` | 2.915 | 167.346 | 57 |
| `app/Services/ImageAIService.php` | 1.503 | 78.977 | 52 |
| `app/Services/ProductAIService.php` | 1.197 | 54.252 | 45 |
| `app/Support/helpers.php` | 1.117 | 50.623 | 45 |
| `resources/js/studio/StudioApp.vue` | 579 | 54.829 | 94 |
| `resources/js/studio/components/ConceptCard.vue` | 744 | 50.060 | 67 |

Toàn module studio: PHP 551.842 B + JS/Vue 559.228 B ≈ **1,11 MB ≈ 337k token** → không thể đọc trong 1 ngữ cảnh, bắt buộc fan-out.

## 4. Kế hoạch chia task ĐO SẴN cho module studio (22 task)

### File lớn — chia chunk (offset/limit dùng ngay)

| Task | File | offset | limit | ~KB | ~token |
|---|---|---|---|---|---|
| `ctrl-1` | StudioController.php | 1 | 950 | 49 | 15k |
| `ctrl-2` | StudioController.php | 951 | 950 | 49 | 15k |
| `ctrl-3` | StudioController.php | 1901 | 950 | 49 | 15k |
| `ctrl-4` | StudioController.php | 2851 | 950 | 49 | 15k |
| `ctrl-5` | StudioController.php | 3801 | 950 | 47 | 14k |
| `store-1` | store.js | 1 | 972 | 56 | 17k |
| `store-2` | store.js | 973 | 972 | 56 | 17k |
| `store-3` | store.js | 1945 | 971 | 55 | 17k |
| `imgai-1` | ImageAIService.php | 1 | 752 | 40 | 12k |
| `imgai-2` | ImageAIService.php | 753 | 751 | 39 | 12k |

### File vừa — 1 task mỗi file (đều < 60 KB)

`ProductAIService.php` (1.197d) · `helpers.php` (1.117d) · `StudioApp.vue` (579d) · `ConceptCard.vue` (744d)

### File nhỏ — gộp nhóm (mỗi nhóm ≤ ~1.100 dòng)

| Task | Gồm |
|---|---|
| `grp-tryon` | VirtualTryOnService (533) + StylistService (285) + GeminiService (184) |
| `grp-suggest` | StyleSuggestService (524) + StudioLibraryService (520) |
| `grp-project` | ProjectController (213) + ProjectWorkflowService (201) + ProjectWorkspace.vue (351) |
| `grp-comp-a` | ComposeCard (405) + RefImageCard (361) + LibraryApp.vue (363) |
| `grp-comp-b` | InpaintCard (294) + GalleryModal (278) + CanvasMaskTools (227) + SourceLibraryPicker (218) |
| `grp-comp-c` | ContextToolbar (186) + SwapCard (184) + StylistDataManager (178) + StylistCard (118) + SuggestCard (96) + SourcePanel (95) |
| `grp-config` | config/studio.php + app/Modules/Studio/* + Models (StudioApiKey, StudioModel, StudioAsset, StudioOutfitSetting) + StylistDataController (173) + SettingsApp.vue (72) |
| `grp-misc` | migrations `*studio*` + UpscaleCard, OutputModule, StudioIcon, RegionTools, LoadingSpinner, CompareSlider, SourceCard, DirectorCard, BaseModal, PaletteTextureCard, StylistDataApp |

**22 task / concurrency 6 → ~4 đợt.** Nên chạy 1 đợt (≤6 task) mỗi lần để dễ phát hiện lỗi, thay vì bắn cả 22.

## 5. Template workflow — dán nguyên khối

`meta` (tham số riêng, KHÔNG nằm trong script):

```json
{
  "name": "studio-review",
  "description": "Fan-out read-only review of the TrillfaShop Studio module via qwen-token-plan",
  "phases": [{ "title": "review", "detail": "qwen-token-plan / deepseek-v4-pro" }]
}
```

`script` (plain JS, KHÔNG dùng backtick, KHÔNG dùng Node API/Date/fs):

```js
const ROUTE = { provider: "qwen-token-plan", model: "deepseek-v4-pro" };
const BASE = "/home/anhtuan/DEV/TrillfaShop";

const schema = {
  type: "object",
  properties: {
    area: { type: "string" },
    summary: { type: "string" },
    findings: {
      type: "array",
      items: {
        type: "object",
        properties: {
          severity: { type: "string" },
          category: { type: "string" },
          file: { type: "string" },
          location: { type: "string" },
          description: { type: "string" },
          recommendation: { type: "string" }
        },
        required: ["severity", "description"]
      }
    }
  },
  required: ["area", "summary", "findings"]
};

function promptFor(t) {
  return "READ-ONLY code review of part of the TrillfaShop Studio module (Laravel 11 + Vue 3 AI fashion image studio).\n\n" +
    "Base path: " + BASE + "\n\n" +
    "Read EXACTLY these files/ranges with the read tool (pass offset and limit for ranges):\n" +
    t.files.map(function (f) { return "  - " + f; }).join("\n") + "\n\n" +
    "FOCUS: " + t.focus + "\n\n" +
    "Prioritize: security (SQLi, XSS, path traversal, SSRF, IDOR/authz, secret leakage, mass assignment), correctness, performance (N+1, unbounded image decode), error handling.\n\n" +
    "Rules: cite real line numbers; AT MOST 8 findings ordered by severity; 1-2 short sentences each; never modify any file.\n\n" +
    "Output ONLY a JSON object: { area, summary, findings: [ { severity (critical|high|medium|low|info), category, file, location, description, recommendation } ] }";
}

const tasks = [
  { id: "ctrl-1", phase: "review",
    files: ["app/Http/Controllers/StudioController.php  (offset 1, limit 950)"],
    focus: "generation/edit endpoints in this range: validation, URL-to-file resolution, credit handling" }
  // ... thêm task theo bảng mục 4, giữ mỗi lượt <= 6 task
];

const results = await parallel(tasks.map(function (t) {
  return function () {
    return agent(promptFor(t), { label: t.id, phase: t.phase, schema: schema, provider: ROUTE.provider, model: ROUTE.model });
  };
}));

const failed = tasks.filter(function (t, i) { return !results[i]; }).map(function (t) { return t.id; });
const ok = results.filter(Boolean);
const bySeverity = {};
let n = 0;
for (const r of ok) {
  const l = Array.isArray(r.findings) ? r.findings : [];
  n += l.length;
  for (const f of l) { const s = typeof f.severity === "string" ? f.severity : "info"; bySeverity[s] = (bySeverity[s] || 0) + 1; }
}

return {
  areasReviewed: ok.length, areasRequested: tasks.length, failed: failed,
  totalFindings: n, bySeverity: bySeverity,
  areas: ok.map(function (r) { return { area: r.area, summary: r.summary, findings: Array.isArray(r.findings) ? r.findings : [] }; })
};
```

**Chỉ được dùng các opt của `agent()`:** `label`, `phase`, `schema`, `provider`, `model`.
Opt khác (`effort`, `isolation`, `agentType`) → throw `UNSUPPORTED_OPTION` và **giết cả workflow**.
Schema chỉ hỗ trợ: `type`, `properties`, `required`, `additionalProperties`, `items`, `enum`, `const`, `oneOf`.

## 6. Quy trình điều phối (checklist)

1. Đọc file này. Không khảo sát lại cấu trúc module — đã có ở mục 4.
2. Chọn phạm vi cần làm → lấy task tương ứng trong bảng mục 4 (hoặc tự chia theo quy tắc mục 3).
3. **Giữ ≤ 6 task mỗi lượt** (bằng `maxConcurrentAgents`) để tránh burst.
4. Chạy workflow với `ROUTE` = `qwen-token-plan` / `deepseek-v4-pro`.
5. Kiểm tra `failed[]` trong kết quả. Với mỗi task fail:
   - chia nhỏ hơn nữa (giảm `limit` một nửa) rồi chạy lại **1 lần**;
   - nếu vẫn fail → **điều phối tự làm** bằng `read`/`grep` có chủ đích (đừng delegate lại vô hạn).
6. Gộp kết quả vào báo cáo (mục 7).
7. Xác minh số liệu bằng `grep`, KHÔNG bằng `read` (xem bẫy mục 8).

## 7. Thủ tục gộp báo cáo

- Ghi markdown: mỗi area là một `##`, mỗi finding là `- **[severity]** category · file:line — mô tả`, dòng sau `  - Fix: ...`.
- Giữ phần thân báo cáo cũ bằng cách cắt từ `## ` đầu tiên, rồi nối header mới + thân cũ + section mới.
- Đếm severity để đối chiếu:

```
grep -nE "^- \*\*\[(critical|high|medium|low|info)\]\*\*" STUDIO_REVIEW.md
```

- Phân vùng theo số dòng heading (`# Phần A/B/C`) để đếm riêng từng nguồn thực hiện.
- **Đối chiếu tổng**: tổng đếm được phải bằng `totalFindings` do workflow trả về. Lệch → có finding bị rớt/label sai.

## 8. Bẫy đã gặp (tránh lặp lại)

| Bẫy | Biểu hiện | Cách tránh |
|---|---|---|
| `read()` trần cắt theo dung lượng | file 66 KB / 337 dòng chỉ trả **233 dòng** dù limit mặc định 2000 | đọc `offset`/`limit` tường minh; đếm & xác minh bằng `grep` |
| `agent()` fail trả về `null` **không kèm lý do** | `failed[]` có tên task nhưng không biết vì sao | luôn log `failed[]`; dùng `read`/`grep` tự kiểm tra phạm vi đó |
| Schema validation fail → mất TRỌNG vẹn area | `structured === undefined` → `null` | để `severity` là `string` thường (KHÔNG dùng `enum`); chỉ `required` tối thiểu |
| Phạm vi task quá lớn | 2 task 4.732 dòng + 1 task 21 file fail ở lượt 1 | bám quy tắc ≤60 KB / ≤1.200 dòng / ≤10 file |
| Burst rate-limit | 4 task đồng thời cùng provider fail hết | ≤6 task/lượt; cân nhắc chạy tuần tự khi cần ổn định |
| Script workflow dùng backtick / Node API | throw, chết cả run | chỉ dùng string concat; không `Date`, `fs`, `fetch`, `setTimeout` |
| `meta` viết trong `script` | parse fail | `meta` là tham số riêng của tool call |

## 9. Ngữ cảnh module studio (khỏi phải tìm lại)

- **Bản chất**: AI fashion image-generation studio, INTERNAL only — route group `studio` chạy middleware `[auth, admin, nostore]` (`routes/web.php:94`).
- **Ngoại lệ public**: `/studio` (index), `/studio/image/{path}`, `/studio/image-thumb/{path}`, `/garment/{id}` + `/thumb` (`routes/web.php:210-221`).
- ~90 route, gần như tất cả vào **một** `StudioController` (4.732 dòng).
- Services: `ImageAIService`, `ProductAIService`, `VirtualTryOnService`, `StyleSuggestService`, `StudioLibraryService`, `StylistService`, `GeminiService`, `ProjectWorkflowService`, `CreativeDirectionService`.
- Helper tập trung ở `app/Support/helpers.php` (`studio_config`, `studio_api_key`, `studio_model_candidates`, `studio_candidate_key`, `studio_qwen_*`).
- Module wiring: `app/Modules/Studio/StudioModuleServiceProvider.php` + `StudioBridge.php`; config namespace `studio` và `studio_module` (tách rời — đã ghi nhận là điểm gây nhầm).
- Frontend: Pinia store đơn khối `resources/js/studio/store.js` (2.915 dòng) + ~25 component; 4 entry Vite (`app`, `settings`, `library`, `stylist-data`).
- **5 vấn đề nặng nhất đã tìm ra** (chi tiết trong `STUDIO_REVIEW.md`):
  1. Path traversal → đọc file cục bộ: `buildMaskImage()` :262-268 (`source_url` chỉ validate `string`, không rule `url`, không chặn `..`); cùng pattern ở `faceDescription()`/`poseDescription()` :416-450, reachable từ :928.
  2. API key material xuống trình duyệt: `settingsData()` :3857 + settings view :3925 trả nguyên model `StudioApiKey`; model không có `$hidden`, `value` nằm trong `$fillable`; mã hóa chỉ gọi thủ công ở :3805, :4122, :4142.
  3. `config/studio.php` đọc `env()` lúc parse → sau `config:cache` việc rotation key bị bỏ qua âm thầm.
  4. `studioImage()` :1739-1758 public, fallback `base_path(public_html/$path)` không giới hạn prefix `studio/`.
  5. ~10 chỗ `@imagecreatefromstring(file_get_contents(...))` không kiểm tra kích thước → dễ OOM.
- Điểm tốt sẵn có để tái sử dụng: `assetDestroy()` :1722-1723 có containment check trước `@unlink()`; `studioImage()` :1741 có guard `..` + charset; mọi outbound `Http` đều set `timeout()`.

