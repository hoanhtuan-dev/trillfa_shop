# DELEGATION PLAYBOOK — quy trình giao việc đa-agent (DSH workflow)

> **Đọc file này trước khi điều phối. Đừng khảo sát lại từ đầu.**
>
> **Bộ ba file** — đọc theo thứ tự này:
> 1. `STUDIO_REVIEW_PROGRESS.md` — **trạng thái + QUEUE** (~9 KB). Đọc ĐẦU TIÊN khi chạy bền bỉ.
> 2. `DELEGATION_PLAYBOOK.md` — **quy trình** (file này, ~24 KB). Việc dài nhiều lượt → đọc thêm **mục 10**.
> 3. `STUDIO_REVIEW.md` — **kết quả, BẢN GỘP v2** (§0–§7: nhiệm vụ mở T1–T7 · bảng đã vá · high/critical còn lại · backlog một dòng/mục). Bằng chứng nguyên văn Phần A–L: `git show 8817f85:STUDIO_REVIEW.md`.
>
> Mọi con số/nguyên nhân dưới đây đã đo và kiểm chứng bằng smoke-test thật trên máy này.
> Template ở mục 5 **đã chạy 6 lần, 0 fail** (provider `qwen-token-plan`): smoke-test 6/6 · `ctrl-1` nguyên văn 7/7 · 2-model 8/8 + 8/8 · per-task model 8/8 + 7/7 · QUEUE A đợt 1 (Phần H) 6/6 task, 41 finding parse sạch · **`kimi-k3` smoke 1/1** (2026-09-07, scope 3 file Vue 46,5 KB: 8 finding parse sạch, đúng format 4-field, 6/8 đúng · 1/8 sai · 0/5 bẫy "đã fix").

---

## 0. Bài học số 1 (đọc trước tiên)

**KHÔNG dùng `schema` trong `agent()`.** Đây là nguyên nhân thật của 10/16 task fail ở đợt trước — KHÔNG phải hết hạn mức, KHÔNG phải task quá to.

