<?php

namespace App\Policies;

use App\Models\User;

/**
 * Phân quyền quản lý tài khoản — chuẩn Laravel Policy.
 *
 * Chỉ Super Admin mới có quyền xem / tạo / sửa / xóa / đặt lại mật khẩu
 * của các tài khoản khác. Admin thường vào được khu vực quản trị nhưng
 * không được quyền đụng tới quản lý người dùng.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    /**
     * Super Admin có thể sửa tài khoản khác, nhưng không được tự sửa
     * chính mình qua đây (tránh tự hạ quyền / tự khóa tài khoản quản trị).
     */
    public function update(User $actor, User $target): bool
    {
        return $actor->isSuperAdmin() && $actor->id !== $target->id;
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->isSuperAdmin() && $actor->id !== $target->id;
    }

    public function resetPassword(User $actor, User $target): bool
    {
        return $this->update($actor, $target);
    }
}
