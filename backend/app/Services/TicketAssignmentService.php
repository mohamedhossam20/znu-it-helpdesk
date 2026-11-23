<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\User;

class TicketAssignmentService
{
    /**
     * Technician for a new ticket, or null to leave it for the supervisor.
     */
    public function pickFor(Category $category): ?User
    {
        if (config('helpdesk.assignment_mode') !== 'category') {
            return null;
        }

        return $category->rootCategory()
            ->technicians()
            ->where('users.is_active', true)
            ->withCount(['assignedTickets as open_load' => fn ($q) => $q->whereIn('status', TicketStatus::openStatuses())])
            ->orderBy('open_load')
            ->orderBy('users.id')
            ->first();
    }

    /**
     * Staff who may work on a ticket of this category: its technicians,
     * plus anyone who can see every ticket.
     */
    public function isEligibleAssignee(User $user, Category $category): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return $user->can('tickets.view_all')
            || ($user->can('tickets.change_status') && $category->rootCategory()->technicians()->whereKey($user->id)->exists());
    }
}