Bằng chứng (log session child `0804ddc5…`, giải nén bằng `zstd -dc`):
- child `turn/end` = `completed`, provider `qwen-token-plan`, đã đọc file, đã sinh đủ findings;
- nhưng model bọc JSON trong hàng rào ```` ```json ```` → engine không trích được `structured` → `agent()` trả về `null` **không kèm lý do**;
- bỏ `schema`, đổi sang contract text thuần → `failed: []`, trả 1970 ký tự, parse sạch 6 finding.

Task càng lớn → JSON output càng dài → xác suất model bọc fence càng cao. Đó là lý do task to fail nhiều hơn (tương quan, không phải nhân quả).

## 1. Chính sách provider

| Provider | Model | Context | Đo thực tế (cùng 1 task nhỏ) | Dùng khi |
|---|---|---|---|---|
| **`qwen-token-plan`** | **`qwen3.8-max`** | 262.144 | 4.817 input · 3.546 output · **2 step** | ✅ **MẶC ĐỊNH** — rẻ nhất, bám format chuẩn |
| **`qwen-token-plan`** | **`glm-5.2`** | 262.144 | 23.076 input · 1.658 output · **6 step** | Task cần **suy luận liên file** (authz/route/config precedence) — tự xác minh, đắt ~4,8× input |
| **`qwen-token-plan`** | **`kimi-k3`** | 262.144 | 24.772 input · 6.280 output · **3 step** (scope KHÁC: 3 file Vue 46,5 KB/708 dòng) | ✅ Review **frontend chiều sâu** (Vue lifecycle/listener leak/race/a11y) — 6/8 finding đúng, cơ chế+dòng chính xác; **phồng severity** (xem bullet dưới) |
| `qwen-token-plan` | `deepseek-v4-pro` | 262.144 | đã chạy tốt ở đợt trước | Dự phòng / task cần output dài |
| `deepseek-official` | `deepseek-v4-pro` | 1.000.000 | — | ⏸ tạm dừng theo chính sách tiết kiệm hạn mức |

- `agent-default-model` hiện là **`qwen-token-plan` / `qwen3.8-max`** → chính điều phối cũng chạy model này.
- Mọi model của route `qwen-token-plan` (hiện **5**, gồm `kimi-k3`) đều **không khai `contextWindow`** → cùng resolve về `defaultContextWindow` = **262.144** → **cùng một ngân sách chia task (mục 3) áp dụng cho tất cả**.
- **Bằng chứng chất lượng (đo được, cùng scope `StylistDataController`):** `qwen3.8-max` khẳng định SAI rằng "`page()` serves the admin UI to any visitor"; thực tế route `/stylist-data` (`routes/web.php:128`) và các route mutating (:192-196) nằm TRONG group `[auth, admin, nostore]`. `glm-5.2` tự đọc `routes/web.php` và mô tả đúng. → **finding `high`/`critical` của `qwen3.8-max` bắt buộc xác minh**; `glm-5.2` đáng tin hơn ở suy luận liên file.
- `glm-5.2` hay thêm **đoạn dạo đầu** trước `AREA:` và **bỏ dấu ` | ` trước `fix:`** (chỉ 3 field) → parser PHẢI bao dung (mục 5). Đừng kết luận model "hỏng" khi parser trượt.
- **`kimi-k3` — bằng chứng đo được (smoke 2026-09-07, scope = Phần I.4 trên tree hiện tại, cài sẵn 5 bẫy "đã fix"):** đúng cơ chế + đúng dòng **6/8** finding — M6 listener leak (`CanvasMaskTools:63-70,149`) · M8 Ctrl+Z hijack (`:142-146`) · a11y modal (`GalleryModal:220`) · `doDelete` không pending flag (`:29-40`) · SourcePanel load-fail → empty state gây hiểu lầm (`:11`) · **tìm ra bug MỚI THẬT trong code phiên M vừa viết**: `clipboard.writeText` không await (`GalleryModal:126`) → toast thành công giả. **0/5 bẫy** (không claim: res.ok thiếu · toDataURL · watch(items) · keydown không gỡ · catch pointer-capture cố ý). Hai lỗi: (1) **1/8 claim SAI** — "unhandled rejection" ở `doDelete`, vì KHÔNG đọc phụ thuộc chéo `store.deleteGen` (catch nội bộ + return `false`, `store.js:963`); (2) bỏ qua **comment chủ đích ngay cạnh** (`GalleryModal:194` "capture để ưu tiên khi modal mở") → bẫy mục 8. Format: đúng 4-field, có **dạo đầu trước `AREA:`** như `glm-5.2` (parser bao dung phủ). **Hiệu chỉnh severity kém** (gán `[high]`×2 cho thứ thật là medium/low, `[low]` cho M8 thật là medium) → bước xác minh điều phối vẫn BẮT BUỘC.
- `qwen-token-plan` = DashScope compatible-mode `https://dashscope-intl.aliyuncs.com/compatible-mode/v1`, key `QWEN_TOKEN_PLAN_API_KEY` (rẻ hơn).
- **Đính chính:** đợt trước tôi quy kết `deepseek-official` bị rate-limit là SAI. Route đó không hỏng; thủ phạm là `schema` (mục 0).
- Credential nằm ở `~/.dsh/.credentials.yaml` (mục `refs:`), KHÔNG nằm trong env của bash tool → đừng chẩn đoán provider bằng `env`.
- Nguồn cấu hình: `~/.dsh/settings.yaml` → `llm-pi-ai.providers`, `agent-default-model`.
- Context 262.144 = `DEFAULT_CONTEXT_WINDOW` của pi-ai, vì settings.yaml không khai `contextWindow` cho route này.

## 2. Trần của workflow engine (đã xác minh trong code)

| Giới hạn | Giá trị | Ghi chú |
|---|---|---|
| `maxConcurrentAgents` | **6** trên máy này | `min(16, max(1, CPU-2))`, máy có 8 CPU |
| `maxTotalAgents` | 1000 | không phải rào cản thực tế |
| `maxItemsPerCall` | 4096 | số item mỗi `parallel()`/`pipeline()` |

→ Chỉ **6 agent chạy song song**. Giữ mỗi lượt ≤ 6 task để kết quả về gọn và dễ quy lỗi.

