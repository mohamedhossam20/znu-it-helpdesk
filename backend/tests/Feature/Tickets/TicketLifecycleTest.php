<?php

namespace Tests\Feature\Tickets;

use App\Actions\Tickets\AddTicketComment;
use App\Actions\Tickets\AssignTicket;
use App\Actions\Tickets\ChangeTicketStatus;
use App\Actions\Tickets\RateTicket;
use App\Enums\TicketStatus;
use App\Events\TicketAssigned;
use App\Events\TicketStatusChanged;
use App\Models\Ticket;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesTickets;
use Tests\TestCase;

class TicketLifecycleTest extends TestCase
{
    use CreatesTickets, RefreshDatabase;

    protected $seed = true;

    private function move(Ticket $ticket, TicketStatus $to, string $who, ?string $note = null): Ticket
    {
        return app(ChangeTicketStatus::class)->handle($ticket, $to, $this->user($who), $note);
    }

    public function test_full_happy_path(): void
    {
        Event::fake([TicketStatusChanged::class, TicketAssigned::class]);
        $ticket = $this->pcTicket();

        $ticket = $this->move($ticket, TicketStatus::Acknowledged, 'tech_pc');
        $this->assertSame($this->user('tech_pc')->id, $ticket->assignee_id, 'starting work on an unassigned ticket takes it');
        $this->assertNotNull($ticket->first_response_at);
        Event::assertDispatched(TicketAssigned::class);

        $this->move($ticket, TicketStatus::InProgress, 'tech_pc');
        $ticket = $this->move($ticket, TicketStatus::Resolved, 'tech_pc');
        $this->assertNotNull($ticket->resolved_at);

        $ticket = $this->move($ticket, TicketStatus::Closed, 'staff');
        $this->assertNotNull($ticket->closed_at);

        $this->assertSame(
            ['new', 'acknowledged', 'in_progress', 'resolved', 'closed'],
            $ticket->statusChanges()->pluck('to_status')->map->value->all(),
        );
        Event::assertDispatchedTimes(TicketStatusChanged::class, 4);
    }

    public function test_technician_of_another_specialty_cannot_work_on_it(): void
    {
        $this->expectException(AuthorizationException::class);

        $this->move($this->pcTicket(), TicketStatus::Acknowledged, 'tech_av');
    }

    public function test_technician_cannot_take_a_ticket_assigned_to_someone_else(): void
    {
        $ticket = $this->pcTicket();
        app(AssignTicket::class)->handle($ticket, $this->user('supervisor'), $this->user('supervisor'));

        $this->expectException(AuthorizationException::class);
        $this->move($ticket->fresh(), TicketStatus::InProgress, 'tech_pc');
    }

    public function test_requester_cannot_resolve_their_own_ticket(): void
    {
        $ticket = $this->move($this->pcTicket(), TicketStatus::InProgress, 'tech_pc');

        $this->expectException(AuthorizationException::class);
        $this->move($ticket, TicketStatus::Resolved, 'staff');
    }

    public function test_on_hold_requires_a_reason(): void
    {
        $ticket = $this->move($this->pcTicket(), TicketStatus::InProgress, 'tech_pc');

        try {
            $this->move($ticket, TicketStatus::OnHold, 'tech_pc');
            $this->fail('Expected a validation error');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('note', $e->errors());
        }

        $ticket = $this->move($ticket, TicketStatus::OnHold, 'tech_pc', 'بانتظار باور سبلاي جديد');
        $this->assertSame('بانتظار باور سبلاي جديد', $ticket->statusChanges()->reorder('id', 'desc')->value('note'));
    }

