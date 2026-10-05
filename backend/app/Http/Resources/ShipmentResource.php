<?php

namespace App\Http\Resources;

use App\Services\Shipments\ShipmentSummary;
use App\Support\Dates;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $today = Dates::today();
        $summary = $this->summary();

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'external_reference' => $this->external_reference,
            'shipping_method' => $this->shipping_method,
            'shipping_line' => $this->shipping_line,
            'origin_port' => $this->origin_port,
            'destination_port' => $this->destination_port,
            'incoterm' => $this->incoterm,
            'etd' => $this->etd?->toDateString(),
            'etd_original' => $this->etd_original?->toDateString(),
            'etd_delay_days' => $this->etd_delay_days,
            'eta' => $this->eta?->toDateString(),
            'eta_original' => $this->eta_original?->toDateString(),
            'eta_delay_days' => $this->eta_delay_days,
            'status' => $this->status,
            'status_label' => $this->status->label(),
            'is_overdue' => $this->isOverdue($today),
            'overdue_days' => $this->overdueDays($today),
            'notes' => $this->notes,
            'summary' => $this->when($summary !== null, fn () => [
                'status_counts' => (object) $summary->statusCounts,
                'items_count' => $summary->itemsCount,
                'containers_count' => $summary->containersCount,
                'customers' => $summary->customers,
            ]),
            'containers' => ContainerResource::collection($this->whenLoaded('containers')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function summary(): ?ShipmentSummary
    {
        return $this->relationLoaded('summary') ? $this->getRelation('summary') : null;
    }
}
