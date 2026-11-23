<?php

namespace App\Filament;

use Filament\Support\Contracts\HasLabel;

enum NavigationGroup: string implements HasLabel
{
    case Locations = 'locations';
    case Assets = 'assets';
    case Settings = 'settings';

    public function getLabel(): string
    {
        return match ($this) {
            self::Locations => __('Locations'),
            self::Assets => __('Devices'),
            self::Settings => __('Setup'),
        };
    }
}
