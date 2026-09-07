# STUDIO UI REDESIGN — "/studio" Designer Workspace (2026-09-07)

> **Mục tiêu:** đồng bộ phong cách + icon toàn bộ "/studio"; biến canvas + layers thành
> không gian làm việc kiểu công cụ thiết kế chuyên nghiệp (Figma/Photoshop) — đủ tin cậy
> cho môi trường sản xuất. File này là SPEC DUY NHẤT cho các agent triển khai.

## 1. Phân tích hiện trạng (đã kiểm chứng trên working tree + build thật)

### 1.1 Icon đang lệch phong cách — 3 hệ thống song song

| Nơi | Vấn đề | Bằng chứng |
|---|---|---|
| `StudioApp.vue` | **27 inline SVG** trùng lặp icon đã có trong `StudioIcon.vue` (undo/redo/trash/eye/lock/chevron/pencil/download/save/layers/move/flip...) + emoji `🔒 🎨 ☰ ✕ ⚙️ ⚠️ 🚫 −` | grep `<svg` = 27 |
| `ContextToolbar.vue` | **29 inline SVG**, 0 StudioIcon | grep = 29 |
| `RegionTools.vue` | **8 inline SVG**, 0 StudioIcon | grep = 8 |
| `SourcePanel.vue` | **12 inline SVG** | grep = 12 |
| `SourceLibraryPicker.vue` | **10 inline SVG** | grep = 10 |
| `CanvasMaskTools.vue` | 2 thẻ `<svg>` ở dòng ~176/~186 là **overlay dữ liệu mask** (render vùng chọn) — KHÔNG phải icon, GIỮ NGUYÊN | read |
| `GalleryModal.vue` | emoji `⚠` confirm xóa, nút `−`/`+` zoom | read |
| `SwapCard.vue` | nhiều emoji — **component đang ẨN** ([SWAP TẠM ẨN] trong StudioApp) → không đụng | comment StudioApp:8 |
| `ConceptCard.vue` | danh sách `emoji:` kiểu tóc = **dữ liệu nội dung** (picker), giữ nguyên | read :333-373 |

Quy ước chuẩn (đã có sẵn): `StudioIcon.vue` = Lucide-style 24×24, stroke 2, round caps, `v-html` path.
Session M đã đưa 20 icon project-management vào cùng hệ này → mở rộng tiếp, KHÔNG tạo hệ mới.

### 1.2 Lỗ hổng CSS production (nghiêm trọng hơn icon)

Theme `resources/css/app.css @theme` chỉ định nghĩa `ink-500/700/800/900`, `cream-50/100/200/300`:
- `ink-600` — **dùng 56 lần** trong studio SFC (border-ink-600 ×29, bg-ink-600 ×26, text-ink-600 ×1) → **KHÔNG sinh CSS** → viền/nền mất hẳn trên production.
- `ink-950` — dùng 4 lần (gồm root `StudioApp.vue:247` `bg-ink-950`) → không sinh CSS (kiểm chứng: grep `bg-ink-950` trong `public_html/build/assets/app-*.css` = **0** sau `npm run build` thật).
- `cream-400` (`text-cream-400`) — không định nghĩa.
- `z-32` — hợp lệ trong Tailwind v4 (bare value → `z-index:32`), KHÔNG phải lỗi (đã kiểm chứng trong build).
- `@source` trong app.css thiếu `../js/studio` NHƯNG v4 auto-detect vẫn quét (không có `source(none)`) → class thông thường vẫn sinh; chỉ class thuộc **màu chưa định nghĩa** mới chết. Không cần sửa @source.

**Fix (điều phối làm):** thêm `--color-ink-600/ink-950/cream-400` vào `@theme`. Tuyệt đối KHÔNG thêm ink-100..400 (storefront có `.studio-dark .text-ink-300` override — thêm scale sáng sẽ sinh utility mới ngoài ý muốn trên storefront).

### 1.3 UX canvas + layers hiện tại — chưa đạt chuẩn workspace

