<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Http\Resources\ShipmentResource;
use App\Models\Shipment;
use App\Services\Shipments\ShipmentSummaries;

trait PresentsShipments
{
    protected function shipmentDetail(Shipment $shipment): ShipmentResource
    {
        $shipment->load('containers.cargoItems');
        app(ShipmentSummaries::class)->attach(collect([$shipment]));

        return new ShipmentResource($shipment);
    }
}