    public function test_invalid_jump_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->move($this->pcTicket(), TicketStatus::Closed, 'supervisor');
    }

    public function test_requester_can_cancel_only_before_it_is_picked_up(): void
    {
        $cancelled = $this->move($this->pcTicket(), TicketStatus::Cancelled, 'staff');
        $this->assertSame(TicketStatus::Cancelled, $cancelled->status);

        $picked = $this->move($this->pcTicket(), TicketStatus::Acknowledged, 'tech_pc');
        $this->expectException(ValidationException::class);
        $this->move($picked, TicketStatus::Cancelled, 'staff');
    }

    public function test_reopen_needs_reason_and_resets_resolution(): void
    {
        $ticket = $this->move($this->pcTicket(), TicketStatus::InProgress, 'tech_pc');
        $ticket = $this->move($ticket, TicketStatus::Resolved, 'tech_pc');

        $ticket = $this->move($ticket, TicketStatus::Reopened, 'staff', 'الجهاز فصل تاني بعد ساعة');

        $this->assertNull($ticket->resolved_at);
        $this->assertTrue($ticket->status->isOpen());
    }

    public function test_reopen_window_expires(): void
    {
        $ticket = $this->move($this->pcTicket(), TicketStatus::InProgress, 'tech_pc');
        $ticket = $this->move($ticket, TicketStatus::Resolved, 'tech_pc');
        $ticket->forceFill(['resolved_at' => now()->subDays(8)])->save();

        $this->expectException(AuthorizationException::class);
        $this->move($ticket, TicketStatus::Reopened, 'staff', 'رجعت تاني');
    }

    public function test_auto_close_command_closes_old_resolved_tickets(): void
    {
        $old = $this->move($this->move($this->pcTicket(), TicketStatus::InProgress, 'tech_pc'), TicketStatus::Resolved, 'tech_pc');
        $old->forceFill(['resolved_at' => now()->subDays(4)])->save();
        $recent = $this->move($this->move($this->pcTicket(), TicketStatus::InProgress, 'tech_pc'), TicketStatus::Resolved, 'tech_pc');

        $this->artisan('tickets:auto-close')->assertSuccessful();

        $this->assertSame(TicketStatus::Closed, $old->fresh()->status);
        $this->assertNull($old->statusChanges()->reorder('id', 'desc')->value('user_id'));
        $this->assertSame(TicketStatus::Resolved, $recent->fresh()->status);
    }

    public function test_rating_is_once_and_only_after_closing(): void
    {
        $ticket = $this->pcTicket();
        $rate = app(RateTicket::class);

        try {
            $rate->handle($ticket, $this->user('staff'), 5);
            $this->fail('Open tickets cannot be rated');
        } catch (AuthorizationException) {
        }

        $ticket = $this->move($this->move($ticket, TicketStatus::InProgress, 'tech_pc'), TicketStatus::Resolved, 'tech_pc');
        $ticket = $this->move($ticket, TicketStatus::Closed, 'staff');

        $rate->handle($ticket, $this->user('staff'), 4, 'سريع جدًا');
        $this->assertSame(4, $ticket->fresh()->rating);

        $this->expectException(AuthorizationException::class);
        $rate->handle($ticket->fresh(), $this->user('staff'), 1);
    }

    public function test_assignment_rules(): void
    {
        $ticket = $this->projectorTicket();
        $assign = app(AssignTicket::class);

        $assign->handle($ticket, $this->user('tech_av'), $this->user('supervisor'));
        $this->assertSame($this->user('tech_av')->id, $ticket->fresh()->assignee_id);

        try {
            $assign->handle($ticket, $this->user('tech_pc'), $this->user('supervisor'));
            $this->fail('PC technician does not handle projectors');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('assignee_id', $e->errors());
        }

        $this->expectException(AuthorizationException::class);
        $assign->handle($ticket, $this->user('tech_av'), $this->user('tech_pc'));
    }

    public function test_internal_notes_are_staff_only(): void
    {
        $ticket = $this->pcTicket();
        $comment = app(AddTicketComment::class);

        $comment->handle($ticket, $this->user('tech_pc'), 'الهارد فيه bad sectors', internal: true);
        $comment->handle($ticket, $this->user('staff'), 'الجهاز مهم جدًا للشغل');

        $this->expectException(AuthorizationException::class);
        $comment->handle($ticket, $this->user('staff'), 'محاولة ملاحظة داخلية', internal: true);
    }

    public function test_visibility_scope_matches_the_policy(): void
    {
        $pc = $this->pcTicket();
        $projector = $this->projectorTicket();

        $visible = fn (string $who) => Ticket::visibleTo($this->user($who))->pluck('id')->sort()->values()->all();

        $this->assertSame([$pc->id], $visible('staff'));
        $this->assertSame([$projector->id], $visible('doctor'));
        $this->assertSame([$pc->id], $visible('tech_pc'));
        $this->assertSame([$projector->id], $visible('tech_av'));
        $this->assertSame([$pc->id, $projector->id], $visible('supervisor'));

        foreach (['staff', 'doctor', 'tech_pc', 'tech_av', 'supervisor'] as $who) {
            foreach ([$pc, $projector] as $ticket) {
                $this->assertSame(
                    in_array($ticket->id, $visible($who), true),
                    $this->user($who)->can('view', $ticket),
                    "{$who} / {$ticket->number}",
                );
            }
        }
    }
}
