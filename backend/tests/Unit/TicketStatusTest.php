<?php

namespace Tests\Unit;

use App\Enums\TicketStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TicketStatusTest extends TestCase
{
    /**
     * @return array<string, array{TicketStatus, TicketStatus, bool}>
     */
    public static function transitions(): array
    {
        return [
            'new to acknowledged' => [TicketStatus::New, TicketStatus::Acknowledged, true],
            'new can be cancelled' => [TicketStatus::New, TicketStatus::Cancelled, true],
            'acknowledged cannot be cancelled' => [TicketStatus::Acknowledged, TicketStatus::Cancelled, false],
            'new cannot jump to closed' => [TicketStatus::New, TicketStatus::Closed, false],
            'resolved can be reopened' => [TicketStatus::Resolved, TicketStatus::Reopened, true],
            'resolved can be closed' => [TicketStatus::Resolved, TicketStatus::Closed, true],
            'reopened goes back to work' => [TicketStatus::Reopened, TicketStatus::InProgress, true],
            'closed is final' => [TicketStatus::Closed, TicketStatus::Reopened, false],
            'cancelled is final' => [TicketStatus::Cancelled, TicketStatus::New, false],
        ];
    }

    #[DataProvider('transitions')]
    public function test_transition_rules(TicketStatus $from, TicketStatus $to, bool $allowed): void
    {
        $this->assertSame($allowed, $from->canTransitionTo($to));
    }

    public function test_open_statuses_exclude_resolved_closed_and_cancelled(): void
    {
        $this->assertEqualsCanonicalizing(
            [TicketStatus::New, TicketStatus::Acknowledged, TicketStatus::InProgress, TicketStatus::OnHold, TicketStatus::Reopened],
            TicketStatus::openStatuses(),
        );
    }
}
