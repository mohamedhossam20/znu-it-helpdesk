<?php

namespace Tests\Feature\Tickets;

use App\Enums\TicketPriority;
use App\Services\DueDateCalculator;
use App\Services\TicketNumberGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class TicketServicesTest extends TestCase
{
    use RefreshDatabase;

    private function cairo(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse($time, 'Africa/Cairo');
    }

    public function test_due_date_skips_the_weekend(): void
    {
        // Thursday 2026-11-26 15:30, 2 working hours → Sunday 09:30.
        $due = app(DueDateCalculator::class)->addWorkingMinutes($this->cairo('2026-11-26 15:30'), 120);

        $this->assertSame('2026-11-29 09:30', $due->setTimezone('Africa/Cairo')->format('Y-m-d H:i'));
    }

    public function test_due_date_starts_counting_at_opening_time(): void
    {
        $due = app(DueDateCalculator::class)->addWorkingMinutes($this->cairo('2026-11-23 06:10'), 15);

        $this->assertSame('2026-11-23 08:15', $due->setTimezone('Africa/Cairo')->format('Y-m-d H:i'));
    }

    public function test_due_date_skips_holidays(): void
    {
        config(['helpdesk.working_hours.holidays' => ['2026-11-24']]);

        $due = app(DueDateCalculator::class)->addWorkingMinutes($this->cairo('2026-11-23 15:00'), 120);

        $this->assertSame('2026-11-25 09:00', $due->setTimezone('Africa/Cairo')->format('Y-m-d H:i'));
    }

    public function test_resolution_target_uses_priority(): void
    {
        $calculator = app(DueDateCalculator::class);
        $from = $this->cairo('2026-11-23 09:00');

        $this->assertSame('11:00', $calculator->resolutionDue(TicketPriority::Urgent, $from)->setTimezone('Africa/Cairo')->format('H:i'));
        $this->assertSame('09:15', $calculator->responseDue(TicketPriority::Urgent, $from)->setTimezone('Africa/Cairo')->format('H:i'));
    }

    public function test_ticket_numbers_are_sequential_per_year(): void
    {
        $generator = app(TicketNumberGenerator::class);

        Date::setTestNow('2026-12-31 10:00:00');
        $this->assertSame('IT-2026-000001', $generator->next());
        $this->assertSame('IT-2026-000002', $generator->next());

        Date::setTestNow('2027-01-01 10:00:00');
        $this->assertSame('IT-2027-000001', $generator->next());
    }
}
