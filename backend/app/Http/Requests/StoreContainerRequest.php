<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\CargoItemRules;
use App\Rules\ValidContainerNumber;
use App\Support\ContainerNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContainerRequest extends FormRequest
{
    use CargoItemRules;

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('container_no'))) {
            $this->merge(['container_no' => ContainerNumber::normalize($this->input('container_no'))]);
        }
    }

    public function rules(): array
    {
        $shipment = $this->route('shipment');

        return [
            'container_no' => [
                'required', 'string', 'max:20', new ValidContainerNumber($shipment?->shipping_method),
                Rule::unique('containers', 'container_no')->where('shipment_id', $shipment?->id),
            ],
            'seal_no' => ['nullable', 'string', 'max:50'],
            'container_type' => ['nullable', 'string', 'max:10'],
            'items' => ['required', 'array', 'min:1', 'max:500'],
            ...$this->cargoItemRules('items.*'),
        ];
    }
}
