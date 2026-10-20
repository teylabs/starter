<?php

use Illuminate\Contracts\Console\Kernel;

// The app every check and test boots: synthetic environment first, then the real bootstrap.
require __DIR__.'/environment.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
// Say once per run which database the tests use, as connected after .env is read.
if (! defined('STARTER_TEST_DATABASE')) {
    $connection = config('database.connections.'.config('database.default'));
    define('STARTER_TEST_DATABASE', config('database.default') === 'pgsql' ? "pgsql {$connection['username']}@{$connection['host']}:{$connection['port']}/{$connection['database']}" : 'sqlite::memory:');
    fwrite(STDERR, 'Test database: '.STARTER_TEST_DATABASE."\n");
}
// A PostgreSQL run starts each app on an empty database, as SQLite memory does.
if (config('database.default') === 'pgsql') {
    $app->make(Kernel::class)->call('db:wipe', ['--force' => true]);
}

return $app;
