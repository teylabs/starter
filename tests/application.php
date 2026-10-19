<?php

use Illuminate\Contracts\Console\Kernel;

// The app every check and test boots: synthetic environment first, then the real bootstrap.
require __DIR__.'/environment.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
// A PostgreSQL run starts each app on an empty database, as SQLite memory does.
if (config('database.default') === 'pgsql') {
    $app->make(Kernel::class)->call('db:wipe', ['--force' => true]);
}

return $app;
