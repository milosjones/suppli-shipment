<?php

namespace App\Services\Shipments;

final readonly class StatusChangeResult
{
    public function __construct(
        public string $changeGroup,
        public int $changed,
        public int $unchanged,
    ) {}
}
