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
    setTimeout(() => { toasts.value = toasts.value.filter((t) => t.id !== id); }, 3500);
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
    if (stock > 5) return 'bg-brand-100 text-brand-700';
    if (stock > 0) return 'bg-amber-100 text-amber-700';
    return 'bg-red-100 text-red-600';
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
        // If the last item on a page was removed, step back one page.
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

const existingImage = ref(null);
const existingGallery = ref([]);
const galleryRemove = ref([]);

const coverFile = ref(null);
const coverFilePreview = ref('');
const coverUrl = ref('');
const galleryUpload = ref([]); // [{ file, url }]
const studioGallery = ref([]);
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
const aiImageUrl = computed(() => coverUrl.value || existingImage.value || studioGallery.value[0] || '');
const coverPreview = computed(() => coverFilePreview.value || coverUrl.value || existingImage.value || '');

function resetEditor() {
    Object.assign(editor, {
        id: null, name: '', category_id: '', brand: '', sku: '', tags: '',
        short_description: '', description: '', price: '', compare_price: '', cost_price: '',
        stock: 0, featured: false, is_active: true, meta_title: '', meta_description: '',
    });
    existingImage.value = null;
    existingGallery.value = [];
    galleryRemove.value = [];
    if (coverFilePreview.value) URL.revokeObjectURL(coverFilePreview.value);
    coverFile.value = null;
    coverFilePreview.value = '';
    coverUrl.value = '';
    galleryUpload.value.forEach((g) => URL.revokeObjectURL(g.url));
    galleryUpload.value = [];
    studioGallery.value = [];
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
    existingImage.value = p.image || null;
    existingGallery.value = Array.isArray(p.gallery) ? p.gallery : [];
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
    if (d.suggested_name) editor.name = d.suggested_name;
    if (d.brand) editor.brand = d.brand;
    if (d.short_description) editor.short_description = d.short_description;
    if (d.description) editor.description = d.description;
    if (d.meta_title) editor.meta_title = d.meta_title;
    if (d.meta_description) editor.meta_description = d.meta_description;
    if (Array.isArray(d.tags) && d.tags.length) editor.tags = d.tags.join(', ');
    return d;
}

function catName() {
    const c = categories.find((x) => x.id == editor.category_id);
    return c?.name || '';
}

