@extends('layouts.admin')

@section('title', 'Người dùng')
@section('page_title', 'Người dùng')

@section('content')
@php
    $userItems = $users->getCollection()->map(fn($u) => [
        'id' => $u->id, 'name' => $u->name, 'email' => $u->email,
        'phone' => $u->phone, 'role' => $u->role, 'is_active' => $u->is_active,
    ])->values();
@endphp

<div class="grid gap-6 lg:grid-cols-[1fr_380px]" x-data="userForm({{ Js::from($userItems) }}, '{{ route('admin.users.store') }}')">
    <!-- List -->
    <div class="card overflow-hidden">
        <div class="flex flex-col gap-3 border-b border-cream-200 p-5 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="font-display text-lg font-semibold text-ink-900">Danh sách ({{ $users->total() }})</h2>
            <form method="GET" class="flex items-center gap-2">
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Tìm tên, email..." class="input !w-56 !py-2">
                <button type="submit" class="btn-primary btn-sm">Tìm</button>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-cream-100 text-left text-xs font-semibold uppercase tracking-wide text-ink-500">
                    <tr>
                        <th class="px-5 py-3">Người dùng</th>
                        <th class="px-5 py-3">Vai trò</th>
                        <th class="px-5 py-3">SĐT</th>
                        <th class="px-5 py-3">Đơn</th>
                        <th class="px-5 py-3">Trạng thái</th>
                        <th class="px-5 py-3 text-right">Thao tác</th>
                    </tr>
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
                            <td class="px-5 py-3"><span class="badge {{ $user->isAdmin() ? 'bg-brand-600 text-white' : 'bg-cream-100 text-ink-500' }}">{{ $user->isAdmin() ? 'Admin' : 'Khách' }}</span></td>
                            <td class="px-5 py-3 text-ink-500">{{ $user->phone ?? '—' }}</td>
                            <td class="px-5 py-3 text-ink-500">{{ $user->orders()->count() }}</td>
                            <td class="px-5 py-3">
                                <form method="POST" action="{{ route('admin.users.toggle', $user) }}">@csrf
                                    <button type="submit" class="badge {{ $user->is_active ? 'bg-brand-100 text-brand-700' : 'bg-red-100 text-red-600' }}">{{ $user->is_active ? 'Hoạt động' : 'Khóa' }}</button>
                                </form>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex justify-end gap-1">
                                    <button @click="edit({{ $user->id }})" class="btn-ghost !p-2" title="Sửa"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg></button>
                                    <button @click="openReset({{ $user->id }})" class="btn-ghost !p-2" title="Đặt lại mật khẩu"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z"/></svg></button>
                                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Xóa người dùng này?')">@csrf @method('DELETE')
                                        <button type="submit" class="btn-ghost !p-2 text-red-500" title="Xóa" {{ $user->isAdmin() ? 'disabled' : '' }}><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166"/></svg></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-10 text-center text-ink-500">Không có người dùng.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-cream-200 p-4">{{ $users->links() }}</div>
    </div>

    <!-- Form create/edit -->
    <div class="card h-fit p-6">
        <h2 class="mb-4 font-display text-lg font-semibold text-ink-900" x-text="editing ? 'Sửa người dùng' : 'Thêm người dùng'"></h2>
        <form :action="formAction" method="POST" class="space-y-4">
            @csrf
            <input type="hidden" name="_method" :value="formMethod">
            <input type="hidden" name="is_active" :value="form.is_active ? '1' : '0'">
            <div>
                <label class="label">Họ và tên *</label>
                <input type="text" name="name" x-model="form.name" class="input" required>
            </div>
            <div>
                <label class="label">Email *</label>
                <input type="email" name="email" x-model="form.email" class="input" required>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label">Số điện thoại</label><input type="text" name="phone" x-model="form.phone" class="input"></div>
                <div>
                    <label class="label">Vai trò</label>
                    <select name="role" x-model="form.role" class="input">
                        <option value="customer">Khách hàng</option>
                        <option value="admin">Quản trị</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="label">Mật khẩu <span x-show="editing" class="text-xs font-normal text-ink-500">(để trống nếu không đổi)</span></label>
                <input type="password" name="password" x-model="form.password" class="input" :required="!editing" placeholder="Ít nhất 8 ký tự">
            </div>
            <div>
                <label class="label">Xác nhận mật khẩu</label>
                <input type="password" name="password_confirmation" x-model="form.password_confirmation" class="input" :required="!editing">
            </div>
            <label class="flex items-center justify-between text-sm text-ink-700"><span>Tài khoản hoạt động</span><input type="checkbox" x-model="form.is_active" class="h-5 w-5 accent-brand-600"></label>
            <div class="flex items-center gap-2 pt-1">
                <button type="submit" class="btn-brand flex-1" x-text="editing ? 'Cập nhật' : 'Tạo người dùng'"></button>
                <button type="button" @click="resetForm()" class="btn-ghost" x-show="editing">Hủy</button>
            </div>
        </form>
        @if($errors->any())<div class="mt-3 rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ $errors->first() }}</div>@endif
    </div>

    <!-- Reset password modal -->
    <div x-show="resetFor" @keydown.escape.window="closeReset()" class="fixed inset-0 z-[80] grid place-items-center bg-ink-900/50 p-4" style="display:none">
        <div @click.outside="closeReset()" class="card w-full max-w-md p-6">
            <h3 class="font-display text-lg font-semibold text-ink-900">Đặt lại mật khẩu</h3>
            <p class="mt-1 text-sm text-ink-500">Nhập mật khẩu mới cho người dùng này.</p>
            <form :action="resetUrl" method="POST" class="mt-4 space-y-3">
                @csrf
                <div><label class="label">Mật khẩu mới *</label><input type="password" name="password" x-model="reset.password" class="input" required></div>
                <div><label class="label">Xác nhận mật khẩu *</label><input type="password" name="password_confirmation" x-model="reset.password_confirmation" class="input" required></div>
                <div class="flex gap-2 pt-1">
                    <button type="submit" class="btn-brand flex-1">Đặt lại</button>
                    <button type="button" @click="closeReset()" class="btn-outline">Hủy</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('userForm', (items, createUrl) => ({
        items, createUrl,
        editing: null,
        form: { id: null, name: '', email: '', phone: '', role: 'customer', password: '', password_confirmation: '', is_active: true },
        resetFor: null,
        reset: { password: '', password_confirmation: '' },
        get formAction() { return this.editing ? '/admin/users/' + this.editing : this.createUrl; },
        get formMethod() { return this.editing ? 'PUT' : 'POST'; },
        edit(id) {
            const u = this.items.find(x => x.id === id);
            if (!u) return;
            this.editing = u.id;
            this.form = { id: u.id, name: u.name, email: u.email, phone: u.phone || '', role: u.role, password: '', password_confirmation: '', is_active: !!u.is_active };
        },
        resetForm() {
            this.editing = null;
            this.form = { id: null, name: '', email: '', phone: '', role: 'customer', password: '', password_confirmation: '', is_active: true };
        },
        openReset(id) { this.resetFor = id; this.reset = { password: '', password_confirmation: '' }; },
        closeReset() { this.resetFor = null; this.reset = { password: '', password_confirmation: '' }; },
        get resetUrl() { return '/admin/users/' + this.resetFor + '/password'; },
    }));
});
</script>
@endpush
@endsection