<?php

namespace App\Services\Reports;

use App\Enums\CargoStatus;
use App\Models\CargoItem;
use App\Models\Shipment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class UpcomingDeliveriesReport
{
    public function generate(CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $asOf, array $filters = []): array
    {
        return [
            'arrivals' => $this->shipments($filters)
                ->whereBetween('eta', [$from->toDateString(), $to->toDateString()])
                ->orderBy('eta')
                ->get(),
            'deliveries' => $this->items($filters)
                ->whereBetween('cargo_items.customer_delivery_date', [$from->toDateString(), $to->toDateString()])
                ->orderBy('cargo_items.customer_delivery_date')
                ->orderBy('cargo_items.customer_name')
                ->get(),
            'overdue_arrivals' => $this->shipments($filters)
                ->where('eta', '<', $asOf->toDateString())
                ->orderBy('eta')
                ->get(),
            'overdue_deliveries' => $this->items($filters)
                ->where('cargo_items.customer_delivery_date', '<', $asOf->toDateString())
                ->orderBy('cargo_items.customer_delivery_date')
                ->orderBy('cargo_items.customer_name')
                ->get(),
        ];
    }

    private function shipments(array $filters): Builder
    {
        return Shipment::query()
            ->whereIn('status', CargoStatus::notArrived())
            ->when($filters['destination_port'] ?? null, fn (Builder $q, string $port) => $q->where('destination_port', $port))
            ->when($filters['customer'] ?? null, fn (Builder $q, string $customer) => $q->whereHas(
                'cargoItems',
                fn (Builder $items) => $items->whereLike('cargo_items.customer_name', '%'.$customer.'%'),
            ));
    }

    private function items(array $filters): Builder
    {
        return CargoItem::query()
            ->select('cargo_items.*')
            ->join('containers', 'containers.id', '=', 'cargo_items.container_id')
            ->join('shipments', 'shipments.id', '=', 'containers.shipment_id')
            ->where('cargo_items.status', '!=', CargoStatus::Delivered)
            ->when($filters['destination_port'] ?? null, fn (Builder $q, string $port) => $q->where('shipments.destination_port', $port))
            ->when($filters['customer'] ?? null, fn (Builder $q, string $customer) => $q->whereLike('cargo_items.customer_name', '%'.$customer.'%'))
            ->with('container.shipment');
    }
}
