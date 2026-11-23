<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Filament\Resources\Assets\AssetResource;
use App\Models\Room;
use Filament\Resources\Pages\CreateRecord;

class CreateAsset extends CreateRecord
{
    protected static string $resource = AssetResource::class;

    /**
     * Opened from a room's "Add device" button: start with that room selected.
     */
    protected function fillForm(): void
    {
        parent::fillForm();

        $room = Room::find(request()->integer('room'));

        if ($room) {
            // Re-fill so the room select's hydration hook also sets building and floor.
            $this->form->fill([...$this->form->getRawState(), 'room_id' => $room->id]);
        }
    }
}
