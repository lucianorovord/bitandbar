<?php

return [
    'base_url' => env('API_TRADUCTION'),
    'source_lang' => env('TRANSLATION_SOURCE_LANG', 'en'),
    'target_lang' => env('TRANSLATION_TARGET_LANG', 'es'),
    'timeout' => (int) env('TRANSLATION_TIMEOUT', 12),
    'connect_timeout' => (int) env('TRANSLATION_CONNECT_TIMEOUT', 6),
    'cache_ttl_seconds' => (int) env('TRANSLATION_CACHE_TTL_SECONDS', 86400),
];