## 3. Ngân sách ngữ cảnh & quy tắc chia task

Quy đổi: **token ≈ bytes / 3.3** (đã kiểm chứng trên code của module).

Mỗi child có 262.144 token, phải trừ: system prompt + schema tool của child (~38 KB ≈ 12k, đo từ log: `request/header` 38.198 ký tự), cộng kết quả read/grep trung gian, reasoning và output.

**QUY TẮC:**
- 🎯 Mục tiêu: **≤ 60 KB nguồn/task (~18k token)** ≈ **≤ 1.200 dòng**.
- 🛑 Trần cứng: **≤ 150 KB (~45k token)** — vẫn còn >200k headroom.
- File > 60 KB → **chia chunk theo `offset`/`limit`**, không giao nguyên file.
- ≤ 10 file mỗi task (nhiều hơn thì agent lạc phạm vi).
- Giới hạn **≤ 8 finding/task** và ≤ 400 ký tự/finding → output ngắn, ít rủi ro, dễ parse.
- Chưa chắc kích thước thì đo: `stat -c%s <file>`; `wc -l <file>`.

### Bytes/dòng đã đo

| File | Dòng | Bytes | B/dòng |
|---|---|---|---|
| `app/Http/Controllers/StudioController.php` | 4.732 | 244.538 | 51 |
| `resources/js/studio/store.js` | 2.915 | 167.346 | 57 |
| `app/Services/ImageAIService.php` | 1.503 | 78.977 | 52 |
| `app/Services/ProductAIService.php` | 1.197 | 54.252 | 45 |
| `app/Support/helpers.php` | 1.117 | 50.623 | 45 |
| `resources/js/studio/StudioApp.vue` | 579 | 54.829 | 94 |
| `resources/js/studio/components/ConceptCard.vue` | 744 | 50.060 | 67 |

Toàn module: PHP 551.842 B + JS/Vue 559.228 B ≈ **1,11 MB ≈ 337k token** → vượt ngữ cảnh 1 model, bắt buộc fan-out.

## 4. Kế hoạch chia task ĐO SẴN cho module studio (22 task)

### File lớn — chunk (offset/limit dùng ngay, mỗi chunk ~40-56 KB)

| Task | File | offset | limit |
|---|---|---|---|
| `ctrl-1` … `ctrl-5` | StudioController.php | 1 / 951 / 1901 / 2851 / 3801 | 950 (chunk cuối 932) |
| `store-1` … `store-3` | store.js | 1 / 973 / 1945 | 972 (chunk cuối 971) |
| `imgai-1`, `imgai-2` | ImageAIService.php | 1 / 753 | 752 / 751 |

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

**22 task / concurrency 6 → chạy 4 đợt, mỗi đợt ≤ 6 task.**

## 5. Template ĐÃ KIỂM CHỨNG — dán nguyên khối

`meta` (tham số riêng của tool call, KHÔNG nằm trong `script`):

```json
{
  "name": "studio-review",
  "description": "Fan-out read-only review of the TrillfaShop Studio module via qwen-token-plan",
  "phases": [{ "title": "review", "detail": "qwen-token-plan / deepseek-v4-pro" }]
}
```

`script` (plain JS; KHÔNG backtick, KHÔNG Node API/`Date`/`fs`; kết thúc bằng `return`):

