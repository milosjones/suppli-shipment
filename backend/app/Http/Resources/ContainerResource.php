<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContainerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $itemsLoaded = $this->relationLoaded('cargoItems');

        return [
            'id' => $this->id,
            'shipment_id' => $this->shipment_id,
            'container_no' => $this->container_no,
            'seal_no' => $this->seal_no,
            'container_type' => $this->container_type,
            'status' => $this->when($itemsLoaded, fn () => $this->status()),
            'status_label' => $this->when($itemsLoaded, fn () => $this->status()?->label()),
            'status_counts' => $this->when($itemsLoaded, fn () => (object) $this->statusCounts()),
            'items' => CargoItemResource::collection($this->whenLoaded('cargoItems')),
        ];
    }
}
