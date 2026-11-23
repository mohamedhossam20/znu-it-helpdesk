<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TicketSource: string implements HasLabel
{
    case MyDevice = 'my_device';
    case OtherLocation = 'other_location';
    case Qr = 'qr';
    case Admin = 'admin';

    public function getLabel(): string
    {
        return __("enums.ticket_source.{$this->value}");
    }
}
