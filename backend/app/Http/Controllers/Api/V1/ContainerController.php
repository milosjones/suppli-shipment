<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContainerRequest;
use App\Http\Requests\UpdateContainerRequest;
use App\Http\Resources\ContainerResource;
use App\Models\Container;
use App\Models\Shipment;
use App\Services\Shipments\ChangeContext;
use App\Services\Shipments\ShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ContainerController extends Controller
{
    public function __construct(private readonly ShipmentService $shipments) {}

    public function store(StoreContainerRequest $request, Shipment $shipment): JsonResponse
    {
        $container = $this->shipments->addContainer(
            $shipment,
            $request->validated(),
            ChangeContext::byUser($request->user(), 'Container added'),
        );

        return (new ContainerResource($container->load('cargoItems')))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateContainerRequest $request, Container $container): ContainerResource
    {
        return new ContainerResource($this->shipments->updateContainer($container, $request->validated())->load('cargoItems'));
    }

    public function destroy(Container $container): Response
    {
        $this->shipments->deleteContainer($container);

        return response()->noContent();
    }
}
