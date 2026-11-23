<?php

namespace App\Exports;

use App\Models\Ticket;
use App\Services\Reports\TicketReport;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * One workbook: summary, the three breakdowns, hotspots, and every ticket
 * of the period so the IT team can build its own pivots.
 */
class TicketReportExport implements Export, WithMultipleSheets
{
    public function __construct(private readonly TicketReport $report) {}

    /**
     * @return list<object>
     */
    public function sheets(): array
    {
        $metricHeadings = [
            __('Tickets'), __('Open'), __('Resolved'), __('Cancelled'), __('Overdue'),
            __('Avg. first response (h)'), __('Avg. time to fix (h)'), __('Fixed on time %'), __('Avg. rating'),
        ];
        $metricRow = fn (array $m) => [
            $m['total'], $m['open'], $m['resolved'], $m['cancelled'], $m['overdue_open'],
            $m['avg_response_hours'], $m['avg_resolution_hours'], $m['on_time_rate'], $m['avg_rating'],
        ];
        $grouped = fn (string $first, array $rows) => [
            [$first, ...$metricHeadings],
            array_map(fn (array $row) => [$row['label'], ...$metricRow($row)], $rows),
        ];

        $period = $this->report->from->setTimezone(config('helpdesk.working_hours.timezone'))->toDateString()
            .' → '.$this->report->to->setTimezone(config('helpdesk.working_hours.timezone'))->toDateString();

        return [
            self::sheet(__('Summary'), [__('Period'), ...$metricHeadings], [[$period, ...$metricRow($this->report->summary())]]),
            self::sheet(__('By problem type'), ...$grouped(__('Problem type'), $this->report->byCategory())),
            self::sheet(__('By building'), ...$grouped(__('Building'), $this->report->byBuilding())),
            self::sheet(__('By technician'), ...$grouped(__('Technician'), $this->report->byTechnician())),
            self::sheet(__('Places with most faults'), [__('Place'), __('Location'), __('Tickets')], array_map('array_values', $this->report->topRooms(50))),
            self::sheet(__('Devices with most faults'), [__('Device'), __('Location'), __('Tickets')], array_map('array_values', $this->report->topAssets(50))),
            self::sheet(__('All tickets'), [
                __('Ticket #'), __('Reported'), __('Problem'), __('Location'), __('Device'), __('Requester'),
                __('Priority'), __('Status'), __('Technician'), __('First response'), __('Resolved'), __('Due'), __('Rating'),
            ], $this->report->tickets()->sortBy('created_at')->map(fn (Ticket $t) => [
                $t->number,
                $this->time($t->created_at),
                $t->category->fullName(),
                $t->snapshotLocationLabel(),
                $t->asset?->asset_tag,
                $t->requester->name,
                $t->priority->getLabel(),
                $t->status->getLabel(),
                $t->assignee?->name,
                $this->time($t->first_response_at),
                $this->time($t->resolved_at),
                $this->time($t->due_at),
                $t->rating,
            ])->values()->all()),
        ];
    }

    private function time(?\DateTimeInterface $value): ?string
    {
        return $value ? \Carbon\CarbonImmutable::instance($value)->setTimezone(config('helpdesk.working_hours.timezone'))->format('Y-m-d H:i') : null;
    }

    /**
     * @param  list<string>  $headings
     * @param  list<list<mixed>>  $rows
     */
    private static function sheet(string $title, array $headings, array $rows): object
    {
        return new class($title, $headings, $rows) implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
        {
            /**
             * @param  list<string>  $headings
             * @param  list<list<mixed>>  $rows
             */
            public function __construct(private string $title, private array $headings, private array $rows) {}

            public function array(): array
            {
                return $this->rows;
            }

            public function headings(): array
            {
                return $this->headings;
            }

            public function title(): string
            {
                // Excel limits sheet names to 31 characters.
                return mb_substr($this->title, 0, 31);
            }

            public function styles(Worksheet $sheet): array
            {
                $sheet->setRightToLeft(app()->getLocale() === 'ar');

                return [1 => ['font' => ['bold' => true]]];
            }
        };
    }
}
