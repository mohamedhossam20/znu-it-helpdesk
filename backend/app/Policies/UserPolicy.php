<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserPolicy extends ManagedByPermissionPolicy
{
    protected function managePermission(): string
    {
        return 'users.manage';
    }

    /**
     * Super admins pass through Gate::before; everyone else must not be able
     * to edit (and so demote or lock out) a super admin account.
     *
     * @param  User  $model
     */
    public function update(User $user, Model $model): bool
    {
        return parent::update($user, $model) && ! $model->hasRole(UserRole::SuperAdmin->value);
    }

    /**
     * @param  User  $model
     */
    public function delete(User $user, Model $model): bool
    {
        return parent::delete($user, $model)
            && ! $user->is($model)
            && ! $model->hasRole(UserRole::SuperAdmin->value);
    }
}
