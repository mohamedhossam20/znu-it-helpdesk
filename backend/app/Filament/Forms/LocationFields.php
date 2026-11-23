<?php

namespace App\Filament\Forms;

use App\Models\Building;
use App\Models\Floor;
use App\Models\Room;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * Cascading building → floor → room selects shared by the room, asset and
 * user forms. Building (and floor, when picking a room) are UI-only helpers
 * that narrow the next list; only the real foreign key is saved.
 */
class LocationFields
{
    public const BUILDING = 'location_building_id';

    public const FLOOR = 'location_floor_id';

    public static function building(): Select
    {
        return Select::make(self::BUILDING)
            ->label(__('Building'))
            ->options(fn () => Building::query()->where('is_active', true)->orderBy('code')->pluck('name_ar', 'id'))
            ->searchable()
            ->live()
            ->dehydrated(false)
            ->afterStateUpdated(function (Set $set): void {
                $set(self::FLOOR, null);
                $set('floor_id', null);
            });
    }

    /**
     * @param  string  $name  "floor_id" when the floor is the saved value (room form),
     *                        self::FLOOR when it only narrows the room list.
     * @param  string  $roomField  room select to clear when the floor changes
     */
    public static function floor(string $name = self::FLOOR, string $roomField = 'room_id'): Select
    {
        $select = Select::make($name)
            ->label(__('Floor'))
            ->options(fn (Get $get) => Floor::query()
                ->where('building_id', $get(self::BUILDING))
                ->orderBy('level')
                ->pluck('name_ar', 'id'))
            ->disabled(fn (Get $get) => blank($get(self::BUILDING)))
            ->live();

        if ($name === 'floor_id') {
            return $select
                ->required()
                ->afterStateHydrated(function (Set $set, ?int $state): void {
                    if ($state) {
                        $set(self::BUILDING, Floor::find($state)?->building_id);
                    }
                });
        }

        return $select
            ->dehydrated(false)
            ->afterStateUpdated(fn (Set $set) => $set($roomField, null));
    }

    public static function room(string $name = 'room_id', bool $required = true): Select
    {
        return Select::make($name)
            ->label(__('Room / office'))
            ->options(fn (Get $get) => Room::query()
                ->where('floor_id', $get(self::FLOOR))
                ->where('is_active', true)
                ->orderBy('code')
                ->get()
                ->mapWithKeys(fn (Room $room) => [$room->id => "{$room->code} — {$room->name_ar}"]))
            ->searchable()
            ->disabled(fn (Get $get) => blank($get(self::FLOOR)))
            ->required($required)
            ->afterStateHydrated(function (Set $set, ?int $state): void {
                $floor = $state ? Room::find($state)?->floor : null;

                if ($floor) {
                    $set(self::BUILDING, $floor->building_id);
                    $set(self::FLOOR, $floor->id);
                }
            });
    }

    /**
     * @return list<Select>
     */
    public static function roomPicker(string $name = 'room_id', bool $required = true): array
    {
        $building = self::building()
            ->required($required)
            ->afterStateUpdated(function (Set $set) use ($name): void {
                $set(self::FLOOR, null);
                $set($name, null);
            });

        return [$building, self::floor(roomField: $name)->required($required), self::room($name, $required)];
    }
}
