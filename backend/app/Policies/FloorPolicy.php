<?php

namespace App\Policies;

class FloorPolicy extends ManagedByPermissionPolicy
{
    protected function managePermission(): string
    {
        return 'locations.manage';
    }
}
