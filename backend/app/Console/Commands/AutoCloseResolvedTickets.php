<?php

namespace App\Console\Commands;

use App\Actions\Tickets\ChangeTicketStatus;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tickets:auto-close')]
#[Description('Close resolved tickets the requester did not confirm or reopen in time')]
class AutoCloseResolvedTickets extends Command
{
    public function handle(ChangeTicketStatus $change): int
    {
        $cutoff = now()->subDays((int) config('helpdesk.auto_close_after_days'));
        $closed = 0;

        Ticket::query()
            ->where('status', TicketStatus::Resolved)
            ->where('resolved_at', '<=', $cutoff)
            ->chunkById(100, function ($tickets) use ($change, &$closed): void {
                foreach ($tickets as $ticket) {
                    $change->handle($ticket, TicketStatus::Closed, by: null, note: __('Closed automatically: no reply after the fix.'));
                    $closed++;
                }
            });

        $this->info("Closed {$closed} ticket(s).");

        return self::SUCCESS;
    }
}
