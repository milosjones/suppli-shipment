<?php

namespace App\Tracking;

use App\Enums\CargoStatus;
use Carbon\CarbonImmutable;

final readonly class TrackingUpdate
{
    public function __construct(
        public ?CargoStatus $status = null,
        public ?CarbonImmutable $occurredAt = null,
        public ?string $containerNo = null,
        public array $packageNos = [],
        public ?CarbonImmutable $etd = null,
        public ?CarbonImmutable $eta = null,
        public ?CarbonImmutable $customerDeliveryDate = null,
        public ?string $eventId = null,
        public ?string $description = null,
    ) {}

    public function targetsPackages(): bool
    {
        return $this->packageNos !== [];
    }

    public function target(): string
    {
        return match (true) {
            $this->targetsPackages() => 'packages '.implode(', ', $this->packageNos),
            $this->containerNo !== null => "container {$this->containerNo}",
            default => 'shipment',
        };
    }
}
