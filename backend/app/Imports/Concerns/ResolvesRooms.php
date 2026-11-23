<?php

namespace App\Imports\Concerns;

use App\Imports\RowImportException;
use App\Models\Room;

trait ResolvesRooms
{
    /**
     * @var array<string, Room>
     */
    private array $roomCache = [];

    protected function findRoom(string $buildingCode, string|int $floorLevel, string $roomCode): Room
    {
        $key = strtoupper($buildingCode)."|{$floorLevel}|{$roomCode}";

        $room = $this->roomCache[$key] ??= Room::query()
            ->where('code', $roomCode)
            ->whereHas('floor', fn ($q) => $q
                ->where('level', (int) $floorLevel)
                ->whereHas('building', fn ($b) => $b->where('code', strtoupper($buildingCode))))
            ->first();

        if (! $room) {
            throw new RowImportException(__('Room :room on floor :floor in building :building was not found. Import locations first.', [
                'room' => $roomCode,
                'floor' => $floorLevel,
                'building' => strtoupper($buildingCode),
            ]));
        }

        return $room;
    }
}
