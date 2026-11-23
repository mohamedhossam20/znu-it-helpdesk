<?php

namespace App\Filament\Widgets;

use App\Services\Reports\TicketReport;
use Filament\Widgets\ChartWidget;

class TicketTrendChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $maxHeight = '260px';

    private const DAYS = 14;

    public static function canView(): bool
    {
        return auth()->user()?->can('reports.view') ?? false;
    }

    public function getHeading(): string
    {
        return __('Reported vs resolved — last :days days', ['days' => self::DAYS]);
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $daily = TicketReport::lastDays(self::DAYS)->daily();

        return [
            'labels' => $daily['labels'],
            'datasets' => [
                [
                    'label' => __('Reported'),
                    'data' => $daily['created'],
                    'borderColor' => '#2563eb',
                    'backgroundColor' => 'rgba(37,99,235,.15)',
                    'fill' => true,
                    'tension' => .3,
                ],
                [
                    'label' => __('Resolved'),
                    'data' => $daily['resolved'],
                    'borderColor' => '#16a34a',
                    'backgroundColor' => 'rgba(22,163,74,.1)',
                    'fill' => true,
                    'tension' => .3,
                ],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
            'plugins' => ['legend' => ['position' => 'bottom']],
        ];
    }
}
