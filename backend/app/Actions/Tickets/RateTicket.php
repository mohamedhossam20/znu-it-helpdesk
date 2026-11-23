<?php

namespace App\Actions\Tickets;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class RateTicket
{
    public function handle(Ticket $ticket, User $by, int $rating, ?string $comment = null): Ticket
    {
        Gate::forUser($by)->authorize('rate', $ticket);

        $ticket->update([
            'rating' => max(1, min(5, $rating)),
            'rating_comment' => filled($comment) ? trim($comment) : null,
        ]);

        return $ticket;
    }
}
