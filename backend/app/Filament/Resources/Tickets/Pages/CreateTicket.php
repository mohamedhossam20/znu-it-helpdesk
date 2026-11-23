<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Actions\Tickets\AttachTicketPhotos;
use App\Actions\Tickets\CreateTicket as CreateTicketAction;
use App\Enums\TicketPriority;
use App\Enums\TicketSource;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Room;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateTicket extends CreateRecord
{
    protected static string $resource = TicketResource::class;

    protected static bool $canCreateAnother = false;

    public function getTitle(): string
    {
        return __('Log a ticket for someone');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): Model {
            $ticket = app(CreateTicketAction::class)->handle(
                requester: User::findOrFail($data['requester_id']),
                createdBy: auth()->user(),
                source: TicketSource::Admin,
                category: Category::findOrFail($data['category_id']),
                room: Room::findOrFail($data['room_id']),
                asset: Asset::find($data['asset_id'] ?? null),
                description: $data['description'] ?? null,
                priority: filled($data['priority'] ?? null) ? TicketPriority::from($data['priority'] instanceof TicketPriority ? $data['priority']->value : $data['priority']) : null,
                classInProgress: (bool) ($data['is_class_in_progress'] ?? false),
            );

            app(AttachTicketPhotos::class)->fromDisk($ticket, $data['photos'] ?? [], 'local');

            return $ticket;
        });
    }

    protected function getRedirectUrl(): string
    {
        return TicketResource::getUrl('view', ['record' => $this->record]);
    }
}
