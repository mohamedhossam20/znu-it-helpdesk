<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Enums\TicketStatus;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Ticket;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListTickets extends ListRecords
{
    protected static string $resource = TicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('Log a ticket for someone'))
                ->icon(Heroicon::OutlinedPhone),
        ];
    }

    public function getTabs(): array
    {
        $count = fn (callable $scope): ?int => ($n = $scope(TicketResource::getEloquentQuery())->count()) > 0 ? $n : null;

        $mine = fn (Builder $query) => $query->open()->where('assignee_id', auth()->id());
        $unassigned = fn (Builder $query) => $query->open()->unassigned();
        $overdue = fn (Builder $query) => $query->overdue();

        return [
            'mine' => Tab::make(__('My tickets'))
                ->icon(Heroicon::OutlinedUser)
                ->badge($count($mine))
                ->modifyQueryUsing($mine),
            'unassigned' => Tab::make(__('Unassigned'))
                ->icon(Heroicon::OutlinedInbox)
                ->badge($count($unassigned))
                ->badgeColor('danger')
                ->modifyQueryUsing($unassigned),
            'open' => Tab::make(__('All open'))
                ->modifyQueryUsing(fn (Builder $query) => $query->open()),
            'overdue' => Tab::make(__('Overdue'))
                ->icon(Heroicon::OutlinedClock)
                ->badge($count($overdue))
                ->badgeColor('warning')
                ->modifyQueryUsing($overdue),
            'done' => Tab::make(__('Resolved & closed'))
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotIn('status', TicketStatus::openStatuses())),
            'all' => Tab::make(__('All')),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        $hasOwnWork = TicketResource::getEloquentQuery()->open()->where('assignee_id', auth()->id())->exists();

        return $hasOwnWork ? 'mine' : 'unassigned';
    }
}
