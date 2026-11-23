<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Filament\Resources\Tickets\Actions\TicketActions;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Ticket;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

/**
 * @property Ticket $record
 */
class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->record->number;
    }

    public function getSubheading(): ?string
    {
        return $this->record->title;
    }

    protected function getHeaderActions(): array
    {
        return TicketActions::all();
    }

    protected function resolveRecord(int|string $key): Ticket
    {
        /** @var Ticket $ticket */
        $ticket = parent::resolveRecord($key);

        return $ticket->load(['category.parent', 'requester.department', 'createdBy', 'assignee', 'asset.type', 'room', 'media']);
    }
}
