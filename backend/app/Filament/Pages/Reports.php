<?php

namespace App\Filament\Pages;

use App\Models\Building;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\TicketReport;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * @property-read Schema $form
 */
class Reports extends Page
{
    protected string $view = 'filament.pages.reports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 0;

    /**
     * @var array{from?: ?string, to?: ?string, building_id?: ?string}
     */
    public ?array $filters = [];

    public static function getNavigationLabel(): string
    {
        return __('Reports');
    }

    public function getTitle(): string
    {
        return __('Reports');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('reports.view') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'from' => now()->subDays(29)->toDateString(),
            'to' => now()->toDateString(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('filters')
            ->columns(3)
            ->components([
                DatePicker::make('from')->label(__('From'))->live()->maxDate(now()),
                DatePicker::make('to')->label(__('To'))->live()->maxDate(now()),
                Select::make('building_id')
                    ->label(__('Building'))
                    ->options(fn () => Building::orderBy('code')->pluck('name_ar', 'id'))
                    ->placeholder(__('All buildings'))
                    ->live(),
            ]);
    }

    public function report(): TicketReport
    {
        return ReportPeriod::report($this->filters ?? []);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('excel')
                ->label(__('Export to Excel'))
                ->icon(Heroicon::OutlinedTableCells)
                ->url(fn () => route('admin.reports.excel', array_filter($this->filters ?? [])))
                ->openUrlInNewTab(),
            Action::make('print')
                ->label(__('Print / PDF'))
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->url(fn () => route('admin.reports.print', array_filter($this->filters ?? [])))
                ->openUrlInNewTab(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $report = $this->report();

        return [
            'summary' => $report->summary(),
            'sections' => [
                __('By problem type') => $report->byCategory(),
                __('By building') => $report->byBuilding(),
                __('By technician') => $report->byTechnician(),
            ],
            'rooms' => $report->topRooms(),
            'assets' => $report->topAssets(),
        ];
    }
}
