@extends('layouts.admin')

@section('title', 'Người dùng')
@section('page_title', 'Người dùng')

@section('content')
    <div class="mb-5">
        <form method="GET" class="flex items-center gap-2">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Tìm tên, email..." class="input !w-72 !py-2.5">
            <button type="submit" class="btn-primary btn-sm">Tìm</button>
        </form>
    </div>

    <div class="card overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-cream-100 text-left text-xs font-semibold uppercase tracking-wide text-ink-500">
                <tr><th class="px-5 py-3">Người dùng</th><th class="px-5 py-3">Vai trò</th><th class="px-5 py-3">Đơn hàng</th><th class="px-5 py-3">Trạng thái</th><th class="px-5 py-3">Ngày</th><th class="px-5 py-3 text-right">Thao tác</th></tr>
            </thead>
            <tbody class="divide-y divide-cream-200">
                @forelse($users as $user)
                    <tr class="hover:bg-cream-50">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                <span class="grid h-9 w-9 place-items-center rounded-full bg-brand-600 font-bold text-white">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                                <div>
                                    <p class="font-medium text-ink-900">{{ $user->name }}</p>
                                    <p class="text-xs text-ink-500">{{ $user->email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3"><span class="badge {{ $user->isAdmin() ? 'bg-brand-600 text-white' : 'bg-cream-100 text-ink-500' }}">{{ $user->isAdmin() ? 'Admin' : 'Khách hàng' }}</span></td>
                        <td class="px-5 py-3 text-ink-500">{{ $user->orders()->count() }}</td>
                        <td class="px-5 py-3">
                            <form method="POST" action="{{ route('admin.users.toggle', $user) }}">
                                @csrf
                                <button type="submit" class="badge {{ $user->is_active ? 'bg-brand-100 text-brand-700' : 'bg-red-100 text-red-600' }}">{{ $user->is_active ? 'Hoạt động' : 'Khóa' }}</button>
                            </form>
                        </td>
                        <td class="px-5 py-3 text-xs text-ink-500">{{ $user->created_at?->format('d/m/Y') }}</td>
                        <td class="px-5 py-3 text-right">
                            <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Xóa người dùng này?')">@csrf @method('DELETE')<button type="submit" class="btn-ghost !p-2 text-red-500"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166"/></svg></button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center text-ink-500">Không có người dùng.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="border-t border-cream-200 p-4">{{ $users->links() }}</div>
    </div>
@endsection
