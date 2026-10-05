<?php

namespace App\Services\Reports;

use App\Enums\CargoStatus;
use App\Models\CargoItem;
use App\Models\Shipment;
use App\Support\Dates;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class DelayedShipmentsReport
{
    public function generate(int $minDays, CarbonImmutable $asOf): array
    {
        $cutoff = $asOf->subDays($minDays)->toDateString();

        $shipments = Shipment::query()
            ->where(fn (Builder $q) => $q
                ->where('eta_delay_days', '>=', $minDays)
                ->orWhere('etd_delay_days', '>=', $minDays)
                ->orWhere(fn (Builder $overdue) => $overdue
                    ->whereIn('status', CargoStatus::notArrived())
                    ->where('eta', '<=', $cutoff)))
            ->get()
            ->map(fn (Shipment $shipment) => [
                'shipment' => $shipment,
                'etd_delay_days' => $shipment->etd_delay_days,
                'eta_delay_days' => $shipment->eta_delay_days,
                'overdue_days' => $shipment->overdueDays($asOf),
                'max_delay_days' => max($shipment->etd_delay_days, $shipment->eta_delay_days, $shipment->overdueDays($asOf)),
            ])
            ->sortByDesc('max_delay_days')
            ->values();

        $items = CargoItem::query()
            ->where(fn (Builder $q) => $q
                ->where('delivery_delay_days', '>=', $minDays)
                ->orWhere(fn (Builder $overdue) => $overdue
                    ->where('status', '!=', CargoStatus::Delivered)
                    ->where('customer_delivery_date', '<=', $cutoff)))
            ->with('container.shipment')
            ->get()
            ->map(function (CargoItem $item) use ($asOf): array {
                $overdue = $item->isDeliveryOverdue($asOf) ? Dates::daysBetween($item->customer_delivery_date, $asOf) : 0;

                return [
                    'item' => $item,
                    'delivery_delay_days' => $item->delivery_delay_days,
                    'overdue_days' => $overdue,
                    'max_delay_days' => max($item->delivery_delay_days, $overdue),
                ];
            })
            ->sortByDesc('max_delay_days')
            ->values();

        $atRisk = CargoItem::query()
            ->select('cargo_items.*')
            ->join('containers', 'containers.id', '=', 'cargo_items.container_id')
            ->join('shipments', 'shipments.id', '=', 'containers.shipment_id')
            ->whereIn('cargo_items.status', CargoStatus::notArrived())
            ->whereNotNull('cargo_items.customer_delivery_date')
            ->whereColumn('shipments.eta', '>', 'cargo_items.customer_delivery_date')
            ->with('container.shipment')
            ->orderBy('cargo_items.customer_delivery_date')
            ->get()
            ->map(fn (CargoItem $item) => [
                'item' => $item,
                'days_short' => Dates::daysBetween($item->customer_delivery_date, $item->container->shipment->eta),
            ]);

        return [
            'as_of' => $asOf->toDateString(),
            'min_days' => $minDays,
            'shipments' => $shipments,
            'items' => $items,
            'at_risk' => $atRisk,
        ];
    }
}
