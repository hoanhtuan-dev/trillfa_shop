import { defineStore } from 'pinia';
import { markRaw } from 'vue';

const CSRF = () => (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

// Shared studio store — each card component reads/writes this so they can share data.
export const useStudioStore = defineStore('studio', {
  state: () => ({
    step: 1,
    opening: false,
    defaultsLoaded: false,
    needsLogin: false,
    previewId: null,
    preview: null,
    generations: [],
    creditsLeft: 0,
    imageCreditCost: 1,  // chi phí credit cho 1 ảnh (load từ defaults)
    // film / reframe / swap share the source image (editSource || preview)
    editSource: null,
    texture: 5,
    // upscale params (fabric-weave slider removed — it affected dark skin & detail edges)
    upscaleScale: 2,
    upscaleRefine: 0,
    studioPhotoreal: 5,
    lightShadow: 5,
    sharpen: 3,   // hậu kỳ: tăng nét chi tiết (không blur)
    clarity: 3,   // hậu kỳ: micro-contrast / độ nổi khối
    vibrance: 3,  // hậu kỳ: độ sống động màu
    upscaling: false,
    // film look
    lookPreset: 'studio',
    lookLevel: 5,
    looking: false,
    // reframe
    reframeRatio: '3:4',
    reframing: false,
    cropMode: false,
    reframeOpen: false,  // hiển thị toolbar crop (dock phía trên)
    filmOpen: false,      // hiển thị toolbar film look (dock phía trên)
    cropBox: { x: 0.15, y: 0.15, w: 0.7, h: 0.7 },
    cvImg: null,
    canvasZoom: null,
    _cropDrag: null,
    _cropRaf: null,
    _cropPending: null,
    // canvas foundation (zoom/pan/background)
    zoom: 1,
    pan: { x: 0, y: 0 },
    canvasBg: 'grid',
    _drag: null,
    _layerDrag: null,
    snapX: null,
    snapY: null,
    _pinch: null,
    // Xóa vùng (erase) với feather
    eraseMode: false,
    eraseFeather: 15,       // độ mềm mép nét xóa (0-60)
    eraseBrushSize: 24,     // độ dày cọ xóa (px 3-150)
    _eraseCanvas: null,
    _eraseCtx: null,
    _eraseDrawing: false,
    _eraseLast: null,
    _eraseHasStrokes: false, // đã có nét thật? (tránh push history/bake khi không xoá gì)
    _eraseBusy: false,   // chống bake trùng khi đang áp dụng nét xóa
    // Vẽ tự do (paint brush) — tô màu lên layer
    drawMode: false,
    drawBrushSize: 24,   // độ dày cọ (px 3-150)
    drawOpacity: 1,      // độ đậm nét (0-1)
    drawSoftness: 15,    // độ mềm mép (0-60)
    _drawCanvas: null,
    _drawCtx: null,
    _drawDrawing: false,
    _drawLast: null,
    _drawHasStrokes: false, // đã có nét thật? (tránh push history/bake khi không vẽ gì)
    _drawPressure: 1,   // áp lực bút stylus (0-1) cho cọ vẽ
    _drawBusy: false,    // chống bake trùng khi đang áp dụng nét vẽ
    _flattenBusy: false, // đang chuẩn hoá transform layer (xoay/lật) — chống gọi song song
    // concept
    imagePromptEn: '',
    negativePromptEn: '',
    creativeLevel: 6,
    variantCount: 1,
    imageRatio: '1:1',
    imageRes: '1K',
    generating: false,
    // palette / texture
    palette: [],
    // director
    videoModel: '',
    videoScene: '',
    videoDuration: '5',
    videoRes: '720',
    videoPromptEn: '',
    videoBusy: false,
    videoSourceId: null,
    // swap (try-on)
    swapModelIds: [],
    swapPoseIds: [],
    swapLoading: false,
    swapDone: 0,      // poses completed (for progress UI)
    swapTotal: 0,     // total poses in the current run
    swapAbort: null,  // AbortController for cancel
    swapProcessing: false, // true while polling the background queue results
    _swapStop: false,     // flag to stop the background-result polling
    swapStage: '',        // '' | 'send' | 'processing' | 'done' | 'error' | 'cancelled' (giống Inpaint)
    swapError: '',        // lỗi cuối của swap
    swapStartTs: 0,       // timestamp bắt đầu (đếm thời gian)
    swapGenIds: [],       // generation ids của lần chạy hiện tại
    // Compose progress (giống Inpaint)
    composeStage: '',     // '' | 'send' | 'processing' | 'done' | 'error' | 'cancelled'
    composeError: '',
    composeStartTs: 0,
    composeGenIds: [],    // generation ids của lần ghép hiện tại
    inpainting: false,
    inpaintPrompt: '',
    // Inpaint progress/status state (rõ ràng cho người dùng)
    inpaintGenId: null,       // generation đang chạy inpaint
    inpaintStage: '',         // '' | 'send' | 'processing' | 'done' | 'error' | 'cancelled'
    inpaintStartTs: 0,        // timestamp bắt đầu (đếm thời gian)
    inpaintError: '',         // thông báo lỗi cuối
    inpaintPreserveBg: true,  // giữ nguyên nền
    inpaintPreserveFace: true,// giữ nguyên khuôn mặt
    // ── Inpaint Mask (tích hợp region selection vào Inpaint) ──
    inpaintMaskMode: 'none',   // 'none' | 'rect' | 'brush' — chọn vùng cần sửa
    inpaintMaskBox: { x: 0.425, y: 0.425, w: 0.15, h: 0.15 }, // vùng mask mặc định = 15% ảnh (giữa), nhỏ để dễ thao tác
    inpaintBrushData: '',      // base64 PNG của brush mask
    _inpaintMaskCanvas: null,  // canvas DOM cho brush mask (tạm thời)
    _inpaintMaskCtx: null,     // 2d context
    _inpaintBrushDrawing: false,
    _inpaintBrushLast: null,
    inpaintErase: false,   // true = cọ tẩy (xoá nét đã vẽ thay vì tô thêm)
    inpaintBrushSize: 10,  // bán kính cọ vẽ (px trên canvas mask 512) — điều chỉnh được
    _inpaintUndoStack: [], // snapshot canvas trước mỗi nét — Ctrl+Z hoàn tác
    inpaintMaskDone: false, // đã bấm "Xong" — mask được LƯU để xử lý dù overlay đã tắt
    _inpaintMaskKind: '',   // 'rect' | 'brush' — loại mask đang được lưu để gửi khi Sửa ảnh
    _inpaintDrag: null,
    _inpaintHandle: null,
    _inpaintRaf: null,           // rAF id cho drag batching (mượt như crop)
    _inpaintPending: null,       // pointermove chờ flush
    _inpaintPrevBox: null,       // box trước khi bắt đầu vẽ vùng mới (để khôi phục nếu click nhầm)
    _inpaintDrew: false,         // đã kéo thật (>4px) khi vẽ vùng mới?
    inpaintFreehandPoints: [],    // path lasso đang vẽ [{nx, ny}] — hiển thị đường GIMP
    inpaintFreehandPaths: [],     // các nét lasso ĐÃ hoàn thành
    inpaintPathPoints: [],        // điểm neo đang vẽ cho vùng chọn bằng đường cong (path/curve)
    inpaintPathRegions: [],       // các vùng path ĐÃ đóng (để preview hiển thị đủ nhiều vùng)
    magicTolerance: 32,           // ngưỡng màu cho Magic Wand (1-128)
    magicFeather: 0,             // độ mịn Magic Wand (blur px 0-20) — làm mềm mép vùng chọn
    _inpaintFreehandActive: false,
    inpaintFeather: 0,            // feather (px 0-50) — làm mềm mép vùng chọn
    inpaintFillColor: '#ffffff',  // màu tô cho nút "🎨 Tô" của vùng chọn
    inpaintSelectMode: 'new',   // lasso: 'new' | 'add' | 'subtract' — chế độ cộng/trừ vùng chọn
    inpaintMaskSource: 'inpaint',  // 'inpaint' (từ card Sửa ảnh) | 'canvas' (từ thanh công cụ vùng chọn trên canvas)
    // Canvas mask overlay (dùng chung cho Inpaint brush trên canvas chính)
    brushOverlay: null,
    _brushCanvas: null,
    _brushCtx: null,
    _brushDrawing: false,
    _brushLast: null,
    _pollTimers: {},          // single-flight poll per generation id
    suggesting: false,
    suggestResult: null,
    suggestEnabled: true,   // bật/tắt tính năng "💡 Gợi ý từ ảnh" (cấu hình Studio)
    suggestLang: 'en',      // ngôn ngữ hiển thị mặc định (en | vi)
    promptOpen: false,
    viewer: null,
    flashMsg: '',
    flashType: 'info',
    _flashTimer: null,
    _highlightTimer: null,
    upscalePresets: [],
    lastBatch: [],
    showBatch: false,
    canvasLayers: [],
    activeLayerId: '',
    undoStack: [],   // lịch sử hoàn tác (snapshot layers + activeLayerId)
    redoStack: [],   // lịch sử làm lại
    highlightLayerId: '',  // layer mới tạo cần viền nổi bật tạm thời
    imgTick: 0,            // tăng mỗi khi ảnh isolate load xong — overlay đo lại vị trí/kích thước
  }),
  getters: {
    upscaleSrc() { if (this.activeLayerId) { const l = this.canvasLayers.find(x => x.id === this.activeLayerId && x.visible !== false); if (l && l.image) return l.image; } return (this.editSource && this.editSource.url) || (this.preview && this.preview.media_url) || ''; },
    upscaleName() { if (this.activeLayerId) { const l = this.canvasLayers.find(x => x.id === this.activeLayerId); if (l) return l.name; } return (this.editSource && this.editSource.name) || (this.preview ? 'Ảnh kết quả #' + this.preview.id : 'Ảnh đang chọn'); },

    activeBatch() { return this.generations.filter(g => this.lastBatch.includes(g.id)); },
    activeLayer() { return this.canvasLayers.find(x => x.id === this.activeLayerId) || null; },
    visibleLayers() { return this.canvasLayers.filter(l => l.visible !== false); },
    // Danh sách layer hiển thị front-first (layer TRƯỚC NHẤT ở trên cùng) — chuẩn trình chỉnh
    // ảnh. canvasLayers giữ thứ tự vẽ (zIndex), getter này chỉ đảo để hiển thị panel.
    layersFrontFirst() { return this.canvasLayers.slice().reverse(); },
    // Kích thước LAYOUT (CSS px, trước transform) của <img> layer đang active — khớp thẻ hiển thị
    // (cạnh dài tối đa 512). Dùng làm khung toạ độ cho overlay preview neo vào layer.
    frameLayout() {
      const l = this.activeLayer;
      let w = l ? Number(l.baseW) : 0, h = l ? Number(l.baseH) : 0;
      if ((!w || w < 1 || !h || h < 1) && this.cvImg) {
        const nw = this.cvImg.naturalWidth || 0, nh = this.cvImg.naturalHeight || 0;
        if (nw > 1 && nh > 1) { const cap = Math.min(1, 512 / nw, 512 / nh); w = nw * cap; h = nh * cap; }
      }
      return (w > 0 && h > 0) ? { w, h } : null;
    },
  },
  actions: {
    async api(url, body = {}, signal = null) {
      const res = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF(), 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(body), signal });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(data.message || 'Có lỗi xảy ra.');
      return data;
    },
    addGen(g) {
      const existing = this.generations.find(x => x.id === g.id);
      if (existing) Object.assign(existing, g);
      else this.generations.unshift(g);
      this.previewId = g.id;
      if (g.status === 'completed') { this.preview = { id: g.id, media_url: g.media_url, type: 'image', status: 'completed' }; }
      if (g.media_url) { this.pushCanvasLayer(String(g.id), 'gen', 'Ảnh #' + g.id, g.media_url, g.id); this.setActiveLayer(String(g.id)); }
    },
    setPreview(g) { if (g) { this.previewId = g.id; this.preview = { id: g.id, media_url: g.media_url, type: g.type || 'image', status: g.status || 'completed' }; } },
    toast(msg, type = 'info') {
      // Use the Vue studio's own toast (works standalone); fall back to Alpine if present.
      this.flashMsg = msg; this.flashType = type;
      if (this._flashTimer) clearTimeout(this._flashTimer);
      this._flashTimer = setTimeout(() => { this.flashMsg = ''; }, 2600);
      if (window.Alpine?.store?.('toast')) window.Alpine.store('toast').show(msg, type);
    },
    async loadDefaults() {
      try {
        const cfg = await fetch('/studio/defaults', { headers: { Accept: 'application/json' } });
        const defaults = await cfg.json();
        this._applyDefaultValues(defaults);
        this.defaultsLoaded = true;
        return defaults;
      } catch (e) { /* keep current values */ return null; }
    },
    _applyDefaultValues(defaults) {
      if (!defaults) return;
      if (defaults.creative_level != null) this.creativeLevel = Number(defaults.creative_level);
      if (defaults.texture != null) this.texture = Number(defaults.texture);
      if (defaults.image_resolution) this.imageRes = defaults.image_resolution;
      if (defaults.image_ratio) this.imageRatio = defaults.image_ratio;
      if (defaults.video_duration) this.videoDuration = defaults.video_duration;
      if (defaults.video_resolution) this.videoRes = defaults.video_resolution;
      if (defaults.negative_prompt !== undefined) this.negativePromptEn = defaults.negative_prompt;
      // 💡 Gợi ý từ ảnh — trạng thái + ngôn ngữ mặc định.
      if (defaults.suggest_enabled !== undefined) this.suggestEnabled = !!defaults.suggest_enabled;
      if (defaults.suggest_default_lang) this.suggestLang = defaults.suggest_default_lang === 'vi' ? 'vi' : 'en';
      if (defaults.image_credits != null) this.imageCreditCost = Number(defaults.image_credits);
    },
    applyDefaults() {
      // Re-fetch and apply default values (used by reset button)
      this.loadDefaults();
    },
    async load() {
      // Reset kết quả/canvas trước khi nạp lại để luôn phản ánh đúng dữ liệu server —
      // ảnh đã xóa sẽ không "sống lại" sau khi tải lại trang (khởi động lại).
      this.generations = [];
      this.previewId = null;
      this.preview = null;
      this.editSource = null;
      this.canvasLayers = [];
      this.activeLayerId = '';
      this.loadUpscaleMemory();
      // Load settings defaults from backend (set in Studio Settings page)
      await this.loadDefaults();
      try {
        const res = await fetch('/studio/latest', { headers: { Accept: 'application/json' } });
        if (res.status === 401 || res.status === 403 || res.redirected || (res.url && res.url.includes('/dang-nhap'))) { this.needsLogin = true; return; }
        this.needsLogin = false;
        const d = await res.json();
        const items = d.items || d.generations || [];
        if (Array.isArray(items)) this.generations = items;
        if (d.credits_left != null) this.creditsLeft = d.credits_left;
        // Khôi phục bố cục layer đã lưu (chỉ giữ layer còn hợp lệ) — thay cho việc tự chọn ảnh kết quả cũ.
        // KHÔNG tự in lại các kết quả swap vào layer nữa: việc này làm layer "sống lại" sau mỗi lần tải lại
        // và khiến người dùng không thể xóa chúng khỏi canvas. Kết quả swap vẫn hiển thị ở Output/Thư viện.
        this.restoreLayerLayout();
        // Deep-link từ Studio Library: /studio?step=2|3&id=<genId> — khôi phục đúng bước + ảnh.
        const sp = new URLSearchParams(window.location.search);
        const stepParam = parseInt(sp.get('step') || '', 10);
        if (stepParam >= 1 && stepParam <= 3) this.step = stepParam;
        const idParam = parseInt(sp.get('id') || '', 10);
        if (idParam) {
          const target = this.generations.find(g => g.id === idParam);
          if (target) this.select(target);
        }
      } catch (e) { /* app data loads via Alpine too */ }
    },
    select(g) { if (!g) return; this.previewId = g.id; this.preview = { id: g.id, media_url: g.media_url, type: g.type || 'image', status: g.status || 'completed' }; if (g.media_url) { this.pushCanvasLayer(String(g.id), 'gen', 'Ảnh #' + g.id, g.media_url, g.id); this.setActiveLayer(String(g.id)); } },
    // Đảm bảo một kết quả swap được "in" vào layer canvas (id duy nhất, không trùng).
    syncLayerForGen(id, mediaUrl, name, setActive) {
      if (!id || !mediaUrl) return;
      const lid = String(id);
      if (this.canvasLayers.some((l) => l.id === lid)) return;
      this.pushCanvasLayer(lid, 'gen', name || 'Ảnh #' + id, mediaUrl, id);
      if (setActive) this.setActiveLayer(lid);
    },
    async processQueue() {
      try { await fetch('/studio/process', { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF(), Accept: 'application/json' } }); } catch (e) {}
      // refresh the generations so the processed images appear
      try { const res = await fetch('/studio/latest', { headers: { Accept: 'application/json' } }); const d = await res.json(); const items = d.items || d.generations || []; if (Array.isArray(items)) this.generations = items; } catch (e) {}
    },
    async generateImage() {
      if (!this.imagePromptEn || this.generating) return;
      this.generating = true;
      try {
        const d = await this.api('/studio/generate', {
          prompt: this.imagePromptEn,
          creative_level: this.creativeLevel,
          texture: this.texture,
          negative_prompt: this.negativePromptEn || '',
          resolution: this.imageRes,
          ratio: this.imageRatio,
          variants: Number(this.variantCount) || 1
        });
        const items = Array.isArray(d.items) ? d.items : (d.generation_id ? [d] : []);
        items.forEach((it) => this.addGen({ id: it.generation_id, type: 'image', status: it.status, model: it.model, provider: it.provider, media_url: it.media_url, error: it.error, credits_cost: 1, created_at: 'Vừa gửi' }));
        this.setBatch(items.map(it => it.generation_id));
        if (d.credits_left != null) this.creditsLeft = d.credits_left;
        // Process the pending generations (RenderImageJob::dispatchSync, fire-and-forget so the UI isn't blocked).
        this.processQueue();
      } catch (e) { this.toast(e.message || 'Lỗi tạo ảnh.', 'error'); }
      finally { this.generating = false; }
    },
    // i2i — Tạo lại ảnh từ ảnh cho trước (Reimagine / Variation)
    async reimagine(image, prompt, similarity = 70, variants = 1) {
      if (!image || !(prompt || '').trim()) { this.toast('Chọn ảnh + nhập mô tả.', 'error'); return null; }
      try {
        const d = await this.api('/studio/reimagine', { image, prompt, similarity: Number(similarity) || 70, variants: Number(variants) || 1 });
        const items = Array.isArray(d.items) ? d.items : (d.generation_id ? [d] : []);
        items.forEach((it) => this.addGen({ id: it.generation_id, type: 'image', status: it.status, model: it.model, provider: it.provider, media_url: it.media_url, error: it.error, credits_cost: 1, created_at: 'Vừa tạo lại ảnh' }));
        if (items.length) this.setBatch(items.map(it => it.generation_id));
        if (d.credits_left != null) this.creditsLeft = d.credits_left;
        this.processQueue();
        return items;
      } catch (e) { this.toast(e.message || 'Lỗi tạo lại ảnh.', 'error'); return null; }
    },
    // Xóa nền AI: giữ chủ thể (vùng chọn hiện tại nếu có), xóa nền bằng model edit.
    async removeBackground() {
      const l = this.activeLayer;
      if (!l || !l.image) { this.toast('Chọn 1 layer ảnh để xóa nền.', 'error'); return null; }
      try {
        const d = await this.api('/studio/remove-bg', {
          image: l.image,
          mask_data: this.inpaintBrushData || undefined, // lasso tuỳ chọn — không có thì AI tự nhận diện chủ thể
          prompt: '',
        });
        if (d.generation_id) {
          this.addGen({ id: d.generation_id, type: 'image', status: d.status || 'pending', model: d.model || 'qwen-image-edit', provider: d.provider || 'qwen', media_url: d.media_url, error: d.error, credits_cost: d.credits_cost ?? 1, created_at: 'Vừa xóa nền' });
          if (d.credits_left != null) this.creditsLeft = d.credits_left;
          this.pollGeneration(d.generation_id);
        }
        return d;
      } catch (e) { this.toast(e.message || 'Lỗi xóa nền.', 'error'); return null; }
    },
    // i2i — Ghép (thay thế) khuôn mặt cho người mẫu
    async faceSwap(image, face) {
      if (!image || !face) { this.toast('Chọn ảnh người mẫu + ảnh khuôn mặt.', 'error'); return null; }
      try {
        const d = await this.api('/studio/face-swap', { image, face });
        if (d.generation_id) {
          this.addGen({ id: d.generation_id, type: 'image', status: d.status || 'pending', model: d.model || 'faceswap', provider: d.provider || 'qwen', media_url: d.media_url, error: d.error, credits_cost: d.credits_cost ?? 1, created_at: 'Vừa thay khuôn mặt' });
          if (d.credits_left != null) this.creditsLeft = d.credits_left;
          this.pollGeneration(d.generation_id);
        }
        return d;
      } catch (e) { this.toast(e.message || 'Lỗi thay khuôn mặt.', 'error'); return null; }
    },
    // i2i — Ghép 2–3 ảnh thành 1 (Compose / Blend)
    async compose(images, prompt, variants = 1, mode = 'compose', creativeLevel = 6, style = '') {
      if (!Array.isArray(images) || images.length < 2 || !(prompt || '').trim()) { this.toast('Chọn ít nhất 2 ảnh + nhập mô tả.', 'error'); return null; }
      this.composeStage = 'send';
      this.composeError = '';
      this.composeStartTs = Date.now();
      try {
        const d = await this.api('/studio/compose', { images, prompt, variants: Number(variants) || 1, mode, creative_level: Number(creativeLevel) || 6, style: style || '' });
        const items = Array.isArray(d.items) ? d.items : (d.generation_id ? [d] : []);
        items.forEach((it) => this.addGen({ id: it.generation_id, type: 'image', status: it.status, model: it.model, provider: it.provider, media_url: it.media_url, error: it.error, credits_cost: it.credits_cost ?? 1, created_at: 'Vừa ghép ảnh' }));
        const ids = items.map(it => it.generation_id).filter(Boolean);
        this.composeGenIds = ids;
        this.composeStage = 'processing';
        if (items.length) this.setBatch(ids);
        if (d.credits_left != null) this.creditsLeft = d.credits_left;
        ids.forEach((id) => this.pollGeneration(id));
        return items;
      } catch (e) {
        this.composeError = e.message || 'Lỗi ghép ảnh.';
        this.composeStage = 'error';
        this.toast(this.composeError, 'error');
        return null;
      }
    },
    // Kiểm tra khi mọi biến thể compose đã về trạng thái cuối → chuyển stage done/error.
    _checkComposeDone() {
      if (!this.composeGenIds.length || this.composeStage !== 'processing') return;
      const gens = this.composeGenIds.map(id => this.generations.find(g => g.id === Number(id))).filter(Boolean);
      if (gens.length < this.composeGenIds.length) return; // chưa đủ thông tin
      if (!gens.every(g => ['completed', 'failed', 'cancelled'].includes(g.status))) return;
      const done = gens.filter(g => g.status === 'completed').length;
      if (done > 0) {
        this.composeStage = 'done';
        this.toast('✅ Đã ghép xong ' + done + ' biến thể.');
      } else {
        this.composeStage = 'error';
        this.composeError = gens.find(g => g.error)?.error || 'Ghép ảnh thất bại.';
        this.toast(this.composeError, 'error');
      }
    },
    async cancelCompose() {
      for (const id of this.composeGenIds) {
        try { await this.api('/studio/generations/' + id + '/cancel', {}); } catch (e) {}
      }
      this.composeStage = 'cancelled';
      this.toast('Đã hủy ghép ảnh.');
    },
    clearComposeStatus() { this.composeStage = ''; this.composeError = ''; this.composeGenIds = []; this.composeStartTs = 0; },
    async loadPalette(id) {
      if (!id) { this.palette = []; return; }
      try { const res = await fetch('/studio/generations/' + id + '/palette', { headers: { Accept: 'application/json' } }); const d = await res.json(); this.palette = d.colors || []; }
      catch (e) { this.palette = []; }
    },
    // Palette màu cho ẢNH BẤT KỲ (source/uploaded/product/result/dataURL) — trích màu NGAY TRÊN TRÌNH DUYỆT.
    async loadPaletteFromImage(url) {
      if (!url) { this.palette = []; return; }
      try { this.palette = await this._extractColors(url); }
      catch (e) { this.palette = []; }
    },
    _extractColors(url) {
      return new Promise((resolve) => {
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = () => {
          try {
            const W = 64;
            const H = Math.max(1, Math.round((img.naturalHeight || 1) * (W / (img.naturalWidth || 1))));
            const c = document.createElement('canvas'); c.width = W; c.height = H;
            const ctx = c.getContext('2d');
            ctx.drawImage(img, 0, 0, W, H);
            const d = ctx.getImageData(0, 0, W, H).data;
            // Bucket màu giống backend: quantize /32, cộng dồn trung bình, lấy 8 màu nhiều nhất.
            const buckets = new Map();
            for (let i = 0; i < d.length; i += 4) {
              const a = d[i + 3];
              if (a < 128) continue; // bỏ pixel trong suốt
              const r = d[i], g = d[i + 1], b = d[i + 2];
              const key = ((r / 32) | 0) + ',' + ((g / 32) | 0) + ',' + ((b / 32) | 0);
              let bk = buckets.get(key);
              if (!bk) { bk = { n: 0, r: 0, g: 0, b: 0 }; buckets.set(key, bk); }
              bk.n++; bk.r += r; bk.g += g; bk.b += b;
            }
            const sorted = [...buckets.values()].sort((a, b) => b.n - a.n).slice(0, 8);
            const colors = sorted.map((bk) => {
              const rr = Math.round(bk.r / bk.n), gg = Math.round(bk.g / bk.n), bb = Math.round(bk.b / bk.n);
              return '#' + [rr, gg, bb].map((v) => v.toString(16).padStart(2, '0')).join('');
            });
            resolve(colors);
          } catch (e) { resolve([]); }
        };
        img.onerror = () => resolve([]);
        img.src = url;
      });
    },
    async renderVideo() {
      if (!this.videoPromptEn && !this.videoSourceId) { this.toast('Chọn ảnh nguồn hoặc nhập prompt video.', 'error'); return; }
      if (this.videoBusy) return;
      this.videoBusy = true;
      try {
        const d = await this.api('/studio/render-video', { prompt: this.videoPromptEn, source_id: this.videoSourceId, model: this.videoModel, scene: this.videoScene, duration: this.videoDuration, resolution: this.videoRes, image: this.preview?.media_url || '' });
        this.addGen({ id: d.generation_id, type: 'video', status: d.status || 'processing', model: d.model || this.videoModel, provider: d.provider || 'video', media_url: d.media_url || null, error: null, credits_cost: 1, created_at: 'Vừa gửi' });
      } catch (e) { this.toast(e.message || 'Lỗi render video.', 'error'); }
      finally { this.videoBusy = false; }
    },
    // Zoom theo điểm chuột (cx, cy = px so với TÂM khung) — điểm ảnh dưới con trỏ
    // không trôi khi phóng/thu (pan' = c*(1-k) + pan*k).
    zoomAt(cx, cy, factor) {
      const z0 = this.zoom;
      const z1 = Math.max(0.1, Math.min(8, z0 * factor));
      if (z1 === z0) return;
      const k = z1 / z0;
      this.zoom = z1;
      this.pan = { x: cx * (1 - k) + this.pan.x * k, y: cy * (1 - k) + this.pan.y * k };
    },
    wheelZoom(e) {
      // e có thể là event (kèm clientX/Y) hoặc số deltaY (tương thích cũ)
      let cx = 0, cy = 0, delta;
      if (typeof e === 'number') { delta = e; }
      else {
        delta = e.deltaY;
        const cont = this.canvasZoom;
        if (cont) {
          const r = cont.getBoundingClientRect();
          cx = e.clientX - r.left - r.width / 2;
          cy = e.clientY - r.top - r.height / 2;
        }
      }
      this.zoomAt(cx, cy, delta > 0 ? 1 / 1.15 : 1.15);
    },
    // ── Canvas refs (set by StudioApp template refs; markRaw so Vue never proxies DOM nodes) ──
    setCanvasRefs(img, zoom) { this.cvImg = img ? markRaw(img) : null; this.canvasZoom = zoom ? markRaw(zoom) : null; },
    setBrushCanvas(el) { this.brushOverlay = el ? markRaw(el) : null; },
    // ── Reframe / Crop ──
    _clamp(v, lo, hi) { return Math.max(lo, Math.min(hi, v)); },
    ratioAspect() { const p = (this.reframeRatio || '3:4').split(':').map(Number); return p[1] ? p[0] / p[1] : 0.75; },
    // CSS transform dùng chung cho layer (stack + isolate + overlay neo khung) — 1 công thức duy
    // nhất để overlay/preview bám CHÍNH XÁC theo <img> (kể cả xoay/lật/scale của layer).
    layerTransformStyle(l) {
      if (!l) return 'none';
      const s = Math.max(0.05, Math.min(8, Number(l.scale) || 1));
      return 'translate(-50%, -50%) translate(' + (Number(l.x) || 0) + 'px, ' + (Number(l.y) || 0) + 'px) rotate(' + (Number(l.rotation) || 0) + 'deg) scale(' + (s * (l.flipX ? -1 : 1)) + ', ' + (s * (l.flipY ? -1 : 1)) + ')';
    },
    // Đo bbox thật của <img> (dùng khi layer còn xoay/lật — crop/reframe) — nhánh DOM gốc.
    _domMetrics(img, cr) {
      const ir = img.getBoundingClientRect();
      if (!ir.width || !ir.height || !cr.width || !cr.height) return null;
      const iw = img.naturalWidth || 1, ih = img.naturalHeight || 1;
      const ia = iw / ih, box = ir.width / ir.height;
      let vw, vh;
      if (ia > box) { vw = ir.width; vh = ir.width / ia; } else { vh = ir.height; vw = ir.height * ia; }
      return { vw, vh, vx: ir.left - cr.left + (ir.width - vw) / 2, vy: ir.top - cr.top + (ir.height - vh) / 2, crW: cr.width, crH: cr.height, crLeft: cr.left, crTop: cr.top, ia, iw, ih };
    },
    // Geometry của vùng ảnh HIỂN THỊ trong pan container (toạ độ container px).
    // Layer đang "identity" (không xoay/lật — mọi công cụ sửa pixel đều flatten trước) → TÍNH
    // thuần bằng số liệu store (zoom/pan/x/y/scale/baseW/baseH) thay vì đo getBoundingClientRect
    // của <img>. Đo DOM khiến overlay/preview phụ thuộc thứ tự render giữa các component: khi
    // phóng to, một component có thể đọc rect lúc style zoom chưa được patch → preview bị TRÔI
    // khỏi vùng thật (thu nhỏ về thì hết). Vì hàm này chỉ đọc state reactive nên mọi computed
    // tự cập nhật đúng ngay trong cùng một flush — khớp 100% ở mọi mức zoom/pan.
    // (Layer còn xoay/lật hoặc ảnh chưa sẵn layout → fallback đo DOM như cũ.)
    canvasMetrics() {
      const img = this.cvImg, cont = this.canvasZoom;
      if (!img || !cont) return null;
      const cr = cont.getBoundingClientRect();
      if (!cr.width || !cr.height) return null;
      const l = this.activeLayer;
      const rot = l ? Math.abs((Number(l.rotation) || 0) % 360) : 0;
      if (!l || (rot > 0.5 && rot < 359.5) || l.flipX || l.flipY) return this._domMetrics(img, cr);
      const fl = this.frameLayout; // kích thước layout của <img> (baseW/baseH)
      if (!fl) return this._domMetrics(img, cr);
      const iw = img.naturalWidth || 1, ih = img.naturalHeight || 1;
      const z = Math.max(0.05, this.zoom || 1);
      const s = Math.max(0.05, Math.min(8, Number(l.scale) || 1));
      const f = s * z; // tỉ lệ hiển thị thực (base px → màn hình)
      const x = Number(l.x) || 0, y = Number(l.y) || 0;
      // Tâm ảnh cách tâm container: z*(x,y) + pan (pan ở screen px, x/y ở layout px).
      return {
        vw: fl.w * f,
        vh: fl.h * f,
        vx: cr.width / 2 + (this.pan ? this.pan.x : 0) + z * x - (fl.w * f) / 2,
        vy: cr.height / 2 + (this.pan ? this.pan.y : 0) + z * y - (fl.h * f) / 2,
        crW: cr.width, crH: cr.height, crLeft: cr.left, crTop: cr.top,
        ia: (iw > 1 && ih > 1) ? iw / ih : fl.w / fl.h, iw, ih,
      };
    },
    cropStyle() {
      const m = this.canvasMetrics(); if (!m) return { display: 'none' };
      const b = this.cropBox || { x: 0.15, y: 0.15, w: 0.7, h: 0.7 };
      return { left: ((m.vx + b.x * m.vw) / m.crW * 100) + '%', top: ((m.vy + b.y * m.vh) / m.crH * 100) + '%', width: (b.w * m.vw / m.crW * 100) + '%', height: (b.h * m.vh / m.crH * 100) + '%' };
    },
    cropSizeLabel() {
      const img = this.cvImg, b = this.cropBox;
      if (!img || !b) return this.reframeRatio;
      const w = Math.max(1, Math.round(b.w * (img.naturalWidth || 1)));
      const h = Math.max(1, Math.round(b.h * (img.naturalHeight || 1)));
      return this.reframeRatio + ' · ' + w + '×' + h;
    },
    // (Re)create the crop box: 70% tall, keeping the current ratio, centered on the image.
    initCropBox() {
      const m = this.canvasMetrics();
      const ia = m ? m.ia : (this.cropBox && this.cropBox.h ? this.cropBox.w / this.cropBox.h : 0.75);
      const r = this.ratioAspect();
      const ratioFrac = r / ia;
      let h = 0.7; if (h * ratioFrac > 1) h = 1 / ratioFrac;
      let w = h * ratioFrac;
      if (w > 1) { w = 1; h = w / ratioFrac; }
      this.cropBox = { x: (1 - w) / 2, y: (1 - h) / 2, w, h };
    },
    // Re-fit an existing crop box to a new ratio, keeping its center and height where possible.
    refitCropBox() {
      const m = this.canvasMetrics(); if (!m) return;
      const old = this.cropBox || { x: 0.15, y: 0.15, w: 0.7, h: 0.7 };
      const cx = old.x + old.w / 2, cy = old.y + old.h / 2;
      const ratioFrac = this.ratioAspect() / m.ia;
      let h = Math.max(0.2, Math.min(0.9, old.h));
      let w = h * ratioFrac;
      if (w > 1) { w = 1; h = w / ratioFrac; }
      if (h > 1) { h = 1; w = h * ratioFrac; }
      this.cropBox = { x: this._clamp(cx - w / 2, 0, 1 - w), y: this._clamp(cy - h / 2, 0, 1 - h), w, h };
    },
    toggleCrop() {
      this.cropMode = !this.cropMode;
      if (this.cropMode) {
        // Nếu mask đang active → lấy luôn vùng mask làm crop box
        if (this.inpaintMaskMode !== 'none' && (this.inpaintMaskBox.w || 0) >= 0.02) {
          this.cropBox = { ...this.inpaintMaskBox };
        } else {
          this.initCropBox();
        }
      } else {
        this._cropStop(null);
      }
    },
    onCanvasImgLoad() {
      this.imgTick++; // overlay đang bật cần đo lại sau khi ảnh decode xong (kích thước thật)
      if (this.cropMode) this.initCropBox();
      // Ảnh load xong có thể làm overlay erase/draw được gắn LÚC ẢNH CHƯA DECODE bị sai kích thước
      // (gắn với ratio mặc định vuông). Re-attach để khớp tỉ lệ thật của ảnh; nét vẽ trước đó sẽ
      // bị xoá nhưng trước khi ảnh hiển thị thì chưa thể vẽ gì có ý nghĩa — nên an toàn.
      if (this.eraseMode && this._eraseCanvas) this.attachEraseCanvas(this._eraseCanvas);
      if (this.drawMode && this._drawCanvas) this.attachDrawCanvas(this._drawCanvas);
    },
    cropStart(e, key) {
      if (!this.cropMode) return;
      // NOTE: no preventDefault() here — canceling pointerdown would also suppress the
      // compatibility dblclick used for "double-click to cancel". touch-action:none (CSS)
      // already blocks scroll/zoom and select-none blocks text selection.
      e.stopPropagation();
      // A previous drag may still be armed (e.g. a fast second press before the first release) — close it first so its window listeners are removed.
      if (this._cropDrag) this._cropStop(this._cropDrag.handlers);
      const handlers = { move: (ev) => this._cropQueue(ev), up: () => this._cropStop(handlers) };
      this._cropDrag = { key, sx: e.clientX, sy: e.clientY, box: { ...(this.cropBox || { x: 0.15, y: 0.15, w: 0.7, h: 0.7 }) }, handlers };
      window.addEventListener('pointermove', handlers.move);
      window.addEventListener('pointerup', handlers.up);
      window.addEventListener('pointercancel', handlers.up);
    },
    // Batch pointermoves through rAF so dragging never triggers a layout read per event.
    _cropQueue(e) {
      if (!this._cropDrag) return;
      this._cropPending = e;
      if (this._cropRaf) return;
      const flush = () => { this._cropRaf = null; const ev = this._cropPending; this._cropPending = null; if (ev && this._cropDrag) this.cropMove(ev); };
      if (typeof requestAnimationFrame === 'function') this._cropRaf = requestAnimationFrame(flush);
      else flush();
    },
    _cropStop(handlers) {
      this._cropDrag = null;
      this._cropPending = null;
      if (this._cropRaf != null) { if (typeof cancelAnimationFrame === 'function') cancelAnimationFrame(this._cropRaf); this._cropRaf = null; }
      if (handlers) {
        window.removeEventListener('pointermove', handlers.move);
        window.removeEventListener('pointerup', handlers.up);
        window.removeEventListener('pointercancel', handlers.up);
      }
    },
    cropMove(e) {
      const d = this._cropDrag; if (!d || !this.cropMode) return;
      const m = this.canvasMetrics(); if (!m) return;
      const bx = (e.clientX - d.sx) / m.vw, by = (e.clientY - d.sy) / m.vh;
      const b = { ...d.box };
      const MIN = 0.05;
      if (d.key === 'move') {
        b.x = this._clamp(b.x + bx, 0, 1 - b.w);
        b.y = this._clamp(b.y + by, 0, 1 - b.h);
      } else {
        // Corner resize, ratio-locked, opposite corner anchored.
        const ratioFrac = this.ratioAspect() / m.ia;
        const maxRight = 1 - b.x, maxBottom = 1 - b.y;
        const right = b.x + b.w, bottom = b.y + b.h;
        let nw = b.w, nh = b.h, x = b.x, y = b.y;
        if (d.key === 'se' || d.key === 'resize') { nh = this._clamp(b.h + Math.max(bx, by), MIN, Math.max(MIN, Math.min(maxBottom, maxRight / ratioFrac))); nw = nh * ratioFrac; }
        else if (d.key === 'sw') { nh = this._clamp(b.h + Math.max(-bx, by), MIN, Math.max(MIN, Math.min(maxBottom, right / ratioFrac))); nw = nh * ratioFrac; x = right - nw; }
        else if (d.key === 'ne') { nw = this._clamp(b.w + Math.max(bx, -by), MIN, Math.max(MIN, Math.min(maxRight, bottom * ratioFrac))); nh = nw / ratioFrac; y = bottom - nh; }
        else if (d.key === 'nw') { nw = this._clamp(b.w + Math.max(-bx, -by), MIN, Math.max(MIN, Math.min(right, bottom * ratioFrac))); nh = nw / ratioFrac; x = right - nw; y = bottom - nh; }
        b.w = this._clamp(nw, MIN, 1); b.h = this._clamp(nh, MIN, 1); b.x = x; b.y = y;
      }
      this.cropBox = b;
    },
    async confirmCrop() {
      if (!this.cropMode || this.reframing) return;
      const img = this.cvImg;
      if (!img) { this.toast('Chưa có ảnh trên canvas.', 'error'); return; }
      const iw = img.naturalWidth, ih = img.naturalHeight;
      if (!iw || !ih) { this.toast('Ảnh chưa tải xong.', 'error'); return; }
      const b = this.cropBox || { x: 0.15, y: 0.15, w: 0.7, h: 0.7 };
      const x = Math.max(0, Math.round(b.x * iw)), y = Math.max(0, Math.round(b.y * ih));
      const w = Math.max(1, Math.min(iw - x, Math.round(b.w * iw))), h = Math.max(1, Math.min(ih - y, Math.round(b.h * ih)));
      this.reframing = true;
      try {
        const d = await this.api('/studio/reframe', { image: this.upscaleSrc, ratio: this.reframeRatio, x, y, w, h });
        this.addGen({ id: d.generation_id, type: 'image', status: 'completed', model: 'reframe', provider: 'reframe', media_url: d.media_url, error: null, credits_cost: 0, created_at: 'Vừa cắt' });
        this.cropMode = false; this._cropStop(null);
        this.toast('Đã cắt vùng đã chọn.');
      } catch (err) { this.toast(err.message || 'Lỗi cắt.', 'error'); }
      finally { this.reframing = false; }
    },
    async reframeCenter() {
      if (!this.upscaleSrc || this.reframing) return;
      this.reframing = true;
      try {
        const d = await this.api('/studio/reframe', { image: this.upscaleSrc, ratio: this.reframeRatio });
        this.addGen({ id: d.generation_id, type: 'image', status: 'completed', model: 'reframe', provider: 'reframe', media_url: d.media_url, error: null, credits_cost: 0, created_at: 'Vừa cắt' });
        this.toast('Đã cắt giữa ' + this.reframeRatio + '.');
      } catch (e) { this.toast(e.message || 'Lỗi cắt.', 'error'); }
      finally { this.reframing = false; }
    },
    async applyFilmLook() {
      if (!this.upscaleSrc || this.looking) return;
      this.looking = true;
      try {
        const d = await this.api('/studio/look', { image: this.upscaleSrc, look: this.lookPreset, level: Number(this.lookLevel) || 5 });
        this.addGen({ id: d.generation_id, type: 'image', status: 'completed', model: 'look', provider: 'look', media_url: d.media_url, error: null, credits_cost: 0, created_at: 'Vừa áp dụng' });
        this.toast('Đã áp dụng Look ' + this.lookPreset + '.');
      } catch (e) { this.toast(e.message || 'Lỗi áp dụng Look.', 'error'); }
      finally { this.looking = false; }
    },
    upscaleCfg() { return { scale: this.upscaleScale, refine: this.upscaleRefine, photoreal: this.studioPhotoreal, light: this.lightShadow, sharpen: this.sharpen, clarity: this.clarity, vibrance: this.vibrance }; },
    loadUpscaleMemory() {
      try { const m = JSON.parse(localStorage.getItem('trillfa.upscale') || '{}'); if (m.settings) Object.assign(this, { upscaleScale: m.settings.scale ?? 2, upscaleRefine: m.settings.refine ?? 5, studioPhotoreal: m.settings.photoreal ?? 5, lightShadow: m.settings.light ?? 5, sharpen: m.settings.sharpen ?? 3, clarity: m.settings.clarity ?? 3, vibrance: m.settings.vibrance ?? 3 }); if (Array.isArray(m.presets)) this.upscalePresets = m.presets; } catch (e) {}
    },
    saveUpscaleMemory() { try { localStorage.setItem('trillfa.upscale', JSON.stringify({ settings: this.upscaleCfg(), presets: this.upscalePresets })); } catch (e) {} },
    savePreset(name) { const n = (name || 'Preset ' + (this.upscalePresets.length + 1)).trim(); const existing = this.upscalePresets.find(p => p.name === n); const cfg = this.upscaleCfg(); if (existing) Object.assign(existing, cfg); else this.upscalePresets.push({ name: n, ...cfg }); this.saveUpscaleMemory(); this.toast('Đã lưu preset "' + n + '".'); },
    applyPreset(p) { Object.assign(this, { upscaleScale: p.scale ?? 2, upscaleRefine: p.refine ?? 5, studioPhotoreal: p.photoreal ?? 5, lightShadow: p.light ?? 5, sharpen: p.sharpen ?? 3, clarity: p.clarity ?? 3, vibrance: p.vibrance ?? 3 }); this.saveUpscaleMemory(); this.toast('Đã áp dụng preset "' + p.name + '".'); },
    deletePreset(name) { this.upscalePresets = this.upscalePresets.filter(p => p.name !== name); this.saveUpscaleMemory(); },
    zoomIn() { this.zoomAt(0, 0, 1.5); },
    zoomOut() { this.zoomAt(0, 0, 1 / 1.5); },
    // "Vừa": thu/phóng cho VỪA KHUNG nhìn — tính bbox các layer đang hiển thị (ở zoom 1, đã tính
    // rotation/scale) rồi chọn zoom = min(cw/bbw, ch/bbh). Trước đây chỉ reset zoom=1/pan=0 nên
    // ảnh nhỏ/ảnh đã di chuyển không bao giờ "vừa" được với vùng canvas.
    zoomFit() {
      const cont = this.canvasZoom;
      const layers = this.canvasLayers.filter((l) => l.visible !== false && l.image);
      if (!cont || !layers.length) { this.zoom = 1; this.pan = { x: 0, y: 0 }; return; }
      let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
      layers.forEach((l) => {
        const bw = Number(l.baseW) || 512, bh = Number(l.baseH) || 512;
        const s = Number(l.scale) || 1;
        const rot = ((Number(l.rotation) || 0) * Math.PI) / 180;
        const cos = Math.cos(rot), sin = Math.sin(rot);
        const hw = (bw * s) / 2, hh = (bh * s) / 2;
        const x = Number(l.x) || 0, y = Number(l.y) || 0;
        [[-hw, -hh], [hw, -hh], [hw, hh], [-hw, hh]].forEach(([cx, cy]) => {
          const px = cx * cos - cy * sin + x;
          const py = cx * sin + cy * cos + y;
          if (px < minX) minX = px; if (px > maxX) maxX = px;
          if (py < minY) minY = py; if (py > maxY) maxY = py;
        });
      });
      const r = cont.getBoundingClientRect();
      const cw = Math.max(1, r.width - 64), ch = Math.max(1, r.height - 64);
      const bw2 = Math.max(1, maxX - minX), bh2 = Math.max(1, maxY - minY);
      // Giới hạn trên 100% để "Vừa" luôn là cái nhìn tổng thể (không phóng to hơn ảnh thật).
      const z = Math.max(0.05, Math.min(1, Math.min(cw / bw2, ch / bh2)));
      const cx = (minX + maxX) / 2, cy = (minY + maxY) / 2;
      this.zoom = z;
      this.pan = { x: -cx * z, y: -cy * z };
    },
    // Pinch-zoom (2 ngón) trên cảm ứng.
    beginPinch(t1, t2) { this._pinch = { dist: Math.hypot(t2.clientX - t1.clientX, t2.clientY - t1.clientY) || 1, zoom: this.zoom }; this._drag = null; },
    pinchMove(t1, t2) { if (!this._pinch) return; const dist = Math.hypot(t2.clientX - t1.clientX, t2.clientY - t1.clientY) || 1; this.zoom = Math.max(0.1, Math.min(8, this._pinch.zoom * (dist / this._pinch.dist))); },
    endPinch() { this._pinch = null; },
    panStart(e) { if (this._cropDrag) return; this._drag = { x: e.clientX, y: e.clientY, px: this.pan.x, py: this.pan.y }; },
    panMove(e) {
      if (!this._drag) return;
      // Pan TỰ DO, không giới hạn, không đọc layout mỗi event → kéo mượt không khựng.
      this.pan.x = this._drag.px + (e.clientX - this._drag.x);
      this.pan.y = this._drag.py + (e.clientY - this._drag.y);
    },
    panEnd() { this._drag = null; },
    async deleteGen(g) {
      // Layer lưu cục bộ từ canvas (saveActiveLayerToOutput) KHÔNG có record server → xóa cục bộ.
      const isLocal = !!(g && (g.provider === 'layer' || g.model === 'layer' || String(g.id || '').startsWith('layer-')));
      if (isLocal) {
        this._removeGenLocal(g);
        this.toast('Đã xóa layer khỏi Output.');
        return true;
      }
      try {
        const r = await fetch('/studio/generations/' + g.id, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': CSRF(), Accept: 'application/json' } });
        const ct = (r.headers.get('content-type') || '');
        const d = ct.includes('application/json') ? await r.json().catch(() => ({})) : {};
        // Redirect về trang login (chưa đăng nhập/hết phiên) trả HTML 200 — phải coi là THẤT BẠI
        // để không báo "Đã xóa" trong khi server thực tế chưa xóa.
        if (!r.ok || r.redirected || !ct.includes('application/json')) throw new Error(d.message || 'Không xóa được — hãy tải lại trang và thử lại.');
        this._removeGenLocal(g);
        this.toast('Đã xóa.');
        return true;
      }
      catch (e) { this.toast(e.message || 'Lỗi xóa.', 'error'); return false; }
    },
    // Gỡ generation khỏi store + layer canvas liên quan (dùng chung cho xóa server & xóa cục bộ).
    _removeGenLocal(g) {
      this.generations = this.generations.filter(x => x.id !== g.id);
      const lid = String(g.id);
      if (this.canvasLayers.some((l) => l.id === lid)) this.canvasLayers = this.canvasLayers.filter((l) => l.id !== lid);
      const orphanSource = g.media_url ? this.canvasLayers.find((l) => l.kind === 'source' && l.image === g.media_url) : null;
      if (orphanSource) this.canvasLayers = this.canvasLayers.filter((l) => !(l.kind === 'source' && l.image === g.media_url));
      if (this.activeLayerId === lid || this.previewId === g.id || (orphanSource && this.activeLayerId === orphanSource.id)) {
        const next = this.canvasLayers.find((x) => x.visible !== false);
        if (next) this.selectLayer(next);
        else { this.activeLayerId = ''; this.editSource = null; this.previewId = null; this.preview = null; }
      }
      this.saveLayerLayout();
    },
    // Điều hướng chuẩn khi bấm "Chỉnh sửa" / "Tạo video" từ GalleryModal —
    // hoạt động ở MỌI nơi GalleryModal được mở (Studio 1 trang / Studio Library / …):
    //  - đang ở trang studio (có step 1/2/3): chọn ảnh + chuyển step ngay.
    //  - đang ở trang library (không có step): redirect về /studio?step=&id= để
    //    StudioApp (qua load()) khôi phục đúng bước + đúng ảnh đang chọn.
    goEditor(g, step) {
      if (!g) return;
      this.viewer = null;
      this.select(g);
      const onLibrary = window.location.pathname.includes('/library');
      if (onLibrary) {
        window.location.href = '/studio?step=' + step + '&id=' + g.id;
      } else {
        this.step = step;
      }
    },
    goEdit(g) { this.goEditor(g, 2); },
    goVideo(g) { this.goEditor(g, 3); },
    pushCanvasLayer(id, kind, name, image, genId) {
      if (!id || !image) return;
      if (this.canvasLayers.some(l => l.id === id)) return;
      const i = this.canvasLayers.length;
      const x = (i % 3) * 360, y = Math.floor(i / 3) * 460; // vị trí tạm, sẽ căn lại theo kích thước thật
      this.canvasLayers.push({ id, kind, name, image, genId, visible: true, locked: false, x, y, scale: 1, rotation: 0, opacity: 1, blend: 'normal', flipX: false, flipY: false });
      this.saveLayerLayout();
      this._positionByImageSize(id, image);
    },
    // Thêm 1 layer TRỐNG (trong suốt) để vẽ — nền tảng cho hiệu ứng sau (brush/vẽ tự do GIMP/PS).
    async addBlankLayer(bg = null) {
      this.pushHistory();
      let w = 1024, h = 1024;
      const src = this.activeLayer;
      if (src && src.image) {
        try {
          const img = await this._loadImageSrc(src.image);
          if (img.naturalWidth && img.naturalHeight) { w = img.naturalWidth; h = img.naturalHeight; }
        } catch (e) { /* giữ kích thước mặc định */ }
      }
      const canvas = document.createElement('canvas');
      canvas.width = w; canvas.height = h;
      if (bg) {
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = bg;
        ctx.fillRect(0, 0, w, h);
      }
      const url = canvas.toDataURL('image/png');
      const id = 'blank-' + Date.now();
      // Thêm TRÙNG KHỚP với layer active (cùng x/y/scale/rotation/blend), không flow ra chỗ khác.
      this.canvasLayers.push({
        id, kind: 'source', name: bg ? 'Layer màu' : 'Layer trong suốt', image: url, genId: null,
        visible: true, locked: false,
        x: src ? (src.x || 0) : 0, y: src ? (src.y || 0) : 0,
        scale: src ? (src.scale || 1) : 1, rotation: src ? (src.rotation || 0) : 0,
        opacity: src ? (src.opacity != null ? src.opacity : 1) : 1,
        blend: src ? (src.blend || 'normal') : 'normal', flipX: false, flipY: false,
        baseW: src ? src.baseW : undefined, baseH: src ? src.baseH : undefined,
      });
      this.saveLayerLayout();
      // Highlight layer mới (viền nổi bật) rồi tự tắt sau 2.5s hoặc khi người dùng chọn layer khác.
      this.highlightLayerId = id;
      clearTimeout(this._highlightTimer);
      this._highlightTimer = setTimeout(() => { if (this.highlightLayerId === id) this.highlightLayerId = ''; }, 2500);
      this.setActiveLayer(id);
      this.toast(bg ? 'Đã thêm layer màu (trùng vị trí layer đang chọn).' : 'Đã thêm layer trong suốt (trùng vị trí layer đang chọn).');
    },
    // Tô màu TOÀN BỘ layer đang chọn bằng màu hiện tại (inpaintFillColor).
    async fillActiveLayer() {
      const l = this.activeLayer;
      if (!l) { this.toast('Chưa có layer để tô — thêm 1 layer trước.', 'error'); return; }
      this.pushHistory();
      let w = 1024, h = 1024;
      if (l.image) {
        try {
          const img = await this._loadImageSrc(l.image);
          if (img.naturalWidth && img.naturalHeight) { w = img.naturalWidth; h = img.naturalHeight; }
        } catch (e) { /* giữ mặc định */ }
      }
      const canvas = document.createElement('canvas');
      canvas.width = w; canvas.height = h;
      const ctx = canvas.getContext('2d');
      ctx.fillStyle = this.inpaintFillColor || '#ffffff';
      ctx.fillRect(0, 0, w, h);
      l.image = canvas.toDataURL('image/png');
      this.saveLayerLayout();
      this.toast('Đã tô màu toàn bộ layer.');
    },
    // Lưu layer active (kể cả layer vẽ/fill/duplicate chưa từng là generation) vào Output.
    async saveActiveLayerToOutput() {
      const l = this.activeLayer;
      if (!l || !l.image) { this.toast('Chưa có layer để lưu.', 'error'); return; }
      try {
        const blob = await (await fetch(l.image)).blob();
        const fd = new FormData();
        fd.append('image', new File([blob], 'layer-' + Date.now() + '.png', { type: 'image/png' }));
        const res = await fetch('/studio/upload-ref', { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF(), Accept: 'application/json' }, body: fd });
        const d = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(d.message || 'Không lưu được.');
        const gid = 'layer-' + Date.now();
        // Thêm generation vào Output mà KHÔNG tạo layer canvas trùng (layer active đã có trên canvas).
        this.generations.unshift({ id: gid, type: 'image', status: 'completed', model: 'layer', provider: 'layer', media_url: d.url, error: null, credits_cost: 0, created_at: 'Vừa lưu' });
        this.toast('Đã lưu layer vào Output.');
      } catch (e) { this.toast(e.message || 'Không lưu được.', 'error'); }
    },
    // Đo kích thước thật của ảnh rồi xếp theo flow: hàng ngang (có gap), tự xuống hàng khi quá rộng.
    _positionByImageSize(id, image) {
      const MAX = 512, GAP = 24;
      const img = new Image();
      img.onload = () => {
        const l = this.canvasLayers.find((x) => x.id === id);
        if (!l) return;
        const base = Math.min(1, MAX / img.naturalWidth, MAX / img.naturalHeight);
        l.baseW = img.naturalWidth * base;
        l.baseH = img.naturalHeight * base;
        const prev = this.canvasLayers.filter((x) => x.id !== id && x.baseW != null);
        if (!prev.length) { l.x = 0; l.y = 0; }
        else {
          const last = prev[prev.length - 1];
          const lw = (last.baseW || MAX) * (last.scale || 1);
          const lh = (last.baseH || MAX) * (last.scale || 1);
          let nx = (last.x || 0) + lw / 2 + l.baseW / 2 + GAP;
          let ny = last.y || 0;
          const ROW_MAX = 3 * (MAX + GAP);
          if (nx > ROW_MAX) { nx = 0; ny = (last.y || 0) + lh + GAP; }
          l.x = nx; l.y = ny;
        }
        this.saveLayerLayout();
      };
      img.onerror = () => {};
      img.src = image;
    },
    setActiveLayer(id) { if (!id) return; const l = this.canvasLayers.find(x => x.id === id); if (!l) return; if (l.visible === false) l.visible = true; this.activeLayerId = id; if (this.highlightLayerId && this.highlightLayerId !== id) this.highlightLayerId = ''; if (l.kind === 'source') { this.editSource = { url: l.image, name: l.name }; this.previewId = null; this.preview = null; } else if (l.genId) { const g = this.generations.find(x => x.id === l.genId); if (g) { this.previewId = g.id; this.preview = { id: g.id, media_url: g.media_url, type: g.type || 'image', status: g.status || 'completed' }; } this.editSource = null; } this.saveLayerLayout(); },
    selectLayer(item) { if (!item) return; this.setActiveLayer(item.id); },
    // Gỡ layer KHỎI CANVAS (chỉ ảnh hưởng hiển thị) — KHÔNG xóa output/ảnh kết quả hay file nguồn.
    deleteLayer(item) {
      if (!item) return;
      if (item.locked) { this.toast('Layer đang khóa — mở khóa trước khi gỡ.', 'error'); return; }
      this.pushHistory();
      const wasSource = item.kind === 'source' || item.id === 'source';
      this.canvasLayers = this.canvasLayers.filter((l) => l.id !== item.id);
      if (wasSource) this.editSource = null;
      if (this.activeLayerId === item.id) {
        const next = this.canvasLayers.find((x) => x.visible !== false);
        if (next) this.selectLayer(next);
        else { this.activeLayerId = ''; this.editSource = null; this.previewId = null; this.preview = null; }
      }
      this.saveLayerLayout();
    },
    // Bỏ ảnh nguồn khỏi canvas (không xóa file/output).
    clearSource() {
      this.editSource = null;
      this.canvasLayers = this.canvasLayers.filter((l) => l.id !== 'source');
      if (this.activeLayerId === 'source') {
        const next = this.canvasLayers.find((x) => x.visible !== false);
        if (next) this.selectLayer(next);
        else { this.activeLayerId = ''; this.previewId = null; this.preview = null; }
      }
      this.saveLayerLayout();
    },
    // Bỏ ĐÚNG ảnh nguồn đang dùng (id 'source' HOẶC các layer src-* do thêm nhiều ảnh) khỏi canvas.
    // clearSource() cũ chỉ xoá layer id 'source' → với ảnh nguồn có id 'src-…' thì ảnh vẫn nằm
    // trên canvas dù nút "Bỏ ảnh nguồn" đã ẩn (trạng thái treo, gây nhầm).
    removeEditSource() {
      const src = this.editSource;
      if (!src) return;
      const pool = this.canvasLayers.filter((x) => x.kind === 'source' && x.image === src.url);
      if (!pool.length) { this.editSource = null; this.saveLayerLayout(); return; }
      // Ưu tiên layer đang active (nhiều layer có thể dùng chung URL sau duplicate).
      const l = pool.find((x) => x.id === this.activeLayerId) || pool[0];
      this.deleteLayer(l); // xoá layer + chuyển active đúng cách (tôn trọng lock)
    },
    // Dọn sạch canvas + toàn bộ layer (chỉ xóa trạng thái hiển thị — KHÔNG xóa output/ảnh kết quả).
    cleanCanvas() {
      this.previewId = null;
      this.preview = null;
      this.editSource = null;
      this.canvasLayers = [];
      this.activeLayerId = '';
      this.palette = [];
      this.pan = { x: 0, y: 0 };
      this.zoom = 1;
      this.saveLayerLayout();
      this.toast('Đã dọn canvas — ảnh kết quả vẫn còn trong Kết quả/Thư viện.');
    },
    // Đổi tên layer. Với layer ảnh kết quả (gen), tên mới cũng được lưu vào generation
    // để hiển thị đồng bộ ở Output/Thư viện.
    renameLayer(id, name) {
      const l = this.canvasLayers.find((x) => x.id === id);
      if (!l) return;
      if (l.locked) { this.toast('Layer đang khóa.', 'error'); return; }
      const n = (name || '').trim();
      if (!n) return;
      l.name = n;
      if (l.kind === 'source' && this.editSource) this.editSource.name = n;
      if (l.kind === 'gen' && l.genId) {
        const g = this.generations.find((x) => x.id === l.genId);
        if (g) { g.meta = Object.assign({}, g.meta || {}, { name: n }); }
        fetch('/studio/generations/' + l.genId + '/rename', { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF(), 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ name: n }) }).catch(() => {});
      }
      this.saveLayerLayout();
      this.toast('Đã đổi tên layer.');
    },
    // Di chuyển layer lên/xuống TRONG NGĂN XẾP (không ảnh hưởng output).
    // Quy ước: mảng canvasLayers[0..n-1] = dưới→trên (zIndex = vị trí mảng); ngăn xếp HIỂN THỊ
    // front-first (layer trên cùng danh sách = đang ở TRƯỚC). Vì vậy:
    //   'up'   = lên đầu danh sách = RA PHÍA TRƯỚC (index +1)
    //   'down' = xuống cuối danh sách = VỀ PHÍA SAU (index -1)
    moveLayer(id, dir) {
      const l = this.canvasLayers.find((x) => x.id === id);
      if (!l || l.locked) return;
      this.pushHistory();
      const i = this.canvasLayers.findIndex((x) => x.id === id);
      const j = i + (dir === 'up' ? 1 : -1);
      if (i < 0 || j < 0 || j >= this.canvasLayers.length) return;
      const arr = this.canvasLayers.slice();
      const t = arr[i]; arr[i] = arr[j]; arr[j] = t;
      this.canvasLayers = arr;
      this.saveLayerLayout();
    },
    // ── Transform layer (vị trí / kích thước / xoay / opacity / blend) ──
    updateLayerTransform(id, patch) {
      const l = this.canvasLayers.find((x) => x.id === id);
      if (!l) return;
      Object.assign(l, patch);
      this.saveLayerLayout();
    },
    resetLayerTransform(id) {
      const l = this.canvasLayers.find((x) => x.id === id);
      if (!l) return;
      this.pushHistory();
      Object.assign(l, { x: 0, y: 0, scale: 1, rotation: 0, opacity: 1, blend: 'normal' });
      this.saveLayerLayout();
    },
    // Nhân đôi layer (giữ nguyên transform, tạo id + tên mới). Bản sao được đẩy lệch 12px để
    // phân biệt ngay với bản gốc (đang chồng khít) + viền highlight như layer mới.
    duplicateLayer(id) {
      const l = this.canvasLayers.find((x) => x.id === id);
      if (!l) return;
      this.pushHistory();
      const copy = {
        ...l,
        id: id + '-copy-' + Date.now(),
        name: (l.name || 'Layer') + ' (bản sao)',
        x: (Number(l.x) || 0) + 12,
        y: (Number(l.y) || 0) + 12,
      };
      this.canvasLayers.push(copy);
      this.highlightLayerId = copy.id;
      clearTimeout(this._highlightTimer);
      this._highlightTimer = setTimeout(() => { if (this.highlightLayerId === copy.id) this.highlightLayerId = ''; }, 2500);
      this.setActiveLayer(copy.id);
      this.saveLayerLayout();
    },
    // Đưa layer lên trên cùng ('front') hoặc xuống dưới cùng ('back').
    bringLayerTo(id, where) {
      const i = this.canvasLayers.findIndex((x) => x.id === id);
      if (i < 0) return;
      this.pushHistory();
      const arr = this.canvasLayers.slice();
      const item = arr.splice(i, 1)[0];
      if (where === 'front') arr.push(item);
      else arr.unshift(item);
      this.canvasLayers = arr;
      this.saveLayerLayout();
    },
    // Lật ngang / lật dọc layer.
    toggleFlipX(id) { const l = this.canvasLayers.find((x) => x.id === id); if (!l) return; this.pushHistory(); l.flipX = !l.flipX; this.saveLayerLayout(); },
    toggleFlipY(id) { const l = this.canvasLayers.find((x) => x.id === id); if (!l) return; this.pushHistory(); l.flipY = !l.flipY; this.saveLayerLayout(); },
    // ── Xóa vùng (erase brush + feather) ──
    async toggleErase() {
      const l = this.activeLayer;
      if (l && l.locked) { this.toast('Layer đang khóa — mở khóa trước khi xóa.', 'error'); return; }
      if (!this.eraseMode && !(await this.flattenActiveLayerTransform())) return;
      this.eraseMode = !this.eraseMode;
      // KHÔNG reset zoom/pan — đóng băng vị trí & độ thu phóng hiện tại khi chọn công cụ.
      if (!this.eraseMode) this.applyErase();
    },
    // Gắn canvas overlay (DOM) làm mask để vẽ + xem trước realtime.
    attachEraseCanvas(el) {
      if (!el) { this._eraseCanvas = null; this._eraseCtx = null; return; }
      // Canvas theo TỈ LỆ HIỂN THỊ của ảnh trên canvas (không vuông cứng) — nếu vuông thì nét xóa
      // bị méo & lệch vị trí khi bake. Dùng vw/vh (vùng ảnh hiển thị) thay vì naturalWidth:
      // đúng tỉ lệ NGAY CẢ khi ảnh chưa decode xong (naturalWidth = 0) vì vw/vh đã có sau layout.
      const m = this.canvasMetrics();
      const base = 1024;
      const ratio = m && m.vw && m.vh ? m.vw / m.vh : 1;
      let w = base, h = base;
      if (ratio >= 1) h = Math.max(1, Math.round(base / ratio));
      else w = Math.max(1, Math.round(base * ratio));
      el.width = w; el.height = h;
      this._eraseCanvas = el;
      this._eraseCtx = el.getContext('2d');
      this._eraseCtx.clearRect(0, 0, w, h);
      this._eraseHasStrokes = false; // canvas mới → chưa có nét
    },
    setEraseFeather(v) { this.eraseFeather = Number(v) || 0; },
    _eraseRadius() { return Math.max(3, Math.min(150, Number(this.eraseBrushSize) || 24)); },
    _erasePoint(e) {
      const m = this.canvasMetrics();
      if (!m) return { nx: 0.5, ny: 0.5 };
      return { nx: this._clamp((e.clientX - m.crLeft - m.vx) / m.vw, 0, 1), ny: this._clamp((e.clientY - m.crTop - m.vy) / m.vh, 0, 1) };
    },
    _drawEraseDot(p) {
      const c = this._eraseCtx; if (!c) return;
      const w = this._eraseCanvas.width, h = this._eraseCanvas.height, r = this._eraseRadius();
      const f = Math.max(0, Math.min(1, (Number(this.eraseFeather) || 0) / 60)); // feather 0-60
      const hard = Math.max(0.15, 0.9 - f * 0.75); // mép cứng (f=0) → rất mềm (f=1)
      const g = c.createRadialGradient(p.nx * w, p.ny * h, 0, p.nx * w, p.ny * h, r);
      g.addColorStop(0, 'rgba(0,0,0,1)');
      g.addColorStop(hard, 'rgba(0,0,0,0.85)');
      g.addColorStop(1, 'rgba(0,0,0,0)');
      c.fillStyle = g;
      c.beginPath(); c.arc(p.nx * w, p.ny * h, r, 0, Math.PI * 2); c.fill();
    },
    _drawEraseLine(from, to) {
      const c = this._eraseCtx; if (!c) return;
      const w = this._eraseCanvas.width, r = this._eraseRadius();
      const steps = Math.max(1, Math.ceil(Math.hypot(to.nx - from.nx, to.ny - from.ny) * w / (r / 2)));
      for (let s = 0; s <= steps; s++) this._drawEraseDot({ nx: from.nx + (to.nx - from.nx) * (s / steps), ny: from.ny + (to.ny - from.ny) * (s / steps) });
    },
    beginEraseBrush(e) { if (!this.eraseMode) return; if (e.currentTarget && e.currentTarget.setPointerCapture && e.pointerId != null) { try { e.currentTarget.setPointerCapture(e.pointerId); } catch (err) {} } this._eraseDrawing = true; this._eraseLast = this._erasePoint(e); this._eraseHasStrokes = true; this._drawEraseDot(this._eraseLast); },
    eraseBrushMove(e) { if (!this._eraseDrawing) return; const p = this._erasePoint(e); this._drawEraseLine(this._eraseLast || p, p); this._eraseLast = p; },
    endEraseBrush() { this._eraseDrawing = false; this._eraseLast = null; },
    applyErase() {
      if (this._eraseBusy) return Promise.resolve();
      // Không có nét thật → không push history / không bake (tránh undo rác khi chỉ bật/tắt công cụ).
      if (!this._eraseHasStrokes) return Promise.resolve();
      this.pushHistory();
      this._eraseBusy = true;
      this._eraseHasStrokes = false;
      return new Promise((resolve) => {
        const l = this.activeLayer;
        const ec = this._eraseCanvas;
        if (!l || l.locked || !ec) {
          if (l && l.locked) this.toast('Layer đang khóa — mở khóa trước khi xóa.', 'error');
          this._eraseBusy = false;
          resolve();
          return;
        }
        const img = new Image();
        img.onload = () => {
          const w = img.naturalWidth, h = img.naturalHeight;
          const canvas = document.createElement('canvas'); canvas.width = w; canvas.height = h;
          const ctx = canvas.getContext('2d');
          ctx.drawImage(img, 0, 0);
          ctx.globalCompositeOperation = 'destination-out';
          ctx.drawImage(ec, 0, 0, w, h);
          l.image = canvas.toDataURL('image/png');
          this.saveLayerLayout();
          this.toast('Đã xóa vùng.');
          this._eraseBusy = false;
          resolve();
        };
        img.onerror = () => { this.toast('Không xóa được ảnh.', 'error'); this._eraseBusy = false; resolve(); };
        img.src = l.image;
      });
    },
    // "🗑 Xóa": áp dụng nét đã vẽ vào layer rồi xoá canvas — GIỮ chế độ để vẽ tiếp (không tự thoát).
    applyEraseNow() {
      this.applyErase().then(() => {
        if (this._eraseCtx && this._eraseCanvas) this._eraseCtx.clearRect(0, 0, this._eraseCanvas.width, this._eraseCanvas.height);
      });
    },
    // "✓ Xong": hoàn tất xóa — áp dụng nét còn lại rồi thoát chế độ.
    finishErase() { if (!this.eraseMode) return; this.applyErase().then(() => { this.eraseMode = false; }); },
    // "✕ Hủy": thoát chế độ erase KHÔNG áp dụng (bỏ nét đã vẽ).
    cancelErase() { if (!this.eraseMode) return; this.eraseMode = false; this.toast('Đã hủy xóa.'); },
    // ── Vẽ tự do (paint brush): tô màu lên layer active ──
    async toggleDraw() {
      const l = this.activeLayer;
      if (l && l.locked) { this.toast('Layer đang khóa — mở khóa trước khi vẽ.', 'error'); return; }
      if (!this.drawMode && !(await this.flattenActiveLayerTransform())) return;
      this.drawMode = !this.drawMode;
      if (!this.drawMode) this.applyDraw();
    },
    attachDrawCanvas(el) {
      if (!el) { this._drawCanvas = null; this._drawCtx = null; return; }
      // Canvas theo TỈ LỆ HIỂN THỊ (không vuông cứng) — khớp đúng tỉ lệ ảnh kể cả khi chưa decode.
      const m = this.canvasMetrics();
      const base = 1024;
      const ratio = m && m.vw && m.vh ? m.vw / m.vh : 1;
      let w = base, h = base;
      if (ratio >= 1) h = Math.max(1, Math.round(base / ratio));
      else w = Math.max(1, Math.round(base * ratio));
      el.width = w; el.height = h;
      this._drawCanvas = el;
      this._drawCtx = el.getContext('2d');
      this._drawCtx.clearRect(0, 0, w, h);
      this._drawHasStrokes = false; // canvas mới → chưa có nét
    },
    _drawRadius() { const base = Math.max(3, Math.min(150, Number(this.drawBrushSize) || 24)); return base * (0.4 + 0.6 * (Number(this._drawPressure) || 1)); },
    _hexToRgba(hex, a = 1) {
      const mm = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex || '');
      if (!mm) return 'rgba(255,255,255,' + a + ')';
      return 'rgba(' + parseInt(mm[1], 16) + ',' + parseInt(mm[2], 16) + ',' + parseInt(mm[3], 16) + ',' + a + ')';
    },
    _drawPoint(e) {
      const m = this.canvasMetrics();
      if (!m) return { nx: 0.5, ny: 0.5 };
      return { nx: this._clamp((e.clientX - m.crLeft - m.vx) / m.vw, 0, 1), ny: this._clamp((e.clientY - m.crTop - m.vy) / m.vh, 0, 1) };
    },
    _drawPaintDot(p) {
      const c = this._drawCtx; if (!c) return;
      const w = this._drawCanvas.width, h = this._drawCanvas.height, r = this._drawRadius();
      const op = Math.max(0.05, Math.min(1, Number(this.drawOpacity) || 1));
      const f = Math.max(0, Math.min(1, (Number(this.drawSoftness) || 0) / 60));
      const hard = Math.max(0.2, 0.9 - f * 0.7);
      const g = c.createRadialGradient(p.nx * w, p.ny * h, 0, p.nx * w, p.ny * h, r);
      g.addColorStop(0, this._hexToRgba(this.inpaintFillColor, op));
      g.addColorStop(hard, this._hexToRgba(this.inpaintFillColor, op * 0.85));
      g.addColorStop(1, this._hexToRgba(this.inpaintFillColor, 0));
      c.fillStyle = g;
      c.beginPath(); c.arc(p.nx * w, p.ny * h, r, 0, Math.PI * 2); c.fill();
    },
    _drawPaintLine(from, to) {
      const c = this._drawCtx; if (!c) return;
      const w = this._drawCanvas.width, r = this._drawRadius();
      const steps = Math.max(1, Math.ceil(Math.hypot(to.nx - from.nx, to.ny - from.ny) * w / (r / 2)));
      for (let s = 0; s <= steps; s++) this._drawPaintDot({ nx: from.nx + (to.nx - from.nx) * (s / steps), ny: from.ny + (to.ny - from.ny) * (s / steps) });
    },
    beginDrawBrush(e) { if (!this.drawMode) return; if (e.currentTarget && e.currentTarget.setPointerCapture && e.pointerId != null) { try { e.currentTarget.setPointerCapture(e.pointerId); } catch (err) {} } this._drawPressure = (e.pointerType === 'pen' && e.pressure != null && e.pressure > 0) ? e.pressure : 1; this._drawDrawing = true; this._drawLast = this._drawPoint(e); this._drawHasStrokes = true; this._drawPaintDot(this._drawLast); },
    drawBrushMove(e) { if (!this._drawDrawing) return; if (e.pointerType === 'pen' && e.pressure != null && e.pressure > 0) this._drawPressure = e.pressure; const p = this._drawPoint(e); this._drawPaintLine(this._drawLast || p, p); this._drawLast = p; },
    endDrawBrush() { this._drawDrawing = false; this._drawLast = null; },
    applyDraw() {
      if (this._drawBusy) return Promise.resolve();
      // Không có nét thật → không push history / không bake.
      if (!this._drawHasStrokes) return Promise.resolve();
      this.pushHistory();
      this._drawBusy = true;
      this._drawHasStrokes = false;
      return new Promise((resolve) => {
        const l = this.activeLayer;
        const dc = this._drawCanvas;
        if (!l || l.locked || !dc) {
          if (l && l.locked) this.toast('Layer đang khóa — mở khóa trước khi vẽ.', 'error');
          this._drawBusy = false;
          resolve();
          return;
        }
        const img = new Image();
        img.onload = () => {
          const w = img.naturalWidth, h = img.naturalHeight;
          const canvas = document.createElement('canvas'); canvas.width = w; canvas.height = h;
          const ctx = canvas.getContext('2d');
          ctx.drawImage(img, 0, 0);
          ctx.globalCompositeOperation = 'source-over';
          ctx.drawImage(dc, 0, 0, w, h);
          l.image = canvas.toDataURL('image/png');
          this.saveLayerLayout();
          this.toast('Đã vẽ lên layer.');
          this._drawBusy = false;
          resolve();
        };
        img.onerror = () => { this.toast('Không vẽ được.', 'error'); this._drawBusy = false; resolve(); };
        img.src = l.image;
      });
    },
    applyDrawNow() {
      this.applyDraw().then(() => {
        if (this._drawCtx && this._drawCanvas) this._drawCtx.clearRect(0, 0, this._drawCanvas.width, this._drawCanvas.height);
      });
    },
    finishDraw() { if (!this.drawMode) return; this.applyDraw().then(() => { this.drawMode = false; }); },
    cancelDraw() { if (!this.drawMode) return; this.drawMode = false; this.toast('Đã hủy vẽ.'); },
    exitErase() { if (this.eraseMode) { this.eraseMode = false; this.applyErase(); } },
    // ── Chuẩn hoá transform layer (xoay/lật) trước khi sửa pixel ──
    // Overlay vẽ/xóa/lasso tính theo khung hiển thị (chưa kể rotation/flip) nên trên layer bị XOAY
    // hoặc LẬT, nét cọ/đường lasso và vùng áp dụng LỆCH khỏi nội dung người dùng nhìn thấy (khi
    // phóng to lệch càng rõ). Khi bật công cụ sửa pixel trên layer như vậy ta "rasterize" transform:
    // nội dung được xoay/lật vào 1 ảnh mới (giữ NGUYÊN vị trí/kích thước hiển thị) rồi reset
    // rotation/flip → mọi overlay chuẩn hoá theo ảnh hoạt động chính xác. Ctrl+Z hoàn lại bản gốc.
    _needsFlatten(l) {
      if (!l || !l.image) return false;
      const rot = Math.abs((Number(l.rotation) || 0) % 360);
      return (rot > 0.5 && rot < 359.5) || !!l.flipX || !!l.flipY;
    },
    // Gọi trước khi bật erase/draw/region-select. Trả true khi sẵn sàng sửa (đã flatten nếu cần).
    async flattenActiveLayerTransform() {
      if (this._flattenBusy) return false;
      const l = this.activeLayer;
      if (!this._needsFlatten(l)) return true;
      try {
        this._flattenBusy = true;
        const img = await this._loadImageSrc(l.image);
        const nw = img.naturalWidth, nh = img.naturalHeight;
        if (!nw || !nh) return false;
        const rot = ((Number(l.rotation) || 0) * Math.PI) / 180;
        const cosR = Math.abs(Math.cos(rot)), sinR = Math.abs(Math.sin(rot));
        const W = Math.max(1, Math.ceil(nw * cosR + nh * sinR));
        const H = Math.max(1, Math.ceil(nw * sinR + nh * cosR));
        const canvas = document.createElement('canvas');
        canvas.width = W; canvas.height = H;
        const ctx = canvas.getContext('2d');
        ctx.translate(W / 2, H / 2);
        // Khớp CSS `transform: rotate(θ) scale(sx,sy)` (scale trước rồi rotate) → ctx.rotate rồi scale.
        ctx.rotate(rot);
        ctx.scale(l.flipX ? -1 : 1, l.flipY ? -1 : 1);
        ctx.drawImage(img, -nw / 2, -nh / 2);
        const MAX = 512;
        const k = Math.min(1, MAX / W, MAX / H);
        const newBaseW = W * k, newBaseH = H * k;
        // Kích thước hiển thị CŨ (không gian base, trước zoom) để giữ layer KHÔNG nhảy cỡ.
        const kOld = Math.min(1, MAX / nw, MAX / nh);
        const oldBaseW = Number(l.baseW) || (nw * kOld);
        const oldBaseH = Number(l.baseH) || (nh * kOld);
        const sOld = Math.max(0.05, Math.min(8, Number(l.scale) || 1));
        const visW = oldBaseW * cosR + oldBaseH * sinR; // bbox hiển thị (CSS px, chưa zoom)
        const visH = oldBaseW * sinR + oldBaseH * cosR;
        // newBase tỉ lệ đúng visW/visH (đồng dạng) nên 1 hệ số scale giữ đúng cả 2 chiều.
        let sNew = (visW * sOld) / Math.max(1, newBaseW);
        sNew = Math.max(0.05, Math.min(8, sNew));
        this.pushHistory(); // undo = khôi phục layer gốc (ảnh + transform)
        l.image = canvas.toDataURL('image/png');
        l.rotation = 0; l.flipX = false; l.flipY = false;
        l.baseW = newBaseW; l.baseH = newBaseH;
        l.scale = sNew;
        // Pixel đã khác generation gốc (nếu layer là kết quả AI) → trở thành ảnh cục bộ để
        // download/lưu/inpaint không nhầm với file gốc trên server.
        if (l.kind === 'gen' && l.genId) {
          l.kind = 'source'; l.genId = null;
          if (this.activeLayerId === l.id) { this.editSource = { url: l.image, name: l.name }; this.previewId = null; this.preview = null; }
        }
        this.saveLayerLayout();
        this.toast('Đã áp xoay/lật vào ảnh để chỉnh sửa đúng vị trí — Ctrl+Z nếu muốn hoàn lại.');
        return true;
      } catch (e) {
        this.toast('Không chuẩn hoá được ảnh xoay/lật.', 'error');
        return false;
      } finally { this._flattenBusy = false; }
    },
    // Kéo layer trên canvas để di chuyển (tách khỏi pan/zoom khung nhìn).
    beginLayerDrag(id, e) {
      const l = this.canvasLayers.find((x) => x.id === id);
      if (!l || l.locked) return;
      this.setActiveLayer(id);
      // Snapshot lúc BẮT ĐẦU kéo (giữ lại để push vào history khi kết thúc) — không push ngay
      // để tránh mỗi cú click (chưa kéo) làm bẩn lịch sử + lệch vị trí.
      this._layerDrag = { id, sx: e.clientX, sy: e.clientY, ox: Number(l.x) || 0, oy: Number(l.y) || 0, snap: this._snapshot() };
    },
    layerDragMove(e) {
      const d = this._layerDrag;
      if (!d) return;
      const l = this.canvasLayers.find((x) => x.id === d.id);
      if (!l) return;
      let nx = d.ox + (e.clientX - d.sx) / (this.zoom || 1);
      let ny = d.oy + (e.clientY - d.sy) / (this.zoom || 1);
      const SNAP = 20 / (this.zoom || 1); // ≈20px trên màn hình — nhạy hơn
      let sx = null, sy = null;
      // Bắt điểm vào tâm canvas (0,0) và vào các layer khác.
      if (Math.abs(nx) < SNAP) { nx = 0; sx = 0; }
      if (Math.abs(ny) < SNAP) { ny = 0; sy = 0; }
      this.canvasLayers.forEach((o) => {
        if (o.id === d.id || o.visible === false) return;
        const ox = o.x || 0, oy = o.y || 0;
        if (Math.abs(nx - ox) < SNAP) { nx = ox; sx = ox; }
        if (Math.abs(ny - oy) < SNAP) { ny = oy; sy = oy; }
      });
      l.x = nx;
      l.y = ny;
      this.snapX = sx;
      this.snapY = sy;
    },
    endLayerDrag() {
      if (!this._layerDrag) return;
      const snap = this._layerDrag.snap;
      this._layerDrag = null;
      this.snapX = null;
      this.snapY = null;
      this.saveLayerLayout();
      // Push snapshot pre-drag vào history (chỉ 1 lần, sau khi kéo xong).
      if (snap) { this.undoStack.push(snap); if (this.undoStack.length > 50) this.undoStack.shift(); this.redoStack = []; }
    },
    // Bỏ chọn layer active (nhấp khoảng trống trên canvas).
    deselectAll() {
      this.activeLayerId = '';
      this.editSource = null;
      this.previewId = null;
      this.preview = null;
      this.saveLayerLayout();
    },
    // Gộp tất cả layer đang hiển thị thành 1 ảnh PNG (data URL) theo đúng transform/opacity/blend.
    async compositeVisible() {
      const layers = this.canvasLayers.filter((l) => l.visible !== false && l.image);
      if (!layers.length) throw new Error('Chưa có layer để gộp.');
      const MAX = 512; // khớp với max-h/max-w hiển thị trên canvas → tỷ lệ gộp khớp với màn hình
      const loaded = await Promise.all(layers.map((l) => new Promise((resolve) => {
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = () => resolve({ layer: l, img });
        img.onerror = () => resolve({ layer: l, img: null });
        img.src = l.image;
      })));
      const valid = loaded.filter((x) => x.img && x.img.naturalWidth);
      if (!valid.length) throw new Error('Không tải được ảnh layer.');
      // Dùng đúng kích thước hiển thị thật của mỗi layer (natural bị giới hạn về MAX giống canvas).
      const items = valid.map(({ layer: l, img }) => {
        const nw = img.naturalWidth, nh = img.naturalHeight;
        const base = Math.min(1, MAX / nw, MAX / nh);
        return { l, img, w: nw * base, h: nh * base };
      });
      let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
      items.forEach(({ l, w, h }) => {
        const s = l.scale || 1;
        const rot = ((l.rotation || 0) * Math.PI) / 180;
        const cos = Math.cos(rot), sin = Math.sin(rot);
        const hw = (w * s) / 2, hh = (h * s) / 2;
        [[-hw, -hh], [hw, -hh], [hw, hh], [-hw, hh]].forEach(([cx, cy]) => {
          const px = cx * cos - cy * sin + (l.x || 0);
          const py = cx * sin + cy * cos + (l.y || 0);
          if (px < minX) minX = px; if (px > maxX) maxX = px;
          if (py < minY) minY = py; if (py > maxY) maxY = py;
        });
      });
      const pad = 8;
      const W = Math.max(1, Math.ceil(maxX - minX + pad * 2));
      const H = Math.max(1, Math.ceil(maxY - minY + pad * 2));
      const canvas = document.createElement('canvas');
      canvas.width = W; canvas.height = H;
      const ctx = canvas.getContext('2d');
      items.forEach(({ l, img, w, h }) => {
        ctx.save();
        ctx.translate(-minX + pad, -minY + pad);
        ctx.translate(l.x || 0, l.y || 0);
        ctx.rotate(((l.rotation || 0) * Math.PI) / 180);
        ctx.scale((l.scale || 1) * (l.flipX ? -1 : 1), (l.scale || 1) * (l.flipY ? -1 : 1));
        ctx.globalAlpha = l.opacity != null ? l.opacity : 1;
        ctx.globalCompositeOperation = (l.blend && l.blend !== 'normal') ? l.blend : 'source-over';
        ctx.drawImage(img, -w / 2, -h / 2, w, h);
        ctx.restore();
      });
      return canvas.toDataURL('image/png');
    },
    // Xuất ảnh gộp (tải xuống PNG).
    async exportComposite() {
      try {
        const url = await this.compositeVisible();
        const a = document.createElement('a');
        a.href = url;
        a.download = 'composite-' + Date.now() + '.png';
        document.body.appendChild(a);
        a.click();
        a.remove();
        this.toast('Đã xuất ảnh gộp.');
      } catch (e) { this.toast(e.message || 'Không gộp được.', 'error'); }
    },
    // Gộp layer thành 1 layer mới (upload lên server để lưu lâu dài).
    async flattenToLayer() {
      try {
        const url = await this.compositeVisible();
        const blob = await (await fetch(url)).blob();
        const fd = new FormData();
        fd.append('image', new File([blob], 'composite.png', { type: 'image/png' }));
        const res = await fetch('/studio/upload-ref', { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF(), Accept: 'application/json' }, body: fd });
        const d = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(d.message || 'Không gộp được.');
        const id = 'flat-' + Date.now();
        this.pushCanvasLayer(id, 'source', 'Gộp layer', d.url);
        this.setActiveLayer(id);
        this.toast('Đã gộp layer thành ảnh mới.');
      } catch (e) { this.toast(e.message || 'Không gộp được.', 'error'); }
    },
    // Tên hiển thị của một generation (dùng tên tuỳ chỉnh nếu có, ngược lại "Ảnh #id").
    genName(g) { return (g && g.meta && g.meta.name) ? g.meta.name : (g ? 'Ảnh #' + g.id : 'Ảnh'); },
    // Tải ảnh đang active trên canvas (kết quả dùng endpoint download; ảnh nguồn tải trực tiếp).
    async downloadActive() {
      const l = this.canvasLayers.find((x) => x.id === this.activeLayerId && x.visible !== false);
      if (!l || !l.image) { this.toast('Chưa có ảnh trên canvas.', 'error'); return; }
      if (l.kind === 'gen' && l.genId) {
        window.location.href = '/studio/generations/' + l.genId + '/download';
        return;
      }
      try {
        const res = await fetch(l.image);
        if (!res.ok) throw new Error();
        const blob = await res.blob();
        const ext = blob.type === 'image/png' ? 'png' : blob.type === 'image/webp' ? 'webp' : blob.type === 'image/gif' ? 'gif' : 'jpg';
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = ((l.name || 'anh').replace(/\.[^.]+$/, '') || 'anh') + '.' + ext;
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
      } catch (e) { this.toast('Không tải được ảnh nguồn.', 'error'); }
    },
    // Ẩn/hiện layer (eye toggle). Ẩn layer đang active thì chuyển sang layer hiển thị kế tiếp.
    toggleLayerVisible(id) {
      const l = this.canvasLayers.find((x) => x.id === id);
      if (!l) return;
      l.visible = !l.visible;
      if (!l.visible && this.activeLayerId === id) {
        const next = this.canvasLayers.find((x) => x.visible !== false && x.id !== id);
        if (next) this.setActiveLayer(next.id);
        else { this.activeLayerId = ''; this.editSource = null; this.previewId = null; this.preview = null; }
      }
      this.saveLayerLayout();
    },
    // Khóa/mở khóa layer (chống xóa/đổi tên/di chuyển nhầm).
    toggleLayerLock(id) {
      const l = this.canvasLayers.find((x) => x.id === id);
      if (!l) return;
      l.locked = !l.locked;
      this.saveLayerLayout();
    },
    // Lưu bố cục layer (danh sách + layer active) để khôi phục khi tải lại trang.
    saveLayerLayout() {
      try { localStorage.setItem('trillfa.layers', JSON.stringify({ layers: this.canvasLayers, activeLayerId: this.activeLayerId })); } catch (e) {}
    },
    // Khôi phục bố cục layer; bỏ layer 'gen' đã bị xóa khỏi output, giữ layer 'source' (URL vẫn hợp lệ).
    restoreLayerLayout() {
      try {
        const raw = localStorage.getItem('trillfa.layers');
        if (!raw) return;
        const d = JSON.parse(raw);
        const genIds = new Set((this.generations || []).map((g) => g.id));
        this.canvasLayers = (Array.isArray(d.layers) ? d.layers : [])
          .filter((l) => l && l.image)
          .filter((l) => l.kind !== 'gen' || (l.genId != null && genIds.has(Number(l.genId))))
          .map((l) => ({ id: l.id, kind: l.kind, name: l.name, image: l.image, genId: l.genId, visible: l.visible !== false, locked: !!l.locked, x: Number(l.x) || 0, y: Number(l.y) || 0, scale: (l.scale != null ? Number(l.scale) : 1) || 1, rotation: Number(l.rotation) || 0, opacity: l.opacity != null ? Number(l.opacity) : 1, blend: l.blend || 'normal', baseW: Number(l.baseW) || null, baseH: Number(l.baseH) || null, flipX: !!l.flipX, flipY: !!l.flipY }));
        const active = this.canvasLayers.find((l) => l.id === d.activeLayerId && l.visible !== false);
        if (active) this.setActiveLayer(active.id);
        else { this.activeLayerId = ''; this.saveLayerLayout(); }
      } catch (e) { this.canvasLayers = []; this.activeLayerId = ''; this.saveLayerLayout(); }
    },
    setBatch(ids) { this.lastBatch = (ids || []).filter(Boolean); this.showBatch = this.lastBatch.length > 1; },
    hideBatch() { this.showBatch = false; },
    setSource(url, name) {
      this.editSource = { url, name: name || 'Ảnh nguồn' };
      // Bỏ layer 'source' CŨ trước khi thêm ảnh mới — nếu không pushCanvasLayer bị chặn
      // do trùng id 'source' → ảnh mới không vào được canvas khi canvas vẫn còn ảnh cũ.
      this.canvasLayers = this.canvasLayers.filter((l) => l.id !== 'source');
      this.pushCanvasLayer('source', 'source', this.editSource.name, url);
      this.setActiveLayer('source');
      this.toast('Đã chọn ảnh nguồn.');
    },
    async uploadRef(file, select = true) {
      if (!file) { this.toast('Chọn file ảnh.', 'error'); return; }
      const fd = new FormData(); fd.append('image', file);
      const res = await fetch('/studio/upload-ref', { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF(), Accept: 'application/json' }, body: fd });
      const d = await res.json().catch(() => ({}));
      if (!res.ok) { this.toast(d.message || 'Lỗi tải ảnh.', 'error'); return; }
      if (select) this.setSource(d.url, file.name);
      return d;
    },
    pickFromProduct(p) { this.setSource(p.url, p.name); },
    pickFromResult(g) { this.setSource(g.media_url, 'Ảnh kết quả #' + g.id); },
    // Thêm NHIỀU ảnh vào canvas — KHÔNG xóa ảnh/layer cũ (mỗi ảnh 1 layer nguồn riêng).
    addImagesToCanvas(items) {
      const list = (items || []).filter((it) => it && it.url);
      if (!list.length) return;
      const ts = Date.now();
      let lastId = '';
      list.forEach((img, i) => {
        const id = 'src-' + ts + '-' + i;
        if (this.canvasLayers.some((l) => l.id === id)) return;
        this.pushCanvasLayer(id, 'source', img.name || 'Ảnh nguồn', img.url);
        lastId = id;
      });
      if (lastId) this.setActiveLayer(lastId);
      this.saveLayerLayout();
      this.toast('Đã thêm ' + list.length + ' ảnh vào canvas.');
    },
    async translate(promptTo) { if (!promptTo) { this.toast('Nhập prompt.', 'error'); return; } this.suggestResult = this.suggestResult || {}; try { const d = await this.api('/studio/translate', { text: promptTo, direction: 'vi' }); this.suggestResult.prompt_vi = d.text || d; this.toast('Đã dịch sang tiếng Việt.'); } catch (e) { this.toast(e.message || 'Lỗi dịch.', 'error'); } },
    async suggestStyle(image) {
      if (!this.suggestEnabled) { this.toast('Tính năng "Gợi ý từ ảnh" đang bị tắt trong cài đặt.', 'error'); return; }
      if (!image) { this.toast('Chọn ảnh nguồn để gợi ý.', 'error'); return; }
      this.suggesting = true;
      try { const d = await this.api('/studio/suggest', { reference_url: image, creative_level: this.creativeLevel }); this.suggestResult = d; const styles = (d.styles || []).join(', '); this.toast(styles ? 'Phong cách: ' + styles + (d.background ? ' · ' + d.background : '') : 'Đã gợi ý.'); }
      catch(e){ this.toast(e.message || 'Lỗi gợi ý.', 'error'); }
      finally { this.suggesting = false; }
    },
    statusLabel(s) { return { pending: 'Đang chờ', processing: 'Đang xử lý', completed: 'Hoàn tất', failed: 'Lỗi', cancelled: 'Đã hủy' }[s] || s || ''; },
    // Lazy worker poll: theo dõi một generation qua /studio/generations/{id} (backend tự xử lý
    // job đang pending và tự "heal" job kẹt). Single-flight: không bao giờ gửi 2 request song song.
    pollGeneration(id) {
      if (this._pollTimers[id]) return;
      const tick = async () => {
        try {
          const res = await fetch('/studio/generations/' + id, { headers: { Accept: 'application/json' } });
          if (!res.ok) { delete this._pollTimers[id]; return; }
          const g = await res.json();
          const item = this.generations.find(x => x.id === Number(g.id));
          if (item) { item.status = g.status; item.media_url = g.media_url; item.error = g.error; item.model = g.model; item.provider = g.provider; item.elapsed_ms = g.elapsed_ms; }
          if (['completed', 'failed', 'cancelled'].includes(g.status)) {
            delete this._pollTimers[id];
            const isInpaint = String(id) === String(this.inpaintGenId);
            const isCompose = this.composeGenIds.length && this.composeGenIds.includes(Number(id));
            if (g.status === 'completed' && g.media_url) {
              if (isInpaint) { this.inpaintStage = 'done'; this.toast('✅ Đã sửa xong ảnh.'); }
              this.select({ id: g.id, media_url: g.media_url, type: 'image', status: 'completed' });
            } else if (g.status === 'failed') {
              if (isInpaint) { this.inpaintStage = 'error'; this.inpaintError = g.error || 'Sửa ảnh thất bại.'; this.toast(this.inpaintError, 'error'); }
            } else {
              if (isInpaint) { this.inpaintStage = 'cancelled'; this.toast('Đã hủy sửa ảnh.'); }
            }
            if (isCompose) this._checkComposeDone();
            return;
          }
        } catch (e) { delete this._pollTimers[id]; return; }
        this._pollTimers[id] = setTimeout(tick, 500);
      };
      this._pollTimers[id] = setTimeout(tick, 2000);
    },
    async inpaint(prompt) {
      const src = this.preview && this.preview.media_url;
      if (!this.previewId || !src || this.inpainting) { this.toast('Chọn ảnh kết quả để sửa.', 'error'); return; }
      if (!(prompt || '').trim()) { this.toast('Nhập mô tả chỉnh sửa.', 'error'); return; }
      this.inpainting = true;
      this.inpaintError = '';
      this.inpaintStage = 'send';
      this.inpaintStartTs = Date.now();
      try {
        const body = { prompt, preserve_background: this.inpaintPreserveBg, preserve_face: this.inpaintPreserveFace, source_url: this.upscaleSrc || src, feather: Number(this.inpaintFeather) || 0 };
        // Gửi mask đã LƯU (bấm "Xong") — dù overlay công cụ đã tắt vẫn xử lý đúng vùng.
        if (this.inpaintMaskDone && this._inpaintMaskKind) {
          body.mask_mode = this._inpaintMaskKind;
          body.region = this.inpaintMaskBox;
          if (this._inpaintMaskKind === 'brush' && this.inpaintBrushData) {
            body.mask_data = this.inpaintBrushData;
          }
        }
        const d = await this.api('/studio/generations/' + this.previewId + '/inpaint', body);
        if (!d.generation_id) { throw new Error(d.message || 'Không tạo được yêu cầu sửa ảnh.'); }
        this.inpaintGenId = d.generation_id;
        this.inpaintStage = 'processing';
        this.addGen({ id: d.generation_id, type: 'image', status: d.status || 'pending', model: d.model || 'inpaint', provider: d.provider || 'qwen', media_url: d.media_url, error: d.error, credits_cost: d.credits_cost ?? 1, created_at: 'Vừa gửi' });
        if (d.credits_left != null) this.creditsLeft = d.credits_left;
        this.pollGeneration(d.generation_id);
      } catch (e) {
        this.inpaintError = e.message || 'Lỗi sửa ảnh.';
        this.inpaintStage = 'error';
        this.toast(this.inpaintError, 'error');
      } finally { this.inpainting = false; }
    },
    async cancelInpaint() {
      if (!this.inpaintGenId || !this.inpaintStage) return;
      try { await this.api('/studio/generations/' + this.inpaintGenId + '/cancel', {}); this.inpaintStage = 'cancelled'; this.toast('Đã hủy sửa ảnh.'); }
      catch (e) { this.toast(e.message || 'Lỗi hủy.', 'error'); }
    },
    clearInpaintStatus() { this._inpaintStopDrag && this._inpaintStopDrag(); this.inpaintStage = ''; this.inpaintError = ''; this.inpaintGenId = null; this.inpaintStartTs = 0; this.inpaintMaskMode = 'none'; this.inpaintMaskDone = false; this._inpaintMaskKind = ''; this.inpaintBrushData = ''; this.inpaintErase = false; this._inpaintMaskCanvas = null; this._inpaintMaskCtx = null; this.inpaintMaskBox = { x: 0.425, y: 0.425, w: 0.15, h: 0.15 }; },
    // ── Inpaint Mask: chọn vùng trên ảnh preview (integrated into InpaintCard) ──
    // "Xong": áp dụng vùng chọn/vùng vẽ, thoát công cụ, LƯU mask vào store để Sửa ảnh dùng.
    confirmInpaintMask() {
      if (this.inpaintMaskMode === 'brush') {
        if (this._inpaintDrag) this._inpaintStopDrag(); // đang kéo dở → finalize trước
        if (!this.inpaintBrushData) { this.toast('Chưa vẽ mask — vẽ vùng cần sửa trước.', 'error'); return; }
        this._inpaintMaskKind = 'brush';
      } else if (this.inpaintMaskMode === 'rect') {
        // Rect đã ĐẢO (có mask_data) → gửi như brush mask, không dùng box nữa.
        this._inpaintMaskKind = this.inpaintBrushData ? 'brush' : 'rect';
      } else if (this.inpaintMaskMode === 'freehand') {
        if (this._inpaintFreehandActive) { this.freehandStop(); }
        if (!this.inpaintBrushData) { this.toast('Chưa vẽ vùng chọn — vẽ tự do để khoanh vùng.', 'error'); return; }
        this._inpaintMaskKind = 'brush'; // freehand tạo mask_data như brush
      } else if (this.inpaintMaskMode === 'path') {
        if (!this.inpaintBrushData) { this.toast('Chưa đóng vùng chọn — thêm điểm rồi bấm "Đóng".', 'error'); return; }
        this._inpaintMaskKind = 'brush'; // path tạo mask_data như brush
      } else if (this.inpaintMaskMode === 'magic') {
        if (!this.inpaintBrushData) { this.toast('Chưa chọn vùng — bấm vào ảnh để chọn theo màu.', 'error'); return; }
        this._inpaintMaskKind = 'brush'; // magic tạo mask_data như brush
      } else {
        return;
      }
      this._inpaintStopDrag && this._inpaintStopDrag();
      this.inpaintMaskMode = 'none'; // tắt overlay
      this.inpaintErase = false;
      this.inpaintFreehandPoints = [];
      this.inpaintFreehandPaths = [];
      this.inpaintPathPoints = [];
      this.inpaintPathRegions = [];
      if (this.inpaintMaskSource === 'canvas') {
        // Vùng chọn trên canvas: chỉ thoát + xoá dữ liệu, không giữ làm mask inpaint.
        this.inpaintMaskDone = false;
        this._inpaintMaskKind = '';
        this.inpaintBrushData = '';
        this.inpaintMaskSource = 'inpaint';
        this.toast('Đã thoát vùng chọn.');
      } else {
        this.inpaintMaskDone = true;
        this.toast('Đã lưu vùng — bấm "Sửa ảnh" để xử lý.');
      }
    },
    // "Bỏ mask": xoá hoàn toàn vùng chọn/vẽ (rect + brush).
    clearInpaintMask() {
      this._inpaintStopDrag && this._inpaintStopDrag();
      this.inpaintMaskMode = 'none';
      this.inpaintMaskDone = false;
      this._inpaintMaskKind = '';
      this.inpaintBrushData = '';
      this.inpaintErase = false;
      this.inpaintMaskSource = 'inpaint';
      this.inpaintFreehandPoints = [];
      this.inpaintFreehandPaths = [];
      this.inpaintPathPoints = [];
      this.inpaintPathRegions = [];
      this.inpaintMaskBox = { x: 0.425, y: 0.425, w: 0.15, h: 0.15 };
    },
    async toggleInpaintMask(mode) {
      if (this.inpaintMaskMode === mode) { this.clearInpaintMask(); return; }
      if (this.inpaintStage === 'send' || this.inpaintStage === 'processing') { this.toast('Đang xử lý — chờ xong rồi chọn vùng.', 'error'); return; }
      // Vẽ mask trên layer bị xoay/lật → vùng chọn lệch khỏi nội dung hiển thị; chuẩn hoá trước.
      if (!(await this.flattenActiveLayerTransform())) return;
      if (this._inpaintDrag) this._inpaintStopDrag();
      this.inpaintMaskSource = 'inpaint';
      this.inpaintMaskMode = mode;
      this.inpaintMaskDone = false;
      this._inpaintMaskKind = '';
      this.inpaintBrushData = '';
      this.inpaintMaskBox = { x: 0.425, y: 0.425, w: 0.15, h: 0.15 }; // khung mặc định 15% khi bật — nhỏ, dễ kéo/chỉnh
      if (mode === 'brush') { this._initInpaintBrush(); this.inpaintErase = false; }
      if (mode === 'freehand') { this.inpaintFreehandPoints = []; this.inpaintFreehandPaths = []; this._initInpaintBrush(); }
      if (mode === 'path') { this.inpaintPathPoints = []; this.inpaintPathRegions = []; this._initInpaintBrush(); }
      if (mode === 'magic') { this._initInpaintBrush(); }
    },
    // Mở vùng chọn từ THANH CÔNG CỤ CANVAS (rect/freehand) — dùng chung overlay chính xác của Inpaint,
    // nhưng hành động là Xóa / Tô màu / Feather tại chỗ (không phải mask AI inpaint).
    async startCanvasSelect(mode) {
      if (this.inpaintStage === 'send' || this.inpaintStage === 'processing') { this.toast('Đang xử lý — chờ xong rồi chọn vùng.', 'error'); return; }
      if (this.inpaintMaskMode === mode) { this.clearInpaintMask(); return; }
      // Lasso/rect/vùng chọn cũng thao tác theo pixel layer → cần layer thẳng hàng (không xoay/lật).
      if (!(await this.flattenActiveLayerTransform())) return;
      this.inpaintMaskSource = 'canvas';
      if (this._inpaintDrag) this._inpaintStopDrag();
      this.inpaintMaskMode = mode;
      this.inpaintMaskDone = false;
      this._inpaintMaskKind = '';
      this.inpaintBrushData = '';
      this.inpaintMaskBox = { x: 0.425, y: 0.425, w: 0.15, h: 0.15 };
      if (mode === 'freehand') { this.inpaintFreehandPoints = []; this.inpaintFreehandPaths = []; this._initInpaintBrush(); }
      if (mode === 'path') { this.inpaintPathPoints = []; this.inpaintPathRegions = []; this._initInpaintBrush(); }
      if (mode === 'magic') { this._initInpaintBrush(); }
    },
    // ── Freehand (lasso) select: vẽ tự do tạo vùng kín → mask ──
    freehandStart(e) {
      if (this.inpaintMaskMode !== 'freehand') return;
      e.stopPropagation();
      const p = this.inpaintMaskPointer(e); if (!p) return;
      this._inpaintFreehandActive = true;
      this.inpaintFreehandPoints = [p];
      // Chế độ 'new' → xoá nét cũ; 'add'/'subtract' → giữ mask + paths để cộng/trừ vào vùng hiện có.
      if (this.inpaintSelectMode === 'new') {
        if (this._inpaintMaskCtx && this._inpaintMaskCanvas) {
          this._inpaintMaskCtx.clearRect(0, 0, this._inpaintMaskCanvas.width, this._inpaintMaskCanvas.height);
        }
        this.inpaintFreehandPaths = [];
      }
    },
    setInpaintSelectMode(mode) { this.inpaintSelectMode = (this.inpaintSelectMode === mode) ? 'new' : mode; },
    freehandMove(e) {
      if (!this._inpaintFreehandActive) return;
      const p = this.inpaintMaskPointer(e); if (!p) return;
      const pts = this.inpaintFreehandPoints;
      const last = pts[pts.length - 1];
      if (last && Math.hypot(p.nx - last.nx, p.ny - last.ny) < 0.002) return;
      pts.push(p);
    },
    freehandStop() {
      if (!this._inpaintFreehandActive) return;
      this._inpaintFreehandActive = false;
      const pts = this.inpaintFreehandPoints;
      if (pts.length < 3) { this.inpaintFreehandPoints = []; return; }
      if (!this._inpaintMaskCtx) { this._initInpaintBrush(); }
      const c = this._inpaintMaskCanvas, ctx = this._inpaintMaskCtx;
      if (!c || !ctx) { return; }
      const w = c.width, h = c.height;
      ctx.globalCompositeOperation = this.inpaintSelectMode === 'subtract' ? 'destination-out' : 'source-over';
      ctx.beginPath();
      pts.forEach((pt, i) => { const x = pt.nx * w, y = pt.ny * h; if (i === 0) { ctx.moveTo(x, y); } else { ctx.lineTo(x, y); } });
      ctx.closePath();
      ctx.fillStyle = 'rgba(220,38,38,0.6)';
      ctx.fill();
      ctx.globalCompositeOperation = 'source-over';
      this._finalizeInpaintBrush();
      // Lưu nét đã hoàn thành vào paths để preview hiển thị ĐỦ các lần cộng/trừ.
      this.inpaintFreehandPaths.push(pts.map((p) => ({ nx: p.nx, ny: p.ny })));
      this.inpaintFreehandPoints = [];
      // Sau vòng lasso đầu tiên chuyển sang 'add' (giống path/magic) — vẽ tiếp sẽ CỘNG DỒN
      // vùng thay vì vô tình XOÁ vùng vừa khoanh (bấm nút ➕ nếu muốn chủ động thêm).
      if (this.inpaintSelectMode === 'new') this.inpaintSelectMode = 'add';
    },
    // ── Path (curve) select: click thêm điểm neo → đường cong mượt → đóng để tạo vùng chọn ──
    pathAddPoint(e) {
      if (this.inpaintMaskMode !== 'path') return;
      e.stopPropagation();
      const p = this.inpaintMaskPointer(e); if (!p) return;
      this.inpaintPathPoints.push(p);
    },
    pathUndoPoint() {
      if (this.inpaintMaskMode !== 'path') return;
      this.inpaintPathPoints.pop();
    },
    pathClose() {
      if (this.inpaintMaskMode !== 'path') return;
      const pts = this.inpaintPathPoints;
      if (pts.length < 3) { this.toast('Cần ít nhất 3 điểm để tạo vùng chọn.', 'error'); return; }
      if (!this._inpaintMaskCtx) this._initInpaintBrush();
      const c = this._inpaintMaskCanvas, ctx = this._inpaintMaskCtx;
      if (!c || !ctx) return;
      const w = c.width, h = c.height;
      // 'new' → chọn mới (xoá mask + regions cũ); 'add'/'subtract' → cộng/trừ vào vùng hiện có.
      if (this.inpaintSelectMode === 'new') {
        ctx.clearRect(0, 0, w, h);
        this.inpaintPathRegions = [];
      }
      const P = pts.map((p) => ({ x: p.nx * w, y: p.ny * h }));
      const n = P.length;
      ctx.globalCompositeOperation = this.inpaintSelectMode === 'subtract' ? 'destination-out' : 'source-over';
      ctx.beginPath();
      ctx.moveTo(P[0].x, P[0].y);
      for (let i = 0; i < n; i++) {
        const p0 = P[(i - 1 + n) % n], p1 = P[i], p2 = P[(i + 1) % n], p3 = P[(i + 2) % n];
        ctx.bezierCurveTo(p1.x + (p2.x - p0.x) / 6, p1.y + (p2.y - p0.y) / 6, p2.x - (p3.x - p1.x) / 6, p2.y - (p3.y - p1.y) / 6, p2.x, p2.y);
      }
      ctx.closePath();
      ctx.fillStyle = 'rgba(220,38,38,0.6)';
      ctx.fill();
      ctx.globalCompositeOperation = 'source-over';
      this._finalizeInpaintBrush();
      // Lưu vùng đã đóng để preview hiển thị ĐỦ nhiều vùng; xoá điểm để vẽ path tiếp theo.
      this.inpaintPathRegions.push(pts.map((p) => ({ nx: p.nx, ny: p.ny })));
      this.inpaintPathPoints = [];
      // Sau lần đóng đầu tiên, chuyển sang 'add' → các path sau tự cộng dồn (dễ vẽ đa vùng).
      if (this.inpaintSelectMode === 'new') this.inpaintSelectMode = 'add';
      this.toast('Đã tạo vùng chọn — vẽ tiếp hoặc bấm Xóa/Tô/Nhân đôi/Xong.');
    },
    // ── Magic Wand: click chọn vùng theo màu tương tự (flood-fill theo ngưỡng) ──
    async magicWand(e) {
      if (this.inpaintMaskMode !== 'magic') return;
      e.stopPropagation();
      const p = this.inpaintMaskPointer(e); if (!p) return;
      const l = this.activeLayer;
      if (!l || !l.image) { this.toast('Chọn 1 layer ảnh để dùng Magic Wand.', 'error'); return; }
      const tol = Math.max(1, Math.min(128, Number(this.magicTolerance) || 32));
      try {
        const img = await this._loadImageSrc(l.image);
        const w = img.naturalWidth, h = img.naturalHeight;
        // Độ chính xác: flood-fill ở 1024 (gấp đôi 512) rồi thu nhỏ về mask canvas
        // → mép vùng chọn được anti-alias (sub-pixel), bớt răng cưa khi phóng to ảnh.
        const base = 1024, ia = w / h;
        const mw = ia >= 1 ? base : Math.max(1, Math.round(base * ia));
        const mh = ia >= 1 ? Math.max(1, Math.round(base / ia)) : base;
        const c = document.createElement('canvas'); c.width = mw; c.height = mh;
        const ctx = c.getContext('2d');
        ctx.drawImage(img, 0, 0, mw, mh);
        const id = ctx.getImageData(0, 0, mw, mh);
        const d = id.data;
        const sx = Math.min(mw - 1, Math.max(0, Math.round(p.nx * mw)));
        const sy = Math.min(mh - 1, Math.max(0, Math.round(p.ny * mh)));
        const si = (sy * mw + sx) * 4;
        const sr = d[si], sg = d[si + 1], sb = d[si + 2];
        // Flood-fill (stack/DFS) theo ngưỡng màu — EUCLIDEAN distance (chính xác hơn Manhattan).
        const visited = new Uint8Array(mw * mh);
        const stack = [sy * mw + sx];
        visited[sy * mw + sx] = 1;
        const threshSq = tol * tol;
        while (stack.length) {
          const idx = stack.pop();
          const x = idx % mw, y = (idx / mw) | 0;
          const neighbors = y > 0 ? [idx - mw] : [];
          if (y < mh - 1) neighbors.push(idx + mw);
          if (x > 0) neighbors.push(idx - 1);
          if (x < mw - 1) neighbors.push(idx + 1);
          for (const ni of neighbors) {
            if (visited[ni]) continue;
            const pi = ni * 4;
            const dr = d[pi] - sr, dg = d[pi + 1] - sg, db = d[pi + 2] - sb;
            if (dr * dr + dg * dg + db * db <= threshSq) { visited[ni] = 1; stack.push(ni); }
          }
        }
        // Tạo temp canvas chứa vùng chọn (đỏ), rồi vẽ lên mask canvas theo add/subtract.
        if (!this._inpaintMaskCtx) this._initInpaintBrush();
        const mc = this._inpaintMaskCanvas, mctx = this._inpaintMaskCtx;
        if (!mc || !mctx) return;
        if (this.inpaintSelectMode === 'new') { mctx.clearRect(0, 0, mc.width, mc.height); this.inpaintPathRegions = []; }
        const temp = document.createElement('canvas'); temp.width = mw; temp.height = mh;
        const tctx = temp.getContext('2d');
        const td = tctx.createImageData(mw, mh);
        for (let i = 0; i < mw * mh; i++) {
          if (visited[i]) { td.data[i * 4] = 220; td.data[i * 4 + 1] = 38; td.data[i * 4 + 2] = 38; td.data[i * 4 + 3] = 255; }
        }
        tctx.putImageData(td, 0, 0);
        // Độ mịn: blur mép vùng chọn → mask có viền mềm, chống răng cưa.
        // Nhân 2 vì xử lý ở 1024 (gấp đôi mask 512) → giá trị slider giữ nguyên cảm giác cũ.
        let selCanvas = temp;
        const feather = Math.max(0, Math.min(20, Number(this.magicFeather) || 0)) * 2;
        if (feather > 0) {
          const blurred = document.createElement('canvas'); blurred.width = mw; blurred.height = mh;
          const bctx = blurred.getContext('2d');
          bctx.filter = 'blur(' + feather + 'px)';
          bctx.drawImage(temp, 0, 0);
          selCanvas = blurred;
        }
        mctx.globalCompositeOperation = this.inpaintSelectMode === 'subtract' ? 'destination-out' : 'source-over';
        // Thu 1024 → kích thước mask canvas (512) — drawImage resize cho anti-alias mép.
        mctx.drawImage(selCanvas, 0, 0, mc.width, mc.height);
        mctx.globalCompositeOperation = 'source-over';
        this._finalizeInpaintBrush();
        if (this.inpaintSelectMode === 'new') this.inpaintSelectMode = 'add';
        this.toast('Đã chọn vùng theo màu.');
      } catch (err) { this.toast('Không chọn được vùng.', 'error'); }
    },
    // ── Đảo ngược vùng chọn (invert selection) ──
    async invertSelection() {
      const mode = this.inpaintMaskMode;
      if (mode === 'none') { this.toast('Chưa có vùng chọn để đảo.', 'error'); return; }
      if (mode === 'rect' && !this.inpaintBrushData) {
        const b = this.inpaintMaskBox;
        if (!b || b.w < 0.02 || b.h < 0.02) { this.toast('Chưa có vùng chọn.', 'error'); return; }
        if (!this._inpaintMaskCtx) this._initInpaintBrush();
        const c = this._inpaintMaskCanvas, ctx = this._inpaintMaskCtx;
        if (!c || !ctx) return;
        const w = c.width, h = c.height;
        ctx.clearRect(0, 0, w, h);
        ctx.fillStyle = 'rgba(220,38,38,0.6)';
        ctx.fillRect(b.x * w, b.y * h, b.w * w, b.h * h);
        this._finalizeInpaintBrush();
        this._inpaintMaskKind = 'brush'; // rect → mask để đảo
      }
      if (!this.inpaintBrushData) { this.toast('Chưa có vùng chọn.', 'error'); return; }
      try {
        const mimg = await this._loadImageSrc('data:image/png;base64,' + this.inpaintBrushData);
        const w = mimg.naturalWidth, h = mimg.naturalHeight;
        const canvas = document.createElement('canvas'); canvas.width = w; canvas.height = h;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(mimg, 0, 0);
        const id = ctx.getImageData(0, 0, w, h);
        const d = id.data;
        for (let i = 0; i < d.length; i += 4) {
          const v = 255 - d[i]; // đảo đen↔trắng
          d[i] = v; d[i + 1] = v; d[i + 2] = v; d[i + 3] = 255;
        }
        ctx.putImageData(id, 0, 0);
        this.inpaintBrushData = canvas.toDataURL('image/png').replace(/^data:image\/png;base64,/, '');
        // Đồng bộ overlay mask canvas (đỏ) theo mask đã đảo → add/subtract/vẽ tiếp vẫn đúng.
        const mc = this._inpaintMaskCanvas, mctx = this._inpaintMaskCtx;
        if (mc && mctx && mc.width === w && mc.height === h) {
          mctx.globalCompositeOperation = 'source-over';
          mctx.clearRect(0, 0, w, h);
          const rd = mctx.createImageData(w, h);
          for (let i = 0; i < w * h; i++) {
            const v = d[i * 4]; // 0=vùng chọn, 255=ngoài
            rd.data[i * 4] = 220; rd.data[i * 4 + 1] = 38; rd.data[i * 4 + 2] = 38;
            rd.data[i * 4 + 3] = Math.round((255 - v) * (153 / 255)); // đỏ theo độ chọn
          }
          mctx.putImageData(rd, 0, 0);
        }
        this.toast('Đã đảo ngược vùng chọn.');
      } catch (err) { this.toast('Không đảo được vùng chọn.', 'error'); }
    },
    inpaintMaskPointer(e) {
      const m = this.canvasMetrics();
      if (!m) return null;
      // Convert clientX/Y to normalized coords (0..1) on the visible image, accounting for zoom/pan
      const nx = this._clamp((e.clientX - m.crLeft - m.vx) / m.vw, 0, 1);
      const ny = this._clamp((e.clientY - m.crTop - m.vy) / m.vh, 0, 1);
      return { nx, ny };
    },
    // Bắt đầu kéo 1 thao tác với KEY TƯỜNG MINH — gọi từ .stop trên chính box/handle
    // (giống hệt crop: mỗi vùng biết mình là gì, KHÔNG hit-test, không bao giờ nhầm
    // 'resize' ↔ 'move', và không vô tình tạo vùng mới khi bấm lệch ra ngoài).
    beginInpaintDrag(key, e) {
      if (this.inpaintMaskMode === 'none') return;
      e.stopPropagation();
      const p = this.inpaintMaskPointer(e); if (!p) return;
      // Drag cũ còn dở → đóng sạch trước.
      if (this._inpaintDrag) this._inpaintStopDrag();
      const handlers = { move: (ev) => this._inpaintQueue(ev), up: () => this._inpaintStopDrag() };
      if (key === 'brush') {
        this._inpaintBrushDrawing = true;
        this._inpaintDrag = { key: 'brush', sx: e.clientX, sy: e.clientY, last: p, handlers };
        // Snapshot để Ctrl+Z hoàn tác từng nét (giới hạn 30 bước).
        if (this._inpaintMaskCanvas && this._inpaintMaskCtx) {
          const snap = this._inpaintMaskCtx.getImageData(0, 0, this._inpaintMaskCanvas.width, this._inpaintMaskCanvas.height);
          this._inpaintUndoStack.push(snap);
          if (this._inpaintUndoStack.length > 30) this._inpaintUndoStack.shift();
        }
        this._drawInpaintBrushDot(p);
      } else if (key === 'draw') {
        // Kéo tạo vùng mới từ điểm nhấn. Lưu box cũ để khôi phục nếu chỉ click nhầm.
        const prev = this.inpaintMaskBox;
        this._inpaintPrevBox = (prev && prev.w >= 0.02 && prev.h >= 0.02) ? { ...prev } : null;
        this._inpaintDrew = false;
        this._inpaintDrag = { key: 'draw', sx: e.clientX, sy: e.clientY, box: { x: p.nx, y: p.ny, w: 0, h: 0 }, handlers };
        this.inpaintMaskBox = { x: p.nx, y: p.ny, w: 0, h: 0 };
      } else {
        // 'move' hoặc 1 trong 'nw'/'ne'/'sw'/'se' — bám vào box hiện tại
        const b = { ...(this.inpaintMaskBox || { x: 0, y: 0, w: 0, h: 0 }) };
        this._inpaintDrag = { key, sx: e.clientX, sy: e.clientY, box: b, handlers };
      }
      window.addEventListener('pointermove', handlers.move);
      window.addEventListener('pointerup', handlers.up);
      window.addEventListener('pointercancel', handlers.up);
    },
    // Container overlay: bấm ngoài box → bắt đầu vẽ vùng mới. Box cũ được lưu lại
    // (_inpaintPrevBox); nếu chỉ click nhầm (không kéo) thì khôi phục — không mất vùng.
    inpaintMaskStart(e) {
      if (this.inpaintMaskMode === 'none') return;
      this.beginInpaintDrag(this.inpaintMaskMode === 'brush' ? 'brush' : 'draw', e);
    },
    // Reset về chế độ vẽ vùng mới (gọi từ nút "🔄 Vẽ lại" hoặc double-click trên box)
    resetInpaintMaskBox() {
      if (this._inpaintDrag) this._inpaintStopDrag();
      this.inpaintMaskBox = { x: 0, y: 0, w: 0, h: 0 };
      this._inpaintPrevBox = null;
      this._inpaintDrew = false;
      this.toast?.('Kéo chọn vùng mới trên ảnh.');
    },

    // Batch pointermoves qua rAF — kéo không giật, không đọc layout mỗi event.
    _inpaintQueue(e) {
      if (!this._inpaintDrag) return;
      this._inpaintPending = e;
      if (this._inpaintRaf) return;
      const flush = () => {
        this._inpaintRaf = null;
        const ev = this._inpaintPending; this._inpaintPending = null;
        if (ev && this._inpaintDrag) this._inpaintDragMove(ev);
      };
      if (typeof requestAnimationFrame === 'function') this._inpaintRaf = requestAnimationFrame(flush);
      else flush();
    },
    _inpaintDragMove(e) {
      const d = this._inpaintDrag; if (!d) return;
      const m = this.canvasMetrics(); if (!m) return;
      // Đánh dấu đã kéo thật (ngưỡng 4px) — phân biệt với click nhầm
      if (d.key === 'draw' && (Math.abs(e.clientX - d.sx) > 4 || Math.abs(e.clientY - d.sy) > 4)) {
        this._inpaintDrew = true;
      }
      if (d.key === 'brush') {
        const p = this.inpaintMaskPointer(e); if (!p) return;
        this._drawInpaintBrushLine(d.last || p, p);
        d.last = p;
        return;
      }
      const bx = (e.clientX - d.sx) / m.vw, by = (e.clientY - d.sy) / m.vh;
      const b = { ...(d.box || { x: 0.425, y: 0.425, w: 0.15, h: 0.15 }) };
      const MIN = 0.02;
      const cl = (v, lo, hi) => this._clamp(v, lo, Math.max(lo, hi));
      if (d.key === 'move') {
        b.x = this._clamp(b.x + bx, 0, 1 - b.w);
        b.y = this._clamp(b.y + by, 0, 1 - b.h);
      } else if (d.key === 'draw') {
        const ox = d.box.x, oy = d.box.y;
        const cx = this._clamp(ox + bx, 0, 1), cy = this._clamp(oy + by, 0, 1);
        b.x = Math.min(ox, cx); b.y = Math.min(oy, cy);
        b.w = Math.max(MIN, Math.abs(cx - ox));
        b.h = Math.max(MIN, Math.abs(cy - oy));
      } else {
        // Kéo handle: góc đối diện neo cố định
        const { x: x0, y: y0, w: w0, h: h0 } = d.box;
        const right = x0 + w0, bottom = y0 + h0;
        let x = x0, y = y0, w = w0, h = h0;
        if (d.key === 'se') { w = cl(w0 + bx, MIN, 1 - x0); h = cl(h0 + by, MIN, 1 - y0); }
        else if (d.key === 'sw') { w = cl(w0 - bx, MIN, right); x = right - w; h = cl(h0 + by, MIN, 1 - y0); }
        else if (d.key === 'ne') { w = cl(w0 + bx, MIN, 1 - x0); h = cl(h0 - by, MIN, bottom); y = bottom - h; }
        else if (d.key === 'nw') { w = cl(w0 - bx, MIN, right); x = right - w; h = cl(h0 - by, MIN, bottom); y = bottom - h; }
        b.x = x; b.y = y; b.w = w; b.h = h;
      }
      this.inpaintMaskBox = b;
    },
    // Kết thúc drag: gỡ window listeners, finalize brush, reset nếu vùng quá nhỏ.
    _inpaintStopDrag() {
      const d = this._inpaintDrag;
      this._inpaintDrag = null;
      this._inpaintHandle = null;
      this._inpaintPending = null;
      if (this._inpaintRaf != null) {
        if (typeof cancelAnimationFrame === 'function') cancelAnimationFrame(this._inpaintRaf);
        this._inpaintRaf = null;
      }
      if (d && d.handlers) {
        window.removeEventListener('pointermove', d.handlers.move);
        window.removeEventListener('pointerup', d.handlers.up);
        window.removeEventListener('pointercancel', d.handlers.up);
      }
      if (this._inpaintBrushDrawing) {
        this._inpaintBrushDrawing = false;
        this._inpaintBrushLast = null;
        this._finalizeInpaintBrush();
      } else if (this.inpaintMaskMode === 'rect') {
        const wasDraw = d && d.key === 'draw';
        const b = this.inpaintMaskBox;
        if (wasDraw && !this._inpaintDrew && this._inpaintPrevBox) {
          // Bấm ngoài box nhưng KHÔNG kéo (click nhầm) → khôi phục vùng cũ
          this.inpaintMaskBox = this._inpaintPrevBox;
        } else if (!b || b.w < 0.02 || b.h < 0.02) {
          // Vùng quá nhỏ và không có gì để khôi phục → reset trống
          this.inpaintMaskBox = { x: 0, y: 0, w: 0, h: 0 };
        }
      }
      this._inpaintPrevBox = null;
      this._inpaintDrew = false;
    },
    // Alias cho pointerup fallback nếu template vẫn gọi
    inpaintMaskStop() { this._inpaintStopDrag(); },
    _inpaintHitTest(p, b) {
      if (!b || b.w < 0.02 || b.h < 0.02) return null;
      // Margin bắt handle theo PIXEL thật trên màn hình (≈ 26px quanh góc) —
      // không phải % cố định → dễ bắt góc dù ảnh nhỏ hay zoom xa.
      const m = this.canvasMetrics();
      const M = m ? Math.max(0.025, 26 / m.vw) : 0.06;
      const corners = [['nw', b.x, b.y], ['ne', b.x + b.w, b.y], ['sw', b.x, b.y + b.h], ['se', b.x + b.w, b.y + b.h]];
      for (const [k, cx, cy] of corners) { if (Math.abs(p.nx - cx) <= M && Math.abs(p.ny - cy) <= M) return k; }
      if (p.nx >= b.x && p.nx <= b.x + b.w && p.ny >= b.y && p.ny <= b.y + b.h) return 'move';
      return null;
    },
    _initInpaintBrush() {
      // Canvas theo TỈ LỆ ẢNH GỐC (không vuông 512×512) — nếu vuông thì mask bị ép méo & lệch vị trí.
      const m = this.canvasMetrics();
      const base = 512;
      let w = base, h = base;
      if (m && m.iw && m.ih) {
        const ia = m.iw / m.ih;
        if (ia >= 1) { w = base; h = Math.max(1, Math.round(base / ia)); }
        else { w = Math.max(1, Math.round(base * ia)); h = base; }
      }
      const c = document.createElement('canvas');
      c.width = w; c.height = h;
      this._inpaintMaskCanvas = c;
      this._inpaintMaskCtx = c.getContext('2d');
      this._inpaintUndoStack = [];
    },
    // Ctrl+Z: khôi phục canvas về trước nét vẽ gần nhất, rồi snapshot lại mask data.
    undoInpaintBrush() {
      if (this.inpaintMaskMode !== 'brush') return;
      const c = this._inpaintMaskCtx; if (!c) return;
      const snap = this._inpaintUndoStack.pop();
      if (!snap) { this.toast('Không còn nét để hoàn tác.', 'info'); return; }
      const w = this._inpaintMaskCanvas.width, h = this._inpaintMaskCanvas.height;
      c.globalCompositeOperation = 'source-over';
      c.clearRect(0, 0, w, h);
      c.putImageData(snap, 0, 0);
      this._finalizeInpaintBrush();
      this.toast('Đã hoàn tác nét vẽ.');
    },
    // Gắn canvas DOM thật (overlay trên ảnh) làm nơi vẽ mask → nét vẽ HIỂN THỊ realtime.
    // Khi tắt brush (el = null) → gỡ tham chiếu để không vẽ vào element đã unmount.
    attachBrushCanvas(el) {
      if (!el) { this._inpaintMaskCanvas = null; this._inpaintMaskCtx = null; return; }
      const c = markRaw(el);
      const ctx = c.getContext('2d');
      ctx.clearRect(0, 0, c.width, c.height);
      this._inpaintMaskCanvas = c;
      this._inpaintMaskCtx = ctx;
    },
    _inpaintBrushRadius() { const s = Math.max(2, Math.min(48, Number(this.inpaintBrushSize) || 10)); return this.inpaintErase ? s * 1.6 : s; },
    _inpaintBrushWidth() { return this._inpaintBrushRadius() * 2; },
    _drawInpaintBrushDot(p) {
      const c = this._inpaintMaskCtx; if (!c) return;
      const w = this._inpaintMaskCanvas.width, h = this._inpaintMaskCanvas.height;
      // Tẩy: destination-out xoá hẳn pixel (cả nét vẽ lẫn mask) — sửa khi vẽ lỡ.
      const erase = !!this.inpaintErase;
      c.globalCompositeOperation = erase ? 'destination-out' : 'source-over';
      c.fillStyle = erase ? 'rgba(0,0,0,1)' : 'rgba(220,38,38,0.6)'; // đỏ 60% — rõ mà không che ảnh
      c.beginPath(); c.arc(p.nx * w, p.ny * h, this._inpaintBrushRadius(), 0, Math.PI * 2); c.fill();
      c.globalCompositeOperation = 'source-over';
    },
    _drawInpaintBrushLine(from, to) {
      const c = this._inpaintMaskCtx; if (!c) return;
      const w = this._inpaintMaskCanvas.width, h = this._inpaintMaskCanvas.height;
      const erase = !!this.inpaintErase;
      c.globalCompositeOperation = erase ? 'destination-out' : 'source-over';
      c.strokeStyle = erase ? 'rgba(0,0,0,1)' : 'rgba(220,38,38,0.6)';
      c.lineWidth = this._inpaintBrushWidth(); c.lineCap = 'round'; c.lineJoin = 'round';
      c.beginPath(); c.moveTo(from.nx * w, from.ny * h); c.lineTo(to.nx * w, to.ny * h); c.stroke();
      c.globalCompositeOperation = 'source-over';
    },
    _finalizeInpaintBrush() {
      const el = this._inpaintMaskCanvas; if (!el || !this._inpaintMaskCtx) return;
      const w = el.width, h = el.height;
      const ctx = this._inpaintMaskCtx;
      const src = ctx.getImageData(0, 0, w, h);
      const mask = document.createElement('canvas'); mask.width = w; mask.height = h;
      const mctx = mask.getContext('2d');
      const out = mctx.createImageData(w, h);
      for (let i = 0; i < w * h; i++) {
        // Grayscale mask chống răng cưa: nét đỏ 60% (alpha≈153) chuẩn hoá → đen(0=edit),
        // mép anti-aliased cho gray chuyển mềm, nền chưa vẽ → trắng(255=keep).
        const a = src.data[i * 4 + 3];
        const v = 255 - Math.min(255, Math.round(a * (255 / 153)));
        out.data[i * 4] = v; out.data[i * 4 + 1] = v; out.data[i * 4 + 2] = v; out.data[i * 4 + 3] = 255;
      }
      mctx.putImageData(out, 0, 0);
      this.inpaintBrushData = mask.toDataURL('image/png').replace(/^data:image\/png;base64,/, '');
      let minX = w, minY = h, maxX = 0, maxY = 0, found = false;
      for (let y = 0; y < h; y++) { for (let x = 0; x < w; x++) {
        if (src.data[(y * w + x) * 4 + 3] > 40) { found = true; if (x < minX) minX = x; if (x > maxX) maxX = x; if (y < minY) minY = y; if (y > maxY) maxY = y; }
      }}
      if (found && minX <= maxX && minY <= maxY) this.inpaintMaskBox = { x: minX / w, y: minY / h, w: (maxX - minX + 1) / w, h: (maxY - minY + 1) / h };
      // KHÔNG null canvas: giữ nét để vẽ TIẾP nhiều nét trên cùng mask (finalize chỉ snapshot
      // dữ liệu). Canvas bị xoá khi bật brush mới / thoát chế độ (attachBrushCanvas clearRect).
    },
    // ── Hành động vùng chọn (Xóa / Tô màu) cho rect + freehand ──
    _loadImageSrc(src) {
      return new Promise((resolve, reject) => {
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = () => resolve(img);
        img.onerror = () => reject(new Error('Không tải được ảnh.'));
        img.src = src;
      });
    },
    // Canvas alpha (trắng=vùng chọn, trong suốt=ngoài) theo kích thước layer, có feather.
    async _buildSelectionAlpha(w, h, maskData = null) {
      const sel = document.createElement('canvas'); sel.width = w; sel.height = h;
      const sctx = sel.getContext('2d');
      const mode = this.inpaintMaskMode;
      const data = maskData ?? this.inpaintBrushData;
      if (mode === 'rect' && !data) {
        const b = this.inpaintMaskBox;
        if (!b || b.w < 0.02 || b.h < 0.02) throw new Error('Chưa có vùng chọn.');
        sctx.fillStyle = '#fff';
        sctx.fillRect(b.x * w, b.y * h, b.w * w, b.h * h);
      } else {
        // freehand/brush/path/magic → mask PNG (đen=vùng chọn); rect đã ĐẢO cũng dùng mask.
        // Dùng maskData ĐÃ CAPTURE (ổn định, không đổi giữa chừng).
        if (!data) throw new Error('Chưa vẽ vùng chọn.');
        const mimg = await this._loadImageSrc('data:image/png;base64,' + data);
        const tmp = document.createElement('canvas'); tmp.width = w; tmp.height = h;
        const tctx = tmp.getContext('2d');
        tctx.drawImage(mimg, 0, 0, w, h);
        const id = tctx.getImageData(0, 0, w, h);
        const d = id.data;
        for (let i = 0; i < d.length; i += 4) {
          const a = 255 - d[i]; // mask grayscale: đen(0)=vùng chọn → alpha 255
          d[i] = 255; d[i + 1] = 255; d[i + 2] = 255; d[i + 3] = a;
        }
        sctx.putImageData(id, 0, 0);
      }
      const feather = Number(this.inpaintFeather) || 0;
      if (feather > 0) {
        const blurred = document.createElement('canvas'); blurred.width = w; blurred.height = h;
        const bctx = blurred.getContext('2d');
        bctx.filter = `blur(${feather}px)`;
        bctx.drawImage(sel, 0, 0);
        return blurred;
      }
      return sel;
    },
    async _applySelectionToLayer(action, color) {
      const l = this.activeLayer;
      if (!l || !l.image) { this.toast('Chọn 1 layer ảnh trước.', 'error'); return; }
      if (l.locked) { this.toast('Layer đang khóa — mở khóa trước khi xóa/tô vùng chọn.', 'error'); return; }
      const maskData = this.inpaintBrushData; // capture đồng bộ
      this.pushHistory();
      try {
        const img = await this._loadImageSrc(l.image);
        const w = img.naturalWidth, h = img.naturalHeight;
        const sel = await this._buildSelectionAlpha(w, h, maskData);
        const out = document.createElement('canvas'); out.width = w; out.height = h;
        const octx = out.getContext('2d');
        octx.drawImage(img, 0, 0);
        if (action === 'delete') {
          octx.globalCompositeOperation = 'destination-out';
          octx.drawImage(sel, 0, 0);
        } else {
          const colorCanvas = document.createElement('canvas'); colorCanvas.width = w; colorCanvas.height = h;
          const cctx = colorCanvas.getContext('2d');
          cctx.fillStyle = color || this.inpaintFillColor;
          cctx.fillRect(0, 0, w, h);
          cctx.globalCompositeOperation = 'destination-in';
          cctx.drawImage(sel, 0, 0);
          octx.drawImage(colorCanvas, 0, 0);
        }
        l.image = out.toDataURL('image/png');
        this.saveLayerLayout();
        this.toast(action === 'delete' ? 'Đã xóa nội dung vùng chọn.' : 'Đã tô màu vùng chọn.');
      } catch (e) {
        this.toast(e.message || 'Không áp dụng được.', 'error');
      }
    },
    deleteSelectedRegion() { this._applySelectionToLayer('delete'); },
    fillSelectedRegion() { this._applySelectionToLayer('fill'); },
    // Nhân đôi vùng chọn (rect/freehand) thành 1 layer mới chứa đúng phần được chọn.
    async duplicateSelectedRegion() {
      const src = this.activeLayer;
      if (!src || !src.image) { this.toast('Chọn 1 layer ảnh trước.', 'error'); return; }
      if (src.locked) { this.toast('Layer đang khóa — mở khóa trước khi nhân đôi vùng chọn.', 'error'); return; }
      const maskData = this.inpaintBrushData; // capture đồng bộ
      this.pushHistory();
      try {
        const img = await this._loadImageSrc(src.image);
        const w = img.naturalWidth, h = img.naturalHeight;
        const sel = await this._buildSelectionAlpha(w, h, maskData);
        const out = document.createElement('canvas'); out.width = w; out.height = h;
        const octx = out.getContext('2d');
        octx.drawImage(img, 0, 0);
        octx.globalCompositeOperation = 'destination-in';
        octx.drawImage(sel, 0, 0); // giữ đúng pixel trong vùng chọn, ngoài trong suốt
        const url = out.toDataURL('image/png');
        const id = 'dup-' + Date.now();
        // Nhân đôi NGAY tại vị trí vùng chọn: cùng transform với layer gốc (chồng khít, dễ kéo đi).
        this.canvasLayers.push({
          id, kind: 'source', name: 'Nhân đôi vùng chọn', image: url, genId: null,
          visible: true, locked: false,
          x: src.x || 0, y: src.y || 0, scale: src.scale || 1, rotation: src.rotation || 0,
          opacity: src.opacity != null ? src.opacity : 1, blend: src.blend || 'normal', flipX: false, flipY: false,
          baseW: src.baseW, baseH: src.baseH,
        });
        this.saveLayerLayout();
        this.setActiveLayer(id);
        this.toast('Đã nhân đôi vùng chọn tại vị trí.');
      } catch (e) {
        this.toast(e.message || 'Không nhân đôi được.', 'error');
      }
    },
    // Nâng (float/cut) vùng chọn: cắt nội dung khỏi layer gốc → đưa lên layer mới chồng khít để kéo đi.
    async floatSelectedRegion() {
      const src = this.activeLayer;
      if (!src || !src.image) { this.toast('Chọn 1 layer ảnh trước.', 'error'); return; }
      if (src.locked) { this.toast('Layer đang khóa — mở khóa trước khi nâng vùng chọn.', 'error'); return; }
      const maskData = this.inpaintBrushData; // capture đồng bộ
      this.pushHistory();
      try {
        const img = await this._loadImageSrc(src.image);
        const w = img.naturalWidth, h = img.naturalHeight;
        const sel = await this._buildSelectionAlpha(w, h, maskData);
        // 1) Layer mới chứa đúng phần chọn (floating)
        const fc = document.createElement('canvas'); fc.width = w; fc.height = h;
        const fctx = fc.getContext('2d');
        fctx.drawImage(img, 0, 0);
        fctx.globalCompositeOperation = 'destination-in';
        fctx.drawImage(sel, 0, 0);
        const floatUrl = fc.toDataURL('image/png');
        // 2) Cắt phần chọn khỏi layer gốc
        const cc = document.createElement('canvas'); cc.width = w; cc.height = h;
        const cctx = cc.getContext('2d');
        cctx.drawImage(img, 0, 0);
        cctx.globalCompositeOperation = 'destination-out';
        cctx.drawImage(sel, 0, 0);
        src.image = cc.toDataURL('image/png');
        // 3) Thêm layer floating trùng vị trí gốc
        const id = 'float-' + Date.now();
        this.canvasLayers.push({
          id, kind: 'source', name: 'Nâng vùng chọn', image: floatUrl, genId: null,
          visible: true, locked: false,
          x: src.x || 0, y: src.y || 0, scale: src.scale || 1, rotation: src.rotation || 0,
          opacity: src.opacity != null ? src.opacity : 1, blend: src.blend || 'normal', flipX: false, flipY: false,
          baseW: src.baseW, baseH: src.baseH,
        });
        this.saveLayerLayout();
        this.setActiveLayer(id);
        this.toast('Đã nâng vùng chọn thành layer mới — kéo để di chuyển.');
      } catch (e) {
        this.toast(e.message || 'Không nâng được.', 'error');
      }
    },
    // ── Undo / Redo (lịch sử layer) ──
    _snapshot() { return { layers: this.canvasLayers.map((l) => ({ ...l })), activeLayerId: this.activeLayerId }; },
    pushHistory() {
      this.undoStack.push(this._snapshot());
      if (this.undoStack.length > 50) this.undoStack.shift();
      this.redoStack = [];
    },
    _restoreSnapshot(snap) {
      this.canvasLayers = snap.layers.map((l) => ({ ...l }));
      this.activeLayerId = snap.activeLayerId;
      const l = this.activeLayer;
      if (l) {
        if (l.kind === 'source') { this.editSource = { url: l.image, name: l.name }; this.previewId = null; this.preview = null; }
        else if (l.genId) {
          const g = this.generations.find((x) => x.id === l.genId);
          if (g) { this.previewId = g.id; this.preview = { id: g.id, media_url: g.media_url, type: g.type || 'image', status: g.status || 'completed' }; }
          this.editSource = null;
        } else { this.editSource = null; this.previewId = null; this.preview = null; }
      } else { this.editSource = null; this.previewId = null; this.preview = null; }
      this.saveLayerLayout();
    },
    undo() {
      const snap = this.undoStack.pop();
      if (!snap) { this.toast('Không còn thao tác để hoàn tác.', 'info'); return; }
      this.redoStack.push(this._snapshot());
      this._restoreSnapshot(snap);
      this.toast('Đã hoàn tác.');
    },
    redo() {
      const snap = this.redoStack.pop();
      if (!snap) { this.toast('Không còn thao tác để làm lại.', 'info'); return; }
      this.undoStack.push(this._snapshot());
      this._restoreSnapshot(snap);
      this.toast('Đã làm lại.');
    },
    async runSwap(opts = {}) {
      const src = this.upscaleSrc; if (!src || this.swapLoading) { this.toast('Chọn ảnh thiết kế để áp dụng.', 'error'); return; }
      // change_face=false (mặc định): giữ nguyên khuôn mặt gốc, chỉ cần pose.
      // change_face=true: 1 face reference + 1 or MORE poses -> one result per pose.
      const changeFace = !!opts.change_face;
      if (changeFace && !this.swapModelIds.length) { this.toast('Chọn 1 khuôn mặt để đổi.', 'error'); return; }
      if (!this.swapPoseIds.length) { this.toast('Chọn ít nhất 1 dáng trước.', 'error'); return; }
      const face = changeFace ? this.swapModelIds[0] : '';
      const poses = [...this.swapPoseIds];
      // Trạng thái hoạt động (giống Inpaint) — hiển thị tiến trình trong card.
      this.swapStage = 'send';
      this.swapError = '';
      this.swapStartTs = Date.now();
      this.swapGenIds = [];
      // P0: keep explicit 0 values (slider minimums) — never coerce 0 back into a default.
      const toInt = (v, dflt) => (v != null && Number.isFinite(Number(v)) ? Number(v) : dflt);

      // Progress + cancel: each pose is one request; user can abort mid-run.
      const abort = new AbortController();
      this.swapAbort = abort;
      this.swapTotal = poses.length;
      this.swapDone = 0;
      this.swapLoading = true;
      let n = 0; let lastErr = '';
      const createdIds = [];
      for (const poseId of poses) {
        if (abort.signal.aborted) { lastErr = 'Đã hủy.'; break; }
        try {
          const d = await this.api('/studio/swap-model', { image: src, model_id: face, pose_id: poseId, background: opts.background || '', tone: opts.tone ?? 'none', change_face: changeFace }, abort.signal);
          // Swap now runs in the background queue (SwapModelJob) — the response is async (pending).
          if (d.generation_id) {
            createdIds.push(d.generation_id);
            this.addGen({ id: d.generation_id, type: 'image', status: d.status || 'processing', model: d.model || 'swap', provider: d.provider || 'swap', media_url: null, error: null, credits_cost: 1, created_at: 'Đang xử lý' });
            n++;
          } else if (d.message) { lastErr = d.message; }
        } catch (e) {
          if (e && e.name === 'AbortError') { lastErr = 'Đã hủy.'; break; }
          lastErr = e.message || 'Lỗi thay đổi người mẫu.';
        } finally {
          this.swapDone++;
        }
      }
      this.swapLoading = false;
      this.swapAbort = null;
      this.swapDone = 0; this.swapTotal = 0;
      if (abort.signal.aborted) {
        this.swapStage = 'cancelled';
      } else if (n > 0) {
        this.swapGenIds = createdIds;
        this.swapStage = 'processing';
        this.toast('Đã gửi ' + n + ' dáng vào hàng đợi xử lý…');
        this.refreshSwapResults(createdIds);
      } else {
        this.swapStage = 'error';
        this.swapError = lastErr || 'Lỗi thay đổi người mẫu.';
        this.toast(this.swapError, 'error');
      }
    },
    // Poll từng generation qua /studio/generations/{id} (show) — GIỐNG Inpaint: vừa trả trạng thái
    // vừa kích lazy xử lý nếu còn pending, nên swap không còn phụ thuộc duy nhất vào queue worker.
    async refreshSwapResults(ids) {
      this.swapProcessing = true;
      this._swapStop = false;
      this.swapStage = 'processing';
      const pending = new Set(ids.map(String));
      const deadline = Date.now() + 300000; // tối đa 5 phút
      while (pending.size > 0 && Date.now() < deadline) {
        if (this._swapStop) break;
        await new Promise((r) => setTimeout(r, 3000));
        for (const id of [...pending]) {
          if (this._swapStop) break;
          try {
            const res = await fetch('/studio/generations/' + id, { headers: { Accept: 'application/json' } });
            if (!res.ok) continue;
            const g = await res.json();
            const idx = this.generations.findIndex((x) => String(x.id) === String(id));
            if (idx >= 0) {
              this.generations[idx] = { ...this.generations[idx], status: g.status, media_url: g.media_url, error: g.error, model: g.model || this.generations[idx].model };
            }
            if (g.status === 'completed' && g.media_url) {
              this.previewId = g.id;
              this.preview = { id: g.id, media_url: g.media_url, type: 'image', status: 'completed' };
              this.syncLayerForGen(g.id, g.media_url, 'Ảnh #' + g.id, true);
              pending.delete(id);
            } else if (['failed', 'cancelled'].includes(g.status)) {
              pending.delete(id);
              if (g.status === 'failed' && g.error && !this.swapError) this.swapError = g.error;
            }
          } catch (e) { /* transient */ }
        }
      }
      const statusOf = (id) => { const g = this.generations.find((x) => String(x.id) === String(id)); return g ? g.status : null; };
      const allDone = ids.every((id) => { const s = statusOf(id); return !s || ['completed', 'failed', 'cancelled'].includes(s); });
      const anyOk = ids.some((id) => statusOf(id) === 'completed');
      if (allDone) {
        if (anyOk) {
          this.swapStage = 'done';
          this.toast('Đã xong thay đổi người mẫu.');
        } else {
          this.swapStage = 'error';
          if (!this.swapError) this.swapError = 'Không tạo được phiên bản người mẫu nào.';
          this.toast(this.swapError, 'error');
        }
      } else if (!this._swapStop) {
        // Hết thời gian poll nhưng vẫn còn chạy → không ép lỗi, kết quả sẽ về qua load()/click Output.
        this.toast('Còn dáng đang xử lý — theo dõi trong Outputs.');
      }
      this.swapProcessing = false;
      this._swapStop = false;
    },
    cancelSwap() {
      if (this.swapAbort) { this.swapAbort.abort(); }
      if (this.swapProcessing) { this._swapStop = true; this.swapProcessing = false; }
      this.swapLoading = false;
      this.swapStage = 'cancelled';
      this.toast('Đã hủy.');
    },
    clearSwapStatus() { this.swapStage = ''; this.swapError = ''; this.swapGenIds = []; this.swapStartTs = 0; },
  },
});
