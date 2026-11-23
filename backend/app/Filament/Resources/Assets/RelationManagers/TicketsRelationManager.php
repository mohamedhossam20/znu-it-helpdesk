<?php

namespace App\Filament\Resources\Assets\RelationManagers;

use App\Models\Ticket;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class TicketsRelationManager extends RelationManager
{
    protected static string $relationship = 'tickets';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Fault history');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('number')
            ->modifyQueryUsing(fn ($query) => $query->with(['category.parent', 'requester', 'assignee']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('number')
                    ->label(__('Ticket #'))
                    ->searchable(),
                TextColumn::make('category_id')
                    ->label(__('Problem'))
                    ->formatStateUsing(fn (Ticket $record) => $record->category->fullName()),
                TextColumn::make('requester.name')
                    ->label(__('Requested by')),
                TextColumn::make('assignee.name')
                    ->label(__('Technician'))
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(),
                TextColumn::make('created_at')
                    ->label(__('Reported'))
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
            ]);
    }
}
