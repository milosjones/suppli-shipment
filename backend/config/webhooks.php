<?php

return [
    'timeout' => (int) env('WEBHOOKS_TIMEOUT', 10),

    'retry_delays' => [60, 300, 1800, 7200, 21600, 43200, 86400],

    'lease_seconds' => 120,

    'overdue_after_minutes' => 5,

    'response_body_limit' => 2048,

    'allow_insecure_urls' => (bool) env('WEBHOOKS_ALLOW_INSECURE_URLS', env('APP_ENV') === 'local'),
];
