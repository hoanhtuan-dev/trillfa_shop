@extends('layouts.admin')

@section('title', 'Sản phẩm')
@section('page_title', 'Sản phẩm')

@section('content')
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <form method="GET" class="flex flex-1 items-center gap-2">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Tìm sản phẩm..." class="input !w-full !py-2.5 sm:max-w-xs">
            <select name="category_id" class="input !w-auto !py-2.5">
                <option value="">Danh mục</option>
                @foreach($categories as $c)<option value="{{ $c->id }}" @selected(request('category_id') == $c->id)>{{ $c->name }}</option>@endforeach
            </select>
            <select name="status" class="input !w-auto !py-2.5">
                <option value="">Trạng thái</option>
                <option value="active" @selected(request('status') === 'active')>Đang bán</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Ngừng bán</option>
            </select>
            <button type="submit" class="btn-primary btn-sm">Lọc</button>
        </form>
        <a href="{{ route('admin.products.create') }}" class="btn-brand">+ Thêm sản phẩm</a>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-cream-100 text-left text-xs font-semibold uppercase tracking-wide text-ink-500">
                    <tr>
                        <th class="px-5 py-3">Sản phẩm</th>
                        <th class="px-5 py-3">Giá</th>
                        <th class="px-5 py-3">Tồn kho</th>
                        <th class="px-5 py-3">Biến thể</th>
                        <th class="px-5 py-3">Trạng thái</th>
                        <th class="px-5 py-3 text-right">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-cream-200">
                    @forelse($products as $product)
                        <tr class="hover:bg-cream-50">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <img src="{{ $product->image_url ?: asset('images/placeholder.svg') }}" class="h-11 w-11 rounded-xl object-cover" alt="">
                                    <div class="min-w-0">
                                        <p class="truncate font-medium text-ink-900">{{ $product->name }}</p>
                                        <p class="text-xs text-ink-500">{{ $product->category?->name ?? '—' }} · {{ $product->sku ?: 'no-sku' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3 font-semibold text-ink-900">{{ format_price($product->min_price) }}</td>
                            <td class="px-5 py-3">
                                <span class="badge {{ $product->total_stock > 5 ? 'bg-brand-100 text-brand-700' : ($product->total_stock > 0 ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-600') }}">{{ $product->total_stock }}</span>
                            </td>
                            <td class="px-5 py-3 text-ink-500">{{ $product->variants->count() }}</td>
                            <td class="px-5 py-3">
                                <form method="POST" action="{{ route('admin.products.toggle', $product) }}">
                                    @csrf
                                    <button type="submit" class="badge {{ $product->is_active ? 'bg-brand-600 text-white' : 'bg-cream-200 text-ink-500' }}">{{ $product->is_active ? 'Đang bán' : 'Ngừng bán' }}</button>
                                </form>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <div class="flex justify-end gap-1">
                                    <a href="{{ route('product.show', $product->slug) }}" target="_blank" class="btn-ghost !p-2" title="Xem"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg></a>
                                    <a href="{{ route('admin.products.edit', $product) }}" class="btn-ghost !p-2" title="Sửa"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg></a>
                                    <form method="POST" action="{{ route('admin.products.destroy', $product) }}" onsubmit="return confirm('Xóa sản phẩm này?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-ghost !p-2 text-red-500" title="Xóa"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-10 text-center text-ink-500">Chưa có sản phẩm nào.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-cream-200 p-4">{{ $products->links() }}</div>
    </div>
@endsection
