<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Resources\Assets\AssetResource;
use App\Models\Asset;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AssetsRelationManager extends RelationManager
{
    protected static string $relationship = 'assets';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Assigned devices');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('asset_tag')
            ->modifyQueryUsing(fn ($query) => $query->with(['type', 'room']))
            ->columns([
                TextColumn::make('asset_tag')->label(__('Asset tag'))->badge(),
                TextColumn::make('type.name_ar')->label(__('Type')),
                TextColumn::make('model')
                    ->label(__('Model'))
                    ->formatStateUsing(fn (Asset $record) => trim("{$record->brand} {$record->model}")),
                TextColumn::make('room.name_ar')->label(__('Location')),
                TextColumn::make('status')->label(__('Status'))->badge(),
            ])
            ->recordUrl(fn (Asset $record) => AssetResource::getUrl('view', ['record' => $record]));
    }
}