```js
const ROUTE = { provider: "qwen-token-plan", model: "qwen3.8-max" }; // mặc định: rẻ nhất
const DEEP = "glm-5.2"; // task cần suy luận liên file: gán `model: DEEP` cho task đó
const KIMI = "kimi-k3"; // task frontend chiều sâu (Vue lifecycle/listener/race/a11y): gán `model: KIMI` — phồng severity, xác minh bắt buộc
const BASE = "/home/anhtuan/DEV/TrillfaShop";

function promptFor(t) {
  return "READ-ONLY code review of part of the TrillfaShop Studio module (Laravel 11 + Vue 3 AI fashion image studio).\n" +
    "Base path: " + BASE + "\n\nRead EXACTLY these files/ranges with the read tool (pass offset and limit for ranges):\n" +
    t.files.map(function (f) { return "  - " + f; }).join("\n") + "\n\n" +
    "FOCUS: " + t.focus + "\n\n" +
    "Prioritize: security (SQLi, XSS, path traversal, SSRF, IDOR/authz, secret leakage, mass assignment), correctness, performance (N+1, unbounded image decode), error handling.\n\n" +
    "OUTPUT FORMAT - plain text, NO markdown fences, NO code blocks, exactly these lines:\n" +
    "AREA: <short label>\n" +
    "SUMMARY: <2-3 sentences>\n" +
    "FINDING: [severity] category | file:location | description | fix: recommendation\n" +
    "Rules: one FINDING line per finding; severity in critical|high|medium|low|info; AT MOST 8, ordered by severity; each line under 400 chars; never use the | character inside a field; cite real line numbers; never modify any file.";
}

const tasks = [
  { id: "ctrl-1", phase: "review",
    files: ["app/Http/Controllers/StudioController.php  (offset 1, limit 950)"],
    focus: "generation/edit endpoints in this range: validation, URL-to-file resolution, credit handling" }
  // task cần kiểm tra authz/route/config liên file thì thêm: , model: DEEP
  // ... thêm task theo bảng mục 4; giữ mỗi lượt <= 6 task
];

const results = await parallel(tasks.map(function (t) {
  return function () {
    return agent(promptFor(t), { label: t.id, phase: t.phase, provider: ROUTE.provider, model: t.model || ROUTE.model });
  };
}));

const items = tasks.map(function (t, i) { return { id: t.id, text: results[i] || null }; });
return {
  areasRequested: tasks.length,
  failed: items.filter(function (x) { return !x.text; }).map(function (x) { return x.id; }),
  items: items
};
```

**Chỉ dùng các opt của `agent()`:** `label`, `phase`, `provider`, `model` (và `schema` — nhưng xem mục 0: đừng dùng).
Opt khác (`effort`, `isolation`, `agentType`) → throw `UNSUPPORTED_OPTION` và **giết cả workflow**.

### Parser host-side (chạy trong `run_code`, SAU khi workflow trả về)

```ts
const r = wf.result;
// BAO DUNG: chấp nhận cả 4-field (qwen3.8-max) lẫn 3-field (glm-5.2 bỏ dấu | trước fix:)
// Đã kiểm chứng: parse 8/8 finding kèm fix trên raw output của CẢ HAI model.
const parse = (text: string) => {
  const area = (text.match(/^AREA:\s*(.+)$/m) || [])[1] || "(unknown)";
  const summary = (text.match(/^SUMMARY:\s*(.+)$/m) || [])[1] || "";
  const findings: any[] = [];
  for (const m of text.matchAll(/^FINDING:\s*\[(\w+)\]\s*(.*)$/gm)) {
    const parts = m[2].split("|").map(s => s.trim());
    const rest = parts.slice(2).join(" | ");
    const split = rest.split(/\s*\bfix:\s*/i);
    findings.push({
      severity: m[1],
      category: parts[0] || "",
      file: parts[1] || "",
      description: (split[0] || "").replace(/\s+/g, " ").trim(),
      recommendation: (split[1] || "").replace(/\s+/g, " ").trim(),
    });
  }
  return { area, summary, findings };
};
const areas = r.items.filter(i => i.text).map(i => ({ id: i.id, ...parse(i.text) }));
const bySeverity = {}; let n = 0;
for (const a of areas) for (const f of a.findings) { n++; bySeverity[f.severity] = (bySeverity[f.severity]||0)+1; }
```

## 6. Quy trình điều phối (checklist)

1. Đọc file này. Không khảo sát lại cấu trúc module — đã có ở mục 4 và 9.
2. Chọn phạm vi → lấy task trong bảng mục 4 (hoặc chia theo quy tắc mục 3).
3. **≤ 6 task mỗi lượt.**
4. Chạy workflow với template mục 5 (KHÔNG `schema`).
5. Parse host-side bằng snippet mục 5. Kiểm tra `failed[]`:
   - task fail → chia `limit` giảm một nửa, chạy lại **1 lần**;
   - vẫn fail → **điều phối tự làm** bằng `read`/`grep` có chủ đích; không delegate lại vô hạn.
