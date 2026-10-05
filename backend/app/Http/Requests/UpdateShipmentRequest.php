<?php

namespace App\Http\Requests;

use App\Enums\ShippingMethod;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShipmentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'shipping_method' => ['sometimes', Rule::enum(ShippingMethod::class)],
            'shipping_line' => ['sometimes', 'nullable', 'string', 'max:100'],
            'origin_port' => ['sometimes', 'nullable', 'string', 'max:100'],
            'destination_port' => ['sometimes', 'required', 'string', 'max:100'],
            'incoterm' => ['sometimes', 'nullable', 'string', 'max:10'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'external_reference' => [
                'sometimes', 'nullable', 'string', 'max:100',
                Rule::unique('shipments', 'external_reference')
                    ->where('company_id', app(TenantContext::class)->id())
                    ->ignore($this->route('shipment')),
            ],
        ];
    }
}
