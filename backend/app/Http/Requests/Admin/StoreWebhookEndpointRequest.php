<?php

namespace App\Http\Requests\Admin;

use App\Rules\WebhookUrl;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWebhookEndpointRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'url' => [
                'required', 'string', 'max:2048', 'url:http,https', new WebhookUrl,
                Rule::unique('webhook_endpoints', 'url')->where('company_id', app(TenantContext::class)->id()),
            ],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', 'distinct', Rule::in(['shipment.status_changed', 'shipment.dates_changed'])],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return ['url.unique' => 'This company already has a webhook for this URL.'];
    }
}
