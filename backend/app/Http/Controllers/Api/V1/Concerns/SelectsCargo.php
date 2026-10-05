<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\Shipment;
use Illuminate\Validation\ValidationException;

trait SelectsCargo
{
    protected function selectCargo(Shipment $shipment, array $data): array
    {
        if (! empty($data['cargo_item_ids'])) {
            $ids = array_map('intval', $data['cargo_item_ids']);
            $items = $shipment->cargoQuery()->whereKey($ids)->get();

            if ($items->count() !== count($ids)) {
                throw ValidationException::withMessages(['cargo_item_ids' => 'Some cargo items do not belong to this shipment.']);
            }

            return [$items, true];
        }

        if (! empty($data['container_ids'])) {
            $ids = array_map('intval', $data['container_ids']);

            if ($shipment->containers()->whereKey($ids)->count() !== count($ids)) {
                throw ValidationException::withMessages(['container_ids' => 'Some containers do not belong to this shipment.']);
            }

            return [$shipment->cargoQuery()->whereIn('container_id', $ids)->get(), false];
        }

        return [$shipment->cargoQuery()->get(), false];
    }
}
