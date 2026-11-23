<?php

namespace Tests\Feature\Notifications;

use App\Actions\Tickets\AddTicketComment;
use App\Actions\Tickets\AssignTicket;
use App\Actions\Tickets\ChangeTicketStatus;
use App\Actions\Tickets\CreateTicket;
use App\Actions\Tickets\UpdateTicketDetails;
use App\Enums\TicketPriority;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Events\TicketUpdated;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesTickets;
use Tests\TestCase;

class TicketNotificationsTest extends TestCase
{
    use CreatesTickets, RefreshDatabase;

    protected $seed = true;

    private function assertKind(User $user, string $kind, ?bool $mail = null): void
    {
        Notification::assertSentTo($user, TicketActivity::class, function (TicketActivity $n, array $channels) use ($kind, $mail) {
            return $n->kind === $kind && ($mail === null || in_array('mail', $channels, true) === $mail);
        });
    }

    private function assertNoKind(User $user, string $kind): void
    {
        Notification::assertNotSentTo($user, TicketActivity::class, fn (TicketActivity $n) => $n->kind === $kind);
    }

    private function move(Ticket $ticket, TicketStatus $to, string $who, ?string $note = null): Ticket
    {
        return app(ChangeTicketStatus::class)->handle($ticket, $to, $this->user($who), $note);
    }

    public function test_new_ticket_goes_to_the_specialty_technicians_only(): void
    {
        Notification::fake();

        $this->pcTicket();

        $this->assertKind($this->user('tech_pc'), 'new_ticket', mail: false);
        Notification::assertNotSentTo([$this->user('tech_av'), $this->user('supervisor'), $this->user('staff')], TicketActivity::class);
    }

    public function test_urgent_ticket_also_alerts_supervisors_by_email(): void
    {
        Notification::fake();

        $this->projectorTicket(classInProgress: true);

        $this->assertKind($this->user('tech_av'), 'new_urgent_ticket', mail: true);
        $this->assertKind($this->user('supervisor'), 'new_urgent_ticket', mail: true);
        Notification::assertNotSentTo($this->user('doctor'), TicketActivity::class);
    }

    public function test_ticket_logged_by_it_informs_the_requester_but_not_the_logger(): void
    {
        Notification::fake();

        app(CreateTicket::class)->handle(
            requester: $this->user('staff'),
            createdBy: $this->user('tech_pc'),
            source: TicketSource::Admin,
            category: $this->category('Computer / laptop', 'Very slow'),
            room: $this->room('204'),
        );

        $this->assertKind($this->user('staff'), 'logged_for_you', mail: true);
        Notification::assertNotSentTo($this->user('tech_pc'), TicketActivity::class);
    }

    public function test_taking_a_ticket_tells_the_requester_once(): void
    {
        $ticket = $this->pcTicket();
        Notification::fake();

        $this->move($ticket, TicketStatus::Acknowledged, 'tech_pc');

        Notification::assertSentToTimes($this->user('staff'), TicketActivity::class, 1);
        $this->assertKind($this->user('staff'), 'picked_up');
        Notification::assertNotSentTo($this->user('tech_pc'), TicketActivity::class);
    }

    public function test_supervisor_assignment_tells_the_technician(): void
    {
        $ticket = $this->projectorTicket();
        Notification::fake();

        app(AssignTicket::class)->handle($ticket, $this->user('tech_av'), $this->user('supervisor'));

        $this->assertKind($this->user('tech_av'), 'assigned_to_you', mail: true);
        Notification::assertNotSentTo($this->user('supervisor'), TicketActivity::class);
    }

    public function test_hold_resolve_and_requester_confirmation(): void
    {
        $ticket = $this->move($this->pcTicket(), TicketStatus::InProgress, 'tech_pc');
        Notification::fake();

        $this->move($ticket, TicketStatus::OnHold, 'tech_pc', 'بانتظار قطعة');
        $this->assertKind($this->user('staff'), 'on_hold', mail: false);

        $this->move($ticket, TicketStatus::InProgress, 'tech_pc');
        $this->move($ticket, TicketStatus::Resolved, 'tech_pc');
        $this->assertKind($this->user('staff'), 'resolved', mail: true);

        Notification::fake();
        $this->move($ticket, TicketStatus::Closed, 'staff');
        Notification::assertNothingSent();
    }

