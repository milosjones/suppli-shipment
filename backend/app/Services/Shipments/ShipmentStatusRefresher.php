<?php

namespace App\Services\Shipments;

use App\Enums\CargoStatus;
use App\Models\CargoItem;
use App\Models\Shipment;

final class ShipmentStatusRefresher
{
    public function refresh(Shipment $shipment): void
    {
        $statuses = CargoItem::query()
            ->join('containers', 'containers.id', '=', 'cargo_items.container_id')
            ->where('containers.shipment_id', $shipment->id)
            ->distinct()
            ->pluck('cargo_items.status');

        $shipment->status = CargoStatus::leastAdvanced($statuses) ?? CargoStatus::Planned;
        $shipment->touch();
    }
}
