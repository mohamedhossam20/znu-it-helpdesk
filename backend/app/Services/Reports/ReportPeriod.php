<?php

namespace App\Services\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Turns the reports filter (from / to / building) into a TicketReport,
 * the same way for the page, the Excel export and the print view.
 */
final class ReportPeriod
{
    public const MAX_DAYS = 366;

    /**
     * @param  array{from?: ?string, to?: ?string, building_id?: int|string|null}  $filters
     */
    public static function report(array $filters): TicketReport
    {
        $timezone = config('helpdesk.working_hours.timezone');
        $to = filled($filters['to'] ?? null) ? CarbonImmutable::parse($filters['to'], $timezone)->endOfDay() : CarbonImmutable::now($timezone)->endOfDay();
        $from = filled($filters['from'] ?? null) ? CarbonImmutable::parse($filters['from'], $timezone)->startOfDay() : $to->subDays(29)->startOfDay();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        if ($from->diffInDays($to) > self::MAX_DAYS) {
            $from = $to->subDays(self::MAX_DAYS)->startOfDay();
        }

        $building = filled($filters['building_id'] ?? null) ? (int) $filters['building_id'] : null;

        return new TicketReport($from->utc(), $to->utc(), $building);
    }

    public static function fromRequest(Request $request): TicketReport
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'building_id' => ['nullable', 'integer'],
        ]);

        return self::report($data);
    }
}
