<?php

// Synthetic checks opt in explicitly; public/index.php never loads this file. They run on PostgreSQL, as production does
// (Jasper's ruling, 2026-11-07). The connection comes from DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME and DB_PASSWORD in the
// process environment (CI sets them), and any of those left unset come from the committed .env.testing, never from .env.
// tests/application.php wipes that database as each app boots, so it must be a disposable one: .env.testing names
// `starter_testing`. TEST_DB_CONNECTION=sqlite is an explicit opt-in for the few checks that compare a SQLite dump.
$selected = getenv('TEST_DB_CONNECTION') ?: 'pgsql';
if (! in_array($selected, ['pgsql', 'sqlite'], true)) {
    fwrite(STDERR, "TEST_DB_CONNECTION must be pgsql (the default) or sqlite.\n");
    exit(2);
}
if ($selected === 'pgsql') {
    $defaults = [];
    foreach (is_file(dirname(__DIR__).'/.env.testing') ? file(dirname(__DIR__).'/.env.testing', FILE_IGNORE_NEW_LINES) : [] as $line) {
        if (preg_match('/^(DB_(?:HOST|PORT|DATABASE|USERNAME|PASSWORD))=(.*)$/', trim($line), $match) === 1) {
            $defaults[$match[1]] = trim($match[2], '"\'');
        }
    }
    foreach ($defaults as $key => $value) {
        if (getenv($key) === false) {
            putenv($key.'='.$value);
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
    }
    if ((getenv('DB_DATABASE') ?: '') === '') {
        fwrite(STDERR, "The tests need DB_DATABASE (from the environment or .env.testing), naming a disposable PostgreSQL database they wipe.\n");
        exit(2);
    }
}
// Parallel workers must never share a destructive fixture database: each gets its own, `{DB_DATABASE}_w{token}`.
$token = getenv('TEST_TOKEN') ?: '';
if ($selected === 'pgsql') {
    $database = (string) getenv('DB_DATABASE');
    if ($token !== '') {
        $suffix = '_w'.preg_replace('/[^a-zA-Z0-9_]/', '_', $token);
        // A worker boots this file more than once: add the suffix only once.
        $database = str_ends_with($database, $suffix) ? $database : $database.$suffix;
    }
    // The name is quoted into `create database`: refuse anything but a plain identifier rather than escape it.
    if (preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,62}$/', $database) !== 1) {
        fwrite(STDERR, "DB_DATABASE must be a plain PostgreSQL identifier (letters, digits and underscores, at most 63 characters).\n");
        exit(2);
    }
    // The run's database is created on first use, so a fresh name just works.
    if (! defined('STARTER_TEST_DATABASE_READY_'.md5($database))) {
        define('STARTER_TEST_DATABASE_READY_'.md5($database), true);
        $dsn = 'pgsql:host='.(getenv('DB_HOST') ?: '127.0.0.1').';port='.(getenv('DB_PORT') ?: '5432').';dbname=postgres';
        $pdo = new PDO($dsn, getenv('DB_USERNAME') ?: 'root', getenv('DB_PASSWORD') ?: '');
        $exists = $pdo->prepare('select 1 from pg_database where datname = ?');
        $exists->execute([$database]);
        if ($exists->fetchColumn() === false) {
            $pdo->exec('create database "'.$database.'"');
        }
        $pdo = null;
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
