<?php

namespace Tests\Feature\Notifications;

use App\Actions\Tickets\ChangeTicketStatus;
use App\Enums\TicketStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTickets;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use CreatesTickets, RefreshDatabase;

    protected $seed = true;

    private function notifyStaffTwice(): string
    {
        $ticket = $this->pcTicket();
        $change = app(ChangeTicketStatus::class);
        $change->handle($ticket, TicketStatus::Acknowledged, $this->user('tech_pc'));
        $change->handle($ticket, TicketStatus::OnHold, $this->user('tech_pc'), 'بانتظار قطعة');

        return $ticket->number;
    }

    public function test_requester_lists_notifications_with_unread_count(): void
    {
        $number = $this->notifyStaffTwice();

        $response = $this->actingAs($this->user('staff'))
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('meta.unread_count', 2)
            ->assertJsonCount(2, 'data');

        $onHold = collect($response->json('data'))->firstWhere('kind', 'on_hold');
        $this->assertSame($number, $onHold['ticket_number']);
        $this->assertSame('السبب: بانتظار قطعة', $onHold['body']);
        $this->assertNull($onHold['read_at']);
        $this->assertEqualsCanonicalizing(['picked_up', 'on_hold'], $response->json('data.*.kind'));
    }

    public function test_mark_one_then_all_as_read(): void
    {
        $this->notifyStaffTwice();
        $id = $this->user('staff')->notifications()->first()->id;

        $this->actingAs($this->user('staff'))
            ->postJson('/api/v1/notifications/read', ['ids' => [$id]])
            ->assertOk()
            ->assertJsonPath('meta.unread_count', 1);

        $this->actingAs($this->user('staff'))
            ->postJson('/api/v1/notifications/read', ['all' => true])
            ->assertJsonPath('meta.unread_count', 0);
    }

    public function test_cannot_mark_someone_elses_notifications(): void
    {
        $this->notifyStaffTwice();
        $id = $this->user('staff')->notifications()->first()->id;

        $this->actingAs($this->user('doctor'))->postJson('/api/v1/notifications/read', ['ids' => [$id]])->assertOk();

        $this->assertSame(2, $this->user('staff')->unreadNotifications()->count());
    }
}
