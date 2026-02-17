<?php

return [
    'api_key' => env('API_NINJA_KEY'),
    'timeout' => (int) env('API_NINJA_TIMEOUT', 15),
    'connect_timeout' => (int) env('API_NINJA_CONNECT_TIMEOUT', 7),
    'cache_ttl_seconds' => (int) env('API_NINJA_CACHE_TTL_SECONDS', 43200),
    'endpoints' => [
        'exercises' => env('API_NINJA_EXERCICES'),
        'all_exercises' => env('API_NINJA_ALL_EXERCICES'),
    ],
];
