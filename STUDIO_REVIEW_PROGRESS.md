# STUDIO REVIEW — LEDGER TIẾN ĐỘ (bộ nhớ bền bỉ)

> **File này là trạng thái, không phải quy trình.** Quy trình ở `DELEGATION_PLAYBOOK.md`.
> Mỗi goal round / session mới: đọc file này TRƯỚC (chỉ ~4 KB), làm việc, rồi cập nhật lại nó.
> Lý do phải có: goal round cộng dồn trong CÙNG session và compaction có thể shadow round cũ
> (`dsh-goal-round-driver/README.md`: "no fresh agent or copied conversation prefix").

## Trạng thái hiện tại
## Phiên UI-3w (2026-09-07) — Căn/chia đều tôn trọng group (single-group sắp thành viên) · fix ẩn hết layer khi chọn tool không có layer

- **Căn/chia đều theo group**: giữ nguyên khối khi chọn ≥2 unit (group/layer đơn); nếu chỉ chọn DUY NHẤT 1 group → sắp xếp các thành viên bên trong group (không còn no-op).
- **Fix ẩn hết layer khi chọn công cụ không có layer active**: chế độ isolate (crop/inpaint/erase) chỉ chạy khi `store.activeLayer` tồn tại; nếu không → hiển thị composite (tất cả layer vẫn hiện).
- Verify: vite build ✓ (783ms).

## Phiên UI-3v (2026-09-07) — Fix chia đều/căn giữa các group không phá vị trí nội bộ

- Căn lề (`alignSelection`) & chia đều (`distributeSelection`) giờ xử lý theo **ĐƠN VỊ** (`_selectionUnits`): mỗi NHÓM = 1 khối cứng (gồm toàn bộ thành viên, `_unitBox` hộp bao của group), layer đơn lẻ = 1 khối; di chuyển bằng `_translateUnit` → **giữ nguyên vị trí tương đối giữa các thành viên trong group** khi chia đều/căn giữa các nhóm.
- Verify: vite build ✓ (752ms) · markers `_selectionUnits`/`_unitBox`/`_translateUnit` (8).

## Phiên UI-3u (2026-09-07) — Kéo-thả ảnh từ dock Outputs vào canvas + chỉ báo khi hover

- **Kéo-thả vào canvas**: thumbnail hoàn tất trong `OutputModule` set `draggable` + `onThumbDrag` (dataTransfer text/plain JSON {type,url,name}); canvas (`StudioApp` bg container) thêm `dragover/dragleave/drop` → `onCanvasDrop` → `store.addImagesToCanvas([{url,name}])` (thêm layer).
- **Chỉ báo khi hover**: mỗi ảnh output có overlay "Kéo thả" hiện khi hover + tooltip "Kéo thả vào canvas để thêm · nhấn để xem lớn"; khi kéo vào canvas hiện khung dashed brand (dropOver).
- Verify: vite build ✓ (767ms) · OutputModule 3, StudioApp 5.

## Phiên UI-3t (2026-09-07) — Nút Save vật lý (icon chuẩn) · lưu/khôi phục đầy đủ nhóm & lựa chọn sau refresh

- **Nút Save**: `store.saveNow()` (flush layout + toast "Đã lưu trang") — nút icon `save` (floppy, chuẩn ngành) trong `CanvasStatusBar`.
- **Đảm bảo lưu & bảo toàn sau refresh**: `saveLayerLayout` lưu thêm `selectedLayerIds` + `layerGroups` (groupId trên layer); `restoreLayerLayout` (gọi trong `load()`) khôi phục `groupId` + nhóm (giữ ≥2 thành viên, nhóm thiếu → tách) + lựa chọn. Trạng thái canvas/nhóm/lựa chọn giữ nguyên khi refresh.
- Verify: vite build ✓ (768ms) · markers store 3, statusbar 2.

## Phiên UI-3s (2026-09-07) — cursor theo select tool · hint edit-in-group · fix group tách khi ẩn hết · auto-save trước khi thoát

