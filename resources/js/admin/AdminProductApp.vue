<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { apiFetch, csrfToken } from '../storefront/composables/useApi.js';

const boot = window.__PRODUCT_BOOT__ || {};
const product = boot.product || null;
const categories = boot.categories || [];
const ai = boot.ai || {};

const action = product ? '/admin/products/' + product.id : '/admin/products';
const method = product ? 'PUT' : 'POST';

const form = reactive({
    name: product?.name || '',
    category_id: product?.category_id || '',
    brand: product?.brand || '',
    sku: product?.sku || '',
    tags: (product?.tags || []).join(', '),
    short_description: product?.short_description || '',
    description: product?.description || '',
    price: product?.price ?? '',
    compare_price: product?.compare_price ?? '',
    cost_price: product?.cost_price ?? '',
    stock: product?.stock ?? 0,
    featured: product?.featured ?? false,
    is_active: product?.is_active ?? true,
    meta_title: product?.meta_title || '',
    meta_description: product?.meta_description || '',
});

const cover = ref(null);
const coverUrl = ref(''); // cover picked from Studio library (absolute URL)
const galleryUpload = ref([]); // files
const studioGallery = ref([]); // studio urls (added)
const pickingFor = ref('gallery'); // 'cover' | 'gallery'
const variants = ref((product?.variants || []).map((v) => ({ name: v.name, sku: v.sku || '', price: v.price ?? '', compare_price: v.compare_price ?? '', stock: v.stock ?? 0 })));
const hint = ref('');
const aiLoading = ref(false);
const aiMsg = ref('');
const aiState = ref(''); // '' | 'analyzing' | 'suggesting'
const forceReanalyze = ref(false);

const pickerOpen = ref(false);
const studioImages = ref([]);
const pickerLoading = ref(false);
const selImages = ref([]);

// Image the AI should look at: cover (existing or Studio-chosen), else first gallery image.
const aiImageUrl = computed(() => coverUrl.value || product?.image || studioGallery.value[0] || '');

// ---------- AI suggest (vision + iterative enrichment) — inline ----------
function applyResult(r) {
    const d = r?.data || r || {};
    if (d.suggested_name) form.name = d.suggested_name;
    if (d.brand) form.brand = d.brand;
    if (d.short_description) form.short_description = d.short_description;
    if (d.description) form.description = d.description;
    if (d.meta_title) form.meta_title = d.meta_title;
    if (d.meta_description) form.meta_description = d.meta_description;
    if (Array.isArray(d.tags) && d.tags.length) form.tags = d.tags.join(', ');
    return d;
}