6. **XÁC MINH từng finding `high`/`critical` trước khi công bố — BẮT BUỘC, không tùy chọn.** Đọc lại đúng dòng bằng `read`. Lý do (đo được ở đợt kiểm chứng): 3/3 claim high-medium MỚI đều không đúng nguyên văn — 1 phóng đại mức độ, 1 sai cơ chế (lỗi thật nằm ở chỗ khác), 1 dương tính giả. Đồng thời agent hay trích dẫn dòng **ngoài** phạm vi `offset`/`limit` được giao.

## 7. Thủ tục gộp báo cáo

- Mỗi area là một `##`; mỗi finding là `- **[severity]** category · file:line — mô tả`, dòng sau `  - Fix: ...`.
- **Mỗi area PHẢI liệt kê danh sách file đã audit** (kèm `offset`/`limit` nếu là chunk) trong một dòng `Files audited:` ngay dưới heading. KHÔNG ghi gộp kiểu "đã audit 13 component nhỏ". Lý do (lỗi thật đã xảy ra): Phần B ghi số lượng mà không liệt kê → về sau **không thể xác minh** file nào đã phủ, phải dùng `grep` tên file làm proxy yếu và chấp nhận rủi ro review trùng 136 KB frontend.
- Giữ báo cáo cũ: cắt từ `## ` đầu tiên, rồi nối header mới + thân cũ + section mới.
- Đếm và đối chiếu:

```
grep -nE "^- \*\*\[(critical|high|medium|low|info)\]\*\*" STUDIO_REVIEW.md
```

- Tổng đếm được PHẢI bằng tổng finding đã parse. Lệch → có finding rớt hoặc label sai.

## 8. Bẫy đã gặp (đã kiểm chứng)

| Bẫy | Biểu hiện | Cách tránh |
|---|---|---|
| **`schema` + model bọc fence JSON** | child `completed` nhưng `agent()` trả `null`, 10/16 task fail | **bỏ `schema`**, dùng contract text `AREA:/SUMMARY:/FINDING:` + parse host-side |
| `agent()` fail không kèm lý do | chỉ thấy tên task trong `failed[]` | đọc log child bằng `zstd -dc` (mục 6.6) |
| `read()` trần cắt theo DUNG LƯỢNG | file 66 KB/337 dòng chỉ trả **233 dòng** dù limit mặc định 2000 | đọc `offset`/`limit` tường minh; đếm & xác minh bằng `grep` |
| Chẩn đoán provider bằng `env` | key báo NOT SET dù route vẫn chạy | credential ở `~/.dsh/.credentials.yaml`, không phải env của bash tool |
| Phạm vi task quá lớn | output dài → dễ bọc fence, dễ lạc phạm vi | ≤60 KB / ≤1.200 dòng / ≤10 file / ≤8 finding |
| Agent vượt phạm vi `offset`/`limit` | finding trích dẫn dòng ngoài chunk được giao (vd giao 1-950, trích 3186) | luôn `read` lại đúng dòng trước khi tin |
| Finding của subagent sai/phóng đại | 0/3 claim high-medium mới đúng nguyên văn | xác minh 100% finding high/critical; ghi rõ mức độ đã hiệu chỉnh và lý do |
| Tin chẩn đoán của subagent về "credit/auth" | subagent bỏ qua comment nghiệp vụ ngay cạnh code (vd `:1499` "never hard-block on credits" · `kimi-k3` bỏ qua `GalleryModal:194` "capture để ưu tiên khi modal mở") | đọc cả comment/docblock quanh dòng bị chỉ ra |
| Parser quá chặt → tưởng model hỏng | `glm-5.2` bỏ dấu ` | ` trước `fix:` → 0 finding khớp, dù nó vẫn trả đủ 8 finding | dùng parser bao dung mục 5; khi parse ra 0 finding hãy đọc RAW text trước khi kết luận |
| Model khẳng định sai về authz | `qwen3.8-max` bảo route public, thực tế nằm trong group `[auth,admin,nostore]` | giao task authz/route cho `glm-5.2`, hoặc luôn tự đọc `routes/web.php` để xác minh |
| Khẳng định hành vi của hàm phụ thuộc mà KHÔNG đọc nó | `kimi-k3` báo `doDelete` "unhandled rejection" — thực tế `store.deleteGen` catch nội bộ + return `false` (`store.js:963`) | finding về hành vi cross-file: đọc body hàm callee/phụ thuộc trước khi ghi nhận |
| `glm-5.2` báo XSS ở blade | **3/3 claim XSS là dương tính giả** — Blade `{{ }}` tự escape, `@json` có HEX flags, `$service` là mảng hardcode (`StudioController.php:4352`) | trước khi tin claim XSS: grep `{!!` và truy nguồn biến |
| `qwen3.8-max` báo "DDL mỗi request" | `Schema::create` chỉ chạy khi `! $hasAll` (`StylistCatalog.php:30`); mỗi request chỉ tốn 3 lệnh `Schema::hasTable`, và là thiết kế có chủ đích (docblock :18-19) | đọc cả docblock ngay trên hàm trước khi kết luận |
| Mỗi model sai đúng chỗ model kia đúng | `qwen3.8-max` sai authz/DDL, `glm-5.2` sai XSS | **không có model nào đáng tin tuyệt đối** — xác minh là bắt buộc bất kể model |
| Xếp hàng đợi theo KÍCH THƯỚC mà không kiểm tra file có sống không | tốn **3 task cho `index.blade.php` 140 KB** rồi mới phát hiện nó là **dead code** — route `studio.index` render `studio.vue`, grep `view('studio.index')` = 0 → 24/41 finding không có tác động runtime | trước khi queue một view/blade: grep `view('<tên>')` và `View::make` để chắc nó được render. File mồ côi → đề xuất XÓA, đừng review |
| Hai quy trình chạy song song trên cùng file | trùng heading `Phần G`, ledger không được cập nhật (0/12 dù 6 task đã xong), header lệch 120 vs 162 | trước khi chạy: đọc ledger; sau khi chạy: cập nhật ledger + recount bằng `grep`. Đừng chạy 2 đợt đồng thời trên cùng artifact |
| Script dùng backtick hoặc Node API | throw, chết cả run | chỉ string concat; không `Date`, `fs`, `fetch`, `setTimeout` |
| `meta` viết bên trong `script` | parse fail | `meta` là tham số riêng của tool call |
| Quoting bash trong `run_code` | `${#${v}}` → `ReferenceError` | viết script ra file `/tmp/*.cjs` rồi chạy, tránh inline phức tạp |

