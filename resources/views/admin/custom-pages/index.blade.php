@extends('layouts.admin')

@section('title', 'Trang đích')
@section('page_title', 'Trang đích (Landing page)')

@section('content')
    <div class="mb-5 flex items-center justify-between">
        <div class="rounded-xl bg-brand-50 p-4 text-sm text-brand-800">
            Tạo các trang đích linh hoạt: bộ sưu tập mới, sự kiện ra mắt, chiến dịch… Mỗi trang có hero riêng, nội dung phong phú và bộ sưu tập sản phẩm.
        </div>
        <a href="{{ route('admin.pages.create') }}" class="btn-brand btn-sm shrink-0">+ Tạo trang đích</a>
    </div>

    <div class="card overflow-hidden">
        @if($pages->isEmpty())
            <div class="p-10 text-center text-ink-500">Chưa có trang đích nào. Bấm “+ Tạo trang đích” để bắt đầu.</div>
        @else
        <table class="w-full text-sm">
            <thead class="bg-cream-100 text-left text-xs uppercase tracking-wide text-ink-500">
                <tr>
                    <th class="px-5 py-3">Tiêu đề</th>
                    <th class="px-5 py-3">Đường dẫn</th>
                    <th class="px-5 py-3">Template</th>
                    <th class="px-5 py-3">Trạng thái</th>
                    <th class="px-5 py-3 text-right">Thao tác</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-cream-200">
                @foreach($pages as $page)
                <tr>
                    <td class="px-5 py-3">
                        <p class="font-medium text-ink-900">{{ $page->title }}</p>
                        <p class="text-xs text-ink-500">{{ $page->updated_at?->format('d/m/Y') }}</p>
                    </td>
                    <td class="px-5 py-3">
                        <a href="{{ $page->url }}" target="_blank" class="link text-xs">{{ '/trang/'.$page->slug }}</a>
                    </td>
                    <td class="px-5 py-3"><span class="badge bg-cream-100 text-ink-700">{{ $page->template === 'landing' ? 'Landing' : 'Cơ bản' }}</span></td>
                    <td class="px-5 py-3">
                        <span class="badge {{ $page->is_active ? 'bg-brand-600 text-white' : 'bg-cream-200 text-ink-500' }}">{{ $page->is_active ? 'Đang hiển thị' : 'Đã ẩn' }}</span>
                    </td>
                    <td class="px-5 py-3">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('admin.pages.edit', $page) }}" class="btn-outline btn-sm">Sửa</a>
                            <form method="POST" action="{{ route('admin.pages.toggle', $page) }}">@csrf
                                <button type="submit" class="btn-outline btn-sm">{{ $page->is_active ? 'Ẩn' : 'Hiện' }}</button>
                            </form>
                            <form method="POST" action="{{ route('admin.pages.destroy', $page) }}" onsubmit="return confirm('Xóa trang này?')">@csrf @method('DELETE')
                                <button type="submit" class="btn-outline btn-sm !border-red-300 !text-red-600 hover:!bg-red-600 hover:!text-white">Xóa</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    @if(session('success'))
        <div class="mt-4 rounded-xl bg-brand-50 p-4 text-sm text-brand-800">{{ session('success') }}</div>
    @endif
@endsection
