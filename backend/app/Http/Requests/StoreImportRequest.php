<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreImportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'extensions:xlsx,xls', 'max:20480'],
            'dry_run' => ['sometimes', 'boolean'],
        ];
    }
}
