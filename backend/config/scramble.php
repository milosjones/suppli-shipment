<?php

use Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy;

return [
    'api_path' => 'api/v1',

    'api_domain' => null,

    'export_path' => 'api.json',

    'cache' => [
        'key' => 'scramble.openapi',
        'store' => 'file',
    ],

    'info' => [
        'version' => env('API_VERSION', '1.0.0'),

        'description' => 'Shipment tracking microservice: shipments, containers, cargo items (tags), status flow with history, date changes with audit trail, lookup, reports and Excel import. Authenticate with a bearer token from `POST /auth/login` (users) or an API-client token (integrations such as Suppli). Super admins select the company with the `X-Company-Id` header.',
    ],

    'ui' => [
        'title' => 'Suppli Shipments API',
    ],

    'dev_tools' => [
        'enabled' => env('SCRAMBLE_DEV_TOOLS', env('APP_DEBUG', false)),
    ],

    'renderer' => 'elements',

    'renderers' => [
        'elements' => [
            'view' => 'scramble::docs',
            'theme' => 'light',
            'hideTryIt' => false,
            'hideSchemas' => false,
            'logo' => '',
            'tryItCredentialsPolicy' => 'include',
            'layout' => 'responsive',
            'router' => 'hash',
        ],
        'scalar' => [
            'view' => 'scramble::scalar',
            'cdn' => 'https://cdn.jsdelivr.net/npm/@scalar/api-reference',
            'theme' => 'laravel',
            'proxyUrl' => 'https://proxy.scalar.com',
            'darkMode' => false,
            'showDeveloperTools' => 'never',
            'agent' => ['disabled' => true],
            'credentials' => 'include',
        ],
    ],

    'servers' => null,

    'enum_cases_description_strategy' => 'description',

    'enum_cases_names_strategy' => false,

    'flatten_deep_query_parameters' => true,

    'middleware' => [],

    'extensions' => [],

    'security_strategy' => MiddlewareAuthSecurityStrategy::class,
];
