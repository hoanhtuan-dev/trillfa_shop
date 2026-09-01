import { defineStore } from 'pinia';

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
    inpainting: false,
    inpaintPrompt: '',
    suggesting: false,
    viewer: null,
    upscalePresets: [],
    lastBatch: [],
    showBatch: false,
  }),
  getters: {
    upscaleSrc: (s) => (s.editSource && s.editSource.url) || (s.preview && s.preview.media_url) || '',
    upscaleName: (s) => (s.editSource && s.editSource.name) || (s.preview ? 'Ảnh kết quả #' + s.preview.id : 'Ảnh đang chọn'),
    canvasLayers() {
      const layers = [];
      if (this.editSource) layers.push({ id: 'source', kind: 'source', name: this.editSource.name || 'Ảnh nguồn', image: this.editSource.url });
      this.generations.filter(g => g.media_url).forEach(g => layers.push({ id: String(g.id), kind: 'gen', name: 'Ảnh #' + g.id, image: g.media_url, gen: g }));
      return layers;
    },
    activeLayerId() { return this.editSource ? 'source' : (this.previewId ? String(this.previewId) : ''); },
    activeBatch() { return this.generations.filter(g => this.lastBatch.includes(g.id)); },
  },
  actions: {
    async api(url, body = {}) {
      const res = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF(), 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(body) });
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
    },
    setPreview(g) { if (g) { this.previewId = g.id; this.preview = { id: g.id, media_url: g.media_url, type: g.type || 'image', status: g.status || 'completed' }; } },
    toast(msg, type = 'info') { if (window.Alpine?.store?.('toast')) window.Alpine.store('toast').show(msg, type); },
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
    select(g) { if (!g) return; this.previewId = g.id; this.preview = { id: g.id, media_url: g.media_url, type: g.type || 'image', status: g.status || 'completed' }; },
    async generateImage() {
      if (!this.imagePromptEn || this.generating) return;
      this.generating = true;
      try {
        const d = await this.api('/studio/generate', { prompt: this.imagePromptEn, resolution: this.imageRes, ratio: this.imageRatio, variants: Number(this.variantCount) || 1 });
        const items = Array.isArray(d.items) ? d.items : (d.generation_id ? [d] : []);
        items.forEach((it) => this.addGen({ id: it.generation_id, type: 'image', status: it.status, model: it.model, provider: it.provider, media_url: it.media_url, error: it.error, credits_cost: 1, created_at: 'Vừa gửi' }));
        this.setBatch(items.map(it => it.generation_id));
        if (d.credits_left != null) this.creditsLeft = d.credits_left;
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
    panStart(e) { this._drag = { x: e.clientX, y: e.clientY, px: this.pan.x, py: this.pan.y }; },
    panMove(e) { if (this._drag) { this.pan.x = this._drag.px + (e.clientX - this._drag.x); this.pan.y = this._drag.py + (e.clientY - this._drag.y); } },
    panEnd() { this._drag = null; },
    async deleteGen(g) {
      try { const r = await fetch('/studio/generations/' + g.id, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': CSRF(), Accept: 'application/json' } }); const d = await r.json().catch(() => ({})); if (!r.ok) throw new Error(d.message || 'Lỗi xóa.'); this.generations = this.generations.filter(x => x.id !== g.id); if (this.previewId === g.id) { this.previewId = null; this.preview = null; } this.toast('Đã xóa.'); }
      catch(e){ this.toast(e.message || 'Lỗi xóa.', 'error'); }
    },
    goEdit(g) { this.select(g); this.step = 2; },
    goVideo(g) { this.select(g); this.step = 3; },
    selectLayer(item) { if (!item) return; if (item.kind === 'source') { /** already active */ } else if (item.gen) { this.select(item.gen); } },
    deleteLayer(item) { if (!item) return; if (item.kind === 'source') { this.editSource = null; } else if (item.gen) { this.deleteGen(item.gen); } },
    setBatch(ids) { this.lastBatch = (ids || []).filter(Boolean); this.showBatch = this.lastBatch.length > 1; },
    hideBatch() { this.showBatch = false; },
    setSource(url, name) { this.editSource = { url, name: name || 'Ảnh nguồn' }; this.toast('Đã chọn ảnh nguồn.'); },
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
    async suggestStyle(image) {
      if (!image) { this.toast('Chọn ảnh nguồn để gợi ý.', 'error'); return; }
      this.suggesting = true;
      try { const d = await this.api('/studio/suggest', { image }); const styles = (d.styles || []).join(', '); this.toast(styles ? 'Phong cách: ' + styles + (d.background ? ' · ' + d.background : '') : 'Đã gợi ý.'); }
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
      const src = this.upscaleSrc; if (!src || this.swapLoading || !this.swapModelIds.length) { this.toast('Chọn người mẫu + dáng trước.', 'error'); return; }
      this.swapLoading = true;
      try { const d = await this.api('/studio/swap-model', { image: src, model_id: this.swapModelIds[0], pose_id: this.swapPoseIds[0] || '', background: opts.background || '', texture: 5 }); if (d.generation_id) this.addGen({ id: d.generation_id, type: 'image', status: d.task_id ? 'processing' : 'completed', model: 'swap', provider: d.provider || 'swap', media_url: d.media_url || null, error: null, credits_cost: 1, created_at: 'Vừa gửi' }); }
      catch (e) { this.toast(e.message || 'Lỗi thay đổi người mẫu.', 'error'); }
      finally { this.swapLoading = false; }
    },
  },
});
