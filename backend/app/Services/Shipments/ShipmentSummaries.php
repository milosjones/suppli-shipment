<?php

namespace App\Services\Shipments;

use App\Models\CargoItem;
use App\Models\Container;
use App\Models\Shipment;
use Illuminate\Support\Collection;

final class ShipmentSummaries
{
    public function attach(Collection $shipments): void
    {
        if ($shipments->isEmpty()) {
            return;
        }

        $ids = $shipments->map(fn (Shipment $shipment) => $shipment->getKey())->all();

        $statusCounts = CargoItem::query()
            ->join('containers', 'containers.id', '=', 'cargo_items.container_id')
            ->whereIn('containers.shipment_id', $ids)
            ->groupBy('containers.shipment_id', 'cargo_items.status')
            ->selectRaw('containers.shipment_id, cargo_items.status, count(*) AS total')
            ->toBase()
            ->get()
            ->groupBy('shipment_id');

        $customers = CargoItem::query()
            ->join('containers', 'containers.id', '=', 'cargo_items.container_id')
            ->whereIn('containers.shipment_id', $ids)
            ->whereNotNull('cargo_items.customer_name')
            ->distinct()
            ->orderBy('cargo_items.customer_name')
            ->select('containers.shipment_id', 'cargo_items.customer_name')
            ->toBase()
            ->get()
            ->groupBy('shipment_id');

        $containerCounts = Container::query()
            ->whereIn('shipment_id', $ids)
            ->groupBy('shipment_id')
            ->selectRaw('shipment_id, count(*) AS total')
            ->toBase()
            ->pluck('total', 'shipment_id');

        foreach ($shipments as $shipment) {
            $counts = collect($statusCounts->get($shipment->id, []))
                ->mapWithKeys(fn ($row) => [$row->status => (int) $row->total])
                ->all();

            $shipment->setRelation('summary', new ShipmentSummary(
                statusCounts: $counts,
                itemsCount: array_sum($counts),
                containersCount: (int) ($containerCounts[$shipment->id] ?? 0),
                customers: collect($customers->get($shipment->id, []))->pluck('customer_name')->values()->all(),
            ));
        }
    }
}
