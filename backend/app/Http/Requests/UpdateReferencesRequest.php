<?php

namespace App\Http\Requests;

use App\Enums\ProductCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReferencesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:1000'],
            'items.*.package_no' => ['required', 'string', 'max:50', 'distinct:ignore_case'],
            'items.*.proforma_invoice_no' => ['sometimes', 'nullable', 'string', 'max:50'],
            'items.*.exporter_reference' => ['sometimes', 'nullable', 'string', 'max:50'],
            'items.*.tag_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'items.*.product_code' => ['sometimes', 'nullable', 'string', 'max:50'],
            'items.*.product_description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'items.*.product_category' => ['sometimes', Rule::enum(ProductCategory::class)],
            'items.*.net_type' => ['sometimes', 'nullable', 'string', 'max:50'],
            'items.*.customer_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'items.*.customer_reference' => ['sometimes', 'nullable', 'string', 'max:100'],
            'items.*.customer_po' => ['sometimes', 'nullable', 'string', 'max:100'],
            'items.*.storage_facility' => ['sometimes', 'nullable', 'string', 'max:100'],
            'items.*.quantity' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'items.*.unit' => ['sometimes', 'nullable', 'string', 'max:10'],
            'items.*.net_weight_kg' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'items.*.gross_weight_kg' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ];
    }
}
