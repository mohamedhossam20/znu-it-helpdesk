<?php

namespace App\Http\Resources;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketStatusChange;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * One ticket with its history. Internal IT notes are only included for
 * staff who may see them, never for the requester.
 *
 * @mixin Ticket
 */
class TicketDetailResource extends TicketResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $showInternal = $user->can('viewInternalNotes', $this->resource);

        $comments = $this->comments
            ->when(! $showInternal, fn ($c) => $c->where('is_internal', false))
            ->values();

        $visibleCommentIds = $comments->pluck('id')->all();

        return [
            ...parent::toArray($request),
            'description' => $this->description,
            'requester' => $this->requester->only(['id', 'name']),
            'status_history' => $this->statusChanges->map(fn (TicketStatusChange $change) => [
                'from' => $change->from_status,
                'to' => $change->to_status,
                'to_label' => $change->to_status->getLabel(),
                // Reasons for on-hold / reopen are written for the requester too.
                'note' => $change->note,
                'by' => $change->user?->only(['id', 'name']),
                'at' => $change->created_at,
            ]),
            'comments' => $comments->map(fn (TicketComment $comment) => [
                'id' => $comment->id,
                'body' => $comment->body,
                'is_internal' => $comment->is_internal,
                'by' => $comment->user->only(['id', 'name']),
                'is_mine' => $comment->user_id === $user->id,
                'at' => $comment->created_at,
            ]),
            'photos' => $this->getMedia(Ticket::MEDIA_COLLECTION)
                ->filter(function (Media $media) use ($visibleCommentIds): bool {
                    $commentId = $media->getCustomProperty('comment_id');

                    return $commentId === null || in_array($commentId, $visibleCommentIds, true);
                })
                ->map(fn (Media $media) => [
                    'id' => $media->id,
                    'comment_id' => $media->getCustomProperty('comment_id'),
                    'url' => route('api.v1.tickets.photos.show', [$this->number, $media->id], absolute: false),
                    'mime_type' => $media->mime_type,
                    'size' => $media->size,
                ])
                ->values(),
            'can' => [
                'comment' => $user->can('comment', $this->resource),
                'cancel' => $this->status === TicketStatus::New && $user->can('transition', [$this->resource, TicketStatus::Cancelled]),
                'confirm' => $this->status === TicketStatus::Resolved && $user->can('transition', [$this->resource, TicketStatus::Closed]),
                'reopen' => $this->status === TicketStatus::Resolved && $user->can('transition', [$this->resource, TicketStatus::Reopened]),
                'rate' => $user->can('rate', $this->resource),
            ],
            'rating_comment' => $this->rating_comment,
        ];
    }
}
