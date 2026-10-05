<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CargoItemResource;
use App\Http\Resources\ShipmentResource;
use App\Models\CargoItem;
use App\Services\Lookup\LookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class LookupController extends Controller
{
    public function __invoke(Request $request, LookupService $lookup): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'type' => ['sometimes', Rule::in(LookupService::TYPES)],
        ]);

        $result = $lookup->search($data['q'], $data['type'] ?? 'all');

        $groups = $result->items
            ->groupBy(fn (CargoItem $item) => $item->container->shipment_id)
            ->map(fn (Collection $items) => [
                'shipment' => new ShipmentResource($items->first()->container->shipment),
                'containers' => $items->groupBy('container_id')->map(fn (Collection $containerItems) => [
                    'id' => $containerItems->first()->container->id,
                    'container_no' => $containerItems->first()->container->container_no,
                    'container_type' => $containerItems->first()->container->container_type,
                    'items' => $containerItems->map(fn (CargoItem $item) => [
                        ...(new CargoItemResource($item))->resolve($request),
                        'matched_on' => $lookup->matchedFields($item, $result->query),
                    ])->values(),
                ])->values(),
            ])
            ->values();

        return response()->json([
            'query' => $result->query,
            'type' => $result->type,
            'total_items' => $result->items->count(),
            'truncated' => $result->truncated,
            'results' => $groups,
        ]);
    }
}
