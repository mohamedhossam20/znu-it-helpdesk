@php
    /** @var \App\Models\Ticket $record */
    $record = $getRecord();
    $record->loadMissing(['statusChanges.user', 'comments.user', 'media']);

    $photosByComment = $record->getMedia(\App\Models\Ticket::MEDIA_COLLECTION)
        ->filter(fn ($media) => $media->getCustomProperty('comment_id') !== null)
        ->groupBy(fn ($media) => $media->getCustomProperty('comment_id'));

    $events = collect()
        ->concat($record->statusChanges->map(fn ($change) => ['type' => 'status', 'at' => $change->created_at, 'item' => $change]))
        ->concat($record->comments->map(fn ($comment) => ['type' => 'comment', 'at' => $comment->created_at, 'item' => $comment]))
        ->sortBy([['at', 'asc']])
        ->values();

    $colors = [
        'danger' => '#dc2626', 'info' => '#2563eb', 'warning' => '#d97706',
        'success' => '#16a34a', 'gray' => '#6b7280',
    ];
@endphp

<ol style="list-style: none; margin: 0; padding: 0; display: grid; gap: 1rem;">
    @foreach ($events as $event)
        @if ($event['type'] === 'status')
            @php($change = $event['item'])
            <li style="display: flex; gap: .75rem; align-items: flex-start; font-size: .875rem;">
                <span style="margin-top: .35rem; width: .6rem; height: .6rem; border-radius: 9999px; flex: none; background: {{ $colors[$change->to_status->getColor()] ?? '#6b7280' }};"></span>
                <div>
                    <div>
                        <strong>{{ $change->to_status->getLabel() }}</strong>
                        <span style="opacity: .7;">
                            — {{ $change->user?->name ?? __('System') }}
                            · <time title="{{ $change->created_at->toDayDateTimeString() }}">{{ $change->created_at->diffForHumans() }}</time>
                        </span>
                    </div>
                    @if ($change->note)
                        <div style="margin-top: .25rem; opacity: .85;">{{ $change->note }}</div>
                    @endif
                </div>
            </li>
        @else
            @php($comment = $event['item'])
            <li style="
                padding: .75rem 1rem;
                border-radius: .75rem;
                font-size: .875rem;
                border: 1px solid {{ $comment->is_internal ? 'rgba(217,119,6,.45)' : 'rgba(127,127,127,.25)' }};
                background: {{ $comment->is_internal ? 'rgba(245,158,11,.08)' : 'transparent' }};
            ">
                <div style="display: flex; justify-content: space-between; gap: .5rem; margin-bottom: .35rem;">
                    <strong>{{ $comment->user->name }}</strong>
                    <span style="opacity: .7; white-space: nowrap;">
                        @if ($comment->is_internal)
                            <span style="color: #d97706; font-weight: 600;">{{ __('Internal note') }}</span> ·
                        @endif
                        <time title="{{ $comment->created_at->toDayDateTimeString() }}">{{ $comment->created_at->diffForHumans() }}</time>
                    </span>
                </div>
                <div style="white-space: pre-line;">{{ $comment->body }}</div>
                @if ($photosByComment->has($comment->id))
                    <div style="display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .5rem;">
                        @foreach ($photosByComment[$comment->id] as $media)
                            @php($url = route('admin.tickets.photos', [$record, $media->id]))
                            <a href="{{ $url }}" target="_blank" rel="noopener">
                                <img src="{{ $url }}" alt="" loading="lazy" style="width: 5rem; height: 5rem; object-fit: cover; border-radius: .375rem;">
                            </a>
                        @endforeach
                    </div>
                @endif
            </li>
        @endif
    @endforeach
</ol>
