<?php

namespace App\Services;

use App\Enums\TicketPriority;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Adds working minutes to a moment, skipping nights, weekends and holidays,
 * so a ticket opened Thursday at 15:30 with a 2-hour target is due Sunday
 * at 09:30, not Thursday at 17:30.
 */
class DueDateCalculator
{
    private const MAX_DAYS_SCANNED = 366;

    public function resolutionDue(TicketPriority $priority, DateTimeInterface $from): CarbonImmutable
    {
        return $this->addWorkingMinutes($from, (int) config("helpdesk.sla.{$priority->value}.resolution"));
    }

    public function responseDue(TicketPriority $priority, DateTimeInterface $from): CarbonImmutable
    {
        return $this->addWorkingMinutes($from, (int) config("helpdesk.sla.{$priority->value}.response"));
    }

    public function addWorkingMinutes(DateTimeInterface $from, int $minutes): CarbonImmutable
    {
        $config = config('helpdesk.working_hours');
        $cursor = CarbonImmutable::instance($from)->setTimezone($config['timezone']);
        $remaining = max(0, $minutes);

        for ($day = 0; $day < self::MAX_DAYS_SCANNED; $day++) {
            [$opens, $closes] = $this->workingWindow($cursor, $config);

            if ($opens !== null) {
                $start = $cursor->greaterThan($opens) ? $cursor : $opens;

                if ($start->lessThan($closes)) {
                    $available = (int) $start->diffInMinutes($closes);

                    if ($remaining <= $available) {
                        return $start->addMinutes($remaining)->utc();
                    }

                    $remaining -= $available;
                }
            }

            $cursor = $cursor->addDay()->startOfDay();
        }

        // Misconfigured calendar (no working days): fall back to wall-clock time.
        return CarbonImmutable::instance($from)->addMinutes($minutes)->utc();
    }

    /**
     * @param  array{days: list<int>, start: string, end: string, holidays: list<string>}  $config
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    private function workingWindow(CarbonImmutable $day, array $config): array
    {
        if (! in_array($day->dayOfWeek, $config['days'], true) || in_array($day->toDateString(), $config['holidays'], true)) {
            return [null, null];
        }

        return [
            $day->setTimeFromTimeString($config['start']),
            $day->setTimeFromTimeString($config['end']),
        ];
    }
}
