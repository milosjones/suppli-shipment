<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCargoItemsRequest;
use App\Http\Resources\CargoItemResource;
use App\Models\Container;
use App\Services\Shipments\ChangeContext;
use App\Services\Shipments\ShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class CargoItemController extends Controller
{
    public function store(StoreCargoItemsRequest $request, Container $container, ShipmentService $shipments): JsonResponse
    {
        $items = $shipments->addItems(
            $container,
            $request->validated('items'),
            ChangeContext::byUser($request->user(), 'Cargo added'),
        );

        return CargoItemResource::collection($items)->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