## 9. Ngữ cảnh module studio (khỏi phải tìm lại)

- **Bản chất**: AI fashion image-generation studio, INTERNAL only — route group `studio` chạy middleware `[auth, admin, nostore]` (`routes/web.php:94`).
- **Ngoại lệ public**: `/studio` (index), `/studio/image/{path}`, `/studio/image-thumb/{path}`, `/garment/{id}` + `/thumb` (`routes/web.php:210-221`).
- ~90 route, gần như tất cả vào **một** `StudioController` (4.732 dòng).
- Services: `ImageAIService`, `ProductAIService`, `VirtualTryOnService`, `StyleSuggestService`, `StudioLibraryService`, `StylistService`, `GeminiService`, `ProjectWorkflowService`, `CreativeDirectionService`.
- Helper tập trung ở `app/Support/helpers.php` (`studio_config`, `studio_api_key`, `studio_model_candidates`, `studio_candidate_key`, `studio_qwen_*`).
- Module wiring: `app/Modules/Studio/StudioModuleServiceProvider.php` + `StudioBridge.php`; config namespace `studio` và `studio_module` tách rời (đã ghi nhận là điểm gây nhầm).
- Frontend: Pinia store đơn khối `resources/js/studio/store.js` (2.915 dòng) + ~25 component; 4 entry Vite (`app`, `settings`, `library`, `stylist-data`).
- **Blade views — lớp TRƯỚC ĐÂY BỊ BỎ SÓT khi kiểm kê**: `resources/views/studio/` tổng **243 KB / 2.841 dòng**. `index.blade.php` 1.626 dòng/140 KB · `settings.blade.php` 738 dòng/68 KB (**liên quan trực tiếp issue top-5 số 2**) · `library.blade.php` 142/12 KB · `presets` 95 · `api` 72 · `tryon` 62 · `pattern` 47 · `vue` 35 · `stylist-data` 10 · 2 file `-vue` 7 dòng. Đã review **TOÀN BỘ 11 file** (Phần F: 6 nhỏ; Phần H: `index` ×3 chunk, `settings` ×2 chunk, `library` + 2 shell). **PHÁT HIỆN PHẦN H: `index.blade.php` (140 KB) là FILE MỒ CÔI** — route `studio.index` render `studio.vue` (`StudioController.php:43-47`), grep toàn repo = 0 lời gọi `view('studio.index')` → 24 finding trên nó không có tác động runtime; đề xuất XÓA file. `settings.blade.php` KHÔNG render giá trị key thô (chỉ metadata `:458,:479` + `key_prefix` `:713`) → issue top-5 số 2 giữ `high` qua đường JSON `settingsData()` `:3857`.
- Lớp blade **không có `{!!` nào** (grep = 0 kết quả), dùng `{{ }}` (escape mặc định) và `@json` (Laravel bật sẵn `JSON_HEX_TAG|HEX_APOS|HEX_AMP|HEX_QUOT`) → **lớp XSS-blade gần như bị loại**; đừng để subagent báo lại.
- `app/Services/StylistCatalog.php` (287 dòng): `ensureTables()` tự tạo bảng + seed cho shared hosting không chạy được `artisan migrate`; `savePreset()` :248-264 có race check-then-act và nuốt lỗi.
- **5 vấn đề nặng nhất đã tìm ra** (chi tiết trong `STUDIO_REVIEW.md`):
  1. Path traversal → đọc file cục bộ: `buildMaskImage()` :262-268 (`source_url` chỉ validate `string`, không rule `url`, không chặn `..`); cùng pattern ở `faceDescription()`/`poseDescription()` :416-450, reachable từ :928.
  2. API key material xuống trình duyệt: `settingsData()` :3857 + settings view :3925 trả nguyên model `StudioApiKey`; model không có `$hidden`, `value` nằm trong `$fillable`; mã hóa chỉ gọi thủ công ở :3805, :4122, :4142.
  3. **[medium] Giải mã ảnh không giới hạn kích thước — 41 vị trí** (đính chính từ '~10', đếm lại bằng grep: `StudioController` 20 · `ImageAIService` 15 · `ProductAIService` 3 · `StyleSuggestService` 2 · `helpers` 1) → sửa bằng 1 helper dùng chung. *(Item cũ số 3 "`config/studio.php` đọc `env()`" = **DƯƠNG TÍNH GIẢ đã LOẠI** — bằng chứng: Phần G + H.0; rotation key là DB-first qua `helpers.php:402-413,192-194`.)*
  4. `studioImage()` :1739-1758 public, fallback `base_path(public_html/$path)` không giới hạn prefix `studio/`.
  5. **[medium] Trả nguyên văn `$e->getMessage()` cho client — 12 vị trí / 5 controller** (`StudioController` 5 · `StylistDataController` 4 · `CouponApiController` 1 · `CartApiController` 1 · `ProjectController` 1).