async function aiSuggest() {
    aiLoading.value = true;
    aiMsg.value = '';
    aiState.value = aiImageUrl.value ? 'analyzing' : 'suggesting';
    try {
        // Server runs the AI inline (bounded budget) and returns the result directly.
        const res = await apiFetch('/admin/products/ai-suggest', {
            method: 'POST',
            body: {
                name: form.name,
                category: catName(),
                brand: form.brand,
                hint: hint.value,
                short_description: form.short_description,
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
            aiMsg.value = 'AI chưa có key hoặc hết quota — dùng gợi ý mẫu (offline).';
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
function catName() {
    const c = categories.find((x) => x.id === form.category_id);
    return c?.name || '';
}

// ---------- Studio picker (multi-select, like SourcePanel) ----------
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
            cover.value = null;
        }
        pickerOpen.value = false;
        return;
    }
    let added = 0;
    for (const img of selImages.value) {
        if (img.url && !studioGallery.value.includes(img.url)) {
            studioGallery.value.push(img.url);
            added++;
        }
    }
    selImages.value = [];
    pickerOpen.value = false;
}
function addStudioImage(img) {
    if (pickingFor.value === 'cover') {
        if (img.url) {
            coverUrl.value = img.url;
            cover.value = null;
            pickerOpen.value = false;
        }
        return;
    }
    if (img.url && !studioGallery.value.includes(img.url)) {
        studioGallery.value.push(img.url);
        pickerOpen.value = false;
    }
}
function removeStudioImage(url) { studioGallery.value = studioGallery.value.filter((u) => u !== url); }

function onCoverFile(e) { cover.value = e.target.files[0] || null; }
function onGalleryFiles(e) { galleryUpload.value = Array.from(e.target.files || []); }

function addVariant() { variants.value.push({ name: '', sku: '', price: '', compare_price: '', stock: 0 }); }
function removeVariant(i) { variants.value.splice(i, 1); }

const submitting = ref(false);
function submit() {
    submitting.value = true;
    const el = document.querySelector('#admin-product-form');
    if (el) el.submit();
}
</script>

<template>
  <div class="max-w-7xl">
    <!-- AI helper bar (vision + iterative) -->
    <div class="mb-5 rounded-2xl border border-cream-200 bg-white/80 p-4">
      <div class="flex flex-wrap items-center gap-3">
        <div class="min-w-0 flex-1">
          <p class="text-sm font-semibold text-ink-900">Gợi ý nội dung &amp; SEO bằng AI</p>
          <p class="text-xs text-ink-500">Model {{ ai.model }} ({{ ai.provider }}) — nhìn ảnh, phân tích, làm giàu dần theo nội dung bạn đã nhập.</p>
        </div>
        <input v-model="hint" placeholder="Ý tưởng / điểm nhấn…" class="input !py-2 sm:w-72" @keyup.enter="aiSuggest" />
        <label v-if="aiImageUrl" class="flex items-center gap-2 text-xs text-ink-600"><input type="checkbox" v-model="forceReanalyze" class="h-4 w-4 accent-brand-600" /> Phân tích lại ảnh</label>
        <button @click="aiSuggest" :disabled="aiLoading" class="btn-brand !py-2.5">
          {{ aiState === 'analyzing' ? '🔍 Đang phân tích ảnh…' : aiState === 'suggesting' ? '✨ Đang sinh…' : '✨ Gợi ý AI' }}
        </button>
      </div>
      <div v-if="aiImageUrl" class="mt-3 flex items-center gap-3">
        <img :src="aiImageUrl" class="h-12 w-12 rounded-lg object-cover" />
        <p class="text-xs text-ink-500">AI sẽ nhìn ảnh này ({{ forceReanalyze ? 'phân tích lại' : 'tái sử dụng phân tích cũ nếu ảnh không đổi' }}).</p>
      </div>
      <p v-if="aiMsg" class="mt-2 w-full text-xs text-ink-500">{{ aiMsg }}</p>
    </div>

    <form id="admin-product-form" method="POST" :action="action" enctype="multipart/form-data" @submit.prevent="submit" class="grid gap-6 lg:grid-cols-3">
      <input type="hidden" name="_token" :value="csrfToken()" />
      <input type="hidden" name="_method" :value="method" />

      <div class="space-y-6 lg:col-span-2">
        <!-- Basic info -->
        <div class="card p-6">
          <h2 class="mb-4 font-display text-lg font-semibold text-ink-900">Thông tin sản phẩm</h2>
          <div class="space-y-4">
            <div><label class="label">Tên sản phẩm *</label><input v-model="form.name" name="name" required class="input" /></div>
            <div class="grid gap-4 sm:grid-cols-2">
              <div><label class="label">Danh mục</label><select v-model="form.category_id" name="category_id" class="input"><option value="">— Chọn —</option><template v-for="c in categories" :key="c.id"><option :value="c.id">{{ c.name }}</option><option v-for="ch in c.children" :key="ch.id" :value="ch.id">— {{ ch.name }}</option></template></select></div>
              <div><label class="label">Thương hiệu</label><input v-model="form.brand" name="brand" class="input" /></div>
              <div><label class="label">SKU / Mã</label><input v-model="form.sku" name="sku" class="input" /></div>
              <div><label class="label">Thẻ (phân cách phẩy)</label><input v-model="form.tags" name="tags" class="input" /></div>
            </div>
            <div><label class="label">Mô tả ngắn</label><textarea v-model="form.short_description" name="short_description" rows="2" class="input"></textarea></div>
            <div><label class="label">Mô tả chi tiết (HTML)</label><textarea v-model="form.description" name="description" rows="8" class="input font-mono text-xs"></textarea></div>
          </div>
        </div>

        <!-- Variants -->
        <div class="card p-6">
          <div class="mb-4 flex items-center justify-between"><h2 class="font-display text-lg font-semibold text-ink-900">Phân loại / Biến thể</h2><button type="button" @click="addVariant" class="btn-ghost !p-2 text-brand-700">+ Thêm</button></div>
          <div v-if="variants.length" class="overflow-x-auto">
            <table class="w-full text-sm"><thead><tr class="border-b border-cream-200 text-left text-xs uppercase text-ink-500"><th class="py-2 pr-3">Tên (vd: S/M/L)</th><th class="py-2 pr-3">SKU</th><th class="py-2 pr-3">Giá</th><th class="py-2 pr-3">Giá cũ</th><th class="py-2 pr-3">Tồn kho</th><th></th></tr></thead><tbody>
              <tr v-for="(v, i) in variants" :key="i" class="border-b border-cream-100">
                <td class="py-2 pr-3"><input v-model="v.name" :name="`variants[${i}][name]`" class="input !py-1.5" /></td>
                <td class="py-2 pr-3"><input v-model="v.sku" :name="`variants[${i}][sku]`" class="input !py-1.5" /></td>
                <td class="py-2 pr-3"><input v-model="v.price" :name="`variants[${i}][price]`" class="input !py-1.5" /></td>
                <td class="py-2 pr-3"><input v-model="v.compare_price" :name="`variants[${i}][compare_price]`" class="input !py-1.5" /></td>
                <td class="py-2 pr-3"><input v-model.number="v.stock" :name="`variants[${i}][stock]`" type="number" class="input !py-1.5" /></td>
                <td class="py-2"><button type="button" @click="removeVariant(i)" class="text-red-600">✕</button></td>
              </tr>
            </tbody></table>
          </div>
          <p v-else class="text-sm text-ink-500">Không có biến thể. Giá sản phẩm dùng làm giá mặc định.</p>
        </div>
      </div>

      <!-- Right column -->
      <div class="space-y-6">
        <!-- Publishing -->
        <div class="card p-6">
          <h2 class="mb-4 font-display text-lg font-semibold text-ink-900">Xuất bản</h2>
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="featured" value="1" v-model="form.featured" class="h-4 w-4 accent-brand-600" /> Sản phẩm nổi bật</label>
          <label class="mt-2 flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" v-model="form.is_active" class="h-4 w-4 accent-brand-600" /> Hiển thị</label>
        </div>

        <!-- Pricing -->
        <div class="card p-6">
          <h2 class="mb-4 font-display text-lg font-semibold text-ink-900">Giá &amp; Kho</h2>
          <div class="space-y-3">
            <div><label class="label">Giá *</label><input v-model="form.price" name="price" type="number" step="0.01" required class="input" /></div>
            <div><label class="label">Giá cũ (so sánh)</label><input v-model="form.compare_price" name="compare_price" type="number" step="0.01" class="input" /></div>
            <div><label class="label">Giá vốn</label><input v-model="form.cost_price" name="cost_price" type="number" step="0.01" class="input" /></div>
            <div><label class="label">Tồn kho</label><input v-model.number="form.stock" name="stock" type="number" class="input" /></div>
          </div>
        </div>

        <!-- Media -->
        <div class="card p-6">
          <h2 class="mb-4 font-display text-lg font-semibold text-ink-900">Hình ảnh</h2>
          <div>
            <label class="label">Ảnh đại diện</label>
            <div class="flex items-center gap-3">
              <img v-if="coverUrl || product?.image" :src="coverUrl || product.image" class="h-16 w-16 rounded-xl object-cover" />
              <input type="file" name="image" accept="image/*" @change="onCoverFile" class="text-xs" />
              <button type="button" @click="openPicker('cover')" class="btn-ghost !p-1.5 text-xs font-semibold text-brand-700">📚 Chọn từ Studio</button>
            </div>
            <input v-if="coverUrl" type="hidden" name="cover_url" :value="coverUrl" />
            <p v-if="coverUrl" class="mt-1 text-xs text-ink-400">Đang dùng ảnh đại diện từ Thư viện Studio.</p>
          </div>
          <div class="mt-4">
            <label class="label">Thư viện ảnh (upload)</label>
            <input type="file" name="gallery[]" accept="image/*" multiple @change="onGalleryFiles" class="text-xs" />
          </div>
          <div class="mt-4">
            <div class="mb-2 flex items-center justify-between"><label class="label">Ảnh từ Studio</label><button type="button" @click="openPicker" class="btn-ghost !p-1.5 text-xs font-semibold text-brand-700">📚 Chọn từ Thư viện</button></div>
            <div v-if="studioGallery.length" class="grid grid-cols-3 gap-2">
              <div v-for="u in studioGallery" :key="u" class="group relative"><img :src="u" class="aspect-square w-full rounded-lg object-cover" /><button type="button" @click="removeStudioImage(u)" class="absolute -right-1 -top-1 grid h-5 w-5 place-items-center rounded-full bg-red-600 text-white">✕</button><input type="hidden" name="studio_gallery[]" :value="u" /></div>
            </div>
            <p v-else class="text-xs text-ink-400">Chưa chọn ảnh Studio.</p>
          </div>
        </div>

        <!-- SEO -->
        <div class="card p-6">
          <h2 class="mb-4 font-display text-lg font-semibold text-ink-900">SEO</h2>
          <div class="space-y-3">
            <div><label class="label">Meta title</label><input v-model="form.meta_title" name="meta_title" class="input" /><p class="text-xs text-ink-400">{{ form.meta_title.length }}/60</p></div>
            <div><label class="label">Meta description</label><textarea v-model="form.meta_description" name="meta_description" rows="2" class="input"></textarea><p class="text-xs text-ink-400">{{ form.meta_description.length }}/160</p></div>
          </div>
        </div>

        <button type="submit" class="btn-brand w-full !py-3" :disabled="submitting">{{ product ? 'Cập nhật' : 'Tạo sản phẩm' }}</button>
      </div>
    </form>

    <!-- Studio image picker modal -->
    <Teleport to="body">
      <Transition name="fade">
        <div v-if="pickerOpen" class="fixed inset-0 z-[95] flex items-center justify-center p-4">
          <div class="absolute inset-0 bg-ink-900/50 backdrop-blur-sm" @click="pickerOpen = false"></div>
          <div class="relative w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-cream-200 px-4 py-3"><h3 class="font-display text-base font-semibold">{{ pickingFor === 'cover' ? 'Chọn ảnh đại diện' : 'Chọn ảnh cho bộ sưu tập' }}</h3><button @click="pickerOpen = false" class="btn-ghost !p-1.5">✕</button></div>
            <div class="flex items-center justify-between gap-2 border-b border-cream-100 px-4 py-2">
              <span class="text-xs text-ink-500">{{ pickingFor === 'cover' ? 'Chọn 1 ảnh làm đại diện' : 'Đã chọn ' + selImages.length + ' ảnh' }}</span>
              <button @click="addSelectedImages" :disabled="!selImages.length" class="btn-brand !py-1.5 text-xs" :class="{ '!opacity-50': !selImages.length }">{{ pickingFor === 'cover' ? 'Dùng làm đại diện' : 'Thêm ' + selImages.length + ' vào sản phẩm' }}</button>
            </div>
            <div class="max-h-[64vh] overflow-y-auto p-4">
              <div v-if="pickerLoading" class="grid grid-cols-3 gap-2"><div v-for="i in 9" :key="i" class="aspect-square animate-pulse rounded-lg bg-cream-200"></div></div>
              <div v-else-if="studioImages.length" class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                <button v-for="img in studioImages" :key="img.id" @click="toggleSelImage(img)" class="group relative aspect-square overflow-hidden rounded-lg border transition" :class="isSelImage(img) ? 'border-brand-500 ring-2 ring-brand-500/30' : 'border-cream-200 hover:border-brand-400'">
                  <img :src="img.url" :alt="img.label" class="h-full w-full object-cover" loading="lazy" />
                  <span class="absolute inset-0 grid place-items-center text-white transition" :class="isSelImage(img) ? 'bg-brand-600/30' : 'bg-black/30 opacity-0 group-hover:opacity-100'">{{ isSelImage(img) ? '✓' : '＋' }}</span>
                </button>
              </div>
              <div v-else class="py-16 text-center text-sm text-ink-500">Thư viện Studio chưa có ảnh.</div>
            </div>
            <div class="border-t border-cream-200 px-4 py-3 text-right"><button @click="pickerOpen = false" class="btn-ghost !py-2">Đóng</button><button @click="addSelectedImages" :disabled="!selImages.length" class="btn-brand ml-2 !py-2">Xong</button></div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.15s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
