<?php

namespace App\Services\Import;

final class ImportReport
{
    public int $totalRows = 0;

    public int $blankRows = 0;

    public int $importedRows = 0;

    public int $skippedRows = 0;

    public int $shipmentsCreated = 0;

    public int $containersCreated = 0;

    private array $issues = [];

    private array $rejected = [];

    private array $normalisations = [];

    public function error(?int $row, ?string $column, string $message, mixed $value = null): void
    {
        $this->add('error', $row, $column, $message, $value);

        if ($row !== null) {
            $this->rejected[$row] = true;
        }
    }

    public function warning(?int $row, ?string $column, string $message, mixed $value = null): void
    {
        $this->add('warning', $row, $column, $message, $value);
    }

    public function normalised(string $description): void
    {
        $this->normalisations[$description] = ($this->normalisations[$description] ?? 0) + 1;
    }

    public function isRejected(int $row): bool
    {
        return isset($this->rejected[$row]);
    }

    public function rejectedRows(): int
    {
        return count($this->rejected);
    }

    public function warningCount(): int
    {
        return count(array_filter($this->issues, fn (array $issue) => $issue['severity'] === 'warning'));
    }

    public function issues(): array
    {
        $issues = $this->issues;
        usort($issues, fn (array $a, array $b) => [$a['row'] ?? 0, $a['severity']] <=> [$b['row'] ?? 0, $b['severity']]);

        return $issues;
    }

    public function normalisations(): array
    {
        $normalisations = $this->normalisations;
        arsort($normalisations);

        return array_map(
            fn (string $description, int $count) => ['description' => $description, 'count' => $count],
            array_keys($normalisations),
            array_values($normalisations),
        );
    }

    private function add(string $severity, ?int $row, ?string $column, string $message, mixed $value): void
    {
        $this->issues[] = [
            'row' => $row,
            'severity' => $severity,
            'column' => $column,
            'message' => $message,
            'value' => $value === null ? null : mb_substr((string) (is_scalar($value) ? $value : json_encode($value)), 0, 200),
        ];
    }
}
