<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CargoStatus;
use App\Enums\DateField;
use App\Enums\ProductCategory;
use App\Enums\ShippingMethod;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\CargoItem;
use App\Models\Shipment;
use App\Support\Dates;
use Illuminate\Http\JsonResponse;

class MetaController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'today' => Dates::today()->toDateString(),
            'timezone' => config('shipments.timezone'),
            'statuses' => array_map(fn (CargoStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
                'rank' => $status->rank(),
                'next' => array_map(fn (CargoStatus $next) => $next->value, $status->nextStatuses()),
                'item_level' => $status->canTargetSingleItems(),
            ], CargoStatus::cases()),
            'shipping_methods' => array_map(fn (ShippingMethod $method) => [
                'value' => $method->value,
                'label' => $method->label(),
                'unit_label' => $method->unitLabel(),
            ], ShippingMethod::cases()),
            'product_categories' => array_map(fn (ProductCategory $category) => [
                'value' => $category->value,
                'label' => $category->label(),
                'code' => $category->code(),
            ], ProductCategory::cases()),
            'date_fields' => array_map(fn (DateField $field) => [
                'value' => $field->value,
                'label' => $field->label(),
            ], DateField::cases()),
            'company_roles' => array_map(fn (UserRole $role) => [
                'value' => $role->value,
                'label' => $role->label(),
            ], UserRole::companyRoles()),
            'reference_fields' => CargoItem::REFERENCE_FIELDS,
            'filters' => [
                'destination_ports' => Shipment::query()->distinct()->orderBy('destination_port')->pluck('destination_port'),
                'shipping_lines' => Shipment::query()->whereNotNull('shipping_line')->distinct()->orderBy('shipping_line')->pluck('shipping_line'),
                'customers' => CargoItem::query()->whereNotNull('customer_name')->distinct()->orderBy('customer_name')->pluck('customer_name'),
                'storage_facilities' => CargoItem::query()->whereNotNull('storage_facility')->distinct()->orderBy('storage_facility')->pluck('storage_facility'),
            ],
        ]);
    }
}
