<?php

namespace App\Http\Resources;

use App\Support\Dates;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CargoItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'container_id' => $this->container_id,
            'package_no' => $this->package_no,

            'proforma_invoice_no' => $this->proforma_invoice_no,
            'exporter_reference' => $this->exporter_reference,
            'tag_number' => $this->tag_number,
            'product_code' => $this->product_code,
            'product_description' => $this->product_description,
            'product_category' => $this->product_category,
            'product_category_code' => $this->product_category->code(),
            'net_type' => $this->net_type,
            'customer_name' => $this->customer_name,
            'customer_reference' => $this->customer_reference,
            'customer_po' => $this->customer_po,
            'storage_facility' => $this->storage_facility,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'net_weight_kg' => $this->net_weight_kg,
            'gross_weight_kg' => $this->gross_weight_kg,

            'status' => $this->status,
            'status_label' => $this->status->label(),
            'raw_status' => $this->raw_status,
            'customer_delivery_date' => $this->customer_delivery_date?->toDateString(),
            'customer_delivery_date_original' => $this->customer_delivery_date_original?->toDateString(),
            'delivery_delay_days' => $this->delivery_delay_days,
            'is_delivery_overdue' => $this->isDeliveryOverdue(Dates::today()),
            'notes' => $this->notes,

            'container' => $this->whenLoaded('container', fn () => [
                'id' => $this->container->id,
                'container_no' => $this->container->container_no,
                'container_type' => $this->container->container_type,
            ]),
            'shipment' => $this->when(
                $this->relationLoaded('container') && $this->container->relationLoaded('shipment'),
                fn () => [
                    'id' => $this->container->shipment->id,
                    'reference' => $this->container->shipment->reference,
                    'status' => $this->container->shipment->status,
                    'shipping_method' => $this->container->shipment->shipping_method,
                    'destination_port' => $this->container->shipment->destination_port,
                    'etd' => $this->container->shipment->etd?->toDateString(),
                    'eta' => $this->container->shipment->eta?->toDateString(),
                ],
            ),
        ];
    }
}
