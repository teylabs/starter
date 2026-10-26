<?php

use Illuminate\Support\Str;

// The cache store comes from CACHE_STORE (a platform with a database attached sets `database`; the cache and lock tables are migrations). The key prefix follows APP_NAME, as Laravel's
// own does, so two apps sharing a store never share keys; set CACHE_PREFIX to pin it.
return [
    'default' => env('CACHE_STORE', 'file'),
    'stores' => [
        'array' => ['driver' => 'array', 'serialize' => false],
        'file' => ['driver' => 'file', 'path' => storage_path('framework/cache/data'), 'lock_path' => storage_path('framework/cache/data')],
        'database' => ['driver' => 'database', 'connection' => env('DB_CACHE_CONNECTION'), 'table' => env('DB_CACHE_TABLE', 'cache'), 'lock_connection' => env('DB_CACHE_LOCK_CONNECTION'), 'lock_table' => env('DB_CACHE_LOCK_TABLE', 'cache_locks')],
    ],
    'prefix' => env('CACHE_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-cache-'),
    'serializable_classes' => false,
];
