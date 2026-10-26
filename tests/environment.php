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
// Parallel workers must never share a destructive PostgreSQL fixture database.
$token = getenv('TEST_TOKEN') ?: '';
// Each PostgreSQL worker gets its own disposable database, `{DB_DATABASE}_w{token}`, created on first use.
if ($token !== '' && $selected === 'pgsql') {
    $suffix = '_w'.preg_replace('/[^a-zA-Z0-9_]/', '_', $token);
    // A worker boots this file more than once: add the suffix only once.
    $database = str_ends_with((string) getenv('DB_DATABASE'), $suffix) ? (string) getenv('DB_DATABASE') : getenv('DB_DATABASE').$suffix;
    $dsn = 'pgsql:host='.(getenv('DB_HOST') ?: '127.0.0.1').';port='.(getenv('DB_PORT') ?: '5432').';dbname=postgres';
    $pdo = new PDO($dsn, getenv('DB_USERNAME') ?: 'root', getenv('DB_PASSWORD') ?: '');
    $exists = $pdo->prepare('select 1 from pg_database where datname = ?');
    $exists->execute([$database]);
    if ($exists->fetchColumn() === false) {
        $pdo->exec('create database "'.$database.'"');
    }
    putenv('DB_DATABASE='.$database);
    $_ENV['DB_DATABASE'] = $_SERVER['DB_DATABASE'] = $database;
}
if ($token !== '') {
    $previousRoot = getenv('LARAVEL_STORAGE_PATH') ?: '';
    // Include the PID so separate parallel invocations cannot reuse the same worker files.
    $root = dirname(__DIR__).'/storage/framework/testing/worker-'.preg_replace('/[^a-zA-Z0-9_-]/', '_', $token).'-'.getmypid();
    foreach (['app/private', 'app/public', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs', 'tmp', 'bootstrap'] as $directory) {
        if (! is_dir($root.'/'.$directory)) {
            mkdir($root.'/'.$directory, 0700, true);
        }
    }
    foreach (['LARAVEL_STORAGE_PATH' => $root, 'TMPDIR' => $root.'/tmp', 'APP_CONFIG_CACHE' => $root.'/bootstrap/config.php', 'APP_ROUTES_CACHE' => $root.'/bootstrap/routes.php', 'APP_EVENTS_CACHE' => $root.'/bootstrap/events.php'] as $key => $value) {
        // A cache test may already have selected its own random temporary file.
        if (str_starts_with($key, 'APP_') && getenv($key) && getenv($key) !== $previousRoot.'/bootstrap/'.basename($value)) {
            $value = getenv($key);
        }
        putenv($key.'='.$value);
        $_ENV[$key] = $_SERVER[$key] = $value;
    }
}
$database = $selected === 'pgsql' ? ['DB_CONNECTION' => 'pgsql'] : ['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:'];
foreach (['APP_ENV' => 'testing', 'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=', ...$database, 'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'NIGHTWATCH_ENABLED' => 'false'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}
