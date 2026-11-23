@php
    $cell = 'padding: .4rem 0; border-bottom: 1px solid rgba(127,127,127,.15);';
    $muted = 'font-size: .75rem; opacity: .65;';
    $num = 'text-align: end; font-weight: 700; white-space: nowrap; padding-inline-start: .75rem;';
@endphp

<x-filament-widgets::widget>
    <div style="display: grid; gap: 1.5rem; grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr));">
        <x-filament::section :heading="__('Places with most faults')" :description="__('Last :days days', ['days' => $days])" icon="heroicon-o-map-pin">
            @forelse ($rooms as $row)
                <div style="display: flex; justify-content: space-between; {{ $cell }}">
                    <div>
                        <div>{{ $row['label'] }}</div>
                        <div style="{{ $muted }}">{{ $row['location'] }}</div>
                    </div>
                    <div style="{{ $num }}">{{ $row['count'] }}</div>
                </div>
            @empty
                <div style="{{ $muted }}">{{ __('No tickets in this period.') }}</div>
            @endforelse
        </x-filament::section>

        <x-filament::section :heading="__('Devices with most faults')" :description="__('Last :days days', ['days' => $days])" icon="heroicon-o-computer-desktop">
            @forelse ($assets as $row)
                <div style="display: flex; justify-content: space-between; {{ $cell }}">
                    <div>
                        <div>{{ $row['label'] }}</div>
                        <div style="{{ $muted }}">{{ $row['location'] }}</div>
                    </div>
                    <div style="{{ $num }} {{ $row['count'] >= 3 ? 'color: #dc2626;' : '' }}">{{ $row['count'] }}</div>
                </div>
            @empty
                <div style="{{ $muted }}">{{ __('No device faults in this period.') }}</div>
            @endforelse
        </x-filament::section>

        <x-filament::section :heading="__('Technician load')" :description="__('Open tickets right now')" icon="heroicon-o-users">
            @forelse ($load as $technician)
                <div style="display: flex; justify-content: space-between; {{ $cell }}">
                    <div>{{ $technician->name }}</div>
                    <div style="{{ $num }}">
                        {{ $technician->open_count }}
                        @if ($technician->overdue_count)
                            <span style="color: #dc2626; font-weight: 600;"> · {{ __(':count overdue', ['count' => $technician->overdue_count]) }}</span>
                        @endif
                    </div>
                </div>
            @empty
                <div style="{{ $muted }}">{{ __('No technicians yet.') }}</div>
            @endforelse
        </x-filament::section>
    </div>
</x-filament-widgets::widget>
