<?php

namespace App\Tracking\Providers;

use App\Enums\CargoStatus;
use App\Models\Shipment;
use App\Support\Dates;
use App\Tracking\TrackingProvider;
use App\Tracking\TrackingUpdate;

final class FakeTrackingProvider implements TrackingProvider
{
    private array $updates = [];

    public function __construct(private readonly bool $simulate = false) {}

    public function name(): string
    {
        return 'fake';
    }

    public function supports(Shipment $shipment): bool
    {
        return true;
    }

    public function push(string $shipmentReference, TrackingUpdate ...$updates): void
    {
        $this->updates[$shipmentReference] = [...($this->updates[$shipmentReference] ?? []), ...$updates];
    }

    public function fetchUpdates(Shipment $shipment): array
    {
        if (isset($this->updates[$shipment->reference])) {
            return $this->updates[$shipment->reference];
        }

        return $this->simulate ? $this->simulate($shipment) : [];
    }

    private function simulate(Shipment $shipment): array
    {
        $today = Dates::today();

        if ($shipment->status === CargoStatus::Planned && $shipment->etd?->lte($today)) {
            return [new TrackingUpdate(
                status: CargoStatus::InTransit,
                occurredAt: $shipment->etd->setTime(12, 0),
                description: 'departure confirmed by the carrier (simulated)',
            )];
        }

        if ($shipment->status === CargoStatus::InTransit && $shipment->eta?->lte($today)) {
            return [new TrackingUpdate(
                status: CargoStatus::Arrived,
                occurredAt: $shipment->eta->setTime(12, 0),
                description: 'vessel arrived at the destination port (simulated)',
            )];
        }

        return [];
    }
}
