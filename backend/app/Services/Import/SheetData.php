<?php

namespace App\Services\Import;

final readonly class SheetData
{
    public function __construct(
        public string $sheetName,
        public int $headerRow,
        public array $columns,
        public array $rows,
    ) {}
}
