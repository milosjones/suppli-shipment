<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ChangeDatesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'etd' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'eta' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'customer_delivery_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'container_ids' => ['sometimes', 'array', 'min:1', 'prohibits:cargo_item_ids'],
            'container_ids.*' => ['integer', 'distinct'],
            'cargo_item_ids' => ['sometimes', 'array', 'min:1'],
            'cargo_item_ids.*' => ['integer', 'distinct'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->hasAny(['etd', 'eta', 'customer_delivery_date'])) {
                    $validator->errors()->add('dates', 'Send at least one of etd, eta or customer_delivery_date.');
                }
            },
        ];
    }
}
