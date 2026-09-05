<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { apiFetch, csrfToken } from '../storefront/composables/useApi.js';

const boot = window.__PRODUCTS_BOOT__ || {};
const categories = boot.categories || [];
const ai = boot.ai || {};

const PLACEHOLDER = '/images/placeholder.svg';

// ---------- App state ----------
const view = ref('list'); // 'list' | 'editor'
const products = ref(boot.products?.data || []);
const pagination = reactive({
    current_page: boot.products?.current_page || 1,
    last_page: boot.products?.last_page || 1,
    per_page: boot.products?.per_page || 15,
    total: boot.products?.total || 0,
    from: boot.products?.from || 0,
    to: boot.products?.to || 0,
});
const filters = reactive({
    q: boot.filters?.q || '',
    category_id: boot.filters?.category_id || '',
    status: boot.filters?.status || '',
});
const listLoading = ref(false);

// ---------- Toast ----------
const toasts = ref([]);
let toastSeq = 0;
function toast(message, type = 'success') {
    const id = ++toastSeq;
    toasts.value.push({ id, message, type });
    setTimeout(() => { toasts.value = toasts.value.filter((t) => t.id !== id); }, 3600);
}

function money(value) {
    const n = Number(value || 0);
    return new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 0 }).format(n) + '₫';
}

// ---------- List ----------
const hasFilters = computed(() => !!(filters.q || filters.category_id || filters.status));
const pageNumbers = computed(() => {
    const pages = [];
    const total = pagination.last_page;
    const current = pagination.current_page;
    for (let i = Math.max(1, current - 2); i <= Math.min(total, current + 2); i++) pages.push(i);
    return pages;
});

async function loadProducts(page = 1) {
    listLoading.value = true;
    try {
        const params = new URLSearchParams();
        if (filters.q) params.set('q', filters.q);
        if (filters.category_id) params.set('category_id', filters.category_id);
        if (filters.status) params.set('status', filters.status);
        params.set('page', page);

        const data = await apiFetch('/admin/products/data?' + params.toString());
        products.value = data.data || [];
        Object.assign(pagination, {
            current_page: data.current_page || 1,
            last_page: data.last_page || 1,
            per_page: data.per_page || 15,
            total: data.total || 0,
            from: data.from || 0,
            to: data.to || 0,
        });
    } catch (e) {
        toast(e.message || 'Không tải được danh sách.', 'error');
    } finally {
        listLoading.value = false;
    }
}

let searchTimer = null;
function onSearchInput() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadProducts(1), 300);
}
function onFilterChange() { loadProducts(1); }
function clearFilters() {
    filters.q = '';
    filters.category_id = '';
    filters.status = '';
    loadProducts(1);
}
function goToPage(page) {
    if (page < 1 || page > pagination.last_page || page === pagination.current_page) return;
    loadProducts(page);
}

function stockClass(stock) {
    if (stock > 5) return 'bg-brand-50 text-brand-700';
    if (stock > 0) return 'bg-amber-50 text-amber-700';
    return 'bg-red-50 text-red-600';
}

async function toggleActive(product) {
    try {
        const res = await apiFetch('/admin/products/' + product.id + '/toggle', { method: 'POST' });
        product.is_active = !!res.is_active;
        toast(product.is_active ? 'Đã bật sản phẩm' : 'Đã tắt sản phẩm');
    } catch (e) {
        toast(e.message, 'error');
    }
}

async function removeProduct(product) {
    if (!window.confirm('Xóa sản phẩm "' + product.name + '"?')) return;
    try {
        await apiFetch('/admin/products/' + product.id, { method: 'DELETE' });
        toast('Đã xóa sản phẩm.');
        const page = products.value.length === 1 && pagination.current_page > 1
            ? pagination.current_page - 1
            : pagination.current_page;
        loadProducts(page);
    } catch (e) {
        toast(e.message, 'error');
    }
}

function viewProduct(product) {
    window.open('/san-pham/' + product.slug, '_blank');
}

// ---------- Editor ----------
const editor = reactive({
    id: null,
    name: '',
    category_id: '',
    brand: '',
    sku: '',
    tags: '',
    short_description: '',
    description: '',
    price: '',
    compare_price: '',
    cost_price: '',
    stock: 0,
    featured: false,
    is_active: true,
    meta_title: '',
    meta_description: '',
});

// Cover
const coverFile = ref(null);
const coverFilePreview = ref('');
const coverUrl = ref('');
const existingCover = ref(null);
const removeCover = ref(false);
const coverInput = ref(null);

// Gallery (unified ordered list: existing | upload | studio)
const gallery = ref([]);
const galleryInput = ref(null);
let uploadSeq = 0;

const variants = ref([]);

const hint = ref('');
const aiLoading = ref(false);
const aiMsg = ref('');
const aiState = ref('');
const forceReanalyze = ref(false);

const pickerOpen = ref(false);
const studioImages = ref([]);
const pickerLoading = ref(false);
const selImages = ref([]);
const pickingFor = ref('gallery');

const submitting = ref(false);

const editorTitle = computed(() => (editor.id ? 'Sửa sản phẩm' : 'Thêm sản phẩm'));
const aiImageUrl = computed(() => coverFilePreview.value || coverUrl.value || existingCover.value || (gallery.value[0]?.url || ''));
const coverPreview = computed(() => (removeCover.value ? '' : (coverFilePreview.value || coverUrl.value || existingCover.value || '')));
const aiProvidersLabel = computed(() => {
    const names = { qwen: 'Qwen', gemini: 'Gemini', deepseek: 'DeepSeek' };
    return (ai.providers || []).map((p) => names[p] || p).join(' → ');
});
const aiApplied = ref([]);

const sections = [
    { id: 'info', label: 'Thông tin' },
    { id: 'images', label: 'Hình ảnh' },
    { id: 'variants', label: 'Biến thể' },
    { id: 'seo', label: 'SEO' },
];