- **Cursor theo tool**: khi bật Select → con trỏ **crosshair** (quét chọn), còn lại grab/grabbing như cũ.
- **Hint edit-in-group**: title trên layer thuộc nhóm + badge nhóm kèm "Alt+click layer trong nhóm để chỉnh sửa riêng".
- **Fix group tự tách khi ẩn hết**: `groupLabel`/`isGroupTop`/`groupCount` dùng TẤT CẢ thành viên (kể cả ẩn) → folder nhóm giữ nguyên khi mọi layer trong nhóm bị ẩn.
- **Auto-save trước khi thoát**: `beforeunload` → `store.saveLayerLayout()` (flush layout trước khi rời trang).
- Verify: vite build ✓ (730ms).

## Phiên UI-3r (2026-09-07) — Select tool (đầu dock, luôn nổi) · marquee chỉ khi chọn Select (hết đua pan) · edit in group

- **Tool Lựa chọn**: `store.selectTool` — nút đầu toolbar (icon cursor chuẩn ngành, đơn sắc). Dock toolbar **LUÔN nổi** (bỏ `v-if=upscaleSrc`). Các tool khác tự tắt selectTool.
- **Marquee chỉ khi Select**: kéo vùng trống = pan như cũ (hết đua); chỉ khi bật Select mới quét chọn (giữ Ctrl/Cmd/Alt = pan trong chế độ chọn).
- **Edit in group**: Alt+click layer trong nhóm (canvas hoặc panel) → `setActiveLayer(id)` để chỉnh sửa riêng layer đó (không chọn cả nhóm).
- Verify: vite build ✓ (746ms) · RegionTools 12, StudioApp 1, alt-edit 1+1.

## Phiên UI-3q (2026-09-07) — Group dùng được khóa/nhân đôi/xóa + nút tải hàng loạt trong popup chọn nhiều

- **Tính năng layer cho group**: `toggleGroupLock` (khóa/mở khóa toàn bộ thành viên) · `duplicateGroup` (nhân đôi nhóm thành nhóm mới, lệch +40px) · `deleteGroup` (xóa toàn bộ thành viên) — thêm nút khóa/copy/trash trong folder nhóm (hover) tại LayersPanel + helper `groupLocked`.
- **Tải hàng loạt**: `downloadSelection()` (tải lần lượt từng layer đang chọn, `_downloadLayerImage`) + nút **Tải** trong `MultiSelectBar`.
- Verify: vite build ✓ (710ms) · markers: store 6, LayersPanel 4, MultiSelectBar 1.

## Phiên UI-3p (2026-09-07) — Snap→status bar (mặc định 8px) · group fold trong Layers · quét chọn nhiều layer

- **Snap xuống status bar**: `CanvasStatusBar` thêm nút bật/tắt snap (target, mặc định BẬT 8px) + select 8/16/24/32; bỏ phần "Bắt điểm" khỏi `MultiSelectBar`. `snapGrid` mặc định = 8.
- **Group icon + tên (rename) trên canvas**: `store.groupLabel(l)` hiện badge nhóm (icon group + tên) trên layer đại diện; nhấn đúp (`startGroupRename`) đổi tên. `renameGroup(gid,name)`.
- **Nhóm thành folder trong Layers**: panel hiển thị folder nhóm (icon + tên + đếm + collapse + nút **Tách nhóm**) và các thành viên lùi vào (`ml-3`); thu gọn thì chỉ giữ đại diện (`openGroups`/`isGroupTop`). Kèm `selectGroup(gid)` + `ungroupGroup(gid)` (nút tách nhóm trong dock thuộc tính).
- **Quét chọn nhiều layer**: kéo trên vùng trống = marquee (khung chọn) → `store.selectInRect` chọn mọi layer trong vùng (mở rộng theo nhóm); pan bằng Ctrl/Cmd/Alt+kéo. `marquee` state + `onCanvasBgMove/Up` + `selectInRect`.
- **Verify**: vite build ✓ (747ms) · grep markers đầy đủ màn.

