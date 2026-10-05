<?php

return [
    'timezone' => env('BUSINESS_TIMEZONE', 'Europe/Oslo'),

    'default_origin_port' => 'NHAVA SHEVA',

    'login_token_ttl_hours' => 12,

    'imports' => [
        'disk' => 'local',
        'directory' => 'imports',
    ],

];
