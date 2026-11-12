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
    // The brand's updates@ address comes from config/brand.php (the kit's default in the template), not env.
    expect(array_filter(array_keys($renamed), fn (string $key): bool => str_starts_with($key, 'KIT_BRAND_')))->toBe([]);
    // Laravel's own MAIL_FROM_ADDRESS gets an address of its own, and a leftover brand env sender reaches the child but is ignored, so nothing here is the .env.example value: while the
    // template's brand is all placeholders the kit leaves the sender to Laravel's mail.from, and the brand's address is the kit's default, not env.
    // tests/BrandTest.php covers config/brand.php winning over env.
    $env = ['APP_NAME' => $renamed['APP_NAME'], 'MAIL_FROM_NAME' => $renamed['MAIL_FROM_NAME'], 'MAIL_FROM_ADDRESS' => 'laravel-env@example.test'];
    $inherited = array_filter(getenv(), fn (string $name): bool => ! str_starts_with($name, 'KIT_BRAND_'), ARRAY_FILTER_USE_KEY);
    // A leftover brand env sender must change nothing: tey/kit 0.17 ignores KIT_BRAND_* (the name is built so kit:status's leftover-env scan does not flag this file).
    $env['KIT_BRAND_'.'MAIL_FROM_ADDRESS'] = 'kit-env@example.test';
    $script = '$app = require "'.__DIR__.'/application.php"; echo json_encode([config("app.name"), config("mail.from.name"), config("mail.from.address"), config("kit.brand_sources.mail_from_address")]);';
    $process = proc_open([PHP_BINARY, '-r', $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__), [...$inherited, ...$env]);
    $out = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
    expect(json_decode(substr($out, (int) strrpos($out, '[')), true))->toBe(['Renamed App', 'Renamed App', 'laravel-env@example.test', 'default'], $out);
});
