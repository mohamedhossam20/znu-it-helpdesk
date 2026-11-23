<?php

namespace App\Filament\Widgets;

use App\Services\Reports\TicketReport;
use Filament\Widgets\ChartWidget;

class CategoryChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected ?string $maxHeight = '260px';

    private const DAYS = 30;

    public static function canView(): bool
    {
        return auth()->user()?->can('reports.view') ?? false;
    }

    public function getHeading(): string
    {
        return __('Tickets by problem type — last :days days', ['days' => self::DAYS]);
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = TicketReport::lastDays(self::DAYS)->byCategory();

        return [
            'labels' => array_column($rows, 'label'),
            'datasets' => [
                [
                    'label' => __('Tickets'),
                    'data' => array_column($rows, 'total'),
                    'backgroundColor' => '#3b82f6',
                    'borderRadius' => 6,
                ],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'scales' => ['x' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
            'plugins' => ['legend' => ['display' => false]],
        ];
    }
}
