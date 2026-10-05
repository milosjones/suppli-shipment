<?php

namespace App\Http\Requests;

use App\Enums\CargoStatus;
use App\Enums\ShippingMethod;
use App\Http\Requests\Concerns\CargoItemRules;
use App\Rules\ValidContainerNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShipmentRequest extends FormRequest
{
    use CargoItemRules;

    public function rules(): array
    {
        $method = ShippingMethod::tryFrom((string) $this->input('shipping_method'));

        return [
            'shipping_method' => ['required', Rule::enum(ShippingMethod::class)],
            'shipping_line' => ['nullable', 'string', 'max:100'],
            'origin_port' => ['nullable', 'string', 'max:100'],
            'destination_port' => ['required', 'string', 'max:100'],
            'incoterm' => ['nullable', 'string', 'max:10'],
            'etd' => ['nullable', 'date_format:Y-m-d'],
            'eta' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('etd'), 'after_or_equal:etd')],
            'external_reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'initial_status' => ['nullable', Rule::enum(CargoStatus::class)],
            'containers' => ['required', 'array', 'min:1', 'max:100'],
            'containers.*.container_no' => ['required', 'string', 'max:20', 'distinct:ignore_case', new ValidContainerNumber($method)],
            'containers.*.seal_no' => ['nullable', 'string', 'max:50'],
            'containers.*.container_type' => ['nullable', 'string', 'max:10'],
            'containers.*.items' => ['required', 'array', 'min:1', 'max:500'],
            ...$this->cargoItemRules('containers.*.items.*'),
        ];
    }

    public function messages(): array
    {
        return [
            'eta.after_or_equal' => 'The ETA must be on or after the ETD.',
            'containers.*.items.*.package_no.unique' => 'Package :input already exists.',
        ];
    }
}