1. **Panel Layers là popover nổi** `w-64` góc phải canvas (StudioApp :425-452): chữ 10px, hành động chỉ hiện khi hover, `max-h-44` cuộn chật, đè lên nội dung canvas.
2. **Transform tách rời** thành hộp nổi thứ hai (:454-498) + **Palette** hộp nổi thứ ba (:499-504) — 3 lớp nổi chồng canvas.
3. **Sắp xếp layer chỉ có nút lên/xuống** — không kéo-thả; đổi tên giấu sau nút pencil hover.
4. **Zoom/nền/download nằm ở 2 pill nổi rời rạc** (bottom-left :393-401, bottom-right :507-511) — không có status bar thống nhất.
5. Không có thông tin trạng thái (số layer, layer đang chọn) — nhà thiết kế không có cảm giác môi trường.
6. Nút zoom dùng ký tự `−`/`+`/`Vừa` thay vì icon.

## 2. Nguyên tắc thiết kế mới

1. **Một hệ icon duy nhất** — mọi icon UI chrome qua `<StudioIcon name="..."/>`; cấm emoji trong chrome (trừ dữ liệu nội dung ConceptCard); cấm inline `<svg>` cho icon (overlay dữ liệu được phép).
2. **Dock, không float** — inspector layers + status bar là **cột/hàng dock thật** bên trong khung canvas (flex), không đè canvas; vùng nổi chỉ còn: RegionTools rail (công cụ), ContextToolbar dock, batch slider, menu nhỏ.
3. **Mật độ chuyên nghiệp** — text 11-12px cho panel, hit-target >=28px (h-7), hành động chính luôn hiển thị, destructive = đỏ rõ.
4. **Giữ nguyên hành vi** — mọi handler/store API giữ y hệt; đây là redesign bề mặt + tổ chức, KHÔNG đổi logic tạo ảnh.
5. **a11y tối thiểu** — nút icon-only có `:aria-label` trùng `title`; slider có `aria-label`; tương phản text >= cream-300/60 trên ink-900.

## 3. Layout mới (desktop >= lg)

```
┌──────────────────────────────────────────────────────────────────────┐
│ Top account bar (giữ cấu trúc — icon hóa: 🔒→lock, ⚙️→gear)          │
├───────────┬──────────────────────────────────────────────┬───────────┤
│ Left      │ ┌─ ContextToolbar dock (giữ, icon hóa) ────┐ │ Right     │
│ sidebar   │ │ CANVAS FRAME                             │ │ sidebar   │
│ w-80      │ │ ┌────────────────────────────┬─────────┐ │ │ w-48      │
│ (steps +  │ │ │ vùng canvas (flex-1)       │ INSPECTOR│ │ │ Source +  │
│  cards)   │ │ │  RegionTools rail nổi trái │ Layers  │ │ │ Outputs   │
│           │ │ │  layers stack / crop / mask│  dock   │ │ │ (giữ)     │
│           │ │ │  batch slider / editSource │  w-64   │ │ │           │
│           │ │ └────────────────────────────┴─────────┘ │ │           │
│           │ ├─ CanvasStatusBar (dock bottom) ──────────┤ │           │
│           │ └──────────────────────────────────────────┘ │           │
└───────────┴──────────────────────────────────────────────┴───────────┘
```

- **Canvas frame** đổi thành: dock ContextToolbar (trên) → `div.flex-1.flex.min-h-0` chứa [vùng canvas relative (flex-1)] + [`<LayersPanel/>` dock phải, `hidden lg:flex` khi `store.inspectorOpen`] → `<CanvasStatusBar/>` (dưới cùng).
- **Mobile < lg:** inspector ẩn; giữ strip chip layer mini hiện có (icon hóa); status bar rút gọn (zoom + download).
- Các pill/panel nổi cũ bị XÓA: zoom pill bottom-left, bg/download pill bottom-right, layers popover, transform popover, palette popover (chức năng chuyển hết vào StatusBar/LayersPanel).

## 4. Component contracts (BẮT BUỘC đúng để StudioApp tích hợp)

### 4.1 `resources/js/studio/components/LayersPanel.vue` (MỚI — W1)