## Phiên UI-3o (2026-09-07) — Popup ngữ cảnh đẹp mắt (icon chuẩn Lucide) + tạo nhóm layer

- **MultiSelectBar redesign**: đổi sang **icon chuẩn ngành (Lucide)** — căn lề 6 hướng (`alignStart/Center/EndHorizontal/Vertical`), chia đều X/Y (`distributeHorizontal/Vertical`), nhóm (`group`), bắt điểm, xóa — dạng icon-button mono `h-7 w-7`, tooltip, kèm số layer & nhãn nhỏ; gọn & đẹp hơn.
- **Tạo nhóm layer**: store `layerGroups` + `groupSelection()` (chọn ≥2 → gán `groupId`, chọn cả nhóm) · `ungroupSelection()` (tách nhóm) · `selectLayerWithGroup(id)` (click 1 layer thuộc nhóm = chọn cả nhóm) — nút **Nhóm** / **Tách** trong popup; nhóm di chuyển như một khối (đã hỗ trợ kéo nhóm).
- **Icon mới trong StudioIcon.vue**: align*Horizontal/Vertical (6), distribute* (2), group (1) — kiểu Lucide.
- **Verify**: vite build ✓ (720ms) · grep group methods = 7, icons = 9, bar có Nhóm/Tách.

## Phiên UI-3n (2026-09-07) — Đa chọn & thao tác canvas nâng cao (shift+click, kéo nhóm, snap preset, căn/chia đều, Delete→xác nhận)

- **Chọn nhiều layer (shift+click)**: store thêm `selectedLayerIds` + `selectedIds`/`selection`/`selectionCount`/`isSelected` + `shiftSelectLayer`/`clearSelection`; `setActiveLayer` refactor thành `_setActive` (giữ nhóm) + clear selection. Hoạt động trên canvas (`onLayerPointerDown` shift) lẫn panel Layers (`onRowClick` shift) — row/ảnh được chọn có ring brand mờ.
- **Kéo nhiều layer cùng lúc**: `beginLayerDrag` lấy nhóm `selectedIds`, `layerDragMove` áp delta (đã bắt điểm) cho toàn bộ nhóm.
- **Snap preset**: `snapGrid` (0/8/16/24/32) — kéo layer bắt vào lưới + tâm canvas + cạnh layer ngoài nhóm (giữ `snapX/snapY` guide). Mặc định 0 (tắt).
- **Popup ngữ cảnh chọn nhiều (`MultiSelectBar.vue` — MỚI)**: nổi giữa trên khi `selectionCount>1` — căn lề (6 hướng) · chia đều X/Y · chọn khoảng cách bắt điểm · Xóa. (tiền đề mở rộng — gắn nút mới dễ).
- **Căn/chia đều**: `alignSelection(kind)` (left/hcenter/right/top/vcenter/bottom theo bbox layer) · `distributeSelection('x'/'y')` (giữ 2 đầu, ≥3 layer).
- **Phím Delete → popup xác nhận xóa**: `onLayerKeys` bắt Delete/Backspace → `confirmDeleteOpen` modal (đếm layer · Hủy/Xóa) → `confirmDeleteSelection`; mũi tên giờ `nudgeSelection` di chuyển cả nhóm; chặn phím khi modal mở.
- **Verify**: vite build ✓ (769ms) · grep marker đầy đủ gal.

## Phiên UI-3m (2026-09-07) — Menu Thêm layer: fix nhầm nút chọn màu + thoát chắc khi bấm ngoài

