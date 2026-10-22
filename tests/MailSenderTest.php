<?php

use Dotenv\Dotenv;
use Tests\Concerns\Template;

require_once __DIR__.'/Concerns/Template.php';

test('the mail sender follows the app name', function () {
    if (! Template::isTemplate()) {
        $this->markTestSkipped('Checks the template\'s own .env.example; an app sets its own sender.');
    }

    // The example's own values, with the app renamed, in a fresh process: the environment the app boots in. (This process has
    // already loaded the developer's own .env, which would win over anything set here.)
    $contents = (string) file_get_contents(dirname(__DIR__).'/.env.example');
    expect($contents)->toMatch('/^MAIL_FROM_NAME="\$\{APP_NAME\}"$/m');
    $renamed = Dotenv::parse(preg_replace('/^APP_NAME=.*$/m', 'APP_NAME="Renamed App"', $contents)."\n");
    expect($renamed)->toHaveKey('MAIL_FROM_NAME');
    expect($renamed)->toHaveKey('KIT_BRAND_MAIL_FROM_ADDRESS');
    $env = ['APP_NAME' => $renamed['APP_NAME'], 'MAIL_FROM_NAME' => $renamed['MAIL_FROM_NAME'], 'KIT_BRAND_MAIL_FROM_ADDRESS' => $renamed['KIT_BRAND_MAIL_FROM_ADDRESS']];
    $script = '$app = require "'.__DIR__.'/application.php"; echo json_encode([config("app.name"), config("mail.from.name"), config("mail.from.address")]);';
    $process = proc_open([PHP_BINARY, '-r', $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__), [...getenv(), ...$env]);
    $out = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
    expect(json_decode(substr($out, (int) strrpos($out, '[')), true))->toBe(['Renamed App', 'Renamed App', 'updates@example.com'], $out);
});
