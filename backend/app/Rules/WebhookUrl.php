<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class WebhookUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        $host = strtolower(trim((string) parse_url($value, PHP_URL_HOST), '[]'));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            $fail('The :attribute must be a full http(s) URL.');

            return;
        }

        if (config('webhooks.allow_insecure_urls')) {
            return;
        }

        if ($scheme !== 'https') {
            $fail('The :attribute must use HTTPS.');
        } elseif ($this->isInternal($host)) {
            $fail('The :attribute must point to a public host, not a local or private address.');
        }
    }

    private function isInternal(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
        }

        return ! str_contains($host, '.') || preg_match('/\.(localhost|local|internal|lan|home\.arpa)$/', $host) === 1;
    }
}
