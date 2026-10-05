<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApiTokenRequest extends FormRequest
{
    public const ABILITIES = ['write', 'references:write'];

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'abilities' => ['sometimes', 'array'],
            'abilities.*' => ['string', 'distinct', Rule::in(self::ABILITIES)],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ];
    }
}
