<?php

namespace Tests\Feature\Reports;

use App\Filament\Widgets\HotspotsWidget;
use App\Filament\Widgets\PerformanceStats;
use App\Filament\Widgets\TicketQueueStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\CreatesTickets;
use Tests\TestCase;

class DashboardAndExportsTest extends TestCase
{
    use CreatesTickets, RefreshDatabase;

    protected $seed = true;

    public function test_supervisor_dashboard_shows_queue_and_performance(): void
    {
        $this->pcTicket();
        $this->projectorTicket(classInProgress: true);
        $this->actingAs($this->user('supervisor'));

        $this->get('/admin')->assertOk();

        Livewire::test(TicketQueueStats::class)->assertSee(__('Unassigned'))->assertSee(__(':count urgent', ['count' => 1]));
        Livewire::test(PerformanceStats::class)->assertOk()->assertSee(__('Fixed on time'));
        Livewire::test(HotspotsWidget::class)->assertSee('204')->assertSee('PC-0123')->assertSee('فني الأجهزة');
    }

    public function test_technician_sees_only_their_queue_numbers(): void
    {
        $this->pcTicket();
        $this->projectorTicket();
        $this->actingAs($this->user('tech_pc'));

        $this->assertFalse(PerformanceStats::canView());
        $this->assertFalse(HotspotsWidget::canView());
        $this->assertTrue(TicketQueueStats::canView());

        // Only the PC ticket is in this technician's queue.
        $this->assertSame(1, \App\Filament\Resources\Tickets\TicketResource::getEloquentQuery()->open()->unassigned()->count());
        Livewire::test(TicketQueueStats::class)->assertSeeInOrder([__('Unassigned'), '1', __('My open tickets'), '0']);
        $this->get('/admin/reports')->assertForbidden();
    }

    public function test_reports_page_and_exports(): void
    {
        $this->pcTicket();
        $this->actingAs($this->user('supervisor'));

        $this->get('/admin/reports')->assertOk()->assertSee(__('By problem type'));

        $this->get(route('admin.reports.print', ['from' => now()->subDays(7)->toDateString()]))
            ->assertOk()
            ->assertSee('كمبيوتر / لابتوب')
            ->assertSee(config('helpdesk.organization_name'));

        Excel::fake();
        $this->get(route('admin.reports.excel'))->assertOk();
        Excel::assertDownloaded(
            'it-report-'.now('Africa/Cairo')->subDays(29)->toDateString().'_'.now('Africa/Cairo')->toDateString().'.xlsx',
            fn ($export) => count($export->sheets()) === 7,
        );
    }

    public function test_requesters_cannot_export(): void
    {
        $this->actingAs($this->user('staff'));

        $this->get(route('admin.reports.excel'))->assertForbidden();
        $this->get(route('admin.reports.print'))->assertForbidden();
    }
}
