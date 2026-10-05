<?php

namespace App\Services\Shipments;

final readonly class ShipmentSummary
{
    public function __construct(
        public array $statusCounts,
        public int $itemsCount,
        public int $containersCount,
        public array $customers,
    ) {}
}
