<?php

namespace App\Services\Reports;

use App\Enums\CargoStatus;
use App\Models\CargoItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class StatusOverviewReport
{
    public function generate(array $filters = []): array
    {
        $itemsByStatus = $this->base($filters)
            ->groupBy('cargo_items.status')
            ->selectRaw('cargo_items.status, count(*) AS items, coalesce(sum(cargo_items.gross_weight_kg), 0) AS gross_weight_kg')
            ->toBase()
            ->get()
            ->keyBy('status');

        $containerRanks = $this->leastAdvancedRanks($filters, 'containers.id');
        $shipmentRanks = $this->leastAdvancedRanks($filters, 'shipments.id');

        return [
            'totals' => [
                'shipments' => $shipmentRanks->count(),
                'containers' => $containerRanks->count(),
                'items' => (int) $itemsByStatus->sum('items'),
                'gross_weight_kg' => round((float) $itemsByStatus->sum('gross_weight_kg'), 1),
            ],
            'statuses' => array_map(fn (CargoStatus $status) => [
                'status' => $status->value,
                'label' => $status->label(),
                'items' => (int) ($itemsByStatus[$status->value]->items ?? 0),
                'gross_weight_kg' => round((float) ($itemsByStatus[$status->value]->gross_weight_kg ?? 0), 1),
                'containers' => $containerRanks->filter(fn (int $rank) => $rank === $status->rank())->count(),
                'shipments' => $shipmentRanks->filter(fn (int $rank) => $rank === $status->rank())->count(),
            ], CargoStatus::cases()),
            'by_destination' => $this->breakdown($filters, 'shipments.destination_port', 'destination_port'),
            'by_customer' => $this->breakdown($filters, 'cargo_items.customer_name', 'customer_name'),
        ];
    }

    private function leastAdvancedRanks(array $filters, string $groupColumn): Collection
    {
        $rank = CargoStatus::rankSql('cargo_items.status');

        return $this->base($filters)
            ->groupBy($groupColumn)
            ->selectRaw("{$groupColumn} AS id, min({$rank}) AS status_rank")
            ->toBase()
            ->pluck('status_rank', 'id')
            ->map(fn ($rank) => (int) $rank);
    }

    private function breakdown(array $filters, string $column, string $key): array
    {
        return $this->base($filters)
            ->groupBy($column, 'cargo_items.status')
            ->selectRaw("{$column} AS name, cargo_items.status, count(*) AS items")
            ->toBase()
            ->get()
            ->groupBy(fn ($row) => $row->name ?? '—')
            ->map(function (Collection $rows, string $name) use ($key): array {
                $counts = array_fill_keys(CargoStatus::values(), 0);

                foreach ($rows as $row) {
                    $counts[$row->status] = (int) $row->items;
                }

                return [$key => $name, 'counts' => $counts, 'total' => array_sum($counts)];
            })
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    private function base(array $filters): Builder
    {
        return CargoItem::query()
            ->join('containers', 'containers.id', '=', 'cargo_items.container_id')
            ->join('shipments', 'shipments.id', '=', 'containers.shipment_id')
            ->when($filters['destination_port'] ?? null, fn (Builder $q, string $port) => $q->where('shipments.destination_port', $port))
            ->when($filters['customer'] ?? null, fn (Builder $q, string $customer) => $q->whereLike('cargo_items.customer_name', '%'.$customer.'%'));
    }
}