- **Bỏ nút "Thêm layer màu" gây nhầm**: thay bằng **1 hàng chọn màu rõ ràng** "Chọn màu tùy chỉnh…" (swatch + label + chevron) — bấm cả hàng mở bảng màu; chọn xong (`@change` đóng bảng màu) tự `addBlankLayer(blankColor, ratio)` + đóng menu.
- **Thoát popup khi bấm ra ngoài (chắc chắn)**: dùng **backdrop `fixed inset-0 z-40`** phủ toàn màn hình khi menu mở (`@pointerdown → blankMenuOpen=false`), popover `z-50` nổi trên — thay cách document-listener cũ không hiệu quả.
- Verify: vite build ✓ (782ms) · grep "Thêm layer màu"=0, "Chọn màu tùy chỉnh…"=1, backdrop=1, còn 0 doc-listener.

## Phiên UI-3l (2026-09-07) — Menu Thêm layer (LayersPanel): thoát khi mất tiêu điểm · bảng màu tùy chỉnh · giãn khoảng cách

- **Thoát popup khi thoát tiêu điểm**: click-ra-ngoài (pointerdown) tự đóng menu Thêm layer (`menuRoot` + `onDocPointer` + watch; dọn listener onBeforeUnmount).
- **Bảng chọn màu tùy chỉnh cho layer**: input màu (`blankColor`, mặc định #4f9dff) + nút "Thêm layer màu" → `addBlankLayer(blankColor, blankRatio)`.
- **Giãn khoảng cách**: menu `gap-2 p-3` (rộng rãi hơn) · swatch màu `h-7 w-7` + hover scale-110 · nút tỷ lệ `px-2 py-1` gap-1.5 · nút "Trong suốt" py-1.5 · có dải phân cách trước phần Tỷ lệ · bo/padding to hơn cho dễ bấm.
- Verify: vite build ✓ (783ms).

## Phiên UI-3k (2026-09-07) — addBlankLayer: ưu tiên kích thước ảnh hiện tại · trống theo preset tỷ lệ

- `addBlankLayer(bg, ratio)` khôi phục tính năng đọc kích thước ảnh đang chọn: nếu active layer có ảnh → layer mới tạo **đúng kích thước (naturalWidth/Height) ảnh hiện tại** (ưu tiên); nếu tạo layer trống (không chọn ảnh) → tôn trọng **preset tỷ lệ khung hình** (`ratioToSize(ratio || imageRatio)`), baseW/baseH = w/h.
- Verify: vite build ✓ (772ms).

## Phiên UI-3j (2026-09-07) — RegionTools: dải phân cách nhóm xoay NGANG 90° (bỏ bản dọc trên desktop)

- `sep` đổi từ `h-px w-6 ... lg:h-6 lg:w-px` → chỉ còn `h-px w-6` (luôn nằm ngang): trên desktop (cột dọc) phân cách nhóm là đường ngang thay vì đường dọc.
- Verify: vite build ✓ (752ms).

## Phiên UI-3i (2026-09-07) — ContextToolbar (option ngữ cảnh): đơn sắc theo theme, cỡ chữ/icon nhất quán

- **Đơn sắc theo theme**: mọi ngữ cảnh (vùng chọn · đã lưu vùng · xóa · vẽ · crop · film) dùng chung token `primary` (cream-100/ink-900) + `btn` (ink-800) + `on` (toggle) + `iconBtn`/`iconBtnDanger` (tròn h-7) + `chipOn/Off` (tỷ lệ/look) + `ring` trung tính (`ring-ink-600/70`); bỏ brand/violet/amber/emerald/sky/red accent → grep accent = 0.
- **Cỡ chữ/icon thống nhất**: icon mọi nút `size="I" = h-4 w-4` (31 chỗ) · label `text-xs`, nhãn nhỏ `text-[10px]`, chip `text-[11px]` · slider `accent-cream-300` (mono).
- **Order chuẩn UX**: Xong/Áp dụng (chính, mono) → tùy chọn công cụ (Ngưỡng/Độ mịn/Feather/Cọ) → hành động vùng (Cộng/Trừ/Đảo/Nâng/Nhân đôi/Tô/Xóa) → Bỏ mask/Đóng (iconBtnDanger).
- **Verify**: vite build ✓ (745ms) · bundle chứa `bg-cream-100 text-ink-900`, `accent-cream-300`; grep accent = 0, dup :class = 0.

## Phiên UI-3h (2026-09-07) — Toolbar canvas: đơn sắc theo theme, icon/cỡ nhất quán, thứ tự nhóm chuẩn UX

- **Đơn sắc theo theme**: 7 nút dùng chung cặp `mono.on` (pill sáng cream-100/ink-900) / `mono.off` (text-cream-300/70 hover ink-700) — không còn màu thương hiệu (grep brand = 0).
- **Icon cùng cỡ**: mọi nút dùng `ICON = 'h-5 w-5'` (bỏ biến thể lg), nút `h-9 w-9`/`lg:h-11 lg:w-11` — size nhất quán.
- **Thứ tự nhóm chuẩn UX** (desktop cột dọc · mobile hàng ngang): Cắt khung (Crop) | Vùng chọn ×4 (rect · lasso · path · magic) | Vẽ + Xóa | Film Look — mỗi nhóm 1 dải phân cách (3 separators).
- **Verify**: vite build ✓ (777ms) · grep brand=0 · 7 `:size="ICON"` · 3 separators.

## Phiên UI-3g (2026-09-07) — Toolbar canvas (RegionTools) đơn sắc đồng nhất + thoát công cụ thông minh

- **Đơn sắc đồng nhất**: 7 nút công cụ (crop · 4 kiểu vùng chọn · vẽ · xóa · film) bỏ màu brand → active = pill sáng monochrome (`bg-cream-100 text-ink-950 border-cream-300/40`), inactive = `text-cream-300 hover:bg-ink-700 hover:text-cream-100`; giữ nguyên size h-9/h-11 + icon 20/22px + tooltip nhất quán, 1 hướng (desktop cột dọc · mobile hàng ngang).
- **Thoát công cụ thông minh**: `store.exitCanvasTools()` (finishDraw + exitErase + clearInpaintMask + đóng reframe/crop/film) — tự gọi khi: đổi bước (`step`), bỏ chọn layer (`activeLayerId = ''` = thoát ảnh tiêu điểm), mở viewer (`viewer`), mở popup Prompt Tạo Ảnh (`promptOpen`), bấm slot tải ảnh nguồn (SourcePanel `@click`).
- **Verify**: vite build ✓ (746ms) · RegionTools còn 0 `bg-brand-600` (đơn sắc) · 6 điểm gọi `exitCanvasTools`.

## Phiên UI-3f (2026-09-07) — Redesign thanh điều hướng (header) trang /studio/library

- **Header (`LibraryApp.vue`)**: card bo tròn viền ink-700 + bóng · trái: ô icon thương hiệu (image, nền brand + ring) + tiêu đề "Thư viện" + badge đếm; nút điều hướng "← Về Studio" rõ ràng (tool-btn, mũi tên xoay 180°); chip dự án hiện tại. Phải: nút **Làm mới** (icon-btn tròn sạch + tooltip) · **Quản lý / Dọn dẹp** là tool-btn toggle (`is-active` khi bật).
- **Thanh tab**: chuyển thành `.seg` (Ảnh đã tạo / File tải lên + badge unused_count) — đồng bộ chrome studio.
- **Verify**: vite build ✓ (695ms) · chuỗi header có trong chunk `library-*`.

## Phiên UI-3e (2026-09-07) — GalleryModal: lưới "Thông tin ảnh" thu gọn · tô xanh ưu tiên trả về nút Chỉnh sửa

- **Lưới thông tin ảnh thu gọn được** (`fieldsOpen`): header "Thông tin ảnh" + nút chevron (xoay 180° khi đóng) → ẩn/hiện các dòng Dự án · Model · Provider · Tỷ lệ · Độ phân giải · Thời lượng · Ngày (Transition cf).
- **Tô xanh (btn-brand) trả về "Chỉnh sửa → Fitting Room"** (hành động chính, `v-if=!isVideo`); nút "Sử dụng prompt · Tạo ảnh mới" hạ xuống `btn-outline`.
- **Verify**: vite build ✓ (764ms).

## Phiên UI-3d (2026-09-07) — GalleryModal: chuyển ảnh mượt không chớp · info thu gọn · nút Sử dụng → Prompt Tạo Ảnh

- **Không chớp + crossfade khi chuyển ảnh**: `GalleryModal` giữ ảnh cũ tới khi ảnh mới `load` xong (probe Image + `loadedUrls` cache) rồi mới đổi `shown` → `Transition cf` opacity 0.18s; prefetch 2 ảnh láng giềng để bấm mũi tên gần như tức thì. Bỏ `:key='img-'+current.id` cũ (nguồn chớp trắng).
- **Thông tin ảnh thu gọn được**: `infoOpen` + nút icon `columns` nổi góc trên phải vùng ảnh (title: Thu gọn/Mở thông tin ảnh) · aside bọc `Transition aside` + `v-if`.
- **Nút "Sử dụng prompt · Tạo ảnh mới"**: `usePrompt` = copy prompt (clipboard) + `store.imagePromptEn` + đóng viewer + `store.step=1` (mount ConceptCard chứa popup) + `store.promptOpen=true` (popup Prompt Tạo Ảnh, sẵn prompt để chỉnh & tạo). `Chỉnh sửa → Fitting Room` đổi xuống `btn-outline`. StudioApp thêm watcher `promptOpen` → mở drawer menu + đóng drawer Outputs (đảm bảo ConceptCard mount trên mobile).
- **Verify**: vite build ✓ (761ms) · grep markers OK.

## Phiên UI-3c (2026-09-07) — Card Nguồn tối giản tuyệt đối + tách card icon Thư viện xuống đáy

- **Card Nguồn chỉ còn slot** (`SourcePanel.vue`): xóa tiêu đề "Nguồn" + caption "Thư viện · Sản phẩm" (người dùng chốt: "chỉ giữ slot, tên hiện khi hover") — slot trống = icon imagePlus; có ảnh = ảnh nguồn (`store.editSource`) + nút X bỏ ảnh (overlay góc, `@click.stop`); mọi mô tả qua `title` (hover). Bấm slot → `SourcePickerPopup` (2 tab) như cũ.
- **Tách nút "Xem thư viện"**: bỏ icon grid khỏi header card Outputs (`OutputModule.vue` — xóa luôn hàm goLibrary) · tạo `LibraryCard.vue` (MỚI): card riêng chỉ 1 icon library (grid) làm slot, bấm → `/studio/library` (giữ hành vi cũ, người dùng chốt), title chỉ hiện khi hover · chèn dưới cùng right dock (desktop aside) + dưới cùng drawer Outputs mobile trong `StudioApp.vue`.
- **Verify**: vite build ✓ (725ms) · grep xác nhận OutputModule không còn goLibrary · `LibraryCard` xuất hiện 3 lần (import + aside + drawer).

## Phiên UI-3b (2026-09-07) — Card Nguồn 1 slot + popup gộp 2 nguồn thành 2 tab

- **Card Nguồn = 1 slot duy nhất**: `SourcePanel.vue` viết lại — slot trống = icon tải ảnh + "Thêm ảnh nguồn" (viền dashed); slot có ảnh = hiển thị ảnh nguồn đang dùng (`store.editSource`, aspect-square + tên) + nút thay ảnh nhỏ ở góc; header giữ nút X bỏ ảnh nguồn. Bỏ 2 nút cũ (Tải lên/Sản phẩm).
- **Popup gộp 2 nguồn → 2 tab trong 1 popup**: `SourcePickerPopup.vue` (MỚI) — header + tab `.seg` (Thư viện ảnh / Sản phẩm) + footer chung cộng dồn lựa chọn cả 2 tab → `store.addImagesToCanvas` (GIỮ NGUYÊN ngữ nghĩa thêm nhiều ảnh thành layer — người dùng đã chốt). Tab Thư viện = copy logic `SourceLibraryPicker` multi (/studio/ref-images, upload `/studio/upload-ref` → tự chọn ảnh vừa tải, xóa, output library); tab Sản phẩm = copy popup sản phẩm cũ (/studio/references). Accent đồng bộ brand (thay emerald cũ của tab sản phẩm).
- **Verify**: vite build ✓ (1.35s) · chuỗi "Thêm ảnh nguồn"/"Thư viện ảnh"/"Sản phẩm" có trong bundle · `SourceLibraryPicker.vue` vẫn dùng ở ComposeCard (không đụng).

## Phiên UI-3 (2026-09-07) — Layer theo tỷ lệ khung hình · border đồng nhất ink-700 · mobile tối giản · right dock 115px

- **Provider**: CHỈ `deepseek-official` — workflow 4 agent (store-ratio + layers-ratio = `deepseek-v4-pro` · border-ink700 + source-1col = `deepseek-v4-flash`), 4/4 thành công, 0 fail; điều phối tự làm StudioApp.
- **Layer tôn trọng tỷ lệ khung hình settings**: store `+ratioToSize(r)` (base 1024 cạnh dài, map 8 tỷ lệ) · `addBlankLayer(bg, ratio)` bỏ đo kích thước theo activeLayer, thay bằng `ratioToSize(ratio || imageRatio)` · `baseW/baseH = w/h` · LayersPanel `+blankRatio` (mặc định `store.imageRatio`) + menu thêm 7 preset tỷ lệ (1:1·4:3·3:4·9:16·16:9·4:5·21:9), nút Trong suốt/màu gọi `addBlankLayer(..., blankRatio)`.
- **Border đồng nhất ink-700**: xóa `border:1px solid var(--color-brand-500)` inline ở 9 card (Concept/Inpaint/RefImage/Suggest/Stylist/Director/Upscale/Swap/Compose) — viền lấy từ `.card` = `border-ink-700`; giữ gradient nền. Grep `--color-brand-500` inline = **0**.
- **Mobile tối giản**: ContextToolbar dock → `hidden lg:flex` (desktop only) · thêm `toolActive` computed · floating ContextToolbar `absolute bottom-12` (12px trên status bar) khi có tool active · outputs drawer chỉ đóng bằng nút close (bỏ `@click="outputOpen=false"` overlay).
- **Right dock → 115px**: aside `w-44 → w-[115px]` (người dùng đính chính: là right dock chứa outputs, không phải left) · SourcePanel 2 nút Tải lên/Sản phẩm xếp dọc `flex-col` (1 cột).
- **Verify**: vite build ✓ (1.25s) · đọc diff 100% · grep border inline = 0.

## Phiên UI-2 (2026-09-07) — Đồng bộ chrome: right bar 1 cột · header tool-btn · chips seg · mobile (PHẦN II STUDIO_UI_REDESIGN.md)

- **Provider**: CHỈ `deepseek-official` / `deepseek-v4-pro` (yêu cầu người dùng) — workflow 5 agent (concept · inpaint · refimage · langcards · rightbar), 5/5 thành công, 0 fail.
- **Right bar 1 cột**: StudioApp aside `w-48→w-44` · OutputModule `grid-cols-2→grid-cols-1` + header `panel-head`/`panel-title` + nút `icon-btn` · SourcePanel header `panel-head` + nút `tool-btn` (Tải lên/Sản phẩm) · root `card overflow-hidden`.
- **Header + mobile**: gộp nút Dự án + quick-apply thành 1 `tool-btn` (popover thêm footer "Mở workspace") · chip dự án áp dụng `tool-btn is-active` · credit `tool-btn`+icon coins · admin/logout `tool-btn` · mobile top bar `icon-btn !h-9 !w-9 border ink-700` + title sparkles + badge đếm trên icon outputs (thay nút text "Kết quả (n)") · drawer header `panel-head`+`panel-title` · step-nav → `.seg`/`.seg-btn`.
- **Chips → seg**: ConceptCard (tab nav 4 tab · Lịch sử/Templates/Preset → `tool-btn` · insertMode ×2 → seg · undo/redo → icon-btn · preset filter → tool-btn) · InpaintCard (chế độ mask → seg) · RefImageCard (mode → seg · Số ảnh → seg) · SuggestCard/StylistCard (EN/VI → seg w-28).
- **Verify**: vite build ✓ (765ms) · `.seg/.tool-btn/.icon-btn/.panel-head/.panel-title` sinh CSS · chỉ còn 1 `bg-brand-600 text-white` = checkbox slot multi-view InpaintCard (chủ đích, không phải chip).
- **Deploy**: commit + push → SSH `ssh -p 65002 u310846799@145.79.25.57` (app `~/domains/trillfa.shop`) `git pull --ff-only` + `composer --no-scripts` + `migrate` + `optimize:clear`. Đã lưu: `scripts/deploy.sh` + ghi chú DEPLOY.md.

## Phiên UI-1 (2026-09-07) — REDESIGN "/studio" Designer Workspace (ngoài phạm vi review code)

- Spec: **STUDIO_UI_REDESIGN.md** (phân tích + contract component + icon map + checklist).
- **Lỗ hổng production mới phát hiện & vá**: theme app.css thiếu `ink-600` (dùng 56 lần trong studio SFC) · `ink-950` (4 lần, gồm root StudioApp) · `cream-400` → class không sinh CSS trên production (kiểm chứng grep = 0 trong bundle build thật). Đã thêm 3 biến màu vào `@theme` — KHÔNG thêm ink-100..400 (tránh đụng override .studio-dark storefront). `z-32` xác nhận hợp lệ (Tailwind v4 bare value), không phải lỗi.
- **Icon đồng bộ 1 hệ**: StudioIcon +26 icon (tổng 90) — thay toàn bộ inline SVG icon: StudioApp 27 · ContextToolbar 29 · SourcePanel 12 · SourceLibraryPicker 10 · RegionTools 8; emoji chrome (🔒🎨☰✕⚙️⚠️🚫−+) → icon; CanvasMaskTools giữ đúng 2 `<svg>` overlay dữ liệu mask. Emoji dữ liệu kiểu tóc ConceptCard = nội dung picker, giữ.
- **Canvas + layers → workspace chuyên nghiệp**: `LayersPanel.vue` MỚI (dock phải desktop / drawer mobile — header+đếm, thêm layer, dọn canvas, **kéo-thả sắp xếp** qua `store.reorderLayer()` mới, eye/lock, double-click rename, hover duplicate/delete, empty state, thuộc tính opacity/blend/scale/xoay + flip + front/back + tô màu + Xóa nền AI kèm confirm, palette ảnh, footer Xuất/Gộp/Lưu) · `CanvasStatusBar.vue` MỚI (dock đáy: undo/redo, zoom −/%/+/fit, 4 nền canvas, số lớp + layer đang chọn, download, toggle inspector) · xóa 5 vùng nổi cũ (zoom pill, bg pill, layers popover, transform popover, palette popover).
- Store: +`inspectorOpen` (mặc định theo viewport), +`toggleInspector()`, +`reorderLayer(id, targetId, placeAfter)` (pushHistory + saveLayerLayout, cấm kéo layer locked).
- Triển khai: workflow 5 agent kimi-k3 (W1-W5) + điều phối tích hợp StudioApp/GalleryModal; workflow timeout 600s sau khi agents đã ghi đủ file → xác minh đọc diff 100%: đúng contract, giữ nguyên handler/store call. Vá tích hợp: thiếu 1 `</div>` vùng canvas (build bắt), shrink-0 header/footer panel, cap max-h properties.
- Verify: vite build ✓ (769ms) · 0 inline svg icon (trừ 2 overlay mask) · 0 emoji chrome · 90 icon hợp lệ · CSS mới có .bg-ink-950/.bg-ink-600/.border-ink-600/.text-cream-400 · store API cross-check ✓.


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
