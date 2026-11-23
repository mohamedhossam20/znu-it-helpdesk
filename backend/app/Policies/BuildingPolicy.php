<?php

namespace App\Policies;

class BuildingPolicy extends ManagedByPermissionPolicy
{
    protected function managePermission(): string
    {
        return 'locations.manage';
    }
}
