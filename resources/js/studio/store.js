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
  }),
  getters: {
    upscaleSrc: (s) => (s.editSource && s.editSource.url) || (s.preview && s.preview.media_url) || '',
    upscaleName: (s) => (s.editSource && s.editSource.name) || (s.preview ? 'Ảnh kết quả #' + s.preview.id : 'Ảnh đang chọn'),
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
      try {
        const res = await fetch('/studio/latest', { headers: { Accept: 'application/json' } });
        const d = await res.json();
        if (Array.isArray(d.generations)) this.generations = d.generations;
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
    async inpaint(prompt) {
      if (!this.previewId || this.inpainting) { this.toast('Chọn ảnh để sửa.', 'error'); return; }
      this.inpainting = true;
      try { const d = await this.api('/studio/generations/' + this.previewId + '/inpaint', { prompt, preserve_background: true, preserve_face: true, image: this.preview?.media_url || '' }); if (d.media_url) { this.addGen({ id: d.generation_id || ('ip-' + Date.now()), type: 'image', status: 'completed', model: 'inpaint', provider: d.provider || 'qwen', media_url: d.media_url, error: null, credits_cost: 1, created_at: 'Vừa sửa' }); this.toast('Đã sửa ảnh.'); } }
      catch (e) { this.toast(e.message || 'Lỗi inpaint.', 'error'); }
      finally { this.inpainting = false; }
    },
    async runSwap() {
      const src = this.upscaleSrc; if (!src || this.swapLoading || !this.swapModelIds.length) { this.toast('Chọn người mẫu + dáng trước.', 'error'); return; }
      this.swapLoading = true;
      try { const d = await this.api('/studio/swap-model', { image: src, model_id: this.swapModelIds[0], pose_id: this.swapPoseIds[0] || '', texture: 5 }); if (d.generation_id) this.addGen({ id: d.generation_id, type: 'image', status: d.task_id ? 'processing' : 'completed', model: 'swap', provider: d.provider || 'swap', media_url: d.media_url || null, error: null, credits_cost: 1, created_at: 'Vừa gửi' }); }
      catch (e) { this.toast(e.message || 'Lỗi thay đổi người mẫu.', 'error'); }
      finally { this.swapLoading = false; }
    },
  },
});
