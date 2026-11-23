<?php

namespace App\Filament\Resources\Rooms\Pages;

use App\Filament\Resources\Rooms\RoomResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditRoom extends EditRecord
{
    protected static string $resource = RoomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('qr')
                ->label(__('Print QR label'))
                ->icon(Heroicon::OutlinedQrCode)
                ->color('gray')
                ->url(fn () => route('admin.rooms.qr', ['rooms' => $this->record->id]))
                ->openUrlInNewTab(),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
