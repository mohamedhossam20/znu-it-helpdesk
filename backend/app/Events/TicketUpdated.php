<?php

namespace App\Events;

use App\Models\Ticket;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * "This ticket changed, refetch it": open ticket pages and the IT queue
 * reload instead of trusting a payload that might leak internal details.
 */
class TicketUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public string $number;

    public string $status;

    public function __construct(Ticket $ticket)
    {
        $this->number = $ticket->number;
        $this->status = $ticket->status->value;
    }

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("ticket.{$this->number}"),
            new PrivateChannel('it.queue'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ticket.updated';
    }
}
