<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\PresentsShipments;
use App\Http\Controllers\Api\V1\Concerns\SelectsCargo;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeDatesRequest;
use App\Models\Shipment;
use App\Services\Shipments\ChangeContext;
use App\Services\Shipments\DateChangeService;
use App\Support\Dates;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ShipmentDateController extends Controller
{
    use PresentsShipments, SelectsCargo;

    public function __invoke(ChangeDatesRequest $request, Shipment $shipment, DateChangeService $dates): JsonResponse
    {
        $data = $request->validated();
        $context = ChangeContext::byUser($request->user(), $data['reason']);

        DB::transaction(function () use ($data, $shipment, $dates, $context): void {
            $shipmentDates = array_map(
                fn (string $date) => Dates::parse($date),
                array_intersect_key($data, array_flip(['etd', 'eta'])),
            );

            if ($shipmentDates !== []) {
                $dates->changeShipmentDates($shipment, $shipmentDates, $context);
            }

            if (array_key_exists('customer_delivery_date', $data)) {
                [$items] = $this->selectCargo($shipment, $data);
                $dates->changeDeliveryDates($shipment, $items, Dates::parse($data['customer_delivery_date']), $context);
            }
        });

        return response()->json([
            'change_group' => $context->group,
            'shipment' => $this->shipmentDetail($shipment->refresh()),
        ]);
    }
}
