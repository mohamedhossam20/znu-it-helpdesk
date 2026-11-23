<?php

namespace App\Actions\Tickets;

use App\Events\TicketAssigned;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketAssignmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AssignTicket
{
    public function __construct(private readonly TicketAssignmentService $assignment) {}

    /**
     * @param  User|null  $assignee  null to put the ticket back in the unassigned queue
     */
    public function handle(Ticket $ticket, ?User $assignee, User $by): Ticket
    {
        Gate::forUser($by)->authorize('assign', $ticket);

        if (! $ticket->status->isOpen()) {
            throw ValidationException::withMessages(['assignee_id' => __('Only open tickets can be assigned.')]);
        }

        if ($assignee && ! $this->assignment->isEligibleAssignee($assignee, $ticket->category)) {
            throw ValidationException::withMessages(['assignee_id' => __(':name does not handle this problem type.', ['name' => $assignee->name])]);
        }

        if ($ticket->assignee_id === $assignee?->id) {
            return $ticket;
        }

        return DB::transaction(function () use ($ticket, $assignee, $by): Ticket {
            $previous = $ticket->assignee;

            $ticket->assignee()->associate($assignee);
            $ticket->save();

            TicketAssigned::dispatch($ticket, $previous, $by);

            return $ticket;
        });
    }
}
