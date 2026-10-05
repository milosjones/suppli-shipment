<?php

namespace App\Http\Requests;

use App\Rules\ValidContainerNumber;
use App\Support\ContainerNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContainerRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('container_no'))) {
            $this->merge(['container_no' => ContainerNumber::normalize($this->input('container_no'))]);
        }
    }

    public function rules(): array
    {
        $container = $this->route('container');

        return [
            'container_no' => [
                'sometimes', 'required', 'string', 'max:20',
                new ValidContainerNumber($container?->shipment?->shipping_method),
                Rule::unique('containers', 'container_no')->where('shipment_id', $container?->shipment_id)->ignore($container),
            ],
            'seal_no' => ['sometimes', 'nullable', 'string', 'max:50'],
            'container_type' => ['sometimes', 'nullable', 'string', 'max:10'],
        ];
    }
}
