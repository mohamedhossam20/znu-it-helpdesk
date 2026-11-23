<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TicketStatus: string implements HasColor, HasLabel
{
    case New = 'new';
    case Acknowledged = 'acknowledged';
    case InProgress = 'in_progress';
    case OnHold = 'on_hold';
    case Resolved = 'resolved';
    case Reopened = 'reopened';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::New => [self::Acknowledged, self::InProgress, self::Cancelled],
            self::Acknowledged => [self::InProgress, self::OnHold, self::Resolved],
            self::InProgress => [self::OnHold, self::Resolved],
            self::OnHold => [self::InProgress, self::Resolved],
            self::Resolved => [self::Closed, self::Reopened],
            self::Reopened => [self::Acknowledged, self::InProgress],
            self::Closed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Resolved, self::Closed, self::Cancelled], true);
    }

    /**
     * @return list<self>
     */
    public static function openStatuses(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status) => $status->isOpen()));
    }

    public function getLabel(): string
    {
        return __("enums.ticket_status.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New, self::Reopened => 'danger',
            self::Acknowledged => 'info',
            self::InProgress => 'warning',
            self::Resolved => 'success',
            self::OnHold, self::Closed, self::Cancelled => 'gray',
        };
    }
}
