<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['roles', 'department', 'defaultRoom.floor.building'])
                ->withCount('assets'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->description(fn (User $record) => $record->email)
                    ->searchable(['name', 'email'])
                    ->sortable(),
                TextColumn::make('roles.name')
                    ->label(__('Roles'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => UserRole::tryFrom($state)?->getLabel() ?? $state)
                    ->color(fn (string $state) => $state === UserRole::Requester->value ? 'gray' : 'primary'),
                TextColumn::make('department.name_ar')
                    ->label(__('Department'))
                    ->description(fn (User $record) => $record->job_title)
                    ->placeholder('—'),
                TextColumn::make('defaultRoom.name_ar')
                    ->label(__('Fixed location'))
                    ->description(fn (User $record) => $record->defaultRoom ? "{$record->defaultRoom->floor->building->name_ar} › {$record->defaultRoom->floor->name_ar}" : null)
                    ->placeholder(__('Moves around')),
                TextColumn::make('extension')
                    ->label(__('Ext.'))
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('assets_count')
                    ->label(__('Devices'))
                    ->numeric(),
                IconColumn::make('is_active')
                    ->label(__('Active'))
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->label(__('Role'))
                    ->relationship('roles', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => UserRole::tryFrom($record->name)?->getLabel() ?? $record->name),
                SelectFilter::make('department_id')
                    ->label(__('Department'))
                    ->relationship('department', 'name_ar')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('default_room_id')
                    ->label(__('Fixed location'))
                    ->nullable()
                    ->trueLabel(__('Has a fixed location'))
                    ->falseLabel(__('Moves around')),
                TernaryFilter::make('is_active')
                    ->label(__('Active')),
                TrashedFilter::make(),
            ])
            ->recordActions([
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
