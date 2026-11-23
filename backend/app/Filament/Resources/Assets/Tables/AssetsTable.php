<?php

namespace App\Filament\Resources\Assets\Tables;

use App\Enums\AssetStatus;
use App\Filament\Forms\LocationFields;
use App\Models\Asset;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AssetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['type', 'room.floor.building', 'assignedUser'])
                ->withCount(['tickets', 'tickets as open_tickets_count' => fn (Builder $q) => $q->open()]))
            ->defaultSort('asset_tag')
            ->columns([
                TextColumn::make('asset_tag')
                    ->label(__('Asset tag'))
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type.name_ar')
                    ->label(__('Type')),
                TextColumn::make('model')
                    ->label(__('Model'))
                    ->formatStateUsing(fn (Asset $record) => trim("{$record->brand} {$record->model}"))
                    ->searchable(['brand', 'model', 'serial_number']),
                TextColumn::make('room.name_ar')
                    ->label(__('Location'))
                    ->description(fn (Asset $record) => "{$record->room->floor->building->name_ar} › {$record->room->floor->name_ar}"),
                TextColumn::make('assignedUser.name')
                    ->label(__('Assigned to'))
                    ->placeholder(__('Shared'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(),
                TextColumn::make('tickets_count')
                    ->label(__('Faults'))
                    ->tooltip(__('Total tickets reported on this device'))
                    ->numeric()
                    ->sortable()
                    ->description(fn (Asset $record) => $record->open_tickets_count > 0 ? __(':count open', ['count' => $record->open_tickets_count]) : null),
            ])
            ->filters([
                SelectFilter::make('asset_type_id')
                    ->label(__('Type'))
                    ->relationship('type', 'name_ar')
                    ->multiple()
                    ->preload(),
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(AssetStatus::class),
                Filter::make('location')
                    ->schema([
                        LocationFields::building(),
                        LocationFields::floor(),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data[LocationFields::FLOOR] ?? null, fn (Builder $q, $floorId) => $q->whereRelation('room', 'floor_id', $floorId))
                        ->when(
                            ! ($data[LocationFields::FLOOR] ?? null) && ($data[LocationFields::BUILDING] ?? null),
                            fn (Builder $q) => $q->whereHas('room.floor', fn (Builder $f) => $f->where('building_id', $data[LocationFields::BUILDING])),
                        ))
                    ->columns(2)
                    ->columnSpan(2),
                TernaryFilter::make('assigned')
                    ->label(__('Ownership'))
                    ->placeholder(__('All'))
                    ->trueLabel(__('Personal devices'))
                    ->falseLabel(__('Shared devices'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('assigned_user_id'),
                        false: fn (Builder $query) => $query->whereNull('assigned_user_id'),
                    ),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
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
