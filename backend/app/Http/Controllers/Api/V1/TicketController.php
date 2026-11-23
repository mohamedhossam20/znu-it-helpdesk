<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tickets\CreateTicket;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Resources\TicketDetailResource;
use App\Http\Resources\TicketResource;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Room;
use App\Models\Ticket;
use App\Enums\TicketPriority;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    /**
     * "My requests": tickets I asked for, newest first.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filter = $request->validate(['status' => ['nullable', Rule::in(['open', 'closed'])]])['status'] ?? null;

        $tickets = Ticket::query()
            ->where('requester_id', $request->user()->id)
            ->with(['category.parent', 'asset.type', 'assignee'])
            ->when($filter === 'open', fn ($q) => $q->open())
            ->when($filter === 'closed', fn ($q) => $q->whereNotIn('status', TicketStatus::openStatuses()))
            ->latest()
            ->paginate(20);

        return TicketResource::collection($tickets);
    }

    public function store(StoreTicketRequest $request, CreateTicket $createTicket): JsonResponse
    {
        Gate::authorize('create', Ticket::class);

        $user = $request->user();
        $data = $request->validated();

        $ticket = $createTicket->handle(
            requester: $user,
            createdBy: $user,
            source: TicketSource::from($data['source']),
            category: Category::findOrFail($data['category_id']),
            room: isset($data['room_id']) ? Room::findOrFail($data['room_id']) : null,
            asset: isset($data['asset_id']) ? Asset::findOrFail($data['asset_id']) : null,
            description: $data['description'] ?? null,
            priority: isset($data['priority']) ? TicketPriority::from($data['priority']) : null,
            classInProgress: (bool) ($data['is_class_in_progress'] ?? false),
            photos: $request->file('photos', []),
        );

        return $this->detail($ticket)->response()->setStatusCode(201);
    }

    public function show(Ticket $ticket): TicketDetailResource
    {
        Gate::authorize('view', $ticket);

        return $this->detail($ticket);
    }

    private function detail(Ticket $ticket): TicketDetailResource
    {
        return new TicketDetailResource($ticket->load([
            'category.parent', 'asset.type', 'assignee', 'requester',
            'statusChanges.user', 'comments.user', 'media',
        ]));
    }
}