- **Không props, không emit** — đọc/ghi trực tiếp `useStudioStore()`. Import `StudioIcon` cho mọi icon.
- Root: `<aside class="flex h-full w-64 shrink-0 flex-col border-l border-ink-700 bg-ink-900/95">` gồm 5 vùng:
  1. **Header** (`px-3 py-2`, border-b): icon `layers` + chữ "Layers" + badge đếm `store.canvasLayers.length`; bên phải: nút **Thêm layer** (icon `plus`, menu nhỏ: "Trong suốt" + 6 swatch màu — logic y hệt menu `blankMenuOpen` cũ StudioApp :406-415, gọi `store.addBlankLayer(c?)`), nút **Dọn canvas** (icon `trash`, `store.cleanCanvas()`), nút **Ẩn panel** (icon `panelRight`, `store.toggleInspector()`).
  2. **Danh sách layer** (`flex-1 overflow-y-auto p-1.5 space-y-1`): `v-for="l in store.layersFrontFirst"` (front-first: trên cùng danh sách = trước nhất). Hàng `group flex items-center gap-1.5 rounded-lg border px-1.5 py-1`: active `border-brand-500 bg-brand-600/15`; thường `border-transparent hover:bg-ink-800/70`; ẩn thêm `opacity-45`.
     - **Drag handle** icon `gripVertical` (`cursor-grab`, `draggable=true`; locked → `opacity-30` + không draggable).
     - Nút eye/eyeOff (`store.toggleLayerVisible(l.id)`, `h-6 w-6`).
     - Thumb `<img :src="l.image" class="h-8 w-8 rounded bg-ink-950 object-cover ring-1 ring-ink-700">` + tên span `truncate text-[11px]`; **double-click tên → input rename** (Enter/blur = commit `store.renameLayer(id, val)`, Esc = hủy; input `@click.stop`).
     - Phải: lock/lockOpen (`store.toggleLayerLock`); hover hiện: duplicate (`store.duplicateLayer(l.id)`), delete (`store.deleteLayer(l)`, đỏ, disabled khi locked).
     - Click hàng → `store.selectLayer(l)`.
     - **Kéo-thả sắp xếp:** dragstart lưu `l.id` vào dataTransfer + state cục bộ; dragover hàng khác preventDefault + **vạch chỉ báo** (border-t-2 hoặc border-b-2 border-brand-400 theo nửa trên/dưới con trỏ trong hàng); drop → `store.reorderLayer(dragId, targetId, placeAfter)` (`placeAfter=true` khi thả nửa dưới). Cấm kéo layer locked; thả quanh target locked vẫn được. dragend/dragleave dọn state.
     - **Empty state** khi không có layer: icon `layers` mờ + "Chưa có layer" + gợi ý "Chọn ảnh từ Outputs hoặc thêm layer mới".
  3. **Thuộc tính layer** (khi `store.activeLayer`, `border-t border-ink-700 p-2.5 space-y-2`):
     - Header: icon `move` + "Thuộc tính" + nút "Đặt lại" (`store.resetLayerTransform(store.activeLayer.id)`).
     - 4 hàng slider (nhãn w-12 text-[10px] + range `flex-1 h-1.5 accent-brand-500` + giá trị w-9 text-right + nút reset icon `rotateCcw` h-4 w-4) — giữ logic cũ StudioApp :459-488: opacity 0-1 step .05 (%); blend = select 6 giá trị (normal/multiply/screen/overlay/darken/lighten); scale 0.2-3 step .05 (%); rotation -180..180 step 1 (°). Tất cả qua `store.updateLayerTransform(store.activeLayer.id, {...})`.
     - Lưới 4 nút: `duplicateLayer` (icon `copy`, "Nhân đôi") · `bringLayerTo(id,'front')` (icon `chevronsUp`, "Lên đầu") · `bringLayerTo(id,'back')` (icon `chevronsDown`, "Xuống đáy") · `fillActiveLayer()` (icon `paintBucket`, "Tô màu").
     - Lưới 2 nút flip: `toggleFlipX` (icon `flipHorizontal` + "Lật ngang") · `toggleFlipY` (`flipVertical` + "Lật dọc") — trạng thái active `bg-brand-600/30` khi flipX/flipY true.
     - Nút **Xóa nền AI** full-width ghost violet (icon `userX`, "Xóa nền AI · 1 credit") + **modal confirm nội bộ** (state `removeBgConfirmOpen`, markup y hệt StudioApp :542-551; xác nhận → `store.removeBackground()`).
  4. **Palette** (khi `store.palette.length && store.step !== 3 && store.upscaleSrc`, border-t p-2.5): header icon `palette` + "Palette ảnh"; lưới 4 cột swatch `store.palette.slice(0,8)`; click → gán `store.inpaintFillColor = c` + copy clipboard + `store.toast('Đã chọn màu ' + c)` (y hệt `copyColor` StudioApp :25-28).
  5. **Footer** (`border-t border-ink-700 p-2 flex gap-1.5`): `exportComposite()` (icon `download`, "Xuất PNG", disabled `!store.visibleLayers.length`) · `flattenToLayer()` (icon `layers`, "Gộp") · `saveActiveLayerToOutput()` (icon `save`, "Lưu Output", disabled `!store.activeLayer`).
