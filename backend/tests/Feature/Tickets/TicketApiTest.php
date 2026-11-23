<?php

namespace Tests\Feature\Tickets;

use App\Actions\Tickets\AddTicketComment;
use App\Actions\Tickets\ChangeTicketStatus;
use App\Enums\TicketStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesTickets;
use Tests\TestCase;

class TicketApiTest extends TestCase
{
    use CreatesTickets, RefreshDatabase;

    protected $seed = true;

    public function test_context_returns_fixed_office_and_devices(): void
    {
        $this->actingAs($this->user('staff'))
            ->getJson('/api/v1/me/context')
            ->assertOk()
            ->assertJsonPath('data.default_room.code', '204')
            ->assertJsonPath('data.default_room.location.building.code', 'MAIN')
            ->assertJsonPath('data.my_assets.0.asset_tag', 'PC-0123')
            ->assertJsonPath('data.my_assets.0.is_mine', true)
            ->assertJsonPath('data.room_assets.0.asset_tag', 'PR-0045');
    }

    public function test_room_assets_hide_other_peoples_personal_devices(): void
    {
        $tags = $this->actingAs($this->user('doctor'))
            ->getJson('/api/v1/rooms/'.$this->room('204')->id.'/assets')
            ->assertOk()
            ->json('data.*.asset_tag');

        $this->assertSame(['PR-0045'], $tags);
    }

    public function test_qr_lookup_returns_room_and_devices(): void
    {
        $room = $this->room('105');

        $this->actingAs($this->user('doctor'))
            ->getJson("/api/v1/rooms/by-qr/{$room->qr_token}")
            ->assertOk()
            ->assertJsonPath('data.id', $room->id)
            ->assertJsonPath('data.location.building.code', 'ENG')
            ->assertJsonCount(3, 'data.assets');

        $this->actingAs($this->user('doctor'))->getJson('/api/v1/rooms/by-qr/NOT-A-TOKEN')->assertNotFound();
    }

    public function test_categories_are_a_tree(): void
    {
        $this->actingAs($this->user('doctor'))
            ->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonCount(8, 'data')
            ->assertJsonPath('data.0.name_en', 'Computer / laptop')
            ->assertJsonCount(6, 'data.0.children');
    }

