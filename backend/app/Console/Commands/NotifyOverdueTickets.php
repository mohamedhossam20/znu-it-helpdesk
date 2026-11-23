<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketActivity;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

#[Signature('tickets:notify-overdue')]
#[Description('Alert the technician and supervisors once a ticket passes its due time')]
class NotifyOverdueTickets extends Command
{
    public function handle(): int
    {
        $supervisors = User::role(UserRole::ItSupervisor->value)->where('is_active', true)->get();
        $sent = 0;

        Ticket::query()
            ->overdue()
            ->whereNull('overdue_notified_at')
            ->with(['assignee', 'category.parent'])
            ->chunkById(100, function ($tickets) use ($supervisors, &$sent): void {
                foreach ($tickets as $ticket) {
                    $recipients = $supervisors
                        ->concat([$ticket->assignee])
                        ->filter(fn (?User $user) => $user?->is_active)
                        ->unique('id');

                    rescue(fn () => Notification::send($recipients, new TicketActivity($ticket, 'overdue', email: true)));

                    $ticket->forceFill(['overdue_notified_at' => now()])->saveQuietly();
                    $sent++;
                }
            });

        $this->info("Alerted on {$sent} overdue ticket(s).");

        return self::SUCCESS;
    }
}
