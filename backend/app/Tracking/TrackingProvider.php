<?php

namespace App\Tracking;

use App\Models\Shipment;

interface TrackingProvider
{
    public function name(): string;

    public function supports(Shipment $shipment): bool;

    public function fetchUpdates(Shipment $shipment): array;
}
