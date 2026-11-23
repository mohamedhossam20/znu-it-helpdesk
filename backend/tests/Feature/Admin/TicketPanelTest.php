<?php

namespace Tests\Feature\Admin;

use App\Enums\TicketPriority;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Filament\Resources\Tickets\Pages\CreateTicket;
use App\Filament\Resources\Tickets\Pages\ListTickets;
use App\Filament\Resources\Tickets\Pages\ViewTicket;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\CreatesTickets;
use Tests\TestCase;

class TicketPanelTest extends TestCase
{
    use CreatesTickets, RefreshDatabase;

    protected $seed = true;

    public function test_list_shows_only_tickets_the_technician_may_see(): void
    {
        $pc = $this->pcTicket();
        $projector = $this->projectorTicket();

        $this->actingAs($this->user('tech_pc'));

        Livewire::test(ListTickets::class, ['activeTab' => 'all'])
            ->assertCanSeeTableRecords([$pc])
            ->assertCanNotSeeTableRecords([$projector]);

        $this->get("/admin/tickets/{$pc->number}")->assertOk()->assertSee($pc->number);
        $this->get("/admin/tickets/{$projector->number}")->assertForbidden();
    }

    public function test_tabs_split_mine_unassigned_and_overdue(): void
    {
        $mine = $this->pcTicket();
        $mine->update(['assignee_id' => $this->user('supervisor')->id]);
        $unassigned = $this->projectorTicket();
        $overdue = $this->projectorTicket();
        $overdue->update(['due_at' => now()->subHour()]);

        $this->actingAs($this->user('supervisor'));

        Livewire::test(ListTickets::class, ['activeTab' => 'mine'])
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$unassigned]);

        Livewire::test(ListTickets::class, ['activeTab' => 'unassigned'])
            ->assertCanSeeTableRecords([$unassigned, $overdue])
            ->assertCanNotSeeTableRecords([$mine]);

        Livewire::test(ListTickets::class, ['activeTab' => 'overdue'])
            ->assertCanSeeTableRecords([$overdue])
            ->assertCanNotSeeTableRecords([$mine, $unassigned]);
    }

    public function test_technician_takes_works_and_resolves_from_the_ticket_page(): void
    {
        $ticket = $this->pcTicket();
        $this->actingAs($this->user('tech_pc'));

        $page = Livewire::test(ViewTicket::class, ['record' => $ticket->number])
            ->assertActionVisible('take')
            ->assertActionHidden('assign')
            ->callAction('take');

        $this->assertSame($this->user('tech_pc')->id, $ticket->fresh()->assignee_id);

        $page = Livewire::test(ViewTicket::class, ['record' => $ticket->number])
            ->assertActionHidden('take')
            ->callAction('start');
        $this->assertSame(TicketStatus::InProgress, $ticket->fresh()->status);

        Livewire::test(ViewTicket::class, ['record' => $ticket->number])
            ->callAction('hold', data: ['note' => ''])
            ->assertHasActionErrors(['note' => 'required']);

        Livewire::test(ViewTicket::class, ['record' => $ticket->number])
            ->callAction('resolve', data: ['note' => 'تم تغيير الباور سبلاي'])
            ->assertHasNoActionErrors();

        $ticket->refresh();
        $this->assertSame(TicketStatus::Resolved, $ticket->status);
        $this->assertSame('تم تغيير الباور سبلاي', $ticket->statusChanges()->reorder('id', 'desc')->value('note'));
    }

    public function test_page_shows_the_new_state_right_after_an_action(): void
    {
        $ticket = $this->pcTicket();
        $this->actingAs($this->user('tech_pc'));

        Livewire::test(ViewTicket::class, ['record' => $ticket->number])
            ->callAction('take')
            ->assertActionHidden('take')
            ->assertActionHidden('cancel')
            ->assertActionVisible('start')
            ->assertSee(TicketStatus::Acknowledged->getLabel())
            ->assertSee($this->user('tech_pc')->name)
            ->callAction('start')
            ->assertActionHidden('start')
            ->assertActionVisible('resolve');
    }

    public function test_technician_adds_an_internal_note_with_a_photo(): void
    {
        Storage::fake('local');
        $ticket = $this->pcTicket();
        $this->actingAs($this->user('tech_pc'));

        Livewire::test(ViewTicket::class, ['record' => $ticket->number])
            ->callAction('comment', data: [
                'body' => 'الهارد بيطلع صوت',
                'internal' => true,
                'photos' => [UploadedFile::fake()->image('disk.jpg')],
            ])
            ->assertHasNoActionErrors();

        $comment = $ticket->comments()->firstOrFail();
        $this->assertTrue($comment->is_internal);

        $media = $ticket->fresh()->getFirstMedia(Ticket::MEDIA_COLLECTION);
        $this->assertSame($comment->id, $media->getCustomProperty('comment_id'));

        $this->get(route('admin.tickets.photos', [$ticket, $media->id]))->assertOk();
        $this->actingAs($this->user('staff'))->get(route('admin.tickets.photos', [$ticket, $media->id]))->assertForbidden();
    }

    public function test_supervisor_assigns_only_to_technicians_of_that_specialty(): void
    {
        $ticket = $this->projectorTicket();
        $this->actingAs($this->user('supervisor'));

        Livewire::test(ViewTicket::class, ['record' => $ticket->number])
            ->assertActionVisible('assign')
            ->callAction('assign', data: ['assignee_id' => $this->user('tech_pc')->id]);
        $this->assertNull($ticket->fresh()->assignee_id, 'PC technician is not an option for projectors');

        Livewire::test(ViewTicket::class, ['record' => $ticket->number])
            ->callAction('assign', data: ['assignee_id' => $this->user('tech_av')->id])
            ->assertHasNoActionErrors();
        $this->assertSame($this->user('tech_av')->id, $ticket->fresh()->assignee_id);
    }

    public function test_correcting_priority_recalculates_the_due_time(): void
    {
        $ticket = $this->pcTicket();
        $before = $ticket->due_at;
        $this->actingAs($this->user('supervisor'));

        Livewire::test(ViewTicket::class, ['record' => $ticket->number])
            ->callAction('editDetails', data: [
                'category_id' => $this->category('Computer / laptop', 'Very slow')->id,
                'priority' => TicketPriority::Urgent->value,
                'asset_id' => $ticket->asset_id,
            ])
            ->assertHasNoActionErrors();

        $ticket->refresh();
        $this->assertSame(TicketPriority::Urgent, $ticket->priority);
        $this->assertSame('Very slow', $ticket->category->name_en);
        $this->assertTrue($ticket->due_at->lessThan($before));
    }

    public function test_it_logs_a_ticket_for_a_caller_using_their_fixed_office(): void
    {
        $staff = $this->user('staff');
        $this->actingAs($this->user('tech_pc'));

        Livewire::test(CreateTicket::class)
            ->fillForm(['requester_id' => $staff->id])
            ->assertSchemaStateSet(['room_id' => $this->room('204')->id])
            ->fillForm([
                'category_id' => $this->category('Printer / scanner', 'Not printing')->id,
                'asset_id' => $this->asset('PR-0045')->id,
                'description' => 'اتصل بالتليفون',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $ticket = Ticket::latest('id')->firstOrFail();
        $this->assertSame($staff->id, $ticket->requester_id);
        $this->assertSame($this->user('tech_pc')->id, $ticket->created_by_id);
        $this->assertSame(TicketSource::Admin, $ticket->source);
        $this->assertSame('PR-0045', $ticket->asset->asset_tag);
    }

    public function test_requesters_and_closed_tickets_hide_the_work_buttons(): void
    {
        $ticket = $this->pcTicket();
        $this->actingAs($this->user('staff'))->get("/admin/tickets/{$ticket->number}")->assertForbidden();

        $ticket->update(['status' => TicketStatus::Closed]);
        $this->actingAs($this->user('supervisor'));

        Livewire::test(ViewTicket::class, ['record' => $ticket->number])
            ->assertActionHidden('take')
            ->assertActionHidden('start')
            ->assertActionHidden('assign')
            ->assertActionHidden('editDetails');
    }
}
