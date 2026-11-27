<?php

namespace App\Reports;

/**
 * A finished report: rendered on screen, as .xlsx and as PDF from the same data.
 * Column types: text, money, int, date (rows hold raw values; formatting happens at render time).
 */
class ReportData
{
    /**
     * @param  array<string, array{label: string, type?: string}>  $columns
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $totals  keyed like columns; shown as the last row
     * @param  array<string, string>  $summary  label => formatted value, shown above the table
     */
    public function __construct(
        public string $title,
        public string $subtitle,
        public array $columns,
        public array $rows,
        public array $totals = [],
        public array $summary = [],
    ) {}

    public function type(string $col): string
    {
        return $this->columns[$col]['type'] ?? 'text';
    }

    public function format(string $col, $value, bool $symbol = false): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return match ($this->type($col)) {
            'money' => inr($value, $symbol),
            'int' => number_format((int) $value),
            'date' => $value instanceof \DateTimeInterface ? $value->format('d-m-Y') : (string) $value,
            default => (string) $value,
        };
    }

    /** Sum the given columns over all rows. */
    public static function sum(array $rows, array $cols): array
    {
        $out = [];
        foreach ($cols as $c) {
            $out[$c] = round(array_sum(array_map(fn ($r) => (float) ($r[$c] ?? 0), $rows)), 2);
        }

        return $out;
    }
}
