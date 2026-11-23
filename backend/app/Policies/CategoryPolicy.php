<?php

namespace App\Policies;

class CategoryPolicy extends ManagedByPermissionPolicy
{
    protected function managePermission(): string
    {
        return 'categories.manage';
    }
}
