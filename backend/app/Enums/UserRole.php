<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case Requester = 'requester';
    case Technician = 'technician';
    case ItSupervisor = 'it_supervisor';
    case SuperAdmin = 'super_admin';

    /**
     * @return list<string>
     */
    public static function adminPanelRoles(): array
    {
        return [self::Technician->value, self::ItSupervisor->value, self::SuperAdmin->value];
    }

    public function getLabel(): string
    {
        return __("enums.user_role.{$this->value}");
    }
}
