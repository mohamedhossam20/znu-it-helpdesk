<x-filament-panels::page>
    <x-filament::section>
        {{ $this->form }}
    </x-filament::section>

    <x-filament::section>
        <div wire:loading.delay style="opacity: .6;">{{ __('Updating…') }}</div>
        @include('reports.body')
    </x-filament::section>
</x-filament-panels::page>
