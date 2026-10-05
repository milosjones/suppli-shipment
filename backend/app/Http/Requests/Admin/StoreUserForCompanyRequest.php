<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class StoreUserForCompanyRequest extends StoreUserRequest
{
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')],
            ...parent::rules(),
        ];
    }
}
