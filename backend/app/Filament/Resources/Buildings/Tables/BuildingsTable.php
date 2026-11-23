<?php

namespace App\Filament\Resources\Buildings\Tables;

use App\Models\Building;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class BuildingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['floors', 'rooms']))
            ->defaultSort('code')
            ->columns([
                TextColumn::make('code')
                    ->label(__('Code'))
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name_ar')
                    ->label(__('Name'))
                    ->description(fn ($record) => $record->name_en)
                    ->searchable(['name_ar', 'name_en'])
                    ->sortable(),
                TextColumn::make('floors_count')
                    ->label(__('Floors'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('rooms_count')
                    ->label(__('Rooms'))
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('Active'))
                    ->boolean(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('qr')
                    ->label(__('QR labels'))
                    ->tooltip(__('Print QR labels for every room in this building'))
                    ->icon(Heroicon::OutlinedQrCode)
                    ->color('gray')
                    ->url(fn (Building $record) => route('admin.rooms.qr', ['building' => $record->id]))
                    ->openUrlInNewTab()
                    ->hidden(fn (Building $record) => $record->rooms_count === 0),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
