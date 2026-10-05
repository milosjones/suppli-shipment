<?php

use App\Tracking\Providers\FakeTrackingProvider;

return [
    'providers' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TRACKING_PROVIDERS', 'fake')),
    ))),

    'map' => [
        'fake' => FakeTrackingProvider::class,
    ],

    'fake' => [
        'simulate' => (bool) env('TRACKING_FAKE_SIMULATE', true),
    ],

];
