<?php

use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Personal notifications (Laravel's default notification channel name).
Broadcast::channel('App.Models.User.{id}', fn (User $user, string $id) => (string) $user->id === $id);

// Anyone who may open the ticket may hear that it changed.
Broadcast::channel('ticket.{number}', function (User $user, string $number): bool {
    $ticket = Ticket::firstWhere('number', $number);

    return $ticket !== null && $user->can('view', $ticket);
});

// Ticket list in the admin panel.
Broadcast::channel('it.queue', fn (User $user) => $user->is_active && $user->hasAnyRole(UserRole::adminPanelRoles()));