- Style scoped: chỉ CSS phụ trợ; KHÔNG định nghĩa lại màu.

### 4.2 `resources/js/studio/components/CanvasStatusBar.vue` (MỚI — W2)

- **Không props** — store trực tiếp. Root: `<div class="relative z-30 flex h-9 shrink-0 items-center gap-1 border-t border-ink-700 bg-ink-900/95 px-2">`.
- Trái → phải:
  1. undo (`store.undo()`, icon `undo`, disabled `!store.undoStack.length`, title "Hoàn tác (Ctrl+Z)") + redo (`store.redo()`, icon `redo`, title "Làm lại (Ctrl+Y)").
  2. Divider `h-4 w-px bg-ink-700`.
  3. zoomOut (icon `zoomOut`) → nút % `{{ Math.round(store.zoom*100) }}%` (`min-w-12 text-center text-[11px] tabular-nums`, click = `store.zoomFit()`) → zoomIn (icon `zoomIn`) → fit (icon `maximize`, `store.zoomFit()`, title "Vừa khung hình").
  4. Divider.
  5. **Nền canvas**: 4 swatch tròn h-5 w-5 (`store.canvasBg = b`, b ∈ grid/dark/white/cream — inline style nền y hệt pill cũ StudioApp :510; active `ring-2 ring-brand-400`; title "Nền: <b>").
  6. `flex-1`.
  7. Trạng thái (`hidden md:flex items-center gap-1.5 text-[10px] text-cream-300/60`): icon `layers` + `{{ store.canvasLayers.length }} lớp`; khi có `store.activeLayer`: `· {{ store.activeLayer.name }}` (truncate max-w-32) + `{{ Math.round((store.activeLayer.scale||1)*100) }}%`.
  8. download (`store.downloadActive()`, icon `download`, `bg-brand-600 hover:bg-brand-500 text-white`, disabled `!store.upscaleSrc`, title "Tải ảnh đang chọn").
  9. toggle inspector (`store.toggleInspector()`, icon `panelRight`, active khi `store.inspectorOpen`, `hidden lg:grid`, title "Bật/tắt panel Layers").
- Nút icon chuẩn: `grid h-7 w-7 place-items-center rounded-md text-cream-200 hover:bg-ink-700 disabled:opacity-30` + `:aria-label` = title.

### 4.3 Icon hóa (W3/W4/W5) — quy tắc chung

- Thay **từng** inline `<svg>` icon bằng `<StudioIcon name="..." size="h-4 w-4"/>` (kích thước tương đương), giữ NGUYÊN mọi `@click`, `:class`, `title`, cấu trúc. Thêm `import StudioIcon from './StudioIcon.vue';`.
- Thêm `:aria-label` cho nút chỉ có icon (copy từ title).
- **TUYỆT ĐỐI giữ nguyên** 2 `<svg>` overlay trong CanvasMaskTools (:176/:186 — overlay mask dữ liệu) và mọi `v-html` nghiệp vụ.
- Emoji/ký tự trong nút (vd `−`/`+` chỉnh brush ContextToolbar :45/:47) → icon `minus`/`plus`.
- Không đổi tên class, không đổi store call, không tự cải tiến thêm.

