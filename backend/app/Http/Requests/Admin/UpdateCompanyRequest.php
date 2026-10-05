<?php

namespace App\Http\Requests\Admin;

use App\Rules\UniqueIgnoringCase;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', new UniqueIgnoringCase('companies', 'name', $this->route('company')?->id)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
