<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tickets\AddTicketComment;
use App\Actions\Tickets\AttachTicketPhotos;
use App\Actions\Tickets\ChangeTicketStatus;
use App\Actions\Tickets\RateTicket;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\TicketDetailResource;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * What a requester can do on their own ticket from the PWA.
 */
class TicketActionController extends Controller
{
    public function comment(Request $request, Ticket $ticket, AddTicketComment $addComment): TicketDetailResource
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            ...$this->photoRules(),
        ]);

        $addComment->handle($ticket, $request->user(), $data['body'], internal: false, photos: $request->file('photos', []));

        return $this->detail($ticket);
    }

    public function photos(Request $request, Ticket $ticket, AttachTicketPhotos $attach): TicketDetailResource
    {
        Gate::authorize('comment', $ticket);
        $rules = $this->photoRules();
        $rules['photos'][0] = 'required';
        $request->validate($rules);

        $attach->handle($ticket, $request->file('photos', []));

        return $this->detail($ticket);
    }

    public function cancel(Request $request, Ticket $ticket, ChangeTicketStatus $change): TicketDetailResource
    {
        return $this->move($request, $ticket, TicketStatus::Cancelled, $change);
    }

    public function confirm(Request $request, Ticket $ticket, ChangeTicketStatus $change): TicketDetailResource
    {
        return $this->move($request, $ticket, TicketStatus::Closed, $change);
    }

    public function reopen(Request $request, Ticket $ticket, ChangeTicketStatus $change): TicketDetailResource
    {
        return $this->move($request, $ticket, TicketStatus::Reopened, $change);
    }

    public function rate(Request $request, Ticket $ticket, RateTicket $rate): TicketDetailResource
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $rate->handle($ticket, $request->user(), $data['rating'], $data['comment'] ?? null);

        return $this->detail($ticket);
    }

    public function showPhoto(Ticket $ticket, Media $media): StreamedResponse
    {
        Gate::authorize('view', $ticket);
        abort_unless($media->model_type === $ticket->getMorphClass() && $media->model_id === $ticket->id, 404);

        $commentId = $media->getCustomProperty('comment_id');
        if ($commentId !== null && $ticket->comments()->whereKey($commentId)->value('is_internal')) {
            Gate::authorize('viewInternalNotes', $ticket);
        }

        return $media->toInlineResponse(request());
    }

    private function move(Request $request, Ticket $ticket, TicketStatus $to, ChangeTicketStatus $change): TicketDetailResource
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        $change->handle($ticket, $to, $request->user(), $data['note'] ?? null);

        return $this->detail($ticket);
    }

    /**
     * @return array<string, list<string>>
     */
    private function photoRules(): array
    {
        $attachments = config('helpdesk.attachments');

        return [
            'photos' => ['sometimes', 'array', 'max:'.$attachments['max_files']],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp,heic', 'max:'.$attachments['max_kilobytes']],
        ];
    }

    private function detail(Ticket $ticket): TicketDetailResource
    {
        return new TicketDetailResource($ticket->fresh()->load([
            'category.parent', 'asset.type', 'assignee', 'requester',
            'statusChanges.user', 'comments.user', 'media',
        ]));
    }
}
