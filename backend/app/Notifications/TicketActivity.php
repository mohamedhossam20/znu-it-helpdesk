<?php

namespace App\Notifications;

use App\Enums\UserRole;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Ticket;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Something happened on a ticket that this person should know about.
 * Text comes from lang/{locale}/notifications.php in the recipient's own
 * language; the link opens the admin panel for IT staff and the PWA for
 * everyone else.
 */
class TicketActivity extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $kind  key in lang/{locale}/notifications.php
     * @param  array<string, string|int|null>  $params
     * @param  bool  $email  also send an email (only for things worth an inbox)
     */
    public function __construct(
        public readonly Ticket $ticket,
        public readonly string $kind,
        public readonly array $params = [],
        public readonly bool $email = false,
    ) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        // Broadcast last: if Reverb is down, the stored notification and the email still go out.
        return array_values(array_filter([
            'database',
            $this->email && filled($notifiable->email) ? 'mail' : null,
            config('broadcasting.default') !== 'null' ? 'broadcast' : null,
        ]));
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->title()} — {$this->ticket->number}")
            ->greeting(__('notifications.greeting', ['name' => $notifiable->name]))
            ->line($this->body())
            ->action(__('notifications.open_ticket'), $this->url($notifiable))
            ->salutation(__('notifications.salutation'));
    }

    /**
     * Filament's format, so the bell in the admin panel can show it, plus
     * plain fields the PWA reads.
     *
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        $url = $this->url($notifiable);

        return [
            ...FilamentNotification::make()
                ->title($this->title())
                ->body($this->body())
                ->icon($this->icon())
                ->iconColor($this->color())
                ->actions([
                    Action::make('open')->label(__('notifications.open_ticket'))->url($url)->markAsRead(),
                ])
                ->getDatabaseMessage(),
            'kind' => $this->kind,
            'ticket_number' => $this->ticket->number,
            'url' => $url,
        ];
    }

    public function toBroadcast(User $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function databaseType(object $notifiable): string
    {
        return 'ticket.'.$this->kind;
    }

    private function title(): string
    {
        return __("notifications.{$this->kind}.title", $this->textParams());
    }

    private function body(): string
    {
        return __("notifications.{$this->kind}.body", $this->textParams());
    }

    /**
     * @return array<string, string>
     */
    private function textParams(): array
    {
        return array_map(fn ($value) => (string) $value, [
            'number' => $this->ticket->number,
            'problem' => $this->ticket->category->fullName(),
            'place' => $this->ticket->snapshotLocationLabel(),
            ...$this->params,
        ]);
    }

    private function url(User $notifiable): string
    {
        if ($notifiable->hasAnyRole(UserRole::adminPanelRoles())) {
            return TicketResource::getUrl('view', ['record' => $this->ticket], panel: 'admin');
        }

        return rtrim((string) config('helpdesk.frontend_url'), '/').'/tickets/'.$this->ticket->number;
    }

    private function icon(): string
    {
        return match ($this->kind) {
            'resolved', 'closed_by_it' => 'heroicon-o-check-circle',
            'overdue' => 'heroicon-o-clock',
            'reopened' => 'heroicon-o-arrow-path',
            'reply_from_it', 'reply_from_requester', 'internal_note' => 'heroicon-o-chat-bubble-left-ellipsis',
            'assigned_to_you', 'picked_up' => 'heroicon-o-user-plus',
            default => 'heroicon-o-ticket',
        };
    }

    private function color(): string
    {
        return match ($this->kind) {
            'resolved', 'closed_by_it' => 'success',
            'overdue', 'reopened', 'new_urgent_ticket' => 'danger',
            'on_hold' => 'warning',
            default => 'info',
        };
    }
}
