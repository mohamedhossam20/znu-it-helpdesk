{{-- Shared by the Reports page and its print view; inline styles so both render the same. --}}
@php
    $hours = fn ($value) => $value === null ? '—' : __(':value h', ['value' => $value]);
    $percent = fn ($value) => $value === null ? '—' : $value.'%';
    $rating = fn ($value) => $value === null ? '—' : $value.' / 5';
    $th = 'padding: .5rem .6rem; text-align: start; font-weight: 700; border-bottom: 2px solid rgba(127,127,127,.35); white-space: nowrap;';
    $td = 'padding: .45rem .6rem; border-bottom: 1px solid rgba(127,127,127,.15);';
    $tdNum = $td.' text-align: center;';
    $cards = [
        [__('Tickets reported'), $summary['total']],
        [__('Still open'), $summary['open']],
        [__('Overdue'), $summary['overdue_open']],
        [__('Average first response'), $hours($summary['avg_response_hours'])],
        [__('Average time to fix'), $hours($summary['avg_resolution_hours'])],
        [__('Fixed on time'), $percent($summary['on_time_rate'])],
        [__('Satisfaction'), $rating($summary['avg_rating'])],
    ];
@endphp

<div style="display: grid; gap: 1.5rem;">
    <div style="display: grid; gap: .75rem; grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr));">
        @foreach ($cards as [$label, $value])
            <div style="padding: .9rem 1rem; border: 1px solid rgba(127,127,127,.25); border-radius: .75rem;">
                <div style="font-size: .8rem; opacity: .7;">{{ $label }}</div>
                <div style="font-size: 1.5rem; font-weight: 800;">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    @foreach ($sections as $title => $rows)
        <section style="break-inside: avoid;">
            <h2 style="font-size: 1.05rem; font-weight: 700; margin: 0 0 .5rem;">{{ $title }}</h2>
            @if (count($rows) === 0)
                <div style="opacity: .65;">{{ __('No tickets in this period.') }}</div>
            @else
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: .875rem;">
                        <thead>
                            <tr>
                                <th style="{{ $th }}"></th>
                                <th style="{{ $th }} text-align: center;">{{ __('Tickets') }}</th>
                                <th style="{{ $th }} text-align: center;">{{ __('Open') }}</th>
                                <th style="{{ $th }} text-align: center;">{{ __('Overdue') }}</th>
                                <th style="{{ $th }} text-align: center;">{{ __('First response') }}</th>
                                <th style="{{ $th }} text-align: center;">{{ __('Time to fix') }}</th>
                                <th style="{{ $th }} text-align: center;">{{ __('On time') }}</th>
                                <th style="{{ $th }} text-align: center;">{{ __('Rating') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <td style="{{ $td }} font-weight: 600;">{{ $row['label'] }}</td>
                                    <td style="{{ $tdNum }}">{{ $row['total'] }}</td>
                                    <td style="{{ $tdNum }}">{{ $row['open'] }}</td>
                                    <td style="{{ $tdNum }} {{ $row['overdue_open'] ? 'color: #dc2626; font-weight: 700;' : '' }}">{{ $row['overdue_open'] }}</td>
                                    <td style="{{ $tdNum }}">{{ $hours($row['avg_response_hours']) }}</td>
                                    <td style="{{ $tdNum }}">{{ $hours($row['avg_resolution_hours']) }}</td>
                                    <td style="{{ $tdNum }}">{{ $percent($row['on_time_rate']) }}</td>
                                    <td style="{{ $tdNum }}">{{ $rating($row['avg_rating']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endforeach

    <div style="display: grid; gap: 1.5rem; grid-template-columns: repeat(auto-fit, minmax(18rem, 1fr));">
        @foreach ([__('Places with most faults') => $rooms, __('Devices with most faults') => $assets] as $title => $rows)
            <section style="break-inside: avoid;">
                <h2 style="font-size: 1.05rem; font-weight: 700; margin: 0 0 .5rem;">{{ $title }}</h2>
                @forelse ($rows as $row)
                    <div style="display: flex; justify-content: space-between; gap: .75rem; {{ $td }}">
                        <div>
                            <div>{{ $row['label'] }}</div>
                            <div style="font-size: .75rem; opacity: .65;">{{ $row['location'] }}</div>
                        </div>
                        <strong>{{ $row['count'] }}</strong>
                    </div>
                @empty
                    <div style="opacity: .65;">{{ __('No tickets in this period.') }}</div>
                @endforelse
            </section>
        @endforeach
    </div>
</div>
