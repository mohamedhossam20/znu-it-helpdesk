<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="description">
            {{ __('Import in this order: locations, departments, users, devices. Each file can be uploaded again after fixing errors; existing rows are updated, not duplicated.') }}
        </x-slot>

        <form wire:submit.prevent style="display: grid; gap: 1.5rem;">
            {{ $this->form }}

            <div style="display: flex; flex-wrap: wrap; gap: .75rem;">
                {{ $this->downloadTemplateAction }}
                {{ $this->previewAction }}
                {{ $this->importAction }}
            </div>
        </form>
    </x-filament::section>

    @if ($report)
        @php
            $hasErrors = count($report['errors']) > 0;
            $color = $hasErrors ? 'danger' : ($report['saved'] ? 'success' : 'info');
            $heading = match (true) {
                $hasErrors => __('Found problems in :count rows — nothing was saved', ['count' => count($report['errors'])]),
                $report['saved'] => __('Imported successfully'),
                default => __('File is valid and ready to import'),
            };
            $stats = [
                [__('Rows'), $report['total']],
                [$report['saved'] ? __('Created') : __('Will be created'), $hasErrors ? '—' : $report['created']],
                [$report['saved'] ? __('Updated') : __('Will be updated'), $hasErrors ? '—' : $report['updated']],
            ];
        @endphp

        <x-filament::section
            :icon="$hasErrors ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle'"
            :icon-color="$color"
        >
            <x-slot name="heading">{{ $heading }}</x-slot>

            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; text-align: center;">
                @foreach ($stats as [$label, $value])
                    <div>
                        <div style="font-size: .875rem; opacity: .7;">{{ $label }}</div>
                        <div style="font-size: 1.5rem; font-weight: 700;">{{ $value }}</div>
                    </div>
                @endforeach
            </div>

            @if ($hasErrors)
                <div style="margin-top: 1.5rem; overflow-x: auto;">
                    <table style="width: 100%; font-size: .875rem; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 1px solid rgba(127,127,127,.3);">
                                <th style="padding: .5rem 0; padding-inline-end: 1rem; text-align: start; width: 5rem;">{{ __('Line') }}</th>
                                <th style="padding: .5rem 0; text-align: start;">{{ __('Problem') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($report['errors'] as $line => $messages)
                                <tr style="border-bottom: 1px solid rgba(127,127,127,.15); vertical-align: top;">
                                    <td style="padding: .5rem 0; padding-inline-end: 1rem; font-family: monospace;">{{ $line }}</td>
                                    <td style="padding: .5rem 0; color: rgb(var(--danger-600, 220 38 38));">
                                        @foreach ($messages as $message)
                                            <div>• {{ $message }}</div>
                                        @endforeach
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
