<?php

namespace App\Http\Requests\Admin;

use App\Rules\UniqueIgnoringCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreCompanyRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('admin.email'))) {
            $this->merge(['admin' => [...$this->input('admin'), 'email' => mb_strtolower(trim($this->input('admin.email')))]]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', new UniqueIgnoringCase('companies', 'name')],
            'admin' => ['sometimes', 'array'],
            'admin.name' => ['required_with:admin', 'string', 'max:255'],
            'admin.email' => ['required_with:admin', 'string', 'email', 'max:255', 'unique:users,email'],
            'admin.password' => ['required_with:admin', 'string', Password::min(8)],
        ];
    }
}
