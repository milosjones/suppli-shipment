<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CargoStatus;
use App\Http\Controllers\Api\V1\Concerns\PresentsShipments;
use App\Http\Controllers\Api\V1\Concerns\SelectsCargo;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeStatusRequest;
use App\Models\Shipment;
use App\Services\Shipments\ChangeContext;
use App\Services\Shipments\StatusService;
use App\Support\Dates;
use Illuminate\Http\JsonResponse;

class ShipmentStatusController extends Controller
{
    use PresentsShipments, SelectsCargo;

    public function __invoke(ChangeStatusRequest $request, Shipment $shipment, StatusService $statuses): JsonResponse
    {
        $data = $request->validated();
        [$items, $singleItems] = $this->selectCargo($shipment, $data);

        $result = $statuses->change(
            $shipment,
            $items,
            CargoStatus::from($data['status']),
            ChangeContext::byUser($request->user(), $data['reason'] ?? null, Dates::eventTime($data['occurred_at'] ?? null)),
            itemScope: $singleItems,
            correction: $request->boolean('correction'),
        );

        return response()->json([
            'change_group' => $result->changeGroup,
            'changed' => $result->changed,
            'unchanged' => $result->unchanged,
            'shipment' => $this->shipmentDetail($shipment->refresh()),
        ]);
    }
}
