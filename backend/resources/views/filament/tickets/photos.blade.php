@php
    /** @var \App\Models\Ticket $record */
    $record = $getRecord();
    $photos = $record->getMedia(\App\Models\Ticket::MEDIA_COLLECTION)
        ->filter(fn ($media) => $media->getCustomProperty('comment_id') === null);
@endphp

<div style="display: flex; flex-wrap: wrap; gap: .75rem;">
    @foreach ($photos as $media)
        @php($url = route('admin.tickets.photos', [$record, $media->id]))
        <a href="{{ $url }}" target="_blank" rel="noopener" title="{{ $media->file_name }}">
            <img
                src="{{ $url }}"
                alt=""
                loading="lazy"
                style="width: 9rem; height: 9rem; object-fit: cover; border-radius: .5rem; border: 1px solid rgba(127,127,127,.3);"
            >
        </a>
    @endforeach
</div>
