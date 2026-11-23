<?php

namespace App\Services\Reports;

use App\Enums\TicketStatus;
use App\Models\Asset;
use App\Models\Room;
use App\Models\Ticket;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Numbers for the dashboard and the reports page, for tickets reported in
 * a period. Durations are wall-clock hours (what the requester felt), and
 * "on time" compares the resolution with the working-hours due time.
 *
 * Aggregation happens in PHP over the period's tickets: a university
 * produces a few thousand tickets a year, and this keeps the queries
 * identical on SQLite (dev/tests) and MySQL (production).
 */
class TicketReport
{
    /** @var Collection<int, Ticket>|null */
    private ?Collection $tickets = null;

    public function __construct(
        public readonly CarbonInterface $from,
        public readonly CarbonInterface $to,
        public readonly ?int $buildingId = null,
    ) {}

    public static function lastDays(int $days, ?int $buildingId = null): self
    {
        return new self(now()->subDays($days - 1)->startOfDay(), now()->endOfDay(), $buildingId);
    }

    /**
     * @return Collection<int, Ticket>
     */
    public function tickets(): Collection
    {
        return $this->tickets ??= $this->query()
            ->with(['category.parent', 'assignee', 'requester', 'room.floor.building', 'asset.type'])
            ->get();
    }

    /**
     * @return Builder<Ticket>
     */
    public function query(): Builder
    {
        return Ticket::query()
            ->whereBetween('created_at', [$this->from, $this->to])
            ->when($this->buildingId, fn (Builder $q, int $id) => $q->whereHas('room.floor', fn (Builder $f) => $f->where('building_id', $id)));
    }

    /**
     * @return array{total: int, open: int, resolved: int, cancelled: int, overdue_open: int, avg_response_hours: ?float, avg_resolution_hours: ?float, on_time_rate: ?float, avg_rating: ?float, rated: int}
     */
    public function summary(): array
    {
        return $this->metrics($this->tickets());
    }

    /**
     * Per main problem type, busiest first.
     *
     * @return list<array<string, mixed>>
     */
    public function byCategory(): array
    {
        return $this->groupedBy(fn (Ticket $t) => $t->category->parent ?? $t->category, fn ($category) => $category->name_ar);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function byBuilding(): array
    {
        return $this->groupedBy(fn (Ticket $t) => $t->room->floor->building, fn ($building) => $building->name_ar);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function byTechnician(): array
    {
        return $this->groupedBy(fn (Ticket $t) => $t->assignee, fn (?User $user) => $user?->name ?? __('Unassigned'));
    }

    /**
     * Places and devices with the most faults: candidates for replacement
     * or a closer look at cabling and power.
     *
     * @return list<array{label: string, location: string, count: int}>
     */
    public function topRooms(int $limit = 10): array
    {
        return $this->tickets()
            ->groupBy('room_id')
            ->map(function (Collection $group): array {
                /** @var Room $room */
                $room = $group->first()->room;

                return ['label' => "{$room->code} — {$room->name_ar}", 'location' => "{$room->floor->building->name_ar} › {$room->floor->name_ar}", 'count' => $group->count()];
            })
            ->sortByDesc('count')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return list<array{label: string, location: string, count: int}>
     */
    public function topAssets(int $limit = 10): array
    {
        return $this->tickets()
            ->whereNotNull('asset_id')
            ->groupBy('asset_id')
            ->map(function (Collection $group): array {
                /** @var Asset $asset */
                $asset = $group->first()->asset;
                $ticket = $group->first();

                return [
                    'label' => trim("{$asset->asset_tag} — {$asset->type->name_ar} {$asset->brand} {$asset->model}"),
                    'location' => $ticket->room->name_ar,
                    'count' => $group->count(),
                ];
            })
            ->sortByDesc('count')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Reported vs resolved per day, for the trend chart.
     *
     * @return array{labels: list<string>, created: list<int>, resolved: list<int>}
     */
    public function daily(): array
    {
        // Days are Cairo days: a ticket at 01:00 local time belongs to that morning, not the UTC evening before.
        $tz = config('helpdesk.working_hours.timezone');
        $localDay = fn ($at) => $at->copy()->setTimezone($tz)->toDateString();

        $labels = $created = $resolved = [];
        $resolvedByDay = Ticket::query()
            ->whereBetween('resolved_at', [$this->from, $this->to])
            ->when($this->buildingId, fn (Builder $q, int $id) => $q->whereHas('room.floor', fn (Builder $f) => $f->where('building_id', $id)))
            ->pluck('resolved_at')
            ->countBy($localDay);
        $createdByDay = $this->tickets()->countBy(fn (Ticket $t) => $localDay($t->created_at));

        $last = $this->to->copy()->setTimezone($tz);
        for ($day = $this->from->copy()->setTimezone($tz)->startOfDay(); $day->lte($last); $day = $day->addDay()) {
            $key = $day->toDateString();
            $labels[] = $day->format('d/m');
            $created[] = $createdByDay[$key] ?? 0;
            $resolved[] = $resolvedByDay[$key] ?? 0;
        }

        return ['labels' => $labels, 'created' => $created, 'resolved' => $resolved];
    }

    /**
     * @param  callable(Ticket): mixed  $key
     * @param  callable(mixed): string  $label
     * @return list<array<string, mixed>>
     */
    private function groupedBy(callable $key, callable $label): array
    {
        return $this->tickets()
            ->groupBy(fn (Ticket $t) => $key($t)?->getKey() ?? 0)
            ->map(fn (Collection $group) => ['label' => $label($key($group->first())), ...$this->metrics($group)])
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Ticket>  $tickets
     * @return array{total: int, open: int, resolved: int, cancelled: int, overdue_open: int, avg_response_hours: ?float, avg_resolution_hours: ?float, on_time_rate: ?float, avg_rating: ?float, rated: int}
     */
    private function metrics(Collection $tickets): array
    {
        $resolved = $tickets->whereNotNull('resolved_at');
        $responded = $tickets->whereNotNull('first_response_at');
        $rated = $tickets->whereNotNull('rating');
        $withDue = $resolved->whereNotNull('due_at');

        return [
            'total' => $tickets->count(),
            'open' => $tickets->filter(fn (Ticket $t) => $t->status->isOpen())->count(),
            'resolved' => $resolved->count(),
            'cancelled' => $tickets->where('status', TicketStatus::Cancelled)->count(),
            'overdue_open' => $tickets->filter(fn (Ticket $t) => $t->status->isOpen() && $t->due_at?->isPast())->count(),
            'avg_response_hours' => $this->averageHours($responded, 'first_response_at'),
            'avg_resolution_hours' => $this->averageHours($resolved, 'resolved_at'),
            'on_time_rate' => $withDue->isEmpty() ? null : round($withDue->filter(fn (Ticket $t) => $t->resolved_at->lte($t->due_at))->count() / $withDue->count() * 100, 1),
            'avg_rating' => $rated->isEmpty() ? null : round($rated->avg('rating'), 1),
            'rated' => $rated->count(),
        ];
    }

    /**
     * @param  Collection<int, Ticket>  $tickets
     */
    private function averageHours(Collection $tickets, string $until): ?float
    {
        if ($tickets->isEmpty()) {
            return null;
        }

        return round($tickets->avg(fn (Ticket $t) => $t->created_at->diffInMinutes($t->{$until})) / 60, 1);
    }
}