    public function test_requester_opens_a_ticket_with_minimal_input_and_a_photo(): void
    {
        Storage::fake('local');

        $response = $this->actingAs($this->user('staff'))
            ->post('/api/v1/tickets', [
                'source' => 'my_device',
                'asset_id' => $this->asset('PC-0123')->id,
                'category_id' => $this->category('Computer / laptop', 'Does not turn on')->id,
                'description' => 'الجهاز لا يفتح منذ الصباح',
                'photos' => [UploadedFile::fake()->image('pc.jpg')],
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'new')
            ->assertJsonPath('data.location.room.code', '204')
            ->assertJsonPath('data.asset.asset_tag', 'PC-0123')
            ->assertJsonPath('data.can.cancel', true)
            ->assertJsonCount(1, 'data.photos');

        $number = $response->json('data.number');
        $this->actingAs($this->user('staff'))->get($response->json('data.photos.0.url'))->assertOk();

        $this->actingAs($this->user('staff'))
            ->getJson('/api/v1/tickets?status=open')
            ->assertOk()
            ->assertJsonPath('data.0.number', $number);
    }

    public function test_requesters_cannot_mark_their_ticket_urgent_directly(): void
    {
        $this->actingAs($this->user('doctor'))
            ->postJson('/api/v1/tickets', [
                'source' => 'other_location',
                'room_id' => $this->room('105')->id,
                'category_id' => $this->category('Audio', 'Microphone')->id,
                'priority' => 'urgent',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('priority');

        $this->actingAs($this->user('doctor'))
            ->postJson('/api/v1/tickets', [
                'source' => 'other_location',
                'room_id' => $this->room('105')->id,
                'category_id' => $this->category('Audio', 'Microphone')->id,
                'is_class_in_progress' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.priority', 'urgent');
    }

    public function test_validation_messages_follow_the_app_language(): void
    {
        $payload = ['source' => 'other_location', 'category_id' => $this->category('Other')->id];

        $this->actingAs($this->user('doctor'))
            ->postJson('/api/v1/tickets', $payload, ['Accept-Language' => 'ar'])
            ->assertJsonPath('errors.room_id.0', 'حقل المكان مطلوب.');

        $this->actingAs($this->user('doctor'))
            ->postJson('/api/v1/tickets', $payload, ['Accept-Language' => 'en'])
            ->assertJsonPath('errors.room_id.0', fn (string $message) => str_contains($message, 'required'));
    }

    public function test_other_location_requires_a_room(): void
    {
        $this->actingAs($this->user('doctor'))
            ->postJson('/api/v1/tickets', ['source' => 'other_location', 'category_id' => $this->category('Other')->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('room_id');
    }

    public function test_requester_cannot_see_someone_elses_ticket_or_its_photos(): void
    {
        Storage::fake('local');
        $ticket = $this->pcTicket();
        $ticket->addMedia(UploadedFile::fake()->image('x.jpg'))->toMediaCollection('photos');
        $media = $ticket->getFirstMedia('photos');

        $this->actingAs($this->user('doctor'))->getJson("/api/v1/tickets/{$ticket->number}")->assertForbidden();
        $this->actingAs($this->user('doctor'))->get("/api/v1/tickets/{$ticket->number}/photos/{$media->id}")->assertForbidden();

        $other = $this->projectorTicket();
        $this->actingAs($this->user('doctor'))->get("/api/v1/tickets/{$other->number}/photos/{$media->id}")->assertNotFound();
    }

    public function test_internal_notes_never_reach_the_requester(): void
    {
        $ticket = $this->pcTicket();
        app(AddTicketComment::class)->handle($ticket, $this->user('tech_pc'), 'ملاحظة داخلية سرية', internal: true);
        app(AddTicketComment::class)->handle($ticket, $this->user('tech_pc'), 'هنعدّي عليك بعد الضهر');

        $this->actingAs($this->user('staff'))
            ->getJson("/api/v1/tickets/{$ticket->number}")
            ->assertOk()
            ->assertJsonCount(1, 'data.comments')
            ->assertJsonPath('data.comments.0.body', 'هنعدّي عليك بعد الضهر')
            ->assertDontSee('ملاحظة داخلية سرية');

        $this->actingAs($this->user('tech_pc'))
            ->getJson("/api/v1/tickets/{$ticket->number}")
            ->assertJsonCount(2, 'data.comments');
    }

    public function test_requester_actions_through_the_api(): void
    {
        $ticket = $this->pcTicket();
        $change = app(ChangeTicketStatus::class);

        $this->actingAs($this->user('staff'))
            ->postJson("/api/v1/tickets/{$ticket->number}/comments", ['body' => 'أنا في المكتب لحد 2'])
            ->assertOk()
            ->assertJsonPath('data.comments.0.is_mine', true);

        $change->handle($ticket, TicketStatus::InProgress, $this->user('tech_pc'));
        $change->handle($ticket, TicketStatus::Resolved, $this->user('tech_pc'));

        $this->actingAs($this->user('staff'))
            ->postJson("/api/v1/tickets/{$ticket->number}/reopen")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('note');

        $this->actingAs($this->user('staff'))
            ->postJson("/api/v1/tickets/{$ticket->number}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.can.rate', true);

        $this->actingAs($this->user('staff'))
            ->postJson("/api/v1/tickets/{$ticket->number}/rating", ['rating' => 5])
            ->assertOk()
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.can.rate', false);

        $this->actingAs($this->user('staff'))
            ->postJson("/api/v1/tickets/{$ticket->number}/comments", ['body' => 'بعد القفل'])
            ->assertForbidden();
    }

    public function test_doctor_cannot_cancel_someone_elses_ticket(): void
    {
        $ticket = $this->pcTicket();

        $this->actingAs($this->user('doctor'))->postJson("/api/v1/tickets/{$ticket->number}/cancel")->assertForbidden();
        $this->assertSame(TicketStatus::New, $ticket->fresh()->status);
    }
}
