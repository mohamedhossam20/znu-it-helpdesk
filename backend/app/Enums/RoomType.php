<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RoomType: string implements HasLabel
{
    case Office = 'office';
    case LectureHall = 'lecture_hall';
    case Lab = 'lab';
    case MeetingRoom = 'meeting_room';
    case ServerRoom = 'server_room';
    case Other = 'other';

    public function getLabel(): string
    {
        return __("enums.room_type.{$this->value}");
    }
}
