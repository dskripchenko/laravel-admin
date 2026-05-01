<?php

declare(strict_types=1);

return [
    'driver' => env('ADMIN_SEARCH_DRIVER', 'eloquent'), // 'eloquent' | 'scout'

    'min_length' => 2,
    'debounce_ms' => 200,
    'per_resource' => 10,
    'cache_ttl' => 60,
    'shortcut' => 'mod+k',
];
