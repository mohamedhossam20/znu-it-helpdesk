<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public const PERMISSIONS = [
        'tickets.create',
        'tickets.view_own',
        'tickets.view_assigned',
        'tickets.view_all',
        'tickets.assign',
        'tickets.change_status',
        'tickets.comment_internal',
        'tickets.delete',
        'locations.manage',
        'assets.manage',
        'users.manage',
        'categories.manage',
        'reports.view',
        'settings.manage',
    ];

    public const ROLE_PERMISSIONS = [
        'requester' => ['tickets.create', 'tickets.view_own'],
        'technician' => [
            'tickets.create', 'tickets.view_own', 'tickets.view_assigned',
            'tickets.change_status', 'tickets.comment_internal',
        ],
        'it_supervisor' => [
            'tickets.create', 'tickets.view_own', 'tickets.view_assigned', 'tickets.view_all',
            'tickets.assign', 'tickets.change_status', 'tickets.comment_internal',
            'locations.manage', 'assets.manage', 'users.manage', 'categories.manage', 'reports.view',
        ],
        'super_admin' => self::PERMISSIONS,
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (UserRole::cases() as $role) {
            Role::findOrCreate($role->value, 'web')
                ->syncPermissions(self::ROLE_PERMISSIONS[$role->value]);
        }
    }
}
