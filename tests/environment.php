<?php

// Synthetic checks opt in explicitly; public/index.php never loads this file. They run on SQLite memory, and CI also
// runs them on a disposable PostgreSQL service (TEST_DB_CONNECTION=pgsql, with DB_HOST, DB_DATABASE, DB_USERNAME and
// DB_PASSWORD from the job), which tests/application.php wipes as each app boots.
$database = getenv('TEST_DB_CONNECTION') === 'pgsql' ? ['DB_CONNECTION' => 'pgsql'] : ['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:'];
foreach (['APP_ENV' => 'testing', 'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=', ...$database, 'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'NIGHTWATCH_ENABLED' => 'false'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}
