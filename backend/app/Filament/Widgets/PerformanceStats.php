<?php

namespace App\Filament\Widgets;

use App\Services\Reports\TicketReport;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PerformanceStats extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    private const DAYS = 30;

    public static function canView(): bool
    {
        return auth()->user()?->can('reports.view') ?? false;
    }

    protected function getHeading(): ?string
    {
        return __('Last :days days', ['days' => self::DAYS]);
    }

    protected function getStats(): array
    {
        $summary = TicketReport::lastDays(self::DAYS)->summary();
        $hours = fn (?float $value) => $value === null ? '—' : __(':value h', ['value' => $value]);

        return [
            Stat::make(__('Tickets reported'), $summary['total'])
                ->description(__(':count still open', ['count' => $summary['open']]))
                ->descriptionIcon(Heroicon::OutlinedTicket),
            Stat::make(__('Average first response'), $hours($summary['avg_response_hours']))
                ->description(__('From report to acknowledgement'))
                ->descriptionIcon(Heroicon::OutlinedHandRaised),
            Stat::make(__('Average time to fix'), $hours($summary['avg_resolution_hours']))
                ->descriptionIcon(Heroicon::OutlinedWrenchScrewdriver),
            Stat::make(__('Fixed on time'), $summary['on_time_rate'] === null ? '—' : "{$summary['on_time_rate']}%")
                ->description(__('Resolved before the due time'))
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color(match (true) {
                    $summary['on_time_rate'] === null => 'gray',
                    $summary['on_time_rate'] >= 90 => 'success',
                    $summary['on_time_rate'] >= 70 => 'warning',
                    default => 'danger',
                }),
            Stat::make(__('Satisfaction'), $summary['avg_rating'] === null ? '—' : "{$summary['avg_rating']} / 5")
                ->description(__(':count ratings', ['count' => $summary['rated']]))
                ->descriptionIcon(Heroicon::OutlinedStar)
                ->color('warning'),
        ];
    }
}
