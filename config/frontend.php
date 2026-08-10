<?php

return [
    'reverb' => [
        'key' => env('VITE_REVERB_APP_KEY', env('REVERB_APP_KEY')),
        'host' => env('VITE_REVERB_HOST'),
        'port' => env('VITE_REVERB_PORT', env('REVERB_PORT', 443)),
        'scheme' => env('VITE_REVERB_SCHEME', env('REVERB_SCHEME', 'https')),
    ],
];
