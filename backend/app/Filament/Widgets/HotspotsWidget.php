<?php

namespace App\Filament\Widgets;

use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Reports\TicketReport;
use Filament\Widgets\Widget;

/**
 * Where the problems keep happening, and who is carrying the load.
 */
class HotspotsWidget extends Widget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.hotspots';

    private const DAYS = 30;

    public static function canView(): bool
    {
        return auth()->user()?->can('reports.view') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $report = TicketReport::lastDays(self::DAYS);

        $load = User::role([UserRole::Technician->value, UserRole::ItSupervisor->value])
            ->where('is_active', true)
            ->withCount([
                'assignedTickets as open_count' => fn ($q) => $q->whereIn('status', TicketStatus::openStatuses()),
                'assignedTickets as overdue_count' => fn ($q) => $q->overdue(),
            ])
            ->orderByDesc('open_count')
            ->get();

        return [
            'days' => self::DAYS,
            'rooms' => $report->topRooms(5),
            'assets' => $report->topAssets(5),
            'load' => $load,
        ];
    }
}
