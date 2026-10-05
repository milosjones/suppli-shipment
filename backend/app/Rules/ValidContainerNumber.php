<?php

namespace App\Rules;

use App\Enums\ShippingMethod;
use App\Support\ContainerNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidContainerNumber implements ValidationRule
{
    public function __construct(private readonly ?ShippingMethod $method) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->method === null || ! is_string($value)) {
            return;
        }

        if (! ContainerNumber::isValidFor($this->method, $value)) {
            $fail('The :attribute must be '.ContainerNumber::formatHint($this->method).'.');
        }
    }
}
