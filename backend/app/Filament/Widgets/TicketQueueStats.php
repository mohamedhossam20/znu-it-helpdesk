<?php

namespace App\Filament\Widgets;

use App\Enums\TicketStatus;
use App\Filament\Resources\Tickets\TicketResource;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * What needs attention right now, counted over the tickets this person may see.
 */
class TicketQueueStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $visible = fn () => TicketResource::getEloquentQuery();
        $list = fn (string $tab) => TicketResource::getUrl('index', ['tab' => $tab]);

        $unassigned = $visible()->open()->unassigned()->count();
        $urgentUnassigned = $visible()->open()->unassigned()->where('priority', 'urgent')->count();
        $mine = $visible()->open()->where('assignee_id', auth()->id())->count();
        $overdue = $visible()->overdue()->count();
        $resolvedToday = $visible()->where('resolved_at', '>=', now()->startOfDay())->count();
        $waitingConfirmation = $visible()->where('status', TicketStatus::Resolved)->count();

        return [
            Stat::make(__('Unassigned'), $unassigned)
                ->description($urgentUnassigned ? __(':count urgent', ['count' => $urgentUnassigned]) : __('Waiting for a technician'))
                ->descriptionIcon(Heroicon::OutlinedInbox)
                ->color($urgentUnassigned ? 'danger' : ($unassigned ? 'warning' : 'success'))
                ->url($list('unassigned')),
            Stat::make(__('My open tickets'), $mine)
                ->descriptionIcon(Heroicon::OutlinedUser)
                ->description(__('Assigned to you'))
                ->color('info')
                ->url($list('mine')),
            Stat::make(__('Overdue'), $overdue)
                ->description(__('Past their due time'))
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color($overdue ? 'danger' : 'success')
                ->url($list('overdue')),
            Stat::make(__('Resolved today'), $resolvedToday)
                ->description(__(':count waiting for requester confirmation', ['count' => $waitingConfirmation]))
                ->descriptionIcon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->url($list('done')),
        ];
    }
}
