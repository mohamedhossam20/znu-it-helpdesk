<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Reference data (locations, assets, categories, users): every panel user may
 * look it up while handling tickets, only holders of the manage permission
 * may change it. Force delete stays with super admins via Gate::before.
 */
abstract class ManagedByPermissionPolicy
{
    abstract protected function managePermission(): string;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(UserRole::adminPanelRoles());
    }

    public function view(User $user, Model $model): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can($this->managePermission());
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can($this->managePermission());
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can($this->managePermission());
    }

    public function deleteAny(User $user): bool
    {
        return $user->can($this->managePermission());
    }

    public function restore(User $user, Model $model): bool
    {
        return $user->can($this->managePermission());
    }

    public function restoreAny(User $user): bool
    {
        return $user->can($this->managePermission());
    }

    public function forceDelete(User $user, Model $model): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }
}
