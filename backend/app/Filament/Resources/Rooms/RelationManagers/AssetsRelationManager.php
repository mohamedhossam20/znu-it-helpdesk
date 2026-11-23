<?php

namespace App\Filament\Resources\Rooms\RelationManagers;

use App\Filament\Resources\Assets\AssetResource;
use App\Models\Asset;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AssetsRelationManager extends RelationManager
{
    protected static string $relationship = 'assets';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Devices in this room');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('asset_tag')
            ->modifyQueryUsing(fn ($query) => $query->with(['type', 'assignedUser']))
            ->columns([
                TextColumn::make('asset_tag')
                    ->label(__('Asset tag'))
                    ->badge()
                    ->searchable(),
                TextColumn::make('type.name_ar')
                    ->label(__('Type')),
                TextColumn::make('model')
                    ->label(__('Model'))
                    ->formatStateUsing(fn (Asset $record) => trim("{$record->brand} {$record->model}")),
                TextColumn::make('assignedUser.name')
                    ->label(__('Assigned to'))
                    ->placeholder(__('Shared')),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(),
            ])
            ->headerActions([
                Action::make('addAsset')
                    ->label(__('Add device'))
                    ->icon(Heroicon::OutlinedPlus)
                    ->url(fn () => AssetResource::getUrl('create', ['room' => $this->getOwnerRecord()->getKey()]))
                    ->visible(fn () => AssetResource::canCreate()),
            ])
            ->recordUrl(fn (Asset $record) => AssetResource::getUrl('view', ['record' => $record]));
    }
}