- Điểm tốt sẵn có để tái sử dụng: `assetDestroy()` :1722-1723 có containment check trước `@unlink()`; `studioImage()` :1741 có guard `..` + charset; mọi outbound `Http` đều set `timeout()`.

## 10. Vận hành bền bỉ (goal rounds) — cơ chế ĐÃ XÁC MINH trong source DSH

**Dùng khi:** mục tiêu dài cần tự chạy tiếp nhiều lượt (vd phủ hết 1,34 MB module studio). **Không dùng** cho việc 1 lượt.
Nguồn xác minh: `dsh-goal-round-driver/README.md`, `dsh-tool-goal/README.md`, `dsh-goal/lib/index.js`.

| Sự kiện | Hành vi THẬT (đọc từ source) |
|---|---|
| Agent idle + goal `active` & `armed` + còn capacity | driver checkpoint mutation đang chờ → reserve `roundsStarted + 1` → queue 1 prompt `<goal_round>` |
| Round được tính | chỉ khi `user/message` thực sự đi vào; reservation bị stale **không** tốn round |
| Tin nhắn của người | **không** tốn round cap; việc tự động **nhường** khi có việc của người |
| `complete`/`blocked` trong round tự động | gọi `concludeTurn()` → turn dừng ngay sau step đó |
| `blocked` | bị chặn cơ học tới `blockedAfterConsecutiveRounds = 3`; reason lưu code `model-reported` |
| `resume` | **fail** nếu `roundsStarted >= maxGoalRounds` ("exhausted round budget") → phải `edit` nâng cap trước |
| Session resume / fork / plugin load lại | activation **không** kế thừa → goal bị disarm; cần người yêu cầu rồi `update_goal action=resume` |
| Cancel một round | driver **pause** goal để không tự chạy lại |
| Lỗi provider/persistence tạm thời | **không tự retry** — cần người resume |
| Round cap | **không** phải ngân sách tài nguyên: token/tiền/quota là chính sách độc lập, không map vào blocker code |
| Evaluator độc lập | **không có** — model tự quyết định đã đủ bằng chứng chưa |

