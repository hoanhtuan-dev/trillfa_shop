@extends('layouts.admin')

@section('title', 'Menu')
@section('page_title', 'Quản lý menu')

@section('content')
@php
    $locations = [
        'header' => ['label' => 'Menu Header (thanh điều hướng)', 'items' => $headerMenu],
        'footer' => ['label' => 'Menu Footer', 'items' => $footerMenu],
    ];
    $categoriesJs = collect();
    foreach ($categories as $c) {
        $categoriesJs->push(['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug, 'depth' => 0]);
        foreach ($c->children as $ch) {
            $categoriesJs->push(['id' => $ch->id, 'name' => $ch->name, 'slug' => $ch->slug, 'depth' => 1]);
        }
    }
    $categoriesJs = $categoriesJs->values();
    $pagesJs = collect($pages)->map(fn($p) => ['label'=>$p['label'],'url'=>$p['url']])->values();
    $customPagesJs = collect($customPages)->map(fn($p) => ['id'=>$p->id,'label'=>$p->title,'slug'=>$p->slug])->values();

    $js = [];
    foreach ($locations as $loc => $info) {
        $byId = $info['items']->keyBy('id');
        $depth = [];
        foreach ($info['items'] as $it) {
            $d = 0; $p = $it->parent_id;
            while ($p && isset($byId[$p])) { $d++; $p = $byId[$p]->parent_id; }
            $depth[$it->id] = $d;
        }
        $js[$loc] = [
            'items' => $info['items']->map(fn($m) => ['id'=>$m->id,'label'=>$m->label,'url'=>$m->url,'parent_id'=>$m->parent_id,'sort_order'=>$m->sort_order,'is_active'=>$m->is_active,'type'=>$m->type,'category_id'=>$m->category_id,'custom_page_id'=>$m->custom_page_id])->values(),
            'parents' => $info['items']->map(fn($m) => ['id'=>$m->id,'label'=>(str_repeat('— ', $depth[$m->id] ?? 0)).$m->label])->values(),
            'depth' => $depth,
            'url' => route('admin.menu.store'),
        ];
    }
@endphp

<div class="space-y-8">
    @foreach($locations as $loc => $info)
        @php $cfg = $js[$loc]; @endphp
        <div x-data="menuForm('{{ $loc }}', {{ Js::from($cfg) }}, {{ Js::from($categoriesJs) }}, {{ Js::from($pagesJs) }}, {{ Js::from($customPagesJs) }})" class="grid gap-6 lg:grid-cols-[1fr_420px]">
            <!-- List -->
            <div class="card overflow-hidden">
                <div class="border-b border-cream-200 p-5">
                    <h2 class="font-display text-lg font-semibold text-ink-900">{{ $info['label'] }}</h2>
                    <p class="text-xs text-ink-500">{{ $info['items']->count() }} mục</p>
                </div>
                <div class="divide-y divide-cream-200">
                    @forelse($info['items'] as $item)
                        @php $d = $cfg['depth'][$item->id] ?? 0; @endphp
                        <div class="flex items-center gap-3 p-4" style="padding-left: {{ 16 + $d * 24 }}px">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-ink-500">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.008v.008H3.75V6.75zm0 5.25h.008v.008H3.75V12zm0 5.25h.008v.008H3.75v-.008z"/></svg>
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="truncate font-medium text-ink-900">{{ $item->label }}</p>
                                <p class="truncate text-xs text-ink-500">{{ $item->url ?? '#' }}</p>
                            </div>
                            <span class="badge {{ $item->is_active ? 'bg-brand-100 text-brand-700' : 'bg-red-100 text-red-600' }}">{{ $item->is_active ? 'Bật' : 'Tắt' }}</span>
                            <div class="flex items-center gap-1">
                                <button @click="up({{ $item->id }})" class="btn-ghost !p-1.5" title="Lên">↑</button>
                                <button @click="down({{ $item->id }})" class="btn-ghost !p-1.5" title="Xuống">↓</button>
                                <button @click="edit({{ $item->id }})" class="btn-ghost !p-1.5" title="Sửa"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg></button>
                                <form method="POST" action="/admin/menu/{{ $item->id }}/toggle">@csrf<button class="btn-ghost !p-1.5" title="Bật/Tắt"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button></form>
                                <form method="POST" action="/admin/menu/{{ $item->id }}" onsubmit="return confirm('Xóa mục menu?')">@csrf @method('DELETE')<button class="btn-ghost !p-1.5 text-red-500" title="Xóa"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166"/></svg></button></form>
                            </div>
                        </div>
                    @empty
                        <p class="p-8 text-center text-sm text-ink-500">Chưa có mục nào.</p>
                    @endforelse
                </div>
            </div>

            <!-- Form -->
            <div class="card h-fit p-6">
                <h3 class="mb-4 font-display text-lg font-semibold text-ink-900" x-text="editing ? 'Sửa mục' : 'Thêm mục'"></h3>
                <form :action="formAction" method="POST" class="space-y-4">
                    @csrf
                    <input type="hidden" name="_method" :value="formMethod">
                    <input type="hidden" name="location" :value="location">
                    <input type="hidden" name="is_active" :value="form.is_active ? '1' : '0'">
                    <input type="hidden" name="type" :value="form.type">
                    <input type="hidden" name="category_id" :value="form.category_id || ''">
                    <input type="hidden" name="custom_page_id" :value="form.custom_page_id || ''">
                    <div><label class="label">Nhãn *</label><input type="text" name="label" x-model="form.label" class="input" required></div>
                    <div>
                        <label class="label">Loại liên kết</label>
                        <select x-model="linkType" @change="linkType === 'custom' ? (form.type = 'custom', form.category_id = '') : (form.type = linkType)" class="input">
                            <option value="custom">Đường dẫn tùy chỉnh</option>
                            <option value="category">Danh mục sản phẩm</option>
                            <option value="page">Trang</option>
                            <option value="landing_page">Trang đích</option>
                        </select>
                    </div>
                    <div x-show="linkType === 'custom'">
                        <label class="label">URL (vd /san-pham, https://...)</label>
                        <input type="text" name="url" x-model="form.url" class="input">
                    </div>
                    <div x-show="linkType === 'category'">
                        <label class="label">Chọn danh mục (tự động render danh mục con đệ quy)</label>
                        <select @change="pickCategory($event.target.value)" class="input">
                            <option value="">— Chọn danh mục —</option>
                            <template x-for="c in categories" :key="c.id">
                                <option :value="c.id" x-text="(c.depth ? '   ↳ ' : '') + c.name"></option>
                            </template>
                        </select>
                    </div>
                    <div x-show="linkType === 'page'">
                        <label class="label">Chọn trang</label>
                        <select @change="pickPage($event.target.value)" class="input">
                            <option value="">— Chọn trang —</option>
                            <template x-for="p in pages" :key="p.url">
                                <option :value="p.url" x-text="p.label"></option>
                            </template>
                        </select>
                    </div>
                    <div x-show="linkType === 'landing_page'">
                        <label class="label">Chọn trang đích</label>
                        <select @change="pickLandingPage($event.target.value)" class="input">
                            <option value="">— Chọn trang đích —</option>
                            <template x-for="p in customPages" :key="p.id">
                                <option :value="p.id" x-text="p.label"></option>
                            </template>
                        </select>
                        <p class="mt-1 text-xs text-ink-500">Liên kết tới /trang/{slug} của trang đích (vd bộ sưu tập thu đông 2026).</p>
                    </div>
                    <div>
                        <label class="label">Mục cha (để tạo submenu)</label>
                        <select name="parent_id" x-model="form.parent_id" class="input">
                            <option value="">— Cấp cao nhất —</option>
                            <template x-for="p in parents" :key="p.id">
                                <option :value="p.id" x-text="p.label"></option>
                            </template>
                        </select>
                    </div>
                    <label class="flex items-center justify-between text-sm text-ink-700"><span>Hiển thị</span><input type="checkbox" x-model="form.is_active" class="h-5 w-5 accent-brand-600"></label>
                    <div class="flex items-center gap-2 pt-1">
                        <button type="submit" class="btn-brand flex-1" x-text="editing ? 'Cập nhật' : 'Thêm'"></button>
                        <button type="button" @click="resetForm()" class="btn-ghost" x-show="editing">Hủy</button>
                    </div>
                </form>
                @if($errors->any())<div class="mt-3 rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ $errors->first() }}</div>@endif
            </div>
        </div>
    @endforeach
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('menuForm', (location, cfg, categories, pages, customPages) => ({
        location,
        items: cfg.items, parents: cfg.parents, createUrl: cfg.url,
        categories, pages, customPages,
        editing: null,
        linkType: 'custom',
        get setType() { return this.linkType; },
        form: { id: null, label: '', url: '', type: 'custom', category_id: '', custom_page_id: '', parent_id: '', is_active: true },
        get formAction() { return this.editing ? '/admin/menu/' + this.editing : this.createUrl; },
        get formMethod() { return this.editing ? 'PUT' : 'POST'; },
        edit(id) {
            const m = this.items.find(x => x.id === id);
            if (!m) return;
            this.editing = m.id;
            this.form = { id: m.id, label: m.label, url: m.url || '', type: m.type || 'custom', category_id: m.category_id || '', custom_page_id: m.custom_page_id || '', parent_id: m.parent_id || '', is_active: !!m.is_active };
            this.linkType = m.type === 'category' ? 'category' : (m.type === 'page' ? 'page' : (m.type === 'landing_page' ? 'landing_page' : 'custom'));
        },
        resetForm() { this.editing = null; this.form = { id: null, label: '', url: '', type: 'custom', category_id: '', custom_page_id: '', parent_id: '', is_active: true }; this.linkType = 'custom'; },
        pickCategory(val) {
            if (!val) return;
            const c = this.categories.find(x => x.id === Number(val));
            if (!c) return;
            this.form.category_id = c.id;
            this.form.type = 'category';
            this.form.url = '/danh-muc/' + c.slug;
            if (!this.form.label) this.form.label = c.name;
        },
        pickPage(val) {
            if (!val) return;
            this.form.url = val;
            this.form.type = 'page';
            this.form.category_id = '';
            this.form.custom_page_id = '';
            if (!this.form.label) { const p = this.pages.find(x => x.url === val); if (p) this.form.label = p.label; }
        },
        pickLandingPage(val) {
            if (!val) return;
            const p = this.customPages.find(x => x.id === Number(val));
            if (!p) return;
            this.form.custom_page_id = p.id;
            this.form.type = 'landing_page';
            this.form.url = '/trang/' + p.slug;
            this.form.category_id = '';
            if (!this.form.label) this.form.label = p.label;
        },
        up(id) { this.post('/admin/menu/' + id + '/up'); },
        down(id) { this.post('/admin/menu/' + id + '/down'); },
        post(url) { const f = document.createElement('form'); f.method='POST'; f.action=url; f.innerHTML='@csrf'; document.body.appendChild(f); f.submit(); },
    }));
});
</script>
@endpush
@endsection