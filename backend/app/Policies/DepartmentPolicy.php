<?php

namespace App\Policies;

class DepartmentPolicy extends ManagedByPermissionPolicy
{
    protected function managePermission(): string
    {
        return 'users.manage';
    }
}
