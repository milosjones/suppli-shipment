<?php

namespace App\Http\Requests;

use App\Enums\CargoStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeStatusRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(CargoStatus::class)],
            'container_ids' => ['sometimes', 'array', 'min:1', 'prohibits:cargo_item_ids'],
            'container_ids.*' => ['integer', 'distinct'],
            'cargo_item_ids' => ['sometimes', 'array', 'min:1'],
            'cargo_item_ids.*' => ['integer', 'distinct'],
            'correction' => ['sometimes', 'boolean'],
            'reason' => ['nullable', 'string', 'max:1000', Rule::requiredIf(fn () => $this->boolean('correction'))],
            'occurred_at' => ['nullable', 'date'],
        ];
    }
}