    public function test_reopen_alerts_technician_and_supervisors(): void
    {
        $ticket = $this->move($this->move($this->pcTicket(), TicketStatus::InProgress, 'tech_pc'), TicketStatus::Resolved, 'tech_pc');
        Notification::fake();

        $this->move($ticket, TicketStatus::Reopened, 'staff', 'لسه مش شغال');

        $this->assertKind($this->user('tech_pc'), 'reopened', mail: true);
        $this->assertKind($this->user('supervisor'), 'reopened');
        Notification::assertNotSentTo($this->user('staff'), TicketActivity::class);
    }

    public function test_replies_reach_the_other_side_and_internal_notes_stay_internal(): void
    {
        $ticket = $this->move($this->pcTicket(), TicketStatus::Acknowledged, 'tech_pc');
        $comment = app(AddTicketComment::class);
        Notification::fake();

        $comment->handle($ticket, $this->user('staff'), 'أنا في المكتب');
        $this->assertKind($this->user('tech_pc'), 'reply_from_requester');

        $comment->handle($ticket, $this->user('tech_pc'), 'جاي خلال 10 دقايق');
        $this->assertKind($this->user('staff'), 'reply_from_it');

        Notification::fake();
        $comment->handle($ticket, $this->user('supervisor'), 'راجع الضمان', internal: true);
        $this->assertKind($this->user('tech_pc'), 'internal_note');
        Notification::assertNotSentTo([$this->user('staff'), $this->user('supervisor')], TicketActivity::class);
    }

    public function test_inactive_people_are_not_notified(): void
    {
        $this->user('tech_pc')->update(['is_active' => false]);
        Notification::fake();

        $this->pcTicket();

        Notification::assertNotSentTo($this->user('tech_pc'), TicketActivity::class);
    }

    public function test_every_change_broadcasts_a_refresh_signal(): void
    {
        Event::fake([TicketUpdated::class]);

        $ticket = $this->pcTicket();
        $this->move($ticket, TicketStatus::Acknowledged, 'tech_pc');

        Event::assertDispatched(TicketUpdated::class, fn (TicketUpdated $e) => $e->number === $ticket->number && $e->status === 'acknowledged');
    }

    public function test_reverb_outage_does_not_break_reporting_a_problem(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'k',
            'broadcasting.connections.reverb.secret' => 's',
            'broadcasting.connections.reverb.app_id' => '1',
            'broadcasting.connections.reverb.options.host' => '127.0.0.1',
            'broadcasting.connections.reverb.options.port' => 1,
        ]);

        $ticket = $this->pcTicket();

        $this->assertDatabaseHas('tickets', ['number' => $ticket->number]);
        // The stored notification still went out before the broadcast failed.
        $this->assertSame(1, $this->user('tech_pc')->notifications()->count());
    }

    public function test_stored_notification_is_in_the_recipients_language(): void
    {
        $this->user('tech_pc')->update(['locale' => 'en']);

        $ticket = $this->pcTicket();

        $english = $this->user('tech_pc')->notifications()->firstOrFail();
        $this->assertSame("New ticket {$ticket->number}", $english->data['title']);
        $this->assertSame($ticket->number, $english->data['ticket_number']);
        $this->assertStringContainsString('/admin/tickets/', $english->data['url']);

        $this->user('tech_pc')->update(['locale' => 'ar']);
        $this->move($ticket, TicketStatus::Acknowledged, 'tech_pc');
        $arabic = $this->user('staff')->notifications()->firstOrFail();
        $this->assertSame("تم استلام طلبك {$ticket->number}", $arabic->data['title']);
        $this->assertStringContainsString('/tickets/'.$ticket->number, $arabic->data['url']);
        $this->assertStringNotContainsString('/admin/', $arabic->data['url']);
    }

    public function test_overdue_alert_goes_out_once_per_due_time(): void
    {
        $ticket = $this->move($this->pcTicket(), TicketStatus::Acknowledged, 'tech_pc');
        $ticket->forceFill(['due_at' => now()->subMinute()])->save();
        Notification::fake();

        $this->artisan('tickets:notify-overdue')->assertSuccessful();
        $this->artisan('tickets:notify-overdue')->assertSuccessful();

        Notification::assertSentToTimes($this->user('tech_pc'), TicketActivity::class, 1);
        $this->assertKind($this->user('supervisor'), 'overdue', mail: true);

        // A new priority sets a new due time; if that one passes too, alert again.
        app(UpdateTicketDetails::class)->handle($ticket->fresh(), $this->user('supervisor'), $ticket->category, TicketPriority::Urgent, $ticket->asset);
        $this->assertNull($ticket->fresh()->overdue_notified_at);
    }
}
