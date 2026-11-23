<?php

namespace App\Policies;

class AssetPolicy extends ManagedByPermissionPolicy
{
    protected function managePermission(): string
    {
        return 'assets.manage';
    }
}
