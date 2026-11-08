<?php

use Illuminate\Contracts\Console\Kernel;

$app = require dirname(__DIR__).'/bootstrap/app.php';
// The writer (App\TypeScript\ExportedNamespaceWriter) exports every nested namespace, so a bare `php artisan typescript:transform` gives the same file.
exit($app->make(Kernel::class)->call('typescript:transform'));
