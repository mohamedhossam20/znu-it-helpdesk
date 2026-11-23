<?php

namespace App\Imports;

use App\Models\User;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * The order of the cases is the order imports must happen in: each one
 * refers to things created by the ones before it.
 */
enum ImportType: string implements HasDescription, HasLabel
{
    case Locations = 'locations';
    case Departments = 'departments';
    case Users = 'users';
    case Assets = 'assets';

    public function importer(): RowImporter
    {
        return app(match ($this) {
            self::Locations => LocationsImporter::class,
            self::Departments => DepartmentsImporter::class,
            self::Users => UsersImporter::class,
            self::Assets => AssetsImporter::class,
        });
    }

    public function permission(): string
    {
        return match ($this) {
            self::Locations => 'locations.manage',
            self::Departments, self::Users => 'users.manage',
            self::Assets => 'assets.manage',
        };
    }

    public function allows(User $user): bool
    {
        return $user->can($this->permission());
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Locations => __('1. Buildings, floors & rooms'),
            self::Departments => __('2. Departments & faculties'),
            self::Users => __('3. Users'),
            self::Assets => __('4. Devices'),
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Locations => __('One row per room. Buildings and floors are created automatically.'),
            self::Departments => __('Put a faculty before its departments.'),
            self::Users => __('Leave the room columns empty for people without a fixed office. New accounts cannot log in until a password is set.'),
            self::Assets => __('Leave asset_tag empty to generate one. Leave assigned_user_email empty for shared devices.'),
        };
    }
}