## 5. Icon map chuẩn (StudioIcon)

ĐÃ CÓ: sparkles lightbulb sliders history template coins undo redo puzzle image pose pencil brush search save trash film layers arrowRight check x wand columns gear maximize camera scan spline user shirt refresh target body hair height waist shoulder hip scissors zap background pin pinOff eye link unlink filter calendar tag archive kanban grid download copy folderOpen clock users list briefcase play alertTriangle.

MỚI (điều phối thêm — 26): menu minus plus zoomIn zoomOut eyeOff lock lockOpen chevronUp chevronDown chevronsUp chevronsDown flipHorizontal flipVertical move rotateCcw gripVertical paintBucket userX crop boxSelect lasso penTool eraser ban swapHorizontal palette panelRight imagePlus.

Ánh xạ dùng:
- RegionTools: crop→`crop` · rect select→`boxSelect` · freehand→`lasso` · path→`penTool` · magic→`wand` · brush→`brush` · erase→`eraser` · film look→`palette`.
- ContextToolbar: check/undo/pencil(brush)/eraser/plus/minus/x/trash/copy/scissors/paintBucket giữ nghĩa; add/subtract-select→`boxSelect` (phân biệt bằng nền active + title sẵn có); invert→`swapHorizontal`.
- StudioApp: 🔒→`lock` · ⚙️→`gear` · ☰→`menu` · ✕→`x` · −→`minus` · +→`plus` · ⚠️→`alertTriangle` · 🚫→`ban` · 🎨 header→`sparkles` + chữ Studio · remove-bg→`userX` · fill→`paintBucket`.
- GalleryModal (điều phối): ⚠→`alertTriangle`, −/+→`minus`/`plus`.

## 6. Phân chia file (không được vượt phạm vi)

| Agent | File | Quyền |
|---|---|---|
| điều phối | `resources/css/app.css` (3 dòng theme) · `StudioIcon.vue` (+26 icon) · `store.js` (+reorderLayer, +inspectorOpen/toggleInspector) · `STUDIO_UI_REDESIGN.md` · `StudioApp.vue` · `GalleryModal.vue` | write |
| W1 | `components/LayersPanel.vue` MỚI | create |
| W2 | `components/CanvasStatusBar.vue` MỚI | create |
| W3 | `components/RegionTools.vue` · `components/CanvasMaskTools.vue` | edit |
| W4 | `components/ContextToolbar.vue` | edit |
| W5 | `components/SourcePanel.vue` · `components/SourceLibraryPicker.vue` | edit |

## 7. Store API bổ sung (điều phối thêm vào store.js)

```js
inspectorOpen: true,                       // state — panel Layers dock (desktop)
toggleInspector() { this.inspectorOpen = !this.inspectorOpen; },
// Đặt layer id NGAY TRƯỚC/SAU targetId trong stack canvasLayers (0 = dưới cùng).
// Locked layer không cho kéo; thả quanh target locked vẫn được. Có pushHistory + saveLayerLayout.
reorderLayer(id, targetId, placeAfter) { ... }
```

## 8. Verification (bắt buộc sau triển khai)

1. `npm run build` exit 0.
2. `grep -c '<svg'` trên RegionTools/ContextToolbar/SourcePanel/SourceLibraryPicker = **0** (CanvasMaskTools giữ đúng 2 overlay).
3. Grep emoji chrome (`🔒 🎨 ☰ ✕ ⚙️ ⚠️ 🚫 ⏳`) trên các file đã sửa = 0 (trừ ConceptCard emoji data + SwapCard ẩn).
4. CSS build mới có rule `.bg-ink-950`, `.bg-ink-600`, `.border-ink-600`, `.text-cream-400`.
5. `StudioApp.vue` không còn `<svg` (0), import + render `<LayersPanel/>` + `<CanvasStatusBar/>`.