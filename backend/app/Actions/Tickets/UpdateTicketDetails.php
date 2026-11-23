<?php

namespace App\Actions\Tickets;

use App\Enums\TicketPriority;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Services\DueDateCalculator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * IT correcting what the requester picked: the real problem type, the
 * right device, or the priority after seeing the situation.
 */
class UpdateTicketDetails
{
    public function __construct(private readonly DueDateCalculator $dueDates) {}

    public function handle(Ticket $ticket, User $by, Category $category, TicketPriority $priority, ?Asset $asset): Ticket
    {
        Gate::forUser($by)->authorize('update', $ticket);

        if ($category->children()->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['category_id' => __('Choose the exact problem.')]);
        }

        if ($asset && $asset->room_id !== $ticket->room_id && $asset->assigned_user_id !== $ticket->requester_id) {
            throw ValidationException::withMessages(['asset_id' => __('This device is not in the selected place.')]);
        }

        $ticket->fill([
            'category_id' => $category->id,
            'priority' => $priority,
            'asset_id' => $asset?->id,
        ]);

        // A new priority means a new target, still counted from when it was reported.
        if ($ticket->isDirty('priority') && $ticket->status->isOpen()) {
            $ticket->due_at = $this->dueDates->resolutionDue($priority, $ticket->created_at);
            // A new due time deserves its own overdue alert.
            $ticket->overdue_notified_at = null;
        }

        $ticket->save();

        return $ticket;
    }
}
