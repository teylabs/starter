<?php

use Illuminate\Contracts\Console\Kernel;

$app = require dirname(__DIR__).'/bootstrap/app.php';
$code = $app->make(Kernel::class)->call('typescript:transform');
// Vue's SFC compiler resolves a global type only through exported namespaces, so nested ones are exported.
$path = resource_path('js/types.generated.d.ts');
file_put_contents($path, preg_replace('/^namespace /m', 'export namespace ', file_get_contents($path)));
exit($code);
