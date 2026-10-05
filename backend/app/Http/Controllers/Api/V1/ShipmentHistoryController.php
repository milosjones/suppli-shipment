<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Services\Shipments\ShipmentHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShipmentHistoryController extends Controller
{
    public function __invoke(Request $request, Shipment $shipment, ShipmentHistory $history): JsonResponse
    {
        $type = $request->validate(['type' => ['sometimes', Rule::in(['status', 'dates'])]])['type'] ?? null;

        return response()->json(['data' => $history->timeline($shipment, $type)]);
    }
}
