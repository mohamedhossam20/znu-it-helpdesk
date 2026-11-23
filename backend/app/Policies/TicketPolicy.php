<?php

namespace App\Policies;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;

/**
 * Requesters see their own tickets. Technicians see tickets assigned to them
 * and tickets in the problem types they handle. Supervisors see everything.
 */
class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $this->isRequester($user, $ticket) || $this->seesAsStaff($user, $ticket);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->can('tickets.create');
    }

    public function comment(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket)
            && ! in_array($ticket->status, [TicketStatus::Closed, TicketStatus::Cancelled], true);
    }

    public function commentInternal(User $user, Ticket $ticket): bool
    {
        return $user->can('tickets.comment_internal')
            && $this->seesAsStaff($user, $ticket)
            && $ticket->status !== TicketStatus::Cancelled;
    }

    public function viewInternalNotes(User $user, Ticket $ticket): bool
    {
        return $this->seesAsStaff($user, $ticket);
    }

    public function assign(User $user, Ticket $ticket): bool
    {
        return $user->can('tickets.assign');
    }

    /**
     * Correct category, device or priority: whoever may work on it, while open.
     */
    public function update(User $user, Ticket $ticket): bool
    {
        return $ticket->status->isOpen() && $this->canWorkOn($user, $ticket);
    }

    /**
     * Log a ticket for someone else (phone call, walk-in).
     */
    public function createOnBehalf(User $user): bool
    {
        return $user->can('tickets.change_status');
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->can('tickets.delete');
    }

    public function transition(User $user, Ticket $ticket, TicketStatus $to): bool
    {
        return match ($to) {
            TicketStatus::Cancelled, TicketStatus::Closed => $this->isRequester($user, $ticket) || $user->can('tickets.view_all'),
            TicketStatus::Reopened => ($this->isRequester($user, $ticket) && $this->withinReopenWindow($ticket)) || $user->can('tickets.view_all'),
            TicketStatus::Acknowledged, TicketStatus::InProgress, TicketStatus::OnHold, TicketStatus::Resolved => $this->canWorkOn($user, $ticket),
            TicketStatus::New => false,
        };
    }

    public function rate(User $user, Ticket $ticket): bool
    {
        return $ticket->requester_id === $user->id
            && $ticket->status === TicketStatus::Closed
            && $ticket->rating === null;
    }

    private function isRequester(User $user, Ticket $ticket): bool
    {
        return $ticket->requester_id === $user->id || $ticket->created_by_id === $user->id;
    }

    private function seesAsStaff(User $user, Ticket $ticket): bool
    {
        if ($user->can('tickets.view_all')) {
            return true;
        }

        return $user->can('tickets.view_assigned')
            && ($ticket->assignee_id === $user->id || $this->handlesCategory($user, $ticket));
    }

    /**
     * Work on it: it is theirs, or nobody has it yet and it is their specialty.
     */
    private function canWorkOn(User $user, Ticket $ticket): bool
    {
        if ($user->can('tickets.view_all')) {
            return true;
        }

        if (! $user->can('tickets.change_status')) {
            return false;
        }

        return $ticket->assignee_id === $user->id
            || ($ticket->assignee_id === null && $this->handlesCategory($user, $ticket));
    }

    private function handlesCategory(User $user, Ticket $ticket): bool
    {
        $rootId = $ticket->category->parent_id ?? $ticket->category_id;

        return $user->technicianCategories()->whereKey($rootId)->exists();
    }

    private function withinReopenWindow(Ticket $ticket): bool
    {
        return $ticket->resolved_at !== null
            && $ticket->resolved_at->greaterThan(now()->subDays((int) config('helpdesk.reopen_window_days')));
    }
}
