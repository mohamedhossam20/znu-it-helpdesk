<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Reads the first sheet of an .xlsx/.csv into rows keyed by heading.
 */
class SpreadsheetReader implements ToArray, WithHeadingRow
{
    public const MAX_ROWS = 5000;

    /**
     * Required by the ToArray concern; rows are taken from Excel::toArray() instead.
     *
     * @param  array<int, array<string, mixed>>  $array
     */
    public function array(array $array): void {}

    /**
     * @return list<array<string, mixed>>
     */
    public function read(string $path, ?string $disk = null): array
    {
        $sheets = Excel::toArray($this, $path, $disk);
        $rows = $sheets[0] ?? [];

        if (count($rows) > self::MAX_ROWS) {
            throw new RowImportException(__('The file has more than :max rows. Split it into smaller files.', ['max' => self::MAX_ROWS]));
        }

        return array_values($rows);
    }
}
