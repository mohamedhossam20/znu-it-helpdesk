<?php

namespace App\Actions\Tickets;

use App\Enums\TicketStatus;
use App\Events\TicketAssigned;
use App\Events\TicketStatusChanged;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * The only code that writes tickets.status. Enforces the lifecycle from
 * TicketStatus, who may make each move (TicketPolicy::transition), and the
 * timestamps the reports depend on.
 */
class ChangeTicketStatus
{
    /**
     * @param  User|null  $by  null for system changes (auto-close)
     */
    public function handle(Ticket $ticket, TicketStatus $to, ?User $by, ?string $note = null): Ticket
    {
        $note = filled($note) ? trim($note) : null;

        return DB::transaction(function () use ($ticket, $to, $by, $note): Ticket {
            $ticket = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $from = $ticket->status;

            if (! $from->canTransitionTo($to)) {
                throw ValidationException::withMessages([
                    'status' => __('A ticket that is ":from" cannot be moved to ":to".', ['from' => $from->getLabel(), 'to' => $to->getLabel()]),
                ]);
            }

            if ($by) {
                Gate::forUser($by)->authorize('transition', [$ticket, $to]);
            }

            if (in_array($to, [TicketStatus::OnHold, TicketStatus::Reopened], true) && blank($note)) {
                throw ValidationException::withMessages(['note' => __('Please write the reason.')]);
            }

            $takenBy = $this->takeIfUnassigned($ticket, $to, $by);
            $this->stampTimes($ticket, $to);

            $ticket->status = $to;
            $ticket->save();

            $ticket->statusChanges()->create([
                'user_id' => $by?->id,
                'from_status' => $from,
                'to_status' => $to,
                'note' => $note,
                'created_at' => now(),
            ]);

            TicketStatusChanged::dispatch($ticket, $from, $to, $by, $note);

            if ($takenBy) {
                TicketAssigned::dispatch($ticket, null, $takenBy);
            }

            return $ticket;
        });
    }

    /**
     * A technician who starts on an unassigned ticket takes it.
     */
    private function takeIfUnassigned(Ticket $ticket, TicketStatus $to, ?User $by): ?User
    {
        $isStaffWork = in_array($to, [TicketStatus::Acknowledged, TicketStatus::InProgress], true);

        if (! $isStaffWork || $by === null || $ticket->assignee_id !== null || $by->id === $ticket->requester_id) {
            return null;
        }

        $ticket->assignee_id = $by->id;

        return $by;
    }

    private function stampTimes(Ticket $ticket, TicketStatus $to): void
    {
        $now = now();

        if (in_array($to, [TicketStatus::Acknowledged, TicketStatus::InProgress, TicketStatus::OnHold, TicketStatus::Resolved], true)) {
            $ticket->first_response_at ??= $now;
        }

        match ($to) {
            TicketStatus::Resolved => $ticket->resolved_at = $now,
            TicketStatus::Reopened => $ticket->resolved_at = null,
            TicketStatus::Closed, TicketStatus::Cancelled => $ticket->closed_at = $now,
            default => null,
        };
    }
}
