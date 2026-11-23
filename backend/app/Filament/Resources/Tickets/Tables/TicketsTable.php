<?php

namespace App\Filament\Resources\Tickets\Tables;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Tickets\Actions\TicketActions;
use App\Models\Building;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TicketsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['category.parent', 'requester', 'assignee', 'asset']))
            ->defaultSort('created_at', 'desc')
            ->poll('30s')
            ->striped()
            ->columns([
                TextColumn::make('number')
                    ->label(__('Ticket #'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Ticket $record) => $record->created_at->diffForHumans()),
                TextColumn::make('category_id')
                    ->label(__('Problem'))
                    ->formatStateUsing(fn (Ticket $record) => $record->category->fullName())
                    ->description(fn (Ticket $record) => $record->snapshotLocationLabel())
                    ->searchable(query: fn (Builder $query, string $search) => $query->where('title', 'like', "%{$search}%"))
                    ->wrap(),
                TextColumn::make('asset.asset_tag')
                    ->label(__('Device'))
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('requester.name')
                    ->label(__('Requested by'))
                    ->description(fn (Ticket $record) => $record->requester->extension ? __('Ext.').' '.$record->requester->extension : null)
                    ->searchable(),
                TextColumn::make('priority')
                    ->label(__('Priority'))
                    ->badge()
                    ->icon(fn (Ticket $record) => $record->is_class_in_progress ? Heroicon::OutlinedAcademicCap : null)
                    ->tooltip(fn (Ticket $record) => $record->is_class_in_progress ? __('A class is waiting') : null)
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('assignee.name')
                    ->label(__('Technician'))
                    ->placeholder(__('Unassigned'))
                    ->color(fn (Ticket $record) => $record->assignee_id ? null : 'danger'),
                TextColumn::make('due_at')
                    ->label(__('Due'))
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable()
                    ->color(fn (Ticket $record) => $record->status->isOpen() && $record->due_at?->isPast() ? 'danger' : 'gray')
                    ->icon(fn (Ticket $record) => $record->status->isOpen() && $record->due_at?->isPast() ? Heroicon::OutlinedExclamationTriangle : null),
                IconColumn::make('rating')
                    ->label(__('Rating'))
                    ->icon(fn (?int $state) => $state ? Heroicon::Star : null)
                    ->color(fn (?int $state) => match (true) {
                        $state === null => null,
                        $state >= 4 => 'success',
                        $state === 3 => 'warning',
                        default => 'danger',
                    })
                    ->tooltip(fn (?int $state) => $state ? "{$state}/5" : null)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(TicketStatus::class)
                    ->multiple(),
                SelectFilter::make('priority')
                    ->label(__('Priority'))
                    ->options(TicketPriority::class)
                    ->multiple(),
                SelectFilter::make('category')
                    ->label(__('Problem type'))
                    ->options(fn () => Category::whereNull('parent_id')->orderBy('sort_order')->pluck('name_ar', 'id'))
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $q, $id) => $q->whereHas('category', fn (Builder $c) => $c->whereKey($id)->orWhere('parent_id', $id)),
                    )),
                SelectFilter::make('building')
                    ->label(__('Building'))
                    ->options(fn () => Building::orderBy('code')->pluck('name_ar', 'id'))
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $q, $id) => $q->whereHas('room.floor', fn (Builder $f) => $f->where('building_id', $id)),
                    )),
                SelectFilter::make('assignee_id')
                    ->label(__('Technician'))
                    ->options(fn () => User::role(UserRole::adminPanelRoles())->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                    ->searchable(),
                Filter::make('class_in_progress')
                    ->label(__('A class is waiting'))
                    ->toggle()
                    ->query(fn (Builder $query) => $query->where('is_class_in_progress', true)),
            ])
            ->recordActions([
                TicketActions::take(),
            ])
            ->recordUrl(fn (Ticket $record) => route('filament.admin.resources.tickets.view', $record))
            ->emptyStateHeading(__('No tickets here'))
            ->emptyStateIcon(Heroicon::OutlinedCheckCircle);
    }
}
