<?php

// Synthetic checks opt in explicitly; public/index.php never loads this file. They run on SQLite memory unless
// TEST_DB_CONNECTION=pgsql selects PostgreSQL, with DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME and DB_PASSWORD from the
// process environment, as CI does. tests/application.php wipes that database as each app boots, so DB_DATABASE must be
// set explicitly for the run (a database named in .env is never picked up) and should be a disposable one.
$selected = getenv('TEST_DB_CONNECTION') ?: 'sqlite';
if (! in_array($selected, ['sqlite', 'pgsql'], true)) {
    fwrite(STDERR, "TEST_DB_CONNECTION must be sqlite or pgsql.\n");
    exit(2);
}
if ($selected === 'pgsql' && (getenv('DB_DATABASE') ?: '') === '') {
    fwrite(STDERR, "TEST_DB_CONNECTION=pgsql needs DB_DATABASE set for the run, naming a disposable database the tests wipe.\n");
    exit(2);
}
$database = $selected === 'pgsql' ? ['DB_CONNECTION' => 'pgsql'] : ['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:'];
foreach (['APP_ENV' => 'testing', 'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=', ...$database, 'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'NIGHTWATCH_ENABLED' => 'false'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}
