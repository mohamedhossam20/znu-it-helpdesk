<?php

namespace Tests\Feature\Reports;

use App\Enums\TicketStatus;
use App\Models\Building;
use App\Models\Ticket;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\TicketReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\Concerns\CreatesTickets;
use Tests\TestCase;

class TicketReportTest extends TestCase
{
    use CreatesTickets, RefreshDatabase;

    protected $seed = true;

    /**
     * Three tickets with known timings, all reported on 2026-11-10:
     *  - PC (main building): response 1h, fixed after 4h, before due, rated 5
     *  - PC (main building): response 3h, fixed after 10h, after due, rated 3
     *  - projector (engineering): still open and overdue
     */
    private function seedKnownTickets(): void
    {
        Date::setTestNow('2026-11-10 08:00:00');

        $fast = $this->pcTicket();
        $fast->forceFill([
            'status' => TicketStatus::Closed, 'assignee_id' => $this->user('tech_pc')->id,
            'first_response_at' => '2026-11-10 09:00:00', 'resolved_at' => '2026-11-10 12:00:00',
            'due_at' => '2026-11-10 16:00:00', 'rating' => 5,
        ])->save();

        $slow = $this->pcTicket();
        $slow->forceFill([
            'status' => TicketStatus::Closed, 'assignee_id' => $this->user('tech_pc')->id,
            'first_response_at' => '2026-11-10 11:00:00', 'resolved_at' => '2026-11-10 18:00:00',
            'due_at' => '2026-11-10 16:00:00', 'rating' => 3,
        ])->save();

        $open = $this->projectorTicket();
        $open->forceFill(['due_at' => '2026-11-10 10:00:00'])->save();

        Date::setTestNow('2026-11-20 12:00:00');
    }

    private function november(?int $buildingId = null): TicketReport
    {
        return ReportPeriod::report(['from' => '2026-11-01', 'to' => '2026-11-30', 'building_id' => $buildingId]);
    }

    public function test_summary_numbers(): void
    {
        $this->seedKnownTickets();

        $summary = $this->november()->summary();

        $this->assertSame(3, $summary['total']);
        $this->assertSame(1, $summary['open']);
        $this->assertSame(2, $summary['resolved']);
        $this->assertSame(1, $summary['overdue_open']);
        $this->assertSame(2.0, $summary['avg_response_hours']);
        $this->assertSame(7.0, $summary['avg_resolution_hours']);
        $this->assertSame(50.0, $summary['on_time_rate']);
        $this->assertSame(4.0, $summary['avg_rating']);
        $this->assertSame(2, $summary['rated']);
    }

    public function test_breakdowns_and_hotspots(): void
    {
        $this->seedKnownTickets();
        $report = $this->november();

        $byCategory = collect($report->byCategory())->keyBy('label');
        $this->assertSame(2, $byCategory['كمبيوتر / لابتوب']['total']);
        $this->assertSame(1, $byCategory['شاشات وعرض']['total']);

        $byTechnician = collect($report->byTechnician())->keyBy('label');
        $this->assertSame(2, $byTechnician['فني الأجهزة']['resolved']);
        $this->assertSame(1, $byTechnician[__('Unassigned')]['open']);

        $this->assertSame(2, $report->topRooms()[0]['count']);
        $this->assertStringStartsWith('204', $report->topRooms()[0]['label']);
        $this->assertStringStartsWith('PC-0123', $report->topAssets()[0]['label']);
    }

    public function test_building_filter_and_period(): void
    {
        $this->seedKnownTickets();

        $engineering = $this->november(Building::firstWhere('code', 'ENG')->id);
        $this->assertSame(1, $engineering->summary()['total']);

        $october = ReportPeriod::report(['from' => '2026-10-01', 'to' => '2026-10-31']);
        $this->assertSame(0, $october->summary()['total']);
        $this->assertNull($october->summary()['avg_rating']);
    }

    public function test_daily_series_counts_reports_and_fixes(): void
    {
        $this->seedKnownTickets();

        $daily = ReportPeriod::report(['from' => '2026-11-09', 'to' => '2026-11-11'])->daily();

        $this->assertSame(['09/11', '10/11', '11/11'], $daily['labels']);
        $this->assertSame([0, 3, 0], $daily['created']);
        $this->assertSame([0, 2, 0], $daily['resolved']);
    }

    public function test_reversed_dates_are_swapped(): void
    {
        $report = ReportPeriod::report(['from' => '2026-11-30', 'to' => '2026-11-01']);

        $this->assertTrue($report->from->lessThan($report->to));
    }
}
