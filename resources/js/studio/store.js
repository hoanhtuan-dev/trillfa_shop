import { defineStore } from 'pinia';
import { markRaw } from 'vue';

const CSRF = () => (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

// Shared studio store — each card component reads/writes this so they can share data.
export const useStudioStore = defineStore('studio', {
  state: () => ({
    step: 1,
    opening: false,
    previewId: null,
    preview: null,
    generations: [],
    creditsLeft: 0,
    canvasImg: '',
    // upscale / film / reframe share the source image (editSource || preview)
    editSource: null,
    texture: 5,
    // upscale params
    upscaleScale: 2,
    upscaleRefine: 0,
    studioPhotoreal: 5,
    skinDetail: 4,
    lightShadow: 5,
    fabricDetail: 5,
    upscaling: false,
    // film look
    lookPreset: 'studio',
    lookLevel: 5,
    looking: false,
    // reframe
    reframeRatio: '3:4',
    reframing: false,
    cropMode: false,
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
    // concept
    imagePromptEn: '',
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
    inpainting: false,
    inpaintPrompt: '',
    suggesting: false,
    suggestResult: null,
    promptOpen: false,
    viewer: null,
    flashMsg: '',
    flashType: 'info',
    _flashTimer: null,
    upscalePresets: [],
    lastBatch: [],
    showBatch: false,
    canvasLayers: [],
    activeLayerId: '',
  }),
  getters: {
    upscaleSrc() { if (this.activeLayerId) { const l = this.canvasLayers.find(x => x.id === this.activeLayerId); if (l && l.image) return l.image; } return (this.editSource && this.editSource.url) || (this.preview && this.preview.media_url) || ''; },
    upscaleName() { if (this.activeLayerId) { const l = this.canvasLayers.find(x => x.id === this.activeLayerId); if (l) return l.name; } return (this.editSource && this.editSource.name) || (this.preview ? 'Ảnh kết quả #' + this.preview.id : 'Ảnh đang chọn'); },

    activeBatch() { return this.generations.filter(g => this.lastBatch.includes(g.id)); },
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
    async load() {
      this.loadUpscaleMemory();
      try {
        const res = await fetch('/studio/latest', { headers: { Accept: 'application/json' } });
        const d = await res.json();
        const items = d.items || d.generations || [];
        if (Array.isArray(items)) this.generations = items;
        if (d.credits_left != null) this.creditsLeft = d.credits_left;
        const comp = this.generations.find(g => g.status === 'completed' && (g.type === 'image' || !g.type));
        if (comp) { this.previewId = comp.id; this.preview = { id: comp.id, media_url: comp.media_url, type: comp.type || 'image', status: comp.status }; }
      } catch (e) { /* app data loads via Alpine too */ }
    },
    select(g) { if (!g) return; this.previewId = g.id; this.preview = { id: g.id, media_url: g.media_url, type: g.type || 'image', status: g.status || 'completed' }; if (g.media_url) { this.pushCanvasLayer(String(g.id), 'gen', 'Ảnh #' + g.id, g.media_url, g.id); this.setActiveLayer(String(g.id)); } },
    async processQueue() {
      try { await fetch('/studio/process', { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF(), Accept: 'application/json' } }); } catch (e) {}
      // refresh the generations so the processed images appear
      try { const res = await fetch('/studio/latest', { headers: { Accept: 'application/json' } }); const d = await res.json(); const items = d.items || d.generations || []; if (Array.isArray(items)) this.generations = items; } catch (e) {}
    },
    async generateImage() {
      if (!this.imagePromptEn || this.generating) return;
      this.generating = true;
      try {
        const finalPrompt = (this.imagePromptEn || '') + (this.texture > 0 ? ', fabric/knit texture detail ' + this.texture + '/10' : '');
        const d = await this.api('/studio/generate', { prompt: finalPrompt, resolution: this.imageRes, ratio: this.imageRatio, variants: Number(this.variantCount) || 1 });
        const items = Array.isArray(d.items) ? d.items : (d.generation_id ? [d] : []);
        items.forEach((it) => this.addGen({ id: it.generation_id, type: 'image', status: it.status, model: it.model, provider: it.provider, media_url: it.media_url, error: it.error, credits_cost: 1, created_at: 'Vừa gửi' }));
        this.setBatch(items.map(it => it.generation_id));
        if (d.credits_left != null) this.creditsLeft = d.credits_left;
        // Process the pending generations (RenderImageJob::dispatchSync, fire-and-forget so the UI isn't blocked).
        this.processQueue();
      } catch (e) { this.toast(e.message || 'Lỗi tạo ảnh.', 'error'); }
      finally { this.generating = false; }
    },
    async loadPalette(id) {
      if (!id) { this.palette = []; return; }
      try { const res = await fetch('/studio/generations/' + id + '/palette', { headers: { Accept: 'application/json' } }); const d = await res.json(); this.palette = d.colors || []; }
      catch (e) { this.palette = []; }
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
    wheelZoom(delta) { const f = delta > 0 ? 0.9 : 1.1; const nz = Math.max(0.25, Math.min(4, +(this.zoom * f).toFixed(2))); this.zoom = nz; },
    // ── Canvas refs (set by StudioApp template refs; markRaw so Vue never proxies DOM nodes) ──
    setCanvasRefs(img, zoom) { this.cvImg = img ? markRaw(img) : null; this.canvasZoom = zoom ? markRaw(zoom) : null; },
    // ── Reframe / Crop ──
    _clamp(v, lo, hi) { return Math.max(lo, Math.min(hi, v)); },
    ratioAspect() { const p = (this.reframeRatio || '3:4').split(':').map(Number); return p[1] ? p[0] / p[1] : 0.75; },
    // Geometry of the *visible* (object-contain) image inside the pan container, in container space.
    canvasMetrics() {
      const img = this.cvImg, cont = this.canvasZoom;
      if (!img || !cont) return null;
      const ir = img.getBoundingClientRect(), cr = cont.getBoundingClientRect();
      if (!ir.width || !ir.height || !cr.width || !cr.height) return null;
      const iw = img.naturalWidth || 1, ih = img.naturalHeight || 1;
      const ia = iw / ih, box = ir.width / ir.height;
      let vw, vh;
      if (ia > box) { vw = ir.width; vh = ir.width / ia; } else { vh = ir.height; vw = ir.height * ia; }
      return { vw, vh, vx: ir.left - cr.left + (ir.width - vw) / 2, vy: ir.top - cr.top + (ir.height - vh) / 2, crW: cr.width, crH: cr.height, ia, iw, ih };
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
      if (this.cropMode) this.initCropBox();
      else this._cropStop(null);
    },
    onCanvasImgLoad() { if (this.cropMode) this.initCropBox(); },
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
      if (!this.cropMode) return;
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
    upscaleCfg() { return { scale: this.upscaleScale, refine: this.upscaleRefine, photoreal: this.studioPhotoreal, skin: this.skinDetail, light: this.lightShadow, fabric: this.fabricDetail }; },
    loadUpscaleMemory() {
      try { const m = JSON.parse(localStorage.getItem('trillfa.upscale') || '{}'); if (m.settings) Object.assign(this, { upscaleScale: m.settings.scale ?? 2, upscaleRefine: m.settings.refine ?? 5, studioPhotoreal: m.settings.photoreal ?? 5, skinDetail: m.settings.skin ?? 4, lightShadow: m.settings.light ?? 5, fabricDetail: m.settings.fabric ?? 5 }); if (Array.isArray(m.presets)) this.upscalePresets = m.presets; } catch (e) {}
    },
    saveUpscaleMemory() { try { localStorage.setItem('trillfa.upscale', JSON.stringify({ settings: this.upscaleCfg(), presets: this.upscalePresets })); } catch (e) {} },
    savePreset(name) { const n = (name || 'Preset ' + (this.upscalePresets.length + 1)).trim(); const existing = this.upscalePresets.find(p => p.name === n); const cfg = this.upscaleCfg(); if (existing) Object.assign(existing, cfg); else this.upscalePresets.push({ name: n, ...cfg }); this.saveUpscaleMemory(); this.toast('Đã lưu preset "' + n + '".'); },
    applyPreset(p) { Object.assign(this, { upscaleScale: p.scale ?? 2, upscaleRefine: p.refine ?? 5, studioPhotoreal: p.photoreal ?? 5, skinDetail: p.skin ?? 4, lightShadow: p.light ?? 5, fabricDetail: p.fabric ?? 5 }); this.saveUpscaleMemory(); this.toast('Đã áp dụng preset "' + p.name + '".'); },
    deletePreset(name) { this.upscalePresets = this.upscalePresets.filter(p => p.name !== name); this.saveUpscaleMemory(); },
    zoomIn() { this.zoom = Math.min(4, +(this.zoom + 0.25)); },
    zoomOut() { this.zoom = Math.max(0.25, +(this.zoom - 0.25)); },
    zoomFit() { this.zoom = 1; this.pan = { x: 0, y: 0 }; },
    panStart(e) { if (this._cropDrag) return; this._drag = { x: e.clientX, y: e.clientY, px: this.pan.x, py: this.pan.y }; },
    panMove(e) { if (this._drag) { this.pan.x = this._drag.px + (e.clientX - this._drag.x); this.pan.y = this._drag.py + (e.clientY - this._drag.y); } },
    panEnd() { this._drag = null; },
    async deleteGen(g) {
      try { const r = await fetch('/studio/generations/' + g.id, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': CSRF(), Accept: 'application/json' } }); const d = await r.json().catch(() => ({})); if (!r.ok) throw new Error(d.message || 'Lỗi xóa.'); this.generations = this.generations.filter(x => x.id !== g.id); if (this.previewId === g.id) { this.previewId = null; this.preview = null; } this.toast('Đã xóa.'); }
      catch(e){ this.toast(e.message || 'Lỗi xóa.', 'error'); }
    },
    goEdit(g) { this.select(g); this.step = 2; },
    goVideo(g) { this.select(g); this.step = 3; },
    pushCanvasLayer(id, kind, name, image, genId) { if (!id || !image) return; if (!this.canvasLayers.some(l => l.id === id)) this.canvasLayers.push({ id, kind, name, image, genId }); },
    setActiveLayer(id) { if (!id) return; this.activeLayerId = id; const l = this.canvasLayers.find(x => x.id === id); if (!l) return; if (l.kind === 'source') { this.editSource = { url: l.image, name: l.name }; this.previewId = null; this.preview = null; } else if (l.genId) { const g = this.generations.find(x => x.id === l.genId); if (g) { this.previewId = g.id; this.preview = { id: g.id, media_url: g.media_url, type: g.type || 'image', status: g.status || 'completed' }; } this.editSource = null; } },
    selectLayer(item) { if (!item) return; this.setActiveLayer(item.id); },
    // Remove a layer from the CANVAS ONLY (never deletes the output image or the source file).
    deleteLayer(item) { if (!item) return; this.canvasLayers = this.canvasLayers.filter(l => l.id !== item.id); if (this.activeLayerId === item.id) { const next = this.canvasLayers[0]; if (next) this.selectLayer(next); else { this.editSource = null; this.previewId = null; this.preview = null; } } },
    setBatch(ids) { this.lastBatch = (ids || []).filter(Boolean); this.showBatch = this.lastBatch.length > 1; },
    hideBatch() { this.showBatch = false; },
    setSource(url, name) { this.editSource = { url, name: name || 'Ảnh nguồn' }; this.pushCanvasLayer('source', 'source', this.editSource.name, url); this.setActiveLayer('source'); this.toast('Đã chọn ảnh nguồn.'); },
    async uploadRef(file) {
      if (!file) { this.toast('Chọn file ảnh.', 'error'); return; }
      const fd = new FormData(); fd.append('image', file);
      const res = await fetch('/studio/upload-ref', { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF(), Accept: 'application/json' }, body: fd });
      const d = await res.json().catch(() => ({}));
      if (!res.ok) { this.toast(d.message || 'Lỗi tải ảnh.', 'error'); return; }
      this.setSource(d.url, file.name); return d.url;
    },
    pickFromProduct(p) { this.setSource(p.url, p.name); },
    pickFromResult(g) { this.setSource(g.media_url, 'Ảnh kết quả #' + g.id); },
    async translate(promptTo) { if (!promptTo) { this.toast('Nhập prompt.', 'error'); return; } this.suggestResult = this.suggestResult || {}; try { const d = await this.api('/studio/translate', { text: promptTo, direction: 'vi' }); this.suggestResult.prompt_vi = d.text || d; this.toast('Đã dịch sang tiếng Việt.'); } catch (e) { this.toast(e.message || 'Lỗi dịch.', 'error'); } },
    async suggestStyle(image) {
      if (!image) { this.toast('Chọn ảnh nguồn để gợi ý.', 'error'); return; }
      this.suggesting = true;
      try { const d = await this.api('/studio/suggest', { reference_url: image, creative_level: this.creativeLevel }); this.suggestResult = d; const styles = (d.styles || []).join(', '); this.toast(styles ? 'Phong cách: ' + styles + (d.background ? ' · ' + d.background : '') : 'Đã gợi ý.'); }
      catch(e){ this.toast(e.message || 'Lỗi gợi ý.', 'error'); }
      finally { this.suggesting = false; }
    },
    async inpaint(prompt) {
      if (!this.previewId || this.inpainting) { this.toast('Chọn ảnh để sửa.', 'error'); return; }
      this.inpainting = true;
      try { const d = await this.api('/studio/generations/' + this.previewId + '/inpaint', { prompt, preserve_background: true, preserve_face: true, image: this.preview?.media_url || '' }); if (d.media_url) { this.addGen({ id: d.generation_id || ('ip-' + Date.now()), type: 'image', status: 'completed', model: 'inpaint', provider: d.provider || 'qwen', media_url: d.media_url, error: null, credits_cost: 1, created_at: 'Vừa sửa' }); this.toast('Đã sửa ảnh.'); } }
      catch (e) { this.toast(e.message || 'Lỗi inpaint.', 'error'); }
      finally { this.inpainting = false; }
    },
    async runSwap(opts = {}) {
      const src = this.upscaleSrc; if (!src || this.swapLoading) { this.toast('Chọn ảnh thiết kế để áp dụng.', 'error'); return; }
      // 1 face reference + 1 or MORE poses -> one result per pose.
      if (!this.swapModelIds.length || !this.swapPoseIds.length) { this.toast('Chọn 1 khuôn mặt + ít nhất 1 dáng trước.', 'error'); return; }
      const face = this.swapModelIds[0];
      const poses = [...this.swapPoseIds];
      // P0: keep explicit 0 values (slider minimums) — never coerce 0 back into a default.
      const toInt = (v, dflt) => (v != null && Number.isFinite(Number(v)) ? Number(v) : dflt);
      const build = toInt(opts.build, 6);
      const toneLevel = toInt(opts.tone_level, 5);
      const fabricDetail = toInt(opts.fabric_detail, 0);
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
          const d = await this.api('/studio/swap-model', { image: src, model_id: face, pose_id: poseId, background: opts.background || '', build, tone: opts.tone ?? 'none', tone_level: toneLevel, fabric_detail: fabricDetail }, abort.signal);
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
      if (n > 0) { this.toast('Đã gửi ' + n + ' dáng vào hàng đợi xử lý…'); this.refreshSwapResults(createdIds); }
      else { this.toast(lastErr || 'Lỗi thay đổi người mẫu.', 'error'); }
    },
    // Poll /studio/latest until the just-submitted swap generations finish (the queue worker fills them in).
    async refreshSwapResults(ids) {
      this.swapProcessing = true;
      this._swapStop = false;
      const deadline = Date.now() + 300000; // wait up to 5 min
      while (Date.now() < deadline) {
        if (this._swapStop) { break; }
        await new Promise((r) => setTimeout(r, 5000));
        try {
          const res = await fetch('/studio/latest', { headers: { Accept: 'application/json' } });
          const d = await res.json();
          const items = Array.isArray(d.items) ? d.items : [];
          items.forEach((it) => {
            const idx = this.generations.findIndex((g) => String(g.id) === String(it.id));
            if (idx >= 0 && it.status) {
              const g = this.generations[idx];
              if (g.status !== it.status || (it.media_url && g.media_url !== it.media_url)) {
                this.generations[idx] = { ...g, status: it.status, media_url: it.media_url || g.media_url, error: it.error, model: it.model || g.model };
                if (it.status === 'completed' && it.media_url) {
                  this.previewId = it.id;
                  this.preview = { id: it.id, media_url: it.media_url, type: 'image', status: 'completed' };
                  if (!this.canvasLayers.some((l) => l.id === String(it.id))) {
                    this.pushCanvasLayer(String(it.id), 'gen', 'Ảnh #' + it.id, it.media_url, it.id);
                    this.setActiveLayer(String(it.id));
                  }
                }
              }
            }
          });
        } catch (e) { /* transient — keep polling */ }
        const done = ids.every((id) => {
          const g = this.generations.find((x) => String(x.id) === String(id));
          return !g || g.status === 'completed' || g.status === 'failed' || g.status === 'cancelled';
        });
        if (done) { this.toast('Đã xong thay đổi người mẫu.'); break; }
      }
      this.swapProcessing = false;
      this._swapStop = false;
    },
    cancelSwap() {
      if (this.swapAbort) { this.swapAbort.abort(); }
      if (this.swapProcessing) { this._swapStop = true; this.swapProcessing = false; }
      this.swapLoading = false;
      this.toast('Đã hủy.');
    },
  },
});
