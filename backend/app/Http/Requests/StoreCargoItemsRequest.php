<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\CargoItemRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreCargoItemsRequest extends FormRequest
{
    use CargoItemRules;

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:500'],
            ...$this->cargoItemRules('items.*'),
        ];
    }
}
