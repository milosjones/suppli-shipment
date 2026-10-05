<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\PresentsShipments;
use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Tracking\TrackingSyncService;
use Illuminate\Http\JsonResponse;

class TrackingSyncController extends Controller
{
    use PresentsShipments;

    public function __invoke(Shipment $shipment, TrackingSyncService $sync): JsonResponse
    {
        $result = $sync->sync($shipment);

        return response()->json([
            ...$result->toArray(),
            'shipment' => $this->shipmentDetail($shipment->refresh()),
        ]);
    }
}
