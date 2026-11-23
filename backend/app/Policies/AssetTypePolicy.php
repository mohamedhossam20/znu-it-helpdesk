<?php

namespace App\Policies;

class AssetTypePolicy extends ManagedByPermissionPolicy
{
    protected function managePermission(): string
    {
        return 'assets.manage';
    }
}
