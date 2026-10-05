<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateReferencesRequest;
use App\Models\CargoItem;
use App\Models\Shipment;
use App\Support\TagNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CargoItemReferenceController extends Controller
{
    public function __invoke(UpdateReferencesRequest $request): JsonResponse
    {
        $updated = [];
        $notFound = [];
        $shipmentIds = [];

        DB::transaction(function () use ($request, &$updated, &$notFound, &$shipmentIds): void {
            foreach ($request->validated('items') as $row) {
                $item = CargoItem::query()->with('container')->where('package_no', $row['package_no'])->first();

                if ($item === null) {
                    $notFound[] = $row['package_no'];

                    continue;
                }

                $changes = Arr::only($row, CargoItem::REFERENCE_FIELDS);

                if (array_key_exists('tag_number', $changes)) {
                    $changes['tag_number'] = TagNumber::normalize($changes['tag_number']);
                }

                $item->fill($changes);

                if ($item->isDirty()) {
                    $item->save();
                    $updated[] = $item->package_no;
                    $shipmentIds[$item->container->shipment_id] = true;
                }
            }

            Shipment::query()->whereKey(array_keys($shipmentIds))->update(['updated_at' => now()]);
        });

        return response()->json(['updated' => $updated, 'not_found' => $notFound]);
    }
}
