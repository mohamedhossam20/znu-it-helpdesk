<?php

namespace App\Exports;

use App\Imports\RowImporter;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ImportTemplateExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles
{
    public function __construct(private readonly RowImporter $importer) {}

    public function headings(): array
    {
        return $this->importer->headings();
    }

    /**
     * @return list<list<string|int|null>>
     */
    public function array(): array
    {
        return array_map(
            fn (array $row) => array_map(fn (string $heading) => $row[$heading] ?? null, $this->headings()),
            $this->importer->exampleRows(),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        $sheet->setRightToLeft(true);

        return [1 => ['font' => ['bold' => true]]];
    }
}