### 4 hệ quả vận hành phải nhớ

1. Round cộng dồn trong **cùng session** ("no fresh agent or copied conversation prefix"), compaction có thể shadow round cũ → **trạng thái phải nằm ở file**: `STUDIO_REVIEW_PROGRESS.md` (~4,6 KB). Đừng tin trí nhớ hội thoại.
2. Goal **không tự dừng vì hết tiền/quota** → tự đặt ngân sách cứng: **mỗi round = 1 lượt workflow ≤ 6 task**.
3. **Subagent KHÔNG có quyền** create/edit/pause/resume goal (chỉ root agent + human authority) → đừng giao việc quản lý goal cho child.
4. "Còn việc hữu ích" **không** phải blocked. Chỉ blocked khi cùng một điều kiện chặn lặp lại **≥3 round liên tiếp**, và phải mô tả điều kiện cụ thể trong `blocked_reason`.

### Quy trình 1 round chuẩn

1. `read` `STUDIO_REVIEW_PROGRESS.md` (trạng thái + QUEUE) — chỉ ~1,4k token.
2. Lấy **≤6 task ĐẦU TIÊN** còn `[ ]` ở QUEUE A.
3. Chạy 1 workflow bằng template mục 5, gán `model` theo cột trong ledger.
4. Parse bằng parser **bao dung** mục 5. `failed[]` → halve `limit`, retry đúng 1 lần.
5. **Xác minh 100% finding `high`/`critical`** bằng `read` (mục 6 bước 6).
6. Ghi finding đã xác minh vào `STUDIO_REVIEW.md` Phần kế tiếp; gạch bỏ dương tính giả kèm bằng chứng.
7. Cập nhật ledger: đánh `[x]`, cộng số liệu (đối chiếu `grep -c`), thêm task mới phát sinh.
8. Còn `[ ]` → **để goal active**, KHÔNG gọi `complete`.

### Câu khởi động (người dán, một lần)

```
Đọc DELEGATION_PLAYBOOK.md và STUDIO_REVIEW_PROGRESS.md rồi tiếp tục quét module studio.
Tạo goal chạy bền bỉ: mỗi round tối đa 1 lượt workflow ≤6 task, chỉ dùng provider qwen-token-plan,
xác minh mọi finding high/critical trước khi ghi vào STUDIO_REVIEW.md.
```

`max_goal_rounds` gợi ý: **3** cho QUEUE A (8 task ÷ 6 = 2 round + 1 dự phòng). QUEUE B phải xác nhận trước nên đừng tính vào.

### Goal rounds vs Ralph vs subagent thường

- **Goal rounds** (mặc định): giữ nguyên ngữ cảnh tích luỹ trong session → hợp việc review cần đối chiếu phát hiện cũ.
- **`ralph`**: mỗi round là child MỚI, không seed hội thoại, workspace làm bộ nhớ → hợp việc cần góc nhìn tươi. **Chỉ dùng khi người dùng yêu cầu rõ "Ralph".**
- **`subagent` thường**: việc 1-2 lần delegate, có kết quả rồi thôi → đừng tạo goal.


