<?php

namespace App\Actions\Tickets;

use App\Events\TicketCommented;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AddTicketComment
{
    public function __construct(private readonly AttachTicketPhotos $photos) {}

    /**
     * @param  list<UploadedFile>  $photos
     */
    public function handle(Ticket $ticket, User $author, string $body, bool $internal = false, array $photos = []): TicketComment
    {
        Gate::forUser($author)->authorize($internal ? 'commentInternal' : 'comment', $ticket);

        return DB::transaction(function () use ($ticket, $author, $body, $internal, $photos): TicketComment {
            $comment = $ticket->comments()->create([
                'user_id' => $author->id,
                'body' => trim($body),
                'is_internal' => $internal,
            ]);

            $this->photos->handle($ticket, $photos, $comment);

            TicketCommented::dispatch($comment);

            return $comment;
        });
    }
}
