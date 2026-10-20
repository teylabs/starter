<?php

// `composer setup`: a fresh clone to a working local sign-in. Each step is skipped when already done.
$root = dirname(__DIR__);
$run = function (string $command) use ($root): void {
    echo '> '.$command."\n";
    passthru('cd '.escapeshellarg($root).' && '.$command, $code);
    if ($code !== 0) {
        fwrite(STDERR, "setup stopped: {$command} exited {$code}\n");
        exit($code);
    }
};
$php = escapeshellarg(PHP_BINARY);
$interaction = in_array('--no-interaction', $argv, true) || ! stream_isatty(STDIN) ? ' --no-interaction' : '';

// Composer names its own binary for the scripts it runs.
$run(escapeshellarg(getenv('COMPOSER_BINARY') ?: 'composer').' install --no-interaction');
if (! is_file($root.'/.env')) {
    copy($root.'/.env.example', $root.'/.env');
    echo "Copied .env.example to .env\n";
}
if (preg_match('/^APP_KEY=\S+/m', file_get_contents($root.'/.env')) !== 1) {
    $run($php.' artisan key:generate --ansi');
}
$env = file_get_contents($root.'/.env');
if (preg_match('/^DB_CONNECTION=sqlite\s*$/m', $env) === 1 && preg_match('/^DB_DATABASE=\S/m', $env) !== 1 && ! is_file($root.'/database/database.sqlite')) {
    touch($root.'/database/database.sqlite');
    echo "Created database/database.sqlite\n";
}
$run($php.' artisan migrate --force');
$run('npm ci && npm run build');
$run($php.' artisan starter:first-owner'.$interaction);
echo "Ready: php artisan serve, then sign in at /login\n";
