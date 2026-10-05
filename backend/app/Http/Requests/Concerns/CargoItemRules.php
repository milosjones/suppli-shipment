<?php

namespace App\Http\Requests\Concerns;

use App\Enums\ProductCategory;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\Rule;

trait CargoItemRules
{
    protected function cargoItemRules(string $prefix): array
    {
        $companyId = app(TenantContext::class)->id();

        return [
            "{$prefix}.package_no" => [
                'required', 'string', 'max:50', 'distinct:ignore_case',
                Rule::unique('cargo_items', 'package_no')->where('company_id', $companyId),
            ],
            "{$prefix}.proforma_invoice_no" => ['nullable', 'string', 'max:50'],
            "{$prefix}.exporter_reference" => ['nullable', 'string', 'max:50'],
            "{$prefix}.tag_number" => ['nullable', 'string', 'max:50'],
            "{$prefix}.product_code" => ['nullable', 'string', 'max:50'],
            "{$prefix}.product_description" => ['nullable', 'string', 'max:255'],
            "{$prefix}.product_category" => ['nullable', Rule::enum(ProductCategory::class)],
            "{$prefix}.net_type" => ['nullable', 'string', 'max:50'],
            "{$prefix}.customer_name" => ['nullable', 'string', 'max:255'],
            "{$prefix}.customer_reference" => ['nullable', 'string', 'max:100'],
            "{$prefix}.customer_po" => ['nullable', 'string', 'max:100'],
            "{$prefix}.storage_facility" => ['nullable', 'string', 'max:100'],
            "{$prefix}.quantity" => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            "{$prefix}.unit" => ['nullable', 'string', 'max:10'],
            "{$prefix}.net_weight_kg" => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            "{$prefix}.gross_weight_kg" => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            "{$prefix}.customer_delivery_date" => ['nullable', 'date_format:Y-m-d'],
            "{$prefix}.notes" => ['nullable', 'string', 'max:2000'],
        ];
    }
}