function scrollTo(id) {
    document.getElementById('sec-' + id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function resetEditor() {
    Object.assign(editor, {
        id: null, name: '', category_id: '', brand: '', sku: '', tags: '',
        short_description: '', description: '', price: '', compare_price: '', cost_price: '',
        stock: 0, featured: false, is_active: true, meta_title: '', meta_description: '',
    });

    if (coverFilePreview.value) URL.revokeObjectURL(coverFilePreview.value);
    coverFile.value = null;
    coverFilePreview.value = '';
    coverUrl.value = '';
    existingCover.value = null;
    removeCover.value = false;

    gallery.value.forEach((g) => { if (g.kind === 'upload' && g.url) URL.revokeObjectURL(g.url); });
    gallery.value = [];
    uploadSeq = 0;

    variants.value = [];
    hint.value = '';
    aiLoading.value = false;
    aiMsg.value = '';
    aiState.value = '';
    forceReanalyze.value = false;
    pickerOpen.value = false;
    selImages.value = [];
}

function hydrateEditor(p) {
    editor.id = p.id;
    editor.name = p.name || '';
    editor.category_id = p.category_id ?? '';
    editor.brand = p.brand || '';
    editor.sku = p.sku || '';
    editor.tags = (p.tags || []).join(', ');
    editor.short_description = p.short_description || '';
    editor.description = p.description || '';
    editor.price = p.price ?? '';
    editor.compare_price = p.compare_price ?? '';
    editor.cost_price = p.cost_price ?? '';
    editor.stock = p.stock ?? 0;
    editor.featured = !!p.featured;
    editor.is_active = !!p.is_active;
    editor.meta_title = p.meta_title || '';
    editor.meta_description = p.meta_description || '';

    existingCover.value = p.image || null;
    removeCover.value = false;
    gallery.value = (p.gallery || []).map((g) => ({ key: 'ex-' + g.value, kind: 'existing', url: g.url, value: g.value }));
    variants.value = (p.variants || []).map((v) => ({
        name: v.name || '',
        sku: v.sku || '',
        price: v.price ?? '',
        compare_price: v.compare_price ?? '',
        stock: v.stock ?? 0,
    }));
}

function openCreate() {
    resetEditor();
    view.value = 'editor';
}

async function openEdit(product) {
    resetEditor();
    try {
        const p = await apiFetch('/admin/products/' + product.id + '/payload');
        hydrateEditor(p);
        view.value = 'editor';
    } catch (e) {
        toast(e.message || 'Không tải được sản phẩm.', 'error');
    }
}

function closeEditor() {
    view.value = 'list';
}

// ---------- AI suggest ----------
function applyResult(r) {
    const d = r?.data || r || {};
    aiApplied.value = [];
    if (d.suggested_name) { editor.name = d.suggested_name; aiApplied.value.push('Tên'); }
    if (d.brand) { editor.brand = d.brand; aiApplied.value.push('Thương hiệu'); }
    if (d.short_description) { editor.short_description = d.short_description; aiApplied.value.push('Mô tả ngắn'); }
    if (d.description) { editor.description = d.description; aiApplied.value.push('Mô tả chi tiết'); }
    if (d.meta_title) { editor.meta_title = d.meta_title; aiApplied.value.push('Meta title'); }
    if (d.meta_description) { editor.meta_description = d.meta_description; aiApplied.value.push('Meta description'); }
    if (Array.isArray(d.tags) && d.tags.length) { editor.tags = d.tags.join(', '); aiApplied.value.push('Thẻ'); }
    return d;
}

function catName() {
    const c = categories.find((x) => x.id == editor.category_id);
    return c?.name || '';
}

function fileToBase64(file) {
    return new Promise((resolve, reject) => {
        const r = new FileReader();
        r.onload = () => resolve(String(r.result).split(',')[1] || '');
        r.onerror = reject;
        r.readAsDataURL(file);
    });
}

async function aiSuggest() {
    aiLoading.value = true;
    aiMsg.value = '';
    aiState.value = aiImageUrl.value ? 'analyzing' : 'suggesting';
    try {
        const body = {
            name: editor.name,
            category: catName(),
            brand: editor.brand,
            hint: hint.value,
            short_description: editor.short_description,
            force: forceReanalyze.value,
        };
        // Ảnh vừa upload (chưa lưu lên server) → gửi base64 để AI đọc được ngay,
        // thay vì để trống làm AI sinh mô tả lệch với ảnh.
        if (coverFile.value) {
            body.image_base64 = await fileToBase64(coverFile.value);
            body.image_mime = coverFile.value.type || 'image/jpeg';
        } else {
            body.image_url = aiImageUrl.value;
        }

        const res = await apiFetch('/admin/products/ai-suggest', {
            method: 'POST',
            body,
        });
        if (!res || res.status !== 'done' || !res.data) {
            aiMsg.value = 'AI không trả kết quả — thử lại sau.';
            return;
        }
        const d = applyResult(res);
        if (d.source === 'stub') {
            aiMsg.value = 'Dùng gợi ý offline' + (d.reason ? ' — ' + d.reason : '') + '.';
        } else {
            aiMsg.value = 'Đã sinh nội dung bằng ' + (d.model || ai.model) + (d.image_analyzed ? ' · đã nhìn ảnh' : '') + (aiApplied.value.length ? ' · điền: ' + aiApplied.value.join(', ') : '');
        }
        forceReanalyze.value = false;
    } catch (e) {
        aiMsg.value = e.message || 'AI đang bận/quá lâu — thử lại sau.';
    } finally {
        aiLoading.value = false;
        aiState.value = '';
    }
}

// ---------- Studio picker ----------
async function openPicker(forMode = 'gallery') {
    pickingFor.value = forMode;
    pickerOpen.value = true;
    pickerLoading.value = true;
    selImages.value = [];
    try {
        const data = await apiFetch('/admin/products/studio-images');
        studioImages.value = data.images || [];
    } catch (e) {
        studioImages.value = [];
    } finally {
        pickerLoading.value = false;
    }
}
function toggleSelImage(img) {
    const i = selImages.value.findIndex((x) => x.id === img.id);
    if (i >= 0) selImages.value.splice(i, 1);
    else selImages.value.push(img);
}
const isSelImage = (img) => selImages.value.some((x) => x.id === img.id);
function addStudioToGallery(urls) {
    for (const u of urls) {
        if (!u || gallery.value.some((g) => g.value === u)) continue;
        gallery.value.push({ key: 'st-' + u, kind: 'studio', url: u, value: u });
    }
}
function addSelectedImages() {
    if (pickingFor.value === 'cover') {
        const first = selImages.value[0];
        if (first?.url) {
            if (coverFilePreview.value) URL.revokeObjectURL(coverFilePreview.value);
            coverFile.value = null;
            coverFilePreview.value = '';
            coverUrl.value = first.url;
            removeCover.value = false;
        }
        pickerOpen.value = false;
        return;
    }
    addStudioToGallery(selImages.value.map((i) => i.url));
    selImages.value = [];
    pickerOpen.value = false;
}

// ---------- Cover ----------
function triggerCoverInput() { coverInput.value?.click(); }
function triggerGalleryInput() { galleryInput.value?.click(); }

function setCoverFile(f) {
    if (coverFilePreview.value) URL.revokeObjectURL(coverFilePreview.value);
    coverFile.value = f;
    coverFilePreview.value = URL.createObjectURL(f);
    coverUrl.value = '';
    removeCover.value = false;
}

function onCoverFile(e) {
    const f = e.target.files[0] || null;
    if (f) setCoverFile(f);
    e.target.value = '';
}

function onDropCover(e) {
    const f = e.dataTransfer?.files?.[0];
    if (f && (!f.type || f.type.startsWith('image/'))) setCoverFile(f);
}

function removeCoverImage() {
    if (coverFilePreview.value) URL.revokeObjectURL(coverFilePreview.value);
    coverFile.value = null;
    coverFilePreview.value = '';
    coverUrl.value = '';
    existingCover.value = null;
    removeCover.value = true;
}

// ---------- Gallery ----------
function addUploadFiles(files) {
    for (const f of files) {
        if (f.type && !f.type.startsWith('image/')) continue;
        gallery.value.push({ key: 'up-' + (++uploadSeq), kind: 'upload', url: URL.createObjectURL(f), file: f });
    }
}

function onGalleryFiles(e) {
    addUploadFiles(Array.from(e.target.files || []));
    e.target.value = '';
}

function onDropGallery(e) {
    addUploadFiles(Array.from(e.dataTransfer?.files || []));
}

function removeGalleryItem(key) {
    const idx = gallery.value.findIndex((g) => g.key === key);
    if (idx < 0) return;
    const g = gallery.value[idx];
    if (g.kind === 'upload' && g.url) URL.revokeObjectURL(g.url);
    gallery.value.splice(idx, 1);
}

let dragIndex = null;
function onDragStart(i) { dragIndex = i; }
function onReorderDrop(i) {
    if (dragIndex === null || dragIndex === i) { dragIndex = null; return; }
    const items = [...gallery.value];
    const [moved] = items.splice(dragIndex, 1);
    items.splice(i, 0, moved);
    gallery.value = items;
    dragIndex = null;
}

// ---------- Variants ----------
function addVariant() { variants.value.push({ name: '', sku: '', price: '', compare_price: '', stock: 0 }); }
function removeVariant(i) { variants.value.splice(i, 1); }

// ---------- Submit ----------
function buildFormData() {
    const fd = new FormData();
    fd.append('category_id', editor.category_id || '');
    fd.append('name', editor.name);
    fd.append('sku', editor.sku || '');
    fd.append('brand', editor.brand || '');
    fd.append('tags', editor.tags || '');
    fd.append('short_description', editor.short_description || '');
    fd.append('description', editor.description || '');
    fd.append('price', editor.price);
    fd.append('compare_price', editor.compare_price ?? '');
    fd.append('cost_price', editor.cost_price ?? '');
    fd.append('stock', editor.stock ?? 0);
    fd.append('featured', editor.featured ? '1' : '0');
    fd.append('is_active', editor.is_active ? '1' : '0');
    fd.append('meta_title', editor.meta_title || '');
    fd.append('meta_description', editor.meta_description || '');

    // Cover
    if (removeCover.value) fd.append('remove_image', '1');
    else if (coverFile.value) fd.append('image', coverFile.value);
    else if (coverUrl.value) fd.append('cover_url', coverUrl.value);

    // Gallery uploads (order matches gallery)
    const uploads = gallery.value.filter((g) => g.kind === 'upload');
    uploads.forEach((g) => fd.append('gallery[]', g.file));

    // Authoritative ordered gallery
    fd.append('gallery_managed', '1');
    gallery.value.forEach((g) => {
        const token = g.kind === 'upload' ? '__upload__' + uploads.indexOf(g) : g.value;
        fd.append('gallery_order[]', token);
    });

    // Variants
    variants.value.forEach((v, i) => {
        fd.append(`variants[${i}][name]`, v.name || '');
        fd.append(`variants[${i}][sku]`, v.sku || '');
        fd.append(`variants[${i}][price]`, v.price ?? '');
        fd.append(`variants[${i}][compare_price]`, v.compare_price ?? '');
        fd.append(`variants[${i}][stock]`, v.stock ?? 0);
    });
    fd.append('sync_variants', '1');

    return fd;
}

function firstError(data) {
    const errors = data?.errors;
    if (!errors) return null;
    const keys = Object.keys(errors);
    if (!keys.length) return null;
    const first = errors[keys[0]];
    return Array.isArray(first) ? first[0] : first;
}

async function submit() {
    if (!editor.name.trim()) { toast('Nhập tên sản phẩm.', 'error'); return; }
    if (editor.price === '' || editor.price === null) { toast('Nhập giá sản phẩm.', 'error'); return; }

    submitting.value = true;
    try {
        const fd = buildFormData();
        const url = editor.id ? '/admin/products/' + editor.id : '/admin/products';
        const method = editor.id ? 'PUT' : 'POST';
        const res = await fetch(url, {
            method,
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            body: fd,
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(firstError(data) || data.message || 'Đã có lỗi xảy ra.');
        toast(data.message || 'Đã lưu sản phẩm.');
        view.value = 'list';
        loadProducts(pagination.current_page);
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        submitting.value = false;
    }
}

// ---------- Boot ----------
onMounted(() => {
    if (boot.mode === 'edit' && boot.product) {
        hydrateEditor(boot.product);
        view.value = 'editor';
    } else if (boot.mode === 'create') {
        openCreate();
    }
});
</script>

<template>
  <div class="min-h-screen bg-[#f7f6f2] text-ink-900">
    <!-- ===================== HEADER ===================== -->
    <header class="sticky top-0 z-40 border-b border-ink-900/5 bg-white/85 backdrop-blur">
      <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
        <div class="flex min-w-0 items-center gap-3">
          <a href="/admin" class="flex shrink-0 items-center gap-2 text-ink-900">
            <span class="grid h-8 w-8 place-items-center rounded-lg bg-ink-900 text-sm font-bold text-white">T</span>
            <span class="hidden text-sm font-semibold sm:inline">Trillfa</span>
          </a>
          <span class="text-ink-300">/</span>
          <template v-if="view === 'editor'">
            <button type="button" @click="closeEditor" class="flex items-center gap-1 rounded-lg px-2 py-1 text-sm text-ink-500 transition hover:bg-cream-100 hover:text-ink-900">
              Sản phẩm
            </button>
            <span class="text-ink-300">/</span>
            <span class="truncate text-sm font-semibold text-ink-900">{{ editorTitle }}</span>
          </template>
          <template v-else>
            <span class="text-sm font-semibold text-ink-900">Sản phẩm</span>
          </template>
        </div>

        <div class="flex shrink-0 items-center gap-2">
          <template v-if="view === 'list'">
            <a href="/" target="_blank" class="hidden rounded-lg px-3 py-2 text-sm font-medium text-ink-500 transition hover:bg-cream-100 hover:text-ink-900 sm:inline-flex">Cửa hàng</a>
            <button type="button" @click="openCreate" class="inline-flex items-center justify-center gap-2 rounded-lg bg-ink-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-ink-800 active:scale-[.98]">
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
              Thêm sản phẩm
            </button>
          </template>
          <template v-else>
            <button type="button" @click="closeEditor" class="inline-flex items-center justify-center rounded-lg px-4 py-2.5 text-sm font-semibold text-ink-500 transition hover:bg-cream-100 hover:text-ink-900">Hủy</button>
            <button type="button" @click="submit" :disabled="submitting" class="inline-flex items-center justify-center gap-2 rounded-lg bg-ink-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-ink-800 disabled:cursor-not-allowed disabled:opacity-60">
              <span v-if="submitting" class="h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
              Lưu sản phẩm
            </button>
          </template>
        </div>
      </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6">

      <!-- ===================== LIST ===================== -->
      <div v-if="view === 'list'">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-900">Sản phẩm</h1>
            <p class="mt-1 text-sm text-ink-500">Quản lý toàn bộ danh mục sản phẩm của cửa hàng.</p>
          </div>
        </div>

        <!-- Toolbar -->
        <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div class="flex flex-1 flex-wrap items-center gap-2">
            <div class="relative w-full sm:max-w-xs">
              <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
              <input v-model="filters.q" @input="onSearchInput" placeholder="Tìm sản phẩm…" class="w-full rounded-lg border border-cream-200 bg-white py-2.5 pl-9 pr-3 text-sm text-ink-900 placeholder:text-ink-400 shadow-sm transition focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/10" />
            </div>
            <select v-model="filters.category_id" @change="onFilterChange" class="rounded-lg border border-cream-200 bg-white px-3 py-2.5 text-sm text-ink-700 shadow-sm transition focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/10">
              <option value="">Tất cả danh mục</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <select v-model="filters.status" @change="onFilterChange" class="rounded-lg border border-cream-200 bg-white px-3 py-2.5 text-sm text-ink-700 shadow-sm transition focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/10">
              <option value="">Mọi trạng thái</option>
              <option value="active">Đang bán</option>
              <option value="inactive">Ngừng bán</option>
            </select>
            <button v-if="hasFilters" type="button" @click="clearFilters" class="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-medium text-ink-500 transition hover:bg-cream-100 hover:text-ink-900">Xóa lọc</button>
          </div>
          <span class="text-sm text-ink-500">{{ pagination.total }} sản phẩm</span>
        </div>

        <!-- Table -->
        <div class="overflow-hidden rounded-2xl border border-ink-900/5 bg-white shadow-sm">
          <div v-if="listLoading" class="divide-y divide-cream-100">
            <div v-for="i in 6" :key="i" class="flex items-center gap-4 px-5 py-4">
              <div class="h-12 w-12 shrink-0 animate-pulse rounded-xl bg-cream-100"></div>
              <div class="flex-1 space-y-2"><div class="h-3 w-1/3 animate-pulse rounded bg-cream-100"></div><div class="h-2.5 w-1/5 animate-pulse rounded bg-cream-100"></div></div>
            </div>
          </div>

          <div v-else-if="!products.length" class="flex flex-col items-center justify-center px-6 py-20 text-center">
            <span class="grid h-14 w-14 place-items-center rounded-2xl bg-cream-100 text-ink-400">
              <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9"/></svg>
            </span>
            <h3 class="mt-4 text-base font-semibold text-ink-900">Chưa có sản phẩm nào</h3>
            <p class="mt-1 max-w-sm text-sm text-ink-500">Bắt đầu bằng cách thêm sản phẩm đầu tiên cho cửa hàng của bạn.</p>
            <button type="button" @click="openCreate" class="mt-5 inline-flex items-center gap-2 rounded-lg bg-ink-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-ink-800">
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
              Thêm sản phẩm
            </button>
          </div>

          <ul v-else class="divide-y divide-cream-100">
            <li v-for="p in products" :key="p.id" class="flex items-center gap-4 px-4 py-3 transition hover:bg-cream-50/70 sm:px-5">
              <img :src="p.image || PLACEHOLDER" alt="" class="h-12 w-12 shrink-0 rounded-xl border border-cream-200 bg-cream-50 object-cover" />
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                  <button type="button" @click="openEdit(p)" class="truncate text-left text-sm font-semibold text-ink-900 hover:text-brand-700">{{ p.name }}</button>
                  <span v-if="p.featured" class="text-amber-500" title="Nổi bật">★</span>
                </div>
                <p class="mt-0.5 truncate text-xs text-ink-500">{{ p.category || '—' }} · {{ p.sku || 'no-sku' }} · {{ p.variant_count }} biến thể</p>
              </div>
              <div class="hidden w-32 shrink-0 text-right md:block">
                <p class="text-sm font-semibold text-ink-900">{{ money(p.price) }}</p>
                <p v-if="p.compare_price" class="text-xs text-ink-400 line-through">{{ money(p.compare_price) }}</p>
              </div>
              <div class="hidden w-16 shrink-0 text-center sm:block">
                <span class="inline-flex min-w-[2rem] items-center justify-center rounded-full px-2 py-0.5 text-xs font-semibold" :class="stockClass(p.stock)">{{ p.stock }}</span>
              </div>
              <button type="button" @click="toggleActive(p)" class="hidden shrink-0 sm:inline-flex">
                <span class="relative h-5 w-9 rounded-full transition" :class="p.is_active ? 'bg-brand-600' : 'bg-cream-300'">
                  <span class="absolute top-0.5 h-4 w-4 rounded-full bg-white shadow transition-all" :class="p.is_active ? 'left-[18px]' : 'left-0.5'"></span>
                </span>
              </button>
              <div class="flex shrink-0 items-center gap-0.5">
                <button type="button" @click="viewProduct(p)" title="Xem" class="grid h-8 w-8 place-items-center rounded-lg text-ink-400 transition hover:bg-cream-100 hover:text-ink-900">
                  <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </button>
                <button type="button" @click="openEdit(p)" title="Sửa" class="grid h-8 w-8 place-items-center rounded-lg text-ink-400 transition hover:bg-cream-100 hover:text-ink-900">
                  <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                </button>
                <button type="button" @click="removeProduct(p)" title="Xóa" class="grid h-8 w-8 place-items-center rounded-lg text-ink-400 transition hover:bg-red-50 hover:text-red-600">
                  <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                </button>
              </div>
            </li>
          </ul>
        </div>

        <!-- Pagination -->
        <div v-if="pagination.last_page > 1" class="mt-5 flex items-center justify-between">
          <button type="button" @click="goToPage(pagination.current_page - 1)" :disabled="pagination.current_page <= 1" class="rounded-lg px-3 py-2 text-sm font-medium text-ink-500 transition hover:bg-cream-100 hover:text-ink-900 disabled:cursor-not-allowed disabled:opacity-40">← Trước</button>
          <div class="flex items-center gap-1">
            <button v-for="n in pageNumbers" :key="n" type="button" @click="goToPage(n)" class="grid h-8 w-8 place-items-center rounded-lg text-sm transition" :class="n === pagination.current_page ? 'bg-ink-900 font-semibold text-white' : 'text-ink-500 hover:bg-cream-100'">{{ n }}</button>
          </div>
          <button type="button" @click="goToPage(pagination.current_page + 1)" :disabled="pagination.current_page >= pagination.last_page" class="rounded-lg px-3 py-2 text-sm font-medium text-ink-500 transition hover:bg-cream-100 hover:text-ink-900 disabled:cursor-not-allowed disabled:opacity-40">Sau →</button>
        </div>
      </div>

      <!-- ===================== EDITOR ===================== -->
      <template v-else>
        <!-- Section nav -->
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-900">{{ editorTitle }}</h1>
            <p class="mt-1 text-sm text-ink-500">{{ editor.id ? 'Cập nhật thông tin sản phẩm.' : 'Tạo sản phẩm mới cho cửa hàng.' }}</p>
          </div>
          <nav class="flex items-center gap-1 rounded-xl border border-ink-900/5 bg-white p-1 shadow-sm">
            <button v-for="s in sections" :key="s.id" type="button" @click="scrollTo(s.id)" class="rounded-lg px-3 py-1.5 text-xs font-medium text-ink-500 transition hover:bg-cream-100 hover:text-ink-900">{{ s.label }}</button>
          </nav>
        </div>

        <form @submit.prevent="submit" class="grid gap-6 lg:grid-cols-[1fr_340px] lg:items-start">
          <!-- ============ Main column ============ -->
          <div class="space-y-6">
            <!-- AI helper -->
            <section class="rounded-2xl border border-brand-200/60 bg-gradient-to-r from-brand-50 to-cream-50 p-4">
              <div class="flex flex-wrap items-center gap-3">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-brand-600 text-white">
                  <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z"/></svg>
                </span>
                <div class="min-w-0 flex-1">
                  <p class="text-sm font-semibold text-ink-900">Gợi ý nội dung &amp; SEO bằng AI</p>
                  <p class="text-xs text-ink-500">Model chính: <span class="font-semibold text-ink-700">qwen3.8-flash</span> (đa phương thức — đọc ảnh + text). Chỉ đổi khi không khả dụng: {{ aiProvidersLabel }}.</p>
                </div>
                <input v-model="hint" placeholder="Ý tưởng / điểm nhấn…" class="w-full rounded-lg border border-cream-200 bg-white px-3 py-2 text-sm placeholder:text-ink-400 focus:border-brand-500 focus:outline-none sm:w-56" @keyup.enter="aiSuggest" />
                <label v-if="aiImageUrl" class="flex shrink-0 items-center gap-1.5 text-xs text-ink-600"><input type="checkbox" v-model="forceReanalyze" class="h-3.5 w-3.5 accent-brand-600" /> Phân tích lại ảnh</label>
                <button type="button" @click="aiSuggest" :disabled="aiLoading" class="inline-flex shrink-0 items-center gap-2 rounded-lg bg-ink-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-ink-800 disabled:opacity-60">
                  <span v-if="aiLoading" class="h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                  {{ aiState === 'analyzing' ? 'Đang phân tích…' : aiState === 'suggesting' ? 'Đang sinh…' : 'Gợi ý AI' }}
                </button>
              </div>
              <div v-if="aiImageUrl" class="mt-3 flex items-center gap-3">
                <img :src="aiImageUrl" alt="" class="h-10 w-10 rounded-lg object-cover" />
                <p class="text-xs text-ink-500">AI sẽ nhìn ảnh này{{ forceReanalyze ? ' (phân tích lại)' : ' (tái dùng phân tích cũ nếu ảnh không đổi)' }}.</p>
              </div>
              <p v-if="aiMsg" class="mt-2 w-full text-xs text-ink-600">{{ aiMsg }}</p>
              <div v-if="!ai.enabled" class="mt-3 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                <span>AI Sản phẩm đang bị tắt — bật lại trong <a href="/studio/settings" class="font-semibold underline">Cài đặt Studio</a>.</span>
              </div>
              <div v-else-if="!ai.has_keys" class="mt-3 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/></svg>
                <span>Chưa có API key (Qwen / Gemini / DeepSeek) — AI sẽ dùng gợi ý offline. Thêm key tại <a href="/studio/settings" class="font-semibold underline">Cài đặt Studio</a>.</span>
              </div>
            </section>

            <!-- Info -->
            <section id="sec-info" class="scroll-mt-28 rounded-2xl border border-ink-900/5 bg-white p-5 shadow-sm sm:p-6">
              <h2 class="text-sm font-semibold text-ink-900">Thông tin sản phẩm</h2>
              <div class="mt-4 space-y-4">
                <div>
                  <label class="mb-1.5 block text-xs font-medium text-ink-500">Tên sản phẩm <span class="text-red-500">*</span></label>
                  <input v-model="editor.name" class="w-full rounded-lg border border-cream-200 bg-white px-3.5 py-2.5 text-sm text-ink-900 placeholder:text-ink-400 shadow-sm transition focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/10" placeholder="vd: Áo sơ mi linen oversize" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                  <div>
                    <label class="mb-1.5 block text-xs font-medium text-ink-500">Danh mục</label>
                    <select v-model="editor.category_id" class="w-full rounded-lg border border-cream-200 bg-white px-3.5 py-2.5 text-sm text-ink-900 shadow-sm transition focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/10">
                      <option value="">— Chọn —</option>
                      <template v-for="c in categories" :key="c.id">
                        <option :value="c.id">{{ c.name }}</option>
                        <option v-for="ch in c.children" :key="ch.id" :value="ch.id">&nbsp;&nbsp;— {{ ch.name }}</option>
                      </template>
                    </select>
                  </div>
                  <div>
                    <label class="mb-1.5 block text-xs font-medium text-ink-500">Thương hiệu</label>
                    <input v-model="editor.brand" class="w-full rounded-lg border border-cream-200 bg-white px-3.5 py-2.5 text-sm text-ink-900 placeholder:text-ink-400 shadow-sm transition focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/10" />
                  </div>
                  <div>
                    <label class="mb-1.5 block text-xs font-medium text-ink-500">SKU / Mã</label>
                    <input v-model="editor.sku" class="w-full rounded-lg border border-cream-200 bg-white px-3.5 py-2.5 text-sm text-ink-900 placeholder:text-ink-400 shadow-sm transition focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/10" />
                  </div>
                  <div>
                    <label class="mb-1.5 block text-xs font-medium text-ink-500">Thẻ (phân cách phẩy)</label>
                    <input v-model="editor.tags" class="w-full rounded-lg border border-cream-200 bg-white px-3.5 py-2.5 text-sm text-ink-900 placeholder:text-ink-400 shadow-sm transition focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/10" />
                  </div>
                </div>
                <div>
                  <label class="mb-1.5 block text-xs font-medium text-ink-500">Mô tả ngắn</label>
                  <textarea v-model="editor.short_description" rows="2" class="w-full rounded-lg border border-cream-200 bg-white px-3.5 py-2.5 text-sm text-ink-900 placeholder:text-ink-400 shadow-sm transition focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/10"></textarea>
                </div>
                <div>
                  <label class="mb-1.5 block text-xs font-medium text-ink-500">Mô tả chi tiết (HTML)</label>
                  <textarea v-model="editor.description" rows="8" class="w-full rounded-lg border border-cream-200 bg-white px-3.5 py-2.5 font-mono text-xs text-ink-900 placeholder:text-ink-400 shadow-sm transition focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/10"></textarea>
                </div>
              </div>
            </section>

            <!-- Images -->
            <section id="sec-images" class="scroll-mt-28 rounded-2xl border border-ink-900/5 bg-white p-5 shadow-sm sm:p-6">
              <div class="flex items-center justify-between">
                <div>
                  <h2 class="text-sm font-semibold text-ink-900">Hình ảnh sản phẩm</h2>
                  <p class="mt-0.5 text-xs text-ink-500">Kéo thả để sắp xếp thứ tự. Ảnh bìa là hình hiển thị chính.</p>
                </div>
              </div>

              <!-- Cover -->
              <div class="mt-5">
                <span class="text-xs font-medium text-ink-600">Ảnh bìa</span>
                <div
                  class="relative mt-2 aspect-square w-full max-w-[260px] cursor-pointer overflow-hidden rounded-2xl border-2 border-dashed border-cream-300 bg-cream-50 transition"
                  :class="coverPreview ? 'border-transparent' : 'hover:border-brand-400 hover:bg-brand-50/40'"
                  @click="triggerCoverInput"
                  @dragover.prevent
                  @drop.prevent="onDropCover"
                >
                  <input ref="coverInput" type="file" accept="image/*" class="hidden" @change="onCoverFile" />
                  <template v-if="coverPreview">
                    <img :src="coverPreview" alt="" class="h-full w-full object-cover" />
                    <div class="absolute inset-0 flex items-center justify-center gap-2 bg-ink-900/55 opacity-0 transition hover:opacity-100">
                      <button type="button" @click.stop="triggerCoverInput" class="rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-ink-900 shadow hover:bg-cream-100">Đổi ảnh</button>
                      <button type="button" @click.stop="removeCoverImage" class="rounded-lg bg-white/90 px-3 py-1.5 text-xs font-semibold text-red-600 shadow hover:bg-red-50">Xóa</button>
                    </div>
                    <span v-if="coverFile" class="absolute left-2 top-2 rounded-md bg-brand-600 px-2 py-0.5 text-[11px] font-semibold text-white">Mới</span>
                  </template>
                  <template v-else>
                    <div class="flex h-full flex-col items-center justify-center gap-2 px-4 text-center">
                      <span class="grid h-11 w-11 place-items-center rounded-full bg-white text-ink-400 shadow-sm">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                      </span>
                      <p class="text-sm font-medium text-ink-600">Tải ảnh lên hoặc kéo thả</p>
                      <p class="text-xs text-ink-400">PNG, JPG, WEBP · tối đa 4MB</p>
                    </div>
                  </template>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                  <button type="button" @click="triggerCoverInput" class="inline-flex items-center gap-1.5 rounded-lg border border-cream-200 bg-white px-3 py-1.5 text-xs font-semibold text-ink-700 shadow-sm transition hover:border-ink-900 hover:text-ink-900">
                    <svg class="h-4 w-4 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                    Tải ảnh lên
                  </button>
                  <button type="button" @click="openPicker('cover')" class="inline-flex items-center gap-1.5 rounded-lg border border-brand-200 bg-brand-50 px-3 py-1.5 text-xs font-semibold text-brand-700 shadow-sm transition hover:border-brand-400 hover:bg-brand-100">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z"/></svg>
                    Chọn từ Studio
                  </button>
                </div>
              </div>

              <!-- Gallery -->
              <div class="mt-6">
                <div class="flex items-center justify-between">
                  <span class="text-xs font-medium text-ink-600">Thư viện ảnh</span>
                  <span class="text-xs text-ink-400">{{ gallery.length }} ảnh</span>
                </div>
                <div class="mt-2 grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-5" @dragover.prevent @drop.prevent="onDropGallery">
                  <div
                    v-for="(g, i) in gallery"
                    :key="g.key"
                    draggable="true"
                    @dragstart="onDragStart(i)"
                    @dragover.prevent
                    @drop="onReorderDrop(i)"
                    class="group relative aspect-square cursor-grab overflow-hidden rounded-xl border border-cream-200 bg-cream-50 active:cursor-grabbing"
                  >
                    <img :src="g.url" alt="" class="h-full w-full object-cover" />
                    <div class="absolute inset-0 flex items-start justify-between bg-ink-900/15 p-1.5 opacity-0 transition group-hover:opacity-100">
                      <span class="grid h-6 w-6 cursor-grab place-items-center rounded-md bg-white/95 text-ink-500 shadow-sm" title="Kéo để sắp xếp">
                        <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M7 2a2 2 0 11-4 0 2 2 0 014 0zm0 8a2 2 0 11-4 0 2 2 0 014 0zm0 8a2 2 0 11-4 0 2 2 0 014 0zM13 2a2 2 0 11-4 0 2 2 0 014 0zm0 8a2 2 0 11-4 0 2 2 0 014 0zm0 8a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                      </span>
                      <button type="button" @click.stop="removeGalleryItem(g.key)" class="grid h-6 w-6 place-items-center rounded-md bg-white/95 text-red-600 shadow-sm transition hover:bg-red-600 hover:text-white" title="Xóa">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                      </button>
                    </div>
                    <span v-if="g.kind === 'upload'" class="absolute bottom-1.5 left-1.5 rounded-md bg-brand-600 px-1.5 py-0.5 text-[10px] font-semibold text-white">Mới</span>
                  </div>

                  <button type="button" @click="triggerGalleryInput" class="flex aspect-square flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-cream-300 text-ink-400 transition hover:border-brand-400 hover:text-brand-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    <span class="text-xs font-medium">Thêm ảnh</span>
                  </button>
                </div>
                <input ref="galleryInput" type="file" accept="image/*" multiple class="hidden" @change="onGalleryFiles" />
                <button type="button" @click="openPicker('gallery')" class="mt-3 inline-flex items-center gap-1.5 rounded-lg border border-brand-200 bg-brand-50 px-3 py-1.5 text-xs font-semibold text-brand-700 shadow-sm transition hover:border-brand-400 hover:bg-brand-100">
                  <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z"/></svg>
                  Chọn từ Thư viện Studio
                </button>
              </div>
            </section>

            <!-- Variants -->
            <section id="sec-variants" class="scroll-mt-28 rounded-2xl border border-ink-900/5 bg-white p-5 shadow-sm sm:p-6">
              <div class="flex items-center justify-between">
                <div>
                  <h2 class="text-sm font-semibold text-ink-900">Phân loại / Biến thể</h2>
                  <p class="mt-0.5 text-xs text-ink-500">Ví dụ: S / M / L hoặc Đen / Trắng.</p>
                </div>
                <button type="button" @click="addVariant" class="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-medium text-brand-700 transition hover:bg-brand-50">+ Thêm</button>
              </div>
              <div v-if="variants.length" class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                  <thead>
                    <tr class="border-b border-cream-200 text-left text-xs font-medium text-ink-400">
                      <th class="py-2 pr-3">Tên</th>
                      <th class="py-2 pr-3">SKU</th>
                      <th class="py-2 pr-3">Giá</th>
                      <th class="py-2 pr-3">Giá cũ</th>
                      <th class="py-2 pr-3">Tồn kho</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(v, i) in variants" :key="i" class="border-b border-cream-100 last:border-0">
                      <td class="py-2 pr-3"><input v-model="v.name" class="w-full rounded-lg border border-cream-200 bg-white px-3 py-1.5 text-sm transition focus:border-brand-500 focus:outline-none" placeholder="S / M / L" /></td>
                      <td class="py-2 pr-3"><input v-model="v.sku" class="w-full rounded-lg border border-cream-200 bg-white px-3 py-1.5 text-sm transition focus:border-brand-500 focus:outline-none" /></td>
                      <td class="py-2 pr-3"><input v-model="v.price" type="number" step="0.01" class="w-24 rounded-lg border border-cream-200 bg-white px-3 py-1.5 text-sm transition focus:border-brand-500 focus:outline-none" /></td>
                      <td class="py-2 pr-3"><input v-model="v.compare_price" type="number" step="0.01" class="w-24 rounded-lg border border-cream-200 bg-white px-3 py-1.5 text-sm transition focus:border-brand-500 focus:outline-none" /></td>
                      <td class="py-2 pr-3"><input v-model.number="v.stock" type="number" class="w-20 rounded-lg border border-cream-200 bg-white px-3 py-1.5 text-sm transition focus:border-brand-500 focus:outline-none" /></td>
                      <td class="py-2 text-right"><button type="button" @click="removeVariant(i)" class="grid h-7 w-7 place-items-center rounded-lg text-ink-400 transition hover:bg-red-50 hover:text-red-600">✕</button></td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <p v-else class="mt-3 text-sm text-ink-500">Không có biến thể — giá sản phẩm dùng làm giá mặc định.</p>
            </section>

            <!-- SEO -->
            <section id="sec-seo" class="scroll-mt-28 rounded-2xl border border-ink-900/5 bg-white p-5 shadow-sm sm:p-6">
              <h2 class="text-sm font-semibold text-ink-900">SEO</h2>
              <div class="mt-4 space-y-4">
                <div>
                  <label class="mb-1.5 block text-xs font-medium text-ink-500">Meta title</label>
                  <input v-model="editor.meta_title" class="w-full rounded-lg border border-cream-200 bg-white px-3.5 py-2.5 text-sm text-ink-900 shadow-sm transition focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/10" />
                  <p class="mt-1 text-xs text-ink-400">{{ editor.meta_title.length }}/60</p>
                </div>
                <div>
                  <label class="mb-1.5 block text-xs font-medium text-ink-500">Meta description</label>
                  <textarea v-model="editor.meta_description" rows="2" class="w-full rounded-lg border border-cream-200 bg-white px-3.5 py-2.5 text-sm text-ink-900 shadow-sm transition focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/10"></textarea>
                  <p class="mt-1 text-xs text-ink-400">{{ editor.meta_description.length }}/160</p>
                </div>
              </div>
            </section>
          </div>

          <!-- ============ Aside ============ -->
          <aside class="space-y-6 lg:sticky lg:top-24">
            <!-- Summary -->
            <section class="rounded-2xl border border-ink-900/5 bg-white p-5 shadow-sm">
              <div class="flex items-center gap-3">
                <img v-if="coverPreview" :src="coverPreview" alt="" class="h-14 w-14 rounded-xl border border-cream-200 bg-cream-50 object-cover" />
                <div v-else class="h-14 w-14 rounded-xl border border-cream-200 bg-cream-50"></div>
                <div class="min-w-0">
                  <p class="truncate text-sm font-semibold text-ink-900">{{ editor.name || 'Sản phẩm chưa đặt tên' }}</p>
                  <p class="text-sm font-medium text-brand-700">{{ editor.price ? money(editor.price) : 'Chưa có giá' }}</p>
                </div>
              </div>
              <div class="mt-4 flex items-center justify-between rounded-xl bg-cream-50 px-3 py-2.5">
                <span class="text-xs font-medium text-ink-500">Trạng thái</span>
                <button type="button" @click="editor.is_active = !editor.is_active" class="flex items-center gap-2">
                  <span class="text-xs font-semibold" :class="editor.is_active ? 'text-brand-700' : 'text-ink-400'">{{ editor.is_active ? 'Đang bán' : 'Ngừng bán' }}</span>
                  <span class="relative h-5 w-9 rounded-full transition" :class="editor.is_active ? 'bg-brand-600' : 'bg-cream-300'">
                    <span class="absolute top-0.5 h-4 w-4 rounded-full bg-white shadow transition-all" :class="editor.is_active ? 'left-[18px]' : 'left-0.5'"></span>
                  </span>
                </button>
              </div>
              <label class="mt-4 flex cursor-pointer items-center gap-2.5 text-sm text-ink-700">
                <input type="checkbox" v-model="editor.featured" class="h-4 w-4 accent-brand-600" />
                Sản phẩm nổi bật
              </label>
            </section>

            <!-- Pricing -->
            <section class="rounded-2xl border border-ink-900/5 bg-white p-5 shadow-sm">
              <h2 class="text-sm font-semibold text-ink-900">Giá &amp; Kho</h2>
              <div class="mt-4 space-y-4">
                <div>
                  <label class="mb-1.5 block text-xs font-medium text-ink-500">Giá bán <span class="text-red-500">*</span></label>
                  <div class="relative">
                    <input v-model="editor.price" type="number" step="0.01" class="w-full rounded-lg border border-cream-200 bg-white px-3.5 py-2.5 text-sm text-ink-900 shadow-sm transition focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/10" />
                    <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs text-ink-400">₫</span>
                  </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="mb-1.5 block text-xs font-medium text-ink-500">Giá cũ</label>
                    <input v-model="editor.compare_price" type="number" step="0.01" class="w-full rounded-lg border border-cream-200 bg-white px-3.5 py-2.5 text-sm text-ink-900 shadow-sm transition focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/10" />
                  </div>
                  <div>
                    <label class="mb-1.5 block text-xs font-medium text-ink-500">Giá vốn</label>
                    <input v-model="editor.cost_price" type="number" step="0.01" class="w-full rounded-lg border border-cream-200 bg-white px-3.5 py-2.5 text-sm text-ink-900 shadow-sm transition focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/10" />
                  </div>
                </div>
                <div>
                  <label class="mb-1.5 block text-xs font-medium text-ink-500">Tồn kho</label>
                  <input v-model.number="editor.stock" type="number" class="w-full rounded-lg border border-cream-200 bg-white px-3.5 py-2.5 text-sm text-ink-900 shadow-sm transition focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/10" />
                </div>
              </div>
            </section>

            <button type="submit" :disabled="submitting" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-ink-900 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-ink-800 disabled:cursor-not-allowed disabled:opacity-60">
              <span v-if="submitting" class="h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
              {{ editor.id ? 'Cập nhật sản phẩm' : 'Tạo sản phẩm' }}
            </button>
          </aside>
        </form>
      </template>
    </main>

    <!-- Studio image picker modal -->
    <Teleport to="body">
      <Transition name="fade">
        <div v-if="pickerOpen" class="fixed inset-0 z-[95] flex items-center justify-center p-4">
          <div class="absolute inset-0 bg-ink-900/50 backdrop-blur-sm" @click="pickerOpen = false"></div>
          <div class="relative w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-cream-200 px-4 py-3">
              <h3 class="text-base font-semibold text-ink-900">{{ pickingFor === 'cover' ? 'Chọn ảnh đại diện' : 'Chọn ảnh cho bộ sưu tập' }}</h3>
              <button type="button" @click="pickerOpen = false" class="grid h-8 w-8 place-items-center rounded-lg text-ink-400 transition hover:bg-cream-100 hover:text-ink-900">✕</button>
            </div>
            <div class="flex items-center justify-between gap-2 border-b border-cream-100 px-4 py-2">
              <span class="text-xs text-ink-500">{{ pickingFor === 'cover' ? 'Chọn 1 ảnh làm đại diện' : 'Đã chọn ' + selImages.length + ' ảnh' }}</span>
              <button type="button" @click="addSelectedImages" :disabled="!selImages.length" class="inline-flex items-center rounded-lg bg-ink-900 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-ink-800 disabled:opacity-40">{{ pickingFor === 'cover' ? 'Dùng làm đại diện' : 'Thêm ' + selImages.length + ' vào sản phẩm' }}</button>
            </div>
            <div class="max-h-[64vh] overflow-y-auto p-4">
              <div v-if="pickerLoading" class="grid grid-cols-3 gap-2">
                <div v-for="i in 9" :key="i" class="aspect-square animate-pulse rounded-xl bg-cream-100"></div>
              </div>
              <div v-else-if="studioImages.length" class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                <button v-for="img in studioImages" :key="img.id" type="button" @click="toggleSelImage(img)" class="group relative aspect-square overflow-hidden rounded-xl border transition" :class="isSelImage(img) ? 'border-brand-500 ring-2 ring-brand-500/30' : 'border-cream-200 hover:border-brand-400'">
                  <img :src="img.url" :alt="img.label" class="h-full w-full object-cover" loading="lazy" />
                  <span class="absolute inset-0 grid place-items-center text-white transition" :class="isSelImage(img) ? 'bg-brand-600/30' : 'bg-black/30 opacity-0 group-hover:opacity-100'">{{ isSelImage(img) ? '✓' : '＋' }}</span>
                </button>
              </div>
              <div v-else class="py-16 text-center text-sm text-ink-500">Thư viện Studio chưa có ảnh.</div>
            </div>
            <div class="flex justify-end gap-2 border-t border-cream-200 px-4 py-3">
              <button type="button" @click="pickerOpen = false" class="rounded-lg px-4 py-2 text-sm font-medium text-ink-500 transition hover:bg-cream-100">Đóng</button>
              <button type="button" @click="addSelectedImages" :disabled="!selImages.length" class="rounded-lg bg-ink-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-ink-800 disabled:opacity-40">Xong</button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Toasts -->
    <div class="pointer-events-none fixed bottom-5 left-1/2 z-[120] flex w-full max-w-sm -translate-x-1/2 flex-col gap-2 px-4">
      <TransitionGroup name="toast">
        <div v-for="t in toasts" :key="t.id" class="pointer-events-auto flex items-center gap-3 rounded-xl border bg-white px-4 py-3 shadow-xl" :class="t.type === 'error' ? 'border-red-200' : 'border-brand-200'">
          <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full text-xs" :class="t.type === 'error' ? 'bg-red-100 text-red-600' : 'bg-brand-100 text-brand-700'">{{ t.type === 'error' ? '!' : '✓' }}</span>
          <p class="flex-1 text-sm font-medium text-ink-900">{{ t.message }}</p>
        </div>
      </TransitionGroup>
    </div>
  </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.15s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.toast-enter-active, .toast-leave-active { transition: all 0.25s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(8px); }
</style>
