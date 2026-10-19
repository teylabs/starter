<?php

use Illuminate\Contracts\Console\Kernel;

$app = require dirname(__DIR__).'/bootstrap/app.php';
exit($app->make(Kernel::class)->call('typescript:transform'));
