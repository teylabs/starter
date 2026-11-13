<?php

// bootstrap/app.php must hand back an unbootstrapped app. public/index.php's HTTP kernel bootstraps it for a request; a
// kernel bootstrapped here (the console one, as the starter once did) made every web request also load the console's deferred
// providers and command registration (a few ms under PHP-FPM; the rest of the bootstrap only moved into the HTTP kernel).
// Checked in a child process, so this suite's own booted app is not involved.
it('returns the application from bootstrap/app.php without bootstrapping a kernel', function () {
    $script = 'require '.var_export(dirname(__DIR__).'/tests/environment.php', true).';'
        .'$app = require '.var_export(dirname(__DIR__).'/bootstrap/app.php', true).';'
        .'echo json_encode([$app->hasBeenBootstrapped(), $app->isBooted()]);';
    exec(escapeshellarg(PHP_BINARY).' -r '.escapeshellarg($script).' 2>&1', $output, $code);

    expect($code)->toBe(0, implode("\n", $output))
        ->and(end($output))->toBe('[false,false]');
});

it('never calls a kernel bootstrap in bootstrap/app.php', function () {
    expect((string) file_get_contents(dirname(__DIR__).'/bootstrap/app.php'))->not->toMatch('/Kernel::class\)\s*->\s*bootstrap\s*\(/');
});
