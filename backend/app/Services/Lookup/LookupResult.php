<?php

namespace App\Services\Lookup;

use Illuminate\Support\Collection;

final readonly class LookupResult
{
    public function __construct(
        public string $query,
        public string $type,
        public Collection $items,
        public bool $truncated,
    ) {}
}
