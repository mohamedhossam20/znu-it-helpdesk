<?php

namespace App\Imports;

final class ImportReport
{
    public int $created = 0;

    public int $updated = 0;

    /**
     * @var array<int, list<string>> spreadsheet line number => messages
     */
    public array $errors = [];

    public bool $saved = false;

    public function __construct(public readonly int $totalRows) {}

    public function addError(int $line, string $message): void
    {
        $this->errors[$line][] = $message;
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    /**
     * @return array{total: int, created: int, updated: int, saved: bool, errors: array<int, list<string>>}
     */
    public function toArray(): array
    {
        return [
            'total' => $this->totalRows,
            'created' => $this->created,
            'updated' => $this->updated,
            'saved' => $this->saved,
            'errors' => $this->errors,
        ];
    }
}
