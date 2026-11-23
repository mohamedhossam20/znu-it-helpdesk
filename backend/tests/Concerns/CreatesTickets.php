<?php

namespace Tests\Concerns;

use App\Actions\Tickets\CreateTicket;
use App\Enums\TicketSource;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Room;
use App\Models\Ticket;
use App\Models\User;

/**
 * Shortcuts over the seeded demo data (DemoSeeder).
 */
trait CreatesTickets
{
    protected function user(string $key): User
    {
        $emails = [
            'admin' => 'admin@znu.test',
            'supervisor' => 'supervisor@znu.test',
            'tech_pc' => 'tech.pc@znu.test',
            'tech_av' => 'tech.av@znu.test',
            'staff' => 'staff@znu.test',
            'doctor' => 'doctor@znu.test',
        ];

        return User::where('email', $emails[$key])->firstOrFail();
    }

    protected function category(string $parentEn, ?string $childEn = null): Category
    {
        $parent = Category::whereNull('parent_id')->where('name_en', $parentEn)->firstOrFail();

        return $childEn === null ? $parent : $parent->children()->where('name_en', $childEn)->firstOrFail();
    }

    protected function room(string $code): Room
    {
        return Room::where('code', $code)->firstOrFail();
    }

    protected function asset(string $tag): Asset
    {
        return Asset::where('asset_tag', $tag)->firstOrFail();
    }

    /**
     * Staff member's PC does not turn on: the typical fixed-location ticket.
     */
    protected function pcTicket(): Ticket
    {
        return app(CreateTicket::class)->handle(
            requester: $this->user('staff'),
            createdBy: $this->user('staff'),
            source: TicketSource::MyDevice,
            category: $this->category('Computer / laptop', 'Does not turn on'),
            asset: $this->asset('PC-0123'),
        );
    }

    /**
     * Doctor reports the projector in hall 105.
     */
    protected function projectorTicket(bool $classInProgress = false): Ticket
    {
        return app(CreateTicket::class)->handle(
            requester: $this->user('doctor'),
            createdBy: $this->user('doctor'),
            source: TicketSource::OtherLocation,
            category: $this->category('Screens & display', 'Projector'),
            room: $this->room('105'),
            asset: $this->asset('PJ-0010'),
            classInProgress: $classInProgress,
        );
    }
}
