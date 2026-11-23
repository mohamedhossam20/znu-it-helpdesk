<?php

namespace App\Policies;

class RoomPolicy extends ManagedByPermissionPolicy
{
    protected function managePermission(): string
    {
        return 'locations.manage';
    }
}
