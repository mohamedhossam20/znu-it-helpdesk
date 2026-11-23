<?php

namespace Tests\Feature\Notifications;

use App\Events\TicketUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesTickets;
use Tests\TestCase;

/**
 * Channel authorisation goes through /broadcasting/auth exactly as the PWA
 * and panel do. The Reverb signature is computed locally; nothing connects.
 */
class BroadcastChannelsTest extends TestCase
{
    use CreatesTickets, RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Nothing in these tests should try to reach a Reverb server.
        Event::fake([TicketUpdated::class]);
        Notification::fake();

        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => '1',
        ]);

        // Channels were registered on the broadcaster active at boot ("null" in tests).
        require base_path('routes/channels.php');
    }

    private function authorize(string $who, string $channel): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->user($who))
            ->postJson('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => "private-{$channel}"]);
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function ticketChannelAccess(): array
    {
        return [
            'requester' => ['staff', 200],
            'technician of the specialty' => ['tech_pc', 200],
            'supervisor' => ['supervisor', 200],
            'other technician' => ['tech_av', 403],
            'other requester' => ['doctor', 403],
        ];
    }

    #[DataProvider('ticketChannelAccess')]
    public function test_ticket_channel_follows_ticket_visibility(string $who, int $status): void
    {
        $ticket = $this->pcTicket();

        $this->authorize($who, "ticket.{$ticket->number}")->assertStatus($status);
    }

    public function test_personal_channel_is_only_for_its_owner(): void
    {
        $staff = $this->user('staff');

        $this->authorize('staff', "App.Models.User.{$staff->id}")->assertOk()->assertJsonStructure(['auth']);
        $this->authorize('doctor', "App.Models.User.{$staff->id}")->assertForbidden();
    }

    public function test_it_queue_is_for_panel_users(): void
    {
        $this->authorize('tech_av', 'it.queue')->assertOk();
        $this->authorize('doctor', 'it.queue')->assertForbidden();
    }
}
