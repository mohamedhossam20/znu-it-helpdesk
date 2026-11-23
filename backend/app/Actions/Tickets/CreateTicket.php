<?php

namespace App\Actions\Tickets;

use App\Enums\AssetStatus;
use App\Enums\TicketPriority;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Events\TicketAssigned;
use App\Events\TicketCreated;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Room;
use App\Models\Ticket;
use App\Models\User;
use App\Services\DueDateCalculator;
use App\Services\TicketAssignmentService;
use App\Services\TicketNumberGenerator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The one place a ticket is born, used by the PWA API and the admin panel.
 */
class CreateTicket
{
    public function __construct(
        private readonly TicketNumberGenerator $numbers,
        private readonly DueDateCalculator $dueDates,
        private readonly TicketAssignmentService $assignment,
        private readonly AttachTicketPhotos $photos,
    ) {}

    /**
     * @param  list<UploadedFile>  $photos
     */
    public function handle(
        User $requester,
        User $createdBy,
        TicketSource $source,
        Category $category,
        ?Room $room = null,
        ?Asset $asset = null,
        ?string $description = null,
        ?TicketPriority $priority = null,
        bool $classInProgress = false,
        array $photos = [],
    ): Ticket {
        $room = $this->resolveRoom($requester, $source, $room, $asset);
        $this->ensureCategoryIsSelectable($category);

        if ($asset) {
            $this->ensureAssetFits($asset, $room, $requester);
        }

        $priority = $this->resolvePriority($category, $priority, $classInProgress);

        $ticket = DB::transaction(function () use ($requester, $createdBy, $source, $category, $room, $asset, $description, $priority, $classInProgress, $photos): Ticket {
            $assignee = $this->assignment->pickFor($category);
            $now = now();

            $ticket = Ticket::create([
                'number' => $this->numbers->next(),
                'requester_id' => $requester->id,
                'created_by_id' => $createdBy->id,
                'category_id' => $category->id,
                'room_id' => $room->id,
                'asset_id' => $asset?->id,
                'location_snapshot' => $room->locationSnapshot(),
                'source' => $source,
                'title' => $this->title($category, $room, $asset),
                'description' => filled($description) ? trim($description) : null,
                'priority' => $priority,
                'is_class_in_progress' => $classInProgress,
                'status' => TicketStatus::New,
                'assignee_id' => $assignee?->id,
                'due_at' => $this->dueDates->resolutionDue($priority, $now),
            ]);

            $ticket->statusChanges()->create([
                'user_id' => $createdBy->id,
                'from_status' => null,
                'to_status' => TicketStatus::New,
                'created_at' => $now,
            ]);

            $this->photos->handle($ticket, $photos);

            TicketCreated::dispatch($ticket);

            if ($assignee) {
                TicketAssigned::dispatch($ticket, null, null);
            }

            return $ticket;
        });

        return $ticket->fresh();
    }

    private function resolveRoom(User $requester, TicketSource $source, ?Room $room, ?Asset $asset): Room
    {
        $room ??= match (true) {
            $source === TicketSource::MyDevice && $asset !== null => $asset->room,
            $source === TicketSource::MyDevice => $requester->defaultRoom,
            default => null,
        };

        if (! $room) {
            throw ValidationException::withMessages([
                'room_id' => $source === TicketSource::MyDevice
                    ? __('You have no fixed office on record. Choose the place of the problem.')
                    : __('Choose the place of the problem.'),
            ]);
        }

        if (! $room->is_active) {
            throw ValidationException::withMessages(['room_id' => __('This place is no longer active.')]);
        }

        return $room;
    }

    private function ensureCategoryIsSelectable(Category $category): void
    {
        if (! $category->is_active || ($category->parent && ! $category->parent->is_active)) {
            throw ValidationException::withMessages(['category_id' => __('This problem type is not available.')]);
        }

        if ($category->children()->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['category_id' => __('Choose the exact problem.')]);
        }
    }

    private function ensureAssetFits(Asset $asset, Room $room, User $requester): void
    {
        $inRoom = $asset->room_id === $room->id;
        $ownedByRequester = $asset->assigned_user_id === $requester->id;

        if (! $inRoom && ! $ownedByRequester) {
            throw ValidationException::withMessages(['asset_id' => __('This device is not in the selected place.')]);
        }

        if ($asset->status === AssetStatus::Retired) {
            throw ValidationException::withMessages(['asset_id' => __('This device is out of service.')]);
        }
    }

    private function resolvePriority(Category $category, ?TicketPriority $requested, bool $classInProgress): TicketPriority
    {
        if ($classInProgress) {
            return TicketPriority::Urgent;
        }

        return $requested
            ?? $category->default_priority
            ?? $category->parent?->default_priority
            ?? TicketPriority::Normal;
    }

    private function title(Category $category, Room $room, ?Asset $asset): string
    {
        $parts = [$category->fullName(), $asset?->asset_tag, $room->name_ar];

        return Str::limit(implode(' – ', array_filter($parts)), 147);
    }
}
