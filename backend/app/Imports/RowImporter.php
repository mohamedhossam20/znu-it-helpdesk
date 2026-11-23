<?php

namespace App\Imports;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * All-or-nothing spreadsheet import keyed on a natural key (code / email /
 * asset tag), so re-uploading a corrected file updates rows instead of
 * duplicating them.
 *
 * Rows are written inside one transaction even for a preview: later rows can
 * then reference things created by earlier ones (a building in row 2, its
 * rooms in rows 3–40). A preview, or any row error, rolls everything back.
 */
abstract class RowImporter
{
    /**
     * @return list<string>
     */
    abstract public function headings(): array;

    /**
     * @return list<array<string, string|int|null>>
     */
    abstract public function exampleRows(): array;

    /**
     * @return array<string, mixed>
     */
    abstract protected function rules(): array;

    /**
     * Save one validated row. Return true if a new record was created,
     * false if an existing one was updated. Throw RowImportException for
     * problems that need the database to detect (unknown room, …).
     *
     * @param  array<string, mixed>  $row
     */
    abstract protected function importRow(array $row): bool;

    /**
     * @param  list<array<string, mixed>>  $rows  keyed by heading
     */
    public function run(array $rows, bool $dryRun): ImportReport
    {
        $rows = array_values(array_filter($rows, fn (array $row) => array_filter($row, fn ($value) => filled($value)) !== []));
        $report = new ImportReport(count($rows));

        DB::beginTransaction();

        try {
            foreach ($rows as $index => $row) {
                // Line 1 is the heading row in the spreadsheet.
                $line = $index + 2;
                $row = $this->normalize($row);

                $validator = Validator::make($row, $this->rules(), [], $this->attributeNames());

                if ($validator->fails()) {
                    foreach ($validator->errors()->all() as $message) {
                        $report->addError($line, $message);
                    }

                    continue;
                }

                try {
                    $this->importRow($validator->validated()) ? $report->created++ : $report->updated++;
                } catch (RowImportException $e) {
                    $report->addError($line, $e->getMessage());
                }
            }

            if ($dryRun || $report->hasErrors()) {
                DB::rollBack();
            } else {
                DB::commit();
                $report->saved = true;
            }
        } catch (Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        return $report;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function normalize(array $row): array
    {
        $clean = [];

        foreach ($this->headings() as $heading) {
            $value = $row[$heading] ?? null;

            if (is_string($value)) {
                $value = trim($value);
            }

            if (is_float($value) && floor($value) === $value) {
                // Excel stores 204 as 204.0; codes and levels must stay whole.
                $value = (string) (int) $value;
            }

            $clean[$heading] = $value === '' ? null : (is_int($value) ? (string) $value : $value);
        }

        return $clean;
    }

    /**
     * @return array<string, string>
     */
    protected function attributeNames(): array
    {
        return array_combine($this->headings(), $this->headings());
    }
}
