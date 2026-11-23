<?php

namespace App\Actions\Tickets;

use App\Models\Ticket;
use App\Models\TicketComment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AttachTicketPhotos
{
    /**
     * Photos uploaded through the API.
     *
     * @param  list<UploadedFile>  $photos
     */
    public function handle(Ticket $ticket, array $photos, ?TicketComment $comment = null): void
    {
        if ($photos === []) {
            return;
        }

        $this->ensureRoom($ticket, count($photos));

        foreach ($photos as $photo) {
            $ticket->addMedia($photo)
                ->usingFileName($photo->hashName())
                ->withCustomProperties(array_filter(['comment_id' => $comment?->id]))
                ->toMediaCollection(Ticket::MEDIA_COLLECTION);
        }
    }

    /**
     * Photos already stored by a Filament FileUpload; the temporary copies are moved in.
     *
     * @param  list<string>  $paths
     */
    public function fromDisk(Ticket $ticket, array $paths, string $disk, ?TicketComment $comment = null): void
    {
        $paths = array_values(array_filter($paths, fn (string $path) => Storage::disk($disk)->exists($path)));

        if ($paths === []) {
            return;
        }

        $this->ensureRoom($ticket, count($paths));

        foreach ($paths as $path) {
            $ticket->addMediaFromDisk($path, $disk)
                ->withCustomProperties(array_filter(['comment_id' => $comment?->id]))
                ->toMediaCollection(Ticket::MEDIA_COLLECTION);
        }
    }

    private function ensureRoom(Ticket $ticket, int $adding): void
    {
        $max = (int) config('helpdesk.attachments.max_files');

        if ($ticket->getMedia(Ticket::MEDIA_COLLECTION)->count() + $adding > $max) {
            throw ValidationException::withMessages(['photos' => __('A ticket can have at most :max photos.', ['max' => $max])]);
        }
    }
}
