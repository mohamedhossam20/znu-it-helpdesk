<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AssetStatus: string implements HasColor, HasLabel
{
    case InService = 'in_service';
    case UnderRepair = 'under_repair';
    case Retired = 'retired';

    public function getLabel(): string
    {
        return __("enums.asset_status.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::InService => 'success',
            self::UnderRepair => 'warning',
            self::Retired => 'gray',
        };
    }
}
