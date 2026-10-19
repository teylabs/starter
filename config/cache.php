<?php

// Cached values are arrays and scalars only: nothing the app caches is unserialized into an object (CacheGuardTest).
return ['default' => env('CACHE_STORE', 'file'), 'stores' => ['array' => ['driver' => 'array', 'serialize' => false], 'file' => ['driver' => 'file', 'path' => storage_path('framework/cache/data'), 'lock_path' => storage_path('framework/cache/data')]], 'prefix' => env('CACHE_PREFIX', 'workspace-starter'), 'serializable_classes' => false];
