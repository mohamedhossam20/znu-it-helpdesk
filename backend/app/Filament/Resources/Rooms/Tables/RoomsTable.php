<?php

namespace App\Filament\Resources\Rooms\Tables;

use App\Enums\RoomType;
use App\Filament\Forms\LocationFields;
use App\Models\Room;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RoomsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with('floor.building')
                ->withCount(['assets', 'tickets as open_tickets_count' => fn (Builder $q) => $q->open()]))
            ->defaultSort('code')
            ->columns([
                TextColumn::make('code')
                    ->label(__('Code'))
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name_ar')
                    ->label(__('Name'))
                    ->description(fn (Room $record) => $record->name_en)
                    ->searchable(['name_ar', 'name_en']),
                TextColumn::make('floor.building.name_ar')
                    ->label(__('Building')),
                TextColumn::make('floor.name_ar')
                    ->label(__('Floor')),
                TextColumn::make('type')
                    ->label(__('Type'))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('assets_count')
                    ->label(__('Devices'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('open_tickets_count')
                    ->label(__('Open tickets'))
                    ->numeric()
                    ->color(fn (int $state) => $state > 0 ? 'danger' : 'gray')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('Active'))
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('location')
                    ->schema([
                        LocationFields::building()->live(),
                        LocationFields::floor('floor_id'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['floor_id'] ?? null, fn (Builder $q, $floorId) => $q->where('floor_id', $floorId))
                            ->when(
                                ! ($data['floor_id'] ?? null) && ($data[LocationFields::BUILDING] ?? null),
                                fn (Builder $q) => $q->whereRelation('floor', 'building_id', $data[LocationFields::BUILDING]),
                            );
                    })
                    ->columns(2)
                    ->columnSpan(2),
                SelectFilter::make('type')
                    ->label(__('Type'))
                    ->options(RoomType::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('qr')
                    ->label(__('QR'))
                    ->icon(Heroicon::OutlinedQrCode)
                    ->color('gray')
                    ->url(fn (Room $record) => route('admin.rooms.qr', ['rooms' => $record->id]))
                    ->openUrlInNewTab(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('printQr')
                        ->label(__('Print QR labels'))
                        ->icon(Heroicon::OutlinedPrinter)
                        ->action(fn (Collection $records, $livewire) => $livewire->js(
                            'window.open('.json_encode(route('admin.rooms.qr', ['rooms' => $records->modelKeys()])).', "_blank")'
                        ))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
