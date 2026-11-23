<?php

namespace Tests\Feature\Tickets;

use App\Actions\Tickets\CreateTicket;
use App\Enums\AssetStatus;
use App\Enums\TicketPriority;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Events\TicketAssigned;
use App\Events\TicketCreated;
use App\Models\Asset;
use App\Models\AssetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesTickets;
use Tests\TestCase;

class CreateTicketTest extends TestCase
{
    use CreatesTickets, RefreshDatabase;

    protected $seed = true;

    public function test_fixed_location_ticket_takes_room_from_the_device(): void
    {
        Event::fake([TicketCreated::class, TicketAssigned::class]);

        $ticket = $this->pcTicket();

        $this->assertMatchesRegularExpression('/^IT-\d{4}-000001$/', $ticket->number);
        $this->assertSame($this->room('204')->id, $ticket->room_id);
        $this->assertSame('MAIN', $ticket->location_snapshot['building']['code']);
        $this->assertSame('204', $ticket->location_snapshot['room']['code']);
        $this->assertSame(TicketStatus::New, $ticket->status);
        $this->assertSame(TicketPriority::Normal, $ticket->priority);
        $this->assertNotNull($ticket->due_at);
        $this->assertStringContainsString('PC-0123', $ticket->title);
        $this->assertCount(1, $ticket->statusChanges);
        $this->assertNull($ticket->assignee_id);

        Event::assertDispatched(TicketCreated::class);
        Event::assertNotDispatched(TicketAssigned::class);
    }

    public function test_my_device_without_device_uses_the_fixed_office(): void
    {
        $ticket = app(CreateTicket::class)->handle(
            requester: $this->user('staff'),
            createdBy: $this->user('staff'),
            source: TicketSource::MyDevice,
            category: $this->category('Network & internet', 'No internet'),
        );

        $this->assertSame($this->room('204')->id, $ticket->room_id);
        // Network & internet defaults to high priority.
        $this->assertSame(TicketPriority::High, $ticket->priority);
    }

    public function test_person_without_fixed_office_must_choose_a_place(): void
    {
        $this->expectException(ValidationException::class);

        app(CreateTicket::class)->handle(
            requester: $this->user('doctor'),
            createdBy: $this->user('doctor'),
            source: TicketSource::MyDevice,
            category: $this->category('Other'),
        );
    }

    public function test_class_in_progress_makes_it_urgent(): void
    {
        $ticket = $this->projectorTicket(classInProgress: true);

        $this->assertSame(TicketPriority::Urgent, $ticket->priority);
        $this->assertTrue($ticket->is_class_in_progress);
    }

    public function test_device_from_another_room_is_rejected(): void
    {
        try {
            app(CreateTicket::class)->handle(
                requester: $this->user('doctor'),
                createdBy: $this->user('doctor'),
                source: TicketSource::OtherLocation,
                category: $this->category('Screens & display', 'Projector'),
                room: $this->room('210'),
                asset: $this->asset('PJ-0010'),
            );
            $this->fail('Expected a validation error');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('asset_id', $e->errors());
        }
    }

    public function test_own_laptop_can_be_reported_from_any_room(): void
    {
        $laptop = Asset::create([
            'asset_tag' => 'LT-0001',
            'asset_type_id' => AssetType::firstWhere('code', 'LAPTOP')->id,
            'room_id' => $this->room('204')->id,
            'assigned_user_id' => $this->user('staff')->id,
        ]);

        $ticket = app(CreateTicket::class)->handle(
            requester: $this->user('staff'),
            createdBy: $this->user('staff'),
            source: TicketSource::OtherLocation,
            category: $this->category('Screens & display', 'No picture from laptop'),
            room: $this->room('210'),
            asset: $laptop,
        );

        $this->assertSame($this->room('210')->id, $ticket->room_id);
    }

    public function test_retired_device_is_rejected(): void
    {
        $this->asset('PJ-0010')->update(['status' => AssetStatus::Retired]);

        $this->expectException(ValidationException::class);
        $this->projectorTicket();
    }

    public function test_main_type_with_sub_types_cannot_be_chosen_directly(): void
    {
        $this->expectException(ValidationException::class);

        app(CreateTicket::class)->handle(
            requester: $this->user('doctor'),
            createdBy: $this->user('doctor'),
            source: TicketSource::OtherLocation,
            category: $this->category('Audio'),
            room: $this->room('105'),
        );
    }

    public function test_category_mode_assigns_least_busy_technician(): void
    {
        config(['helpdesk.assignment_mode' => 'category']);
        Event::fake([TicketAssigned::class]);

        $first = $this->projectorTicket();
        $this->assertSame($this->user('tech_av')->id, $first->assignee_id);
        Event::assertDispatched(TicketAssigned::class);

        // Add a second AV technician with no load: the next ticket goes to them.
        $this->category('Screens & display')->technicians()->attach($this->user('tech_pc')->id);
        $second = $this->projectorTicket();

        $this->assertSame($this->user('tech_pc')->id, $second->assignee_id);
    }

    public function test_manual_mode_leaves_ticket_unassigned(): void
    {
        config(['helpdesk.assignment_mode' => 'manual']);

        $this->assertNull($this->projectorTicket()->assignee_id);
    }
}