async function aiSuggest() {
    aiLoading.value = true;
    aiMsg.value = '';
    aiState.value = aiImageUrl.value ? 'analyzing' : 'suggesting';
    try {
        const res = await apiFetch('/admin/products/ai-suggest', {
            method: 'POST',
            body: {
                name: editor.name,
                category: catName(),
                brand: editor.brand,
                hint: hint.value,
                short_description: editor.short_description,
                image_url: aiImageUrl.value,
                force: forceReanalyze.value,
            },
        });
        if (!res || res.status !== 'done' || !res.data) {
            aiMsg.value = 'AI không trả kết quả — thử lại sau.';
            return;
        }
        const d = applyResult(res);
        if (d.source === 'stub') {
            aiMsg.value = 'Dùng gợi ý offline' + (d.reason ? ' — ' + d.reason : '') + '.';
        } else {
            aiMsg.value = 'Đã làm giàu bằng ' + (d.model || ai.model) + (d.image_analyzed ? ' · nhìn ảnh' : '');
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
function addSelectedImages() {
    if (pickingFor.value === 'cover') {
        if (selImages.value[0]?.url) {
            coverUrl.value = selImages.value[0].url;
            coverFile.value = null;
            if (coverFilePreview.value) { URL.revokeObjectURL(coverFilePreview.value); coverFilePreview.value = ''; }
        }
        pickerOpen.value = false;
        return;
    }
    for (const img of selImages.value) {
        if (img.url && !studioGallery.value.includes(img.url)) studioGallery.value.push(img.url);
    }
    selImages.value = [];
    pickerOpen.value = false;
}
function removeStudioImage(url) { studioGallery.value = studioGallery.value.filter((u) => u !== url); }

// ---------- Files ----------
function onCoverFile(e) {
    const f = e.target.files[0] || null;
    if (coverFilePreview.value) URL.revokeObjectURL(coverFilePreview.value);
    coverFile.value = f;
    coverFilePreview.value = f ? URL.createObjectURL(f) : '';
}
function onGalleryFiles(e) {
    const files = Array.from(e.target.files || []);
    for (const f of files) {
        if (f.type && !f.type.startsWith('image/')) continue;
        galleryUpload.value.push({ file: f, url: URL.createObjectURL(f) });
    }
    e.target.value = '';
}
function removeUploadedGallery(i) {
    URL.revokeObjectURL(galleryUpload.value[i].url);
    galleryUpload.value.splice(i, 1);
}
function removeExistingGallery(url) {
    existingGallery.value = existingGallery.value.filter((u) => u !== url);
    galleryRemove.value.push(url);
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

    if (coverFile.value) fd.append('image', coverFile.value);
    else if (coverUrl.value) fd.append('cover_url', coverUrl.value);

    for (const g of galleryUpload.value) fd.append('gallery[]', g.file);
    for (const u of studioGallery.value) fd.append('studio_gallery[]', u);
    for (const u of galleryRemove.value) fd.append('gallery_remove[]', u);

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
  <div class="min-h-screen">
    <!-- Header -->
    <header class="sticky top-0 z-30 border-b border-cream-200 bg-white/90 backdrop-blur">
      <div class="mx-auto flex h-14 max-w-6xl items-center justify-between gap-3 px-4 sm:px-6">
        <div class="flex min-w-0 items-center gap-3">
          <button v-if="view === 'editor'" type="button" @click="closeEditor" class="btn-ghost !p-2 text-ink-500" aria-label="Quay lại">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
          </button>
          <h1 class="truncate text-base font-semibold text-ink-900">{{ view === 'editor' ? editorTitle : 'Sản phẩm' }}</h1>
        </div>
        <div class="flex shrink-0 items-center gap-2">
          <a href="/admin" class="btn-ghost btn-sm text-ink-500">← Quản trị</a>
          <button v-if="view === 'list'" type="button" @click="openCreate" class="btn-brand btn-sm">+ Thêm sản phẩm</button>
        </div>
      </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-6 sm:px-6">

      <!-- ===================== LIST ===================== -->
      <div v-if="view === 'list'">
        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div class="flex flex-1 flex-wrap items-center gap-2">
            <input v-model="filters.q" @input="onSearchInput" placeholder="Tìm sản phẩm…" class="input !w-full !py-2.5 sm:max-w-xs" />
            <select v-model="filters.category_id" @change="onFilterChange" class="input !w-auto !py-2.5">
              <option value="">Tất cả danh mục</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <select v-model="filters.status" @change="onFilterChange" class="input !w-auto !py-2.5">
              <option value="">Mọi trạng thái</option>
              <option value="active">Đang bán</option>
              <option value="inactive">Ngừng bán</option>
            </select>
            <button v-if="hasFilters" type="button" @click="clearFilters" class="btn-ghost btn-sm text-ink-500">Xóa lọc</button>
          </div>
          <span class="text-sm text-ink-500">{{ pagination.total }} sản phẩm</span>
        </div>

        <div class="overflow-hidden rounded-2xl border border-cream-200 bg-white">
          <div v-if="listLoading" class="divide-y divide-cream-100">
            <div v-for="i in 6" :key="i" class="h-16 animate-pulse bg-cream-100/70"></div>
          </div>
          <p v-else-if="!products.length" class="py-20 text-center text-sm text-ink-500">Chưa có sản phẩm nào.</p>
          <ul v-else class="divide-y divide-cream-100">
            <li v-for="p in products" :key="p.id" class="flex items-center gap-4 px-4 py-3 transition hover:bg-cream-50">
              <img :src="p.image || PLACEHOLDER" alt="" class="h-11 w-11 shrink-0 rounded-lg bg-cream-100 object-cover" />
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                  <p class="truncate font-medium text-ink-900">{{ p.name }}</p>
                  <span v-if="p.featured" class="text-amber-500" title="Nổi bật">★</span>
                </div>
                <p class="truncate text-xs text-ink-500">{{ p.category || '—' }} · {{ p.sku || 'no-sku' }} · {{ p.variant_count }} biến thể</p>
              </div>
              <div class="hidden w-32 shrink-0 text-right sm:block">
                <p class="font-medium text-ink-900">{{ money(p.price) }}</p>
                <p v-if="p.compare_price" class="text-xs text-ink-400 line-through">{{ money(p.compare_price) }}</p>
              </div>
              <div class="hidden w-20 shrink-0 text-center md:block">
                <span class="badge" :class="stockClass(p.stock)">{{ p.stock }}</span>
              </div>
              <button type="button" @click="toggleActive(p)" class="badge shrink-0" :class="p.is_active ? 'bg-brand-600 text-white' : 'bg-cream-200 text-ink-500'">
                {{ p.is_active ? 'Đang bán' : 'Ngừng bán' }}
              </button>
              <div class="flex shrink-0 items-center gap-0.5">
                <button type="button" @click="viewProduct(p)" title="Xem" class="btn-ghost !p-2 text-ink-500">
                  <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </button>
                <button type="button" @click="openEdit(p)" title="Sửa" class="btn-ghost !p-2 text-ink-500">
                  <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                </button>
                <button type="button" @click="removeProduct(p)" title="Xóa" class="btn-ghost !p-2 text-red-500">
                  <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                </button>
              </div>
            </li>
          </ul>
        </div>

        <div v-if="pagination.last_page > 1" class="mt-5 flex items-center justify-between">
          <button type="button" @click="goToPage(pagination.current_page - 1)" :disabled="pagination.current_page <= 1" class="btn-ghost btn-sm">← Trước</button>
          <div class="flex items-center gap-1">
            <button v-for="n in pageNumbers" :key="n" type="button" @click="goToPage(n)" class="grid h-8 w-8 place-items-center rounded-lg text-sm transition" :class="n === pagination.current_page ? 'bg-ink-900 font-semibold text-white' : 'text-ink-500 hover:bg-cream-200'">{{ n }}</button>
          </div>
          <button type="button" @click="goToPage(pagination.current_page + 1)" :disabled="pagination.current_page >= pagination.last_page" class="btn-ghost btn-sm">Sau →</button>
        </div>
      </div>

      <!-- ===================== EDITOR ===================== -->
      <form v-else @submit.prevent="submit" class="grid gap-6 lg:grid-cols-[1fr_320px]">
        <!-- Main column -->
        <div class="space-y-6">
          <!-- AI helper -->
          <section class="rounded-xl border border-cream-200 bg-white p-4">
            <div class="flex flex-wrap items-center gap-3">
              <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-ink-900">Gợi ý nội dung &amp; SEO bằng AI</p>
                <p class="text-xs text-ink-500">Model {{ ai.model }} ({{ ai.provider }}) — nhìn ảnh, phân tích, làm giàu nội dung.</p>
              </div>
              <input v-model="hint" placeholder="Ý tưởng / điểm nhấn…" class="input !py-2 sm:w-64" @keyup.enter="aiSuggest" />
              <label v-if="aiImageUrl" class="flex items-center gap-2 text-xs text-ink-600"><input type="checkbox" v-model="forceReanalyze" class="h-4 w-4 accent-brand-600" /> Phân tích lại ảnh</label>
              <button type="button" @click="aiSuggest" :disabled="aiLoading" class="btn-brand !py-2.5">
                {{ aiState === 'analyzing' ? '🔍 Đang phân tích ảnh…' : aiState === 'suggesting' ? '✨ Đang sinh…' : '✨ Gợi ý AI' }}
              </button>
            </div>
            <div v-if="aiImageUrl" class="mt-3 flex items-center gap-3">
              <img :src="aiImageUrl" alt="" class="h-12 w-12 rounded-lg object-cover" />
              <p class="text-xs text-ink-500">AI sẽ nhìn ảnh này{{ forceReanalyze ? ' (phân tích lại)' : ' (tái dùng phân tích cũ nếu ảnh không đổi)' }}.</p>
            </div>
            <p v-if="aiMsg" class="mt-2 w-full text-xs text-ink-500">{{ aiMsg }}</p>
          </section>

          <!-- Basic info -->
          <section class="rounded-xl border border-cream-200 bg-white p-5">
            <h2 class="mb-4 text-sm font-semibold text-ink-900">Thông tin sản phẩm</h2>
            <div class="space-y-4">
              <div><label class="label">Tên sản phẩm *</label><input v-model="editor.name" class="input" /></div>
              <div class="grid gap-4 sm:grid-cols-2">
                <div>
                  <label class="label">Danh mục</label>
                  <select v-model="editor.category_id" class="input">
                    <option value="">— Chọn —</option>
                    <template v-for="c in categories" :key="c.id">
                      <option :value="c.id">{{ c.name }}</option>
                      <option v-for="ch in c.children" :key="ch.id" :value="ch.id">— {{ ch.name }}</option>
                    </template>
                  </select>
                </div>
                <div><label class="label">Thương hiệu</label><input v-model="editor.brand" class="input" /></div>
                <div><label class="label">SKU / Mã</label><input v-model="editor.sku" class="input" /></div>
                <div><label class="label">Thẻ (phân cách phẩy)</label><input v-model="editor.tags" class="input" /></div>
              </div>
              <div><label class="label">Mô tả ngắn</label><textarea v-model="editor.short_description" rows="2" class="input"></textarea></div>
              <div><label class="label">Mô tả chi tiết (HTML)</label><textarea v-model="editor.description" rows="8" class="input font-mono text-xs"></textarea></div>
            </div>
          </section>

          <!-- Variants -->
          <section class="rounded-xl border border-cream-200 bg-white p-5">
            <div class="mb-4 flex items-center justify-between">
              <h2 class="text-sm font-semibold text-ink-900">Phân loại / Biến thể</h2>
              <button type="button" @click="addVariant" class="btn-ghost !p-2 text-brand-700">+ Thêm</button>
            </div>
            <div v-if="variants.length" class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr class="border-b border-cream-200 text-left text-xs uppercase text-ink-500">
                    <th class="py-2 pr-3 font-medium">Tên (vd: S/M/L)</th>
                    <th class="py-2 pr-3 font-medium">SKU</th>
                    <th class="py-2 pr-3 font-medium">Giá</th>
                    <th class="py-2 pr-3 font-medium">Giá cũ</th>
                    <th class="py-2 pr-3 font-medium">Tồn kho</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(v, i) in variants" :key="i" class="border-b border-cream-100">
                    <td class="py-2 pr-3"><input v-model="v.name" class="input !py-1.5" /></td>
                    <td class="py-2 pr-3"><input v-model="v.sku" class="input !py-1.5" /></td>
                    <td class="py-2 pr-3"><input v-model="v.price" type="number" step="0.01" class="input !py-1.5" /></td>
                    <td class="py-2 pr-3"><input v-model="v.compare_price" type="number" step="0.01" class="input !py-1.5" /></td>
                    <td class="py-2 pr-3"><input v-model.number="v.stock" type="number" class="input !py-1.5" /></td>
                    <td class="py-2"><button type="button" @click="removeVariant(i)" class="text-red-500">✕</button></td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p v-else class="text-sm text-ink-500">Không có biến thể. Giá sản phẩm dùng làm giá mặc định.</p>
          </section>
        </div>

        <!-- Aside -->
        <div class="space-y-6">
          <section class="rounded-xl border border-cream-200 bg-white p-5">
            <h2 class="mb-4 text-sm font-semibold text-ink-900">Xuất bản</h2>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" v-model="editor.featured" class="h-4 w-4 accent-brand-600" /> Sản phẩm nổi bật</label>
            <label class="mt-2 flex items-center gap-2 text-sm"><input type="checkbox" v-model="editor.is_active" class="h-4 w-4 accent-brand-600" /> Hiển thị</label>
          </section>

          <section class="rounded-xl border border-cream-200 bg-white p-5">
            <h2 class="mb-4 text-sm font-semibold text-ink-900">Giá &amp; Kho</h2>
            <div class="space-y-3">
              <div><label class="label">Giá *</label><input v-model="editor.price" type="number" step="0.01" class="input" /></div>
              <div><label class="label">Giá cũ (so sánh)</label><input v-model="editor.compare_price" type="number" step="0.01" class="input" /></div>
              <div><label class="label">Giá vốn</label><input v-model="editor.cost_price" type="number" step="0.01" class="input" /></div>
              <div><label class="label">Tồn kho</label><input v-model.number="editor.stock" type="number" class="input" /></div>
            </div>
          </section>

          <section class="rounded-xl border border-cream-200 bg-white p-5">
            <h2 class="mb-4 text-sm font-semibold text-ink-900">Hình ảnh</h2>
            <div>
              <label class="label">Ảnh đại diện</label>
              <div class="flex items-center gap-3">
                <img v-if="coverPreview" :src="coverPreview" alt="" class="h-16 w-16 rounded-lg bg-cream-100 object-cover" />
                <div v-else class="h-16 w-16 rounded-lg bg-cream-100"></div>
                <div class="min-w-0 flex-1">
                  <input type="file" accept="image/*" @change="onCoverFile" class="block w-full text-xs text-ink-500 file:mr-3 file:rounded-lg file:border-0 file:bg-cream-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-ink-700" />
                  <button type="button" @click="openPicker('cover')" class="mt-2 text-xs font-semibold text-brand-700 hover:underline">📚 Chọn từ Studio</button>
                </div>
              </div>
              <p v-if="coverUrl" class="mt-1 text-xs text-ink-400">Đang dùng ảnh đại diện từ Thư viện Studio.</p>
            </div>

            <div class="mt-4">
              <label class="label">Thư viện ảnh (upload)</label>
              <input type="file" accept="image/*" multiple @change="onGalleryFiles" class="block w-full text-xs text-ink-500 file:mr-3 file:rounded-lg file:border-0 file:bg-cream-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-ink-700" />
            </div>

            <div v-if="existingGallery.length || galleryUpload.length || studioGallery.length" class="mt-4 grid grid-cols-3 gap-2">
              <div v-for="(g, i) in galleryUpload" :key="'up-' + i" class="group relative">
                <img :src="g.url" alt="" class="aspect-square w-full rounded-lg object-cover" />
                <button type="button" @click="removeUploadedGallery(i)" class="absolute -right-1 -top-1 grid h-5 w-5 place-items-center rounded-full bg-red-600 text-white">✕</button>
              </div>
              <div v-for="(u, i) in studioGallery" :key="'st-' + i" class="group relative">
                <img :src="u" alt="" class="aspect-square w-full rounded-lg object-cover" />
                <button type="button" @click="removeStudioImage(u)" class="absolute -right-1 -top-1 grid h-5 w-5 place-items-center rounded-full bg-red-600 text-white">✕</button>
              </div>
              <div v-for="u in existingGallery" :key="u" class="group relative">
                <img :src="u" alt="" class="aspect-square w-full rounded-lg object-cover" />
                <button type="button" @click="removeExistingGallery(u)" class="absolute -right-1 -top-1 grid h-5 w-5 place-items-center rounded-full bg-red-600 text-white">✕</button>
              </div>
            </div>

            <button type="button" @click="openPicker" class="mt-3 text-xs font-semibold text-brand-700 hover:underline">📚 Chọn ảnh từ Thư viện Studio</button>
          </section>

          <section class="rounded-xl border border-cream-200 bg-white p-5">
            <h2 class="mb-4 text-sm font-semibold text-ink-900">SEO</h2>
            <div class="space-y-3">
              <div><label class="label">Meta title</label><input v-model="editor.meta_title" class="input" /><p class="mt-1 text-xs text-ink-400">{{ editor.meta_title.length }}/60</p></div>
              <div><label class="label">Meta description</label><textarea v-model="editor.meta_description" rows="2" class="input"></textarea><p class="mt-1 text-xs text-ink-400">{{ editor.meta_description.length }}/160</p></div>
            </div>
          </section>

          <button type="submit" :disabled="submitting" class="btn-brand w-full !py-3">{{ editor.id ? 'Cập nhật' : 'Tạo sản phẩm' }}</button>
        </div>
      </form>
    </main>

    <!-- Studio image picker modal -->
    <Teleport to="body">
      <Transition name="fade">
        <div v-if="pickerOpen" class="fixed inset-0 z-[95] flex items-center justify-center p-4">
          <div class="absolute inset-0 bg-ink-900/50 backdrop-blur-sm" @click="pickerOpen = false"></div>
          <div class="relative w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-cream-200 px-4 py-3">
              <h3 class="text-base font-semibold">{{ pickingFor === 'cover' ? 'Chọn ảnh đại diện' : 'Chọn ảnh cho bộ sưu tập' }}</h3>
              <button type="button" @click="pickerOpen = false" class="btn-ghost !p-1.5">✕</button>
            </div>
            <div class="flex items-center justify-between gap-2 border-b border-cream-100 px-4 py-2">
              <span class="text-xs text-ink-500">{{ pickingFor === 'cover' ? 'Chọn 1 ảnh làm đại diện' : 'Đã chọn ' + selImages.length + ' ảnh' }}</span>
              <button type="button" @click="addSelectedImages" :disabled="!selImages.length" class="btn-brand !py-1.5 text-xs" :class="{ '!opacity-50': !selImages.length }">{{ pickingFor === 'cover' ? 'Dùng làm đại diện' : 'Thêm ' + selImages.length + ' vào sản phẩm' }}</button>
            </div>
            <div class="max-h-[64vh] overflow-y-auto p-4">
              <div v-if="pickerLoading" class="grid grid-cols-3 gap-2">
                <div v-for="i in 9" :key="i" class="aspect-square animate-pulse rounded-lg bg-cream-200"></div>
              </div>
              <div v-else-if="studioImages.length" class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                <button v-for="img in studioImages" :key="img.id" type="button" @click="toggleSelImage(img)" class="group relative aspect-square overflow-hidden rounded-lg border transition" :class="isSelImage(img) ? 'border-brand-500 ring-2 ring-brand-500/30' : 'border-cream-200 hover:border-brand-400'">
                  <img :src="img.url" :alt="img.label" class="h-full w-full object-cover" loading="lazy" />
                  <span class="absolute inset-0 grid place-items-center text-white transition" :class="isSelImage(img) ? 'bg-brand-600/30' : 'bg-black/30 opacity-0 group-hover:opacity-100'">{{ isSelImage(img) ? '✓' : '＋' }}</span>
                </button>
              </div>
              <div v-else class="py-16 text-center text-sm text-ink-500">Thư viện Studio chưa có ảnh.</div>
            </div>
            <div class="border-t border-cream-200 px-4 py-3 text-right">
              <button type="button" @click="pickerOpen = false" class="btn-ghost !py-2">Đóng</button>
              <button type="button" @click="addSelectedImages" :disabled="!selImages.length" class="btn-brand ml-2 !py-2">Xong</button>
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
