<?php

namespace App\Listeners;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Events\TicketAssigned;
use App\Events\TicketCommented;
use App\Events\TicketCreated;
use App\Events\TicketStatusChanged;
use App\Events\TicketUpdated;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketActivity;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Who hears about what. The person who did something is never notified
 * about it, and nobody gets two notifications for one event.
 *
 *  - new ticket       → its technician if assigned, else the specialty's technicians; supervisors too when urgent
 *  - logged by IT     → the requester
 *  - assigned         → the new technician (unless they took it themselves)
 *  - acknowledged     → the requester ("X picked up your request")
 *  - on hold / resolved / closed or cancelled by IT → the requester
 *  - reopened         → the technician and supervisors
 *  - cancelled by requester → the technician
 *  - public reply     → the other side (requester ⇄ technician)
 *  - internal note    → the technician
 */
class SendTicketNotifications
{
    private const EXCERPT_LENGTH = 120;

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            TicketCreated::class => 'onCreated',
            TicketAssigned::class => 'onAssigned',
            TicketStatusChanged::class => 'onStatusChanged',
            TicketCommented::class => 'onCommented',
        ];
    }

    public function onCreated(TicketCreated $event): void
    {
        $ticket = $event->ticket->loadMissing(['requester', 'createdBy', 'category.parent']);
        $urgent = $ticket->priority === TicketPriority::Urgent;
        $actor = $ticket->createdBy;

        if ($ticket->created_by_id !== $ticket->requester_id) {
            $this->send($ticket->requester, $ticket, 'logged_for_you', email: true);
        }

        // An assigned ticket is announced to its technician by TicketAssigned.
        $staff = $ticket->assignee_id ? collect() : $this->specialists($ticket);
        if ($urgent) {
            $staff = $staff->merge($this->supervisors());
        }

        $this->send(
            $this->without($staff, $actor),
            $ticket,
            $urgent ? 'new_urgent_ticket' : 'new_ticket',
            ['requester' => $ticket->requester->name],
            email: $urgent,
        );

        $this->signalUpdate($ticket);
    }

    public function onAssigned(TicketAssigned $event): void
    {
        $ticket = $event->ticket->loadMissing(['assignee', 'category.parent']);

        if ($ticket->assignee && ! $ticket->assignee->is($event->assignedBy)) {
            $this->send($ticket->assignee, $ticket, 'assigned_to_you', email: true);
        }

        $this->signalUpdate($ticket);
    }

    public function onStatusChanged(TicketStatusChanged $event): void
    {
        $ticket = $event->ticket->loadMissing(['requester', 'assignee', 'category.parent']);
        $by = $event->changedBy;
        $byRequester = $by?->id === $ticket->requester_id;
        $reason = $event->note ?? '';

        match ($event->to) {
            TicketStatus::Acknowledged => $this->toRequester($ticket, $by, 'picked_up', ['technician' => $ticket->assignee?->name ?? $by?->name]),
            TicketStatus::OnHold => $this->toRequester($ticket, $by, 'on_hold', ['reason' => $reason]),
            TicketStatus::Resolved => $this->toRequester($ticket, $by, 'resolved', email: true),
            TicketStatus::Closed => $byRequester ? null : $this->toRequester($ticket, $by, 'closed_by_it'),
            TicketStatus::Cancelled => $byRequester
                ? $this->send($this->without(collect([$ticket->assignee])->filter(), $by), $ticket, 'cancelled_by_requester')
                : $this->toRequester($ticket, $by, 'cancelled_by_it', ['reason' => $reason]),
            TicketStatus::Reopened => $this->send(
                $this->without(collect([$ticket->assignee])->filter()->merge($this->supervisors()), $by),
                $ticket,
                'reopened',
                ['reason' => $reason],
                email: true,
            ),
            default => null,
        };

        $this->signalUpdate($ticket);
    }

    public function onCommented(TicketCommented $event): void
    {
        $comment = $event->comment->loadMissing(['user', 'ticket.requester', 'ticket.assignee', 'ticket.category.parent']);
        $ticket = $comment->ticket;
        $author = $comment->user;
        $params = ['author' => $author->name, 'excerpt' => Str::limit($comment->body, self::EXCERPT_LENGTH)];

        if ($comment->is_internal) {
            $this->send($this->without(collect([$ticket->assignee])->filter(), $author), $ticket, 'internal_note', $params);
        } elseif ($author->id === $ticket->requester_id) {
            $staff = $ticket->assignee ? collect([$ticket->assignee]) : $this->specialists($ticket);
            $this->send($this->without($staff, $author), $ticket, 'reply_from_requester', $params);
        } else {
            $this->toRequester($ticket, $author, 'reply_from_it', $params);
        }

        $this->signalUpdate($ticket);
    }

    /**
     * @param  array<string, string|int|null>  $params
     */
    private function toRequester(Ticket $ticket, ?User $actor, string $kind, array $params = [], bool $email = false): void
    {
        $this->send($this->without(collect([$ticket->requester]), $actor), $ticket, $kind, $params, $email);
    }

    /**
     * @param  User|Collection<int, User>  $users
     * @param  array<string, string|int|null>  $params
     */
    private function send(User|Collection $users, Ticket $ticket, string $kind, array $params = [], bool $email = false): void
    {
        $users = collect($users instanceof User ? [$users] : $users)
            ->filter(fn (?User $user) => $user?->is_active)
            ->unique('id')
            ->values();

        if ($users->isNotEmpty()) {
            // A mail server or Reverb outage must never undo the ticket action itself.
            rescue(fn () => Notification::send($users, new TicketActivity($ticket, $kind, $params, $email)));
        }
    }

    private function signalUpdate(Ticket $ticket): void
    {
        rescue(fn () => TicketUpdated::dispatch($ticket));
    }

    /**
     * @param  Collection<int, User>  $users
     * @return Collection<int, User>
     */
    private function without(Collection $users, ?User $actor): Collection
    {
        return $actor ? $users->reject(fn (User $user) => $user->is($actor)) : $users;
    }

    /**
     * @return Collection<int, User>
     */
    private function specialists(Ticket $ticket): Collection
    {
        return $ticket->category->rootCategory()->technicians()->where('users.is_active', true)->get();
    }

    /**
     * @return Collection<int, User>
     */
    private function supervisors(): Collection
    {
        return User::role(UserRole::ItSupervisor->value)->where('is_active', true)->get();
    }
}
