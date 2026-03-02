<?php

return [
    'base_url' => env('FOODDATA_BASE_URL', 'https://api.nal.usda.gov/fdc/v1'),
    'api_key' => env('FOODDATA_API_KEY'),
    'timeout' => (int) env('FOODDATA_TIMEOUT', 10),
    'connect_timeout' => (int) env('FOODDATA_CONNECT_TIMEOUT', 5),
    'cache_ttl_seconds' => (int) env('FOODDATA_CACHE_TTL_SECONDS', 900),
    'translate_top_n' => (int) env('FOODDATA_TRANSLATE_TOP_N', 5),
];
