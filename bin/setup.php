<?php

// `composer setup`: a fresh clone to a working local app, on Laravel Herd when it is there. Safe to re-run: each step is skipped when it is already done,
// and nothing that exists is overwritten. Steps: dependencies, .env and its key, the database (Herd's PostgreSQL when it answers, with the owner and the
// row-level-security runtime role; SQLite otherwise, or with --sqlite), migrations, Passport's keys, assets, the first owner and workspace, the Herd site
// (linked and secured), local mail (Herd's mail catcher when it runs, else the log). It ends with what is left for a person.
$root = dirname(__DIR__);
$php = escapeshellarg(PHP_BINARY);
$interaction = in_array('--no-interaction', $argv, true) || ! stream_isatty(STDIN) ? ' --no-interaction' : '';
$left = [];
$run = function (string $command, array $env = []) use ($root): void {
    echo '> '.$command."\n";
    $prefix = implode(' ', array_map(fn (string $key, string $value): string => $key.'='.escapeshellarg($value), array_keys($env), $env));
    passthru('cd '.escapeshellarg($root).' && '.($prefix !== '' ? $prefix.' ' : '').$command, $code);
    if ($code !== 0) {
        fwrite(STDERR, "setup stopped: {$command} exited {$code}. Fix it and run composer setup again; finished steps are skipped.\n");
        exit($code);
    }
};
$env = fn (): string => (string) file_get_contents($root.'/.env');
$get = function (string $key) use ($env): ?string {
    return preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $env(), $m) === 1 ? trim($m[1], " \t\"'") : null;
};
// Sets a key in .env only when it is missing or empty, so a value someone chose is never replaced.
$set = function (string $key, string $value) use ($root, $env, $get): void {
    if (($get($key) ?? '') !== '') {
        return;
    }
    $line = $key.'='.(preg_match('/[\s#"]/', $value) === 1 ? '"'.addcslashes($value, '"\\').'"' : $value);
    $contents = $env();
    $contents = preg_match('/^#?\s*'.preg_quote($key, '/').'=.*$/m', $contents) === 1
        ? preg_replace('/^#?\s*'.preg_quote($key, '/').'=.*$/m', $line, $contents, 1)
        : rtrim($contents)."\n".$line."\n";
    file_put_contents($root.'/.env', $contents);
    echo "Set {$key} in .env\n";
};
$answers = function (string $host, int $port): bool {
    $socket = @fsockopen($host, $port, $errno, $error, 1);
    if ($socket === false) {
        return false;
    }
    fclose($socket);

    return true;
};
$which = fn (string $binary): bool => trim((string) shell_exec('command -v '.escapeshellarg($binary).' 2>/dev/null')) !== '';

// 1. Dependencies, .env and the app key. Composer names its own binary for the scripts it runs.
$run(escapeshellarg(getenv('COMPOSER_BINARY') ?: 'composer').' install --no-interaction');
if (! is_file($root.'/.env')) {
    copy($root.'/.env.example', $root.'/.env');
    echo "Copied .env.example to .env\n";
}
if (($get('APP_KEY') ?? '') === '') {
    $run($php.' artisan key:generate --ansi');
}

// 2. The site's name: the checkout's folder, as Herd names a parked site (my-app -> my-app.test), and the database's.
$site = strtolower(trim((string) preg_replace('/[^A-Za-z0-9-]+/', '-', basename($root)), '-')) ?: 'app';
$database = str_replace('-', '_', $site);

// 3. The database. The local PostgreSQL (Herd's: 127.0.0.1:5432) when it answers and lets us in, as production runs; SQLite otherwise.
// The login is .env's, else the environment's (DB_USERNAME, DB_PASSWORD), else Herd's: root with no password.
$connection = $get('DB_CONNECTION');
$credentials = fn (): array => [(string) ($get('DB_HOST') ?: '127.0.0.1'), (string) ($get('DB_PORT') ?: '5432'), (string) ($get('DB_USERNAME') ?: (getenv('DB_USERNAME') ?: 'root')), (string) ($get('DB_PASSWORD') ?: (getenv('DB_PASSWORD') ?: ''))];
$connect = function (string $host, string $port, string $user, string $password): PDO {
    return new PDO("pgsql:host={$host};port={$port};dbname=postgres", $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]);
};
if (! in_array('--sqlite', $argv, true) && $connection !== 'pgsql' && $answers('127.0.0.1', 5432) && extension_loaded('pdo_pgsql')) {
    try {
        $connect(...$credentials());
        file_put_contents($root.'/.env', (string) preg_replace('/^DB_CONNECTION=sqlite\s*$/m', 'DB_CONNECTION=pgsql', $env(), 1));
        echo "Using the local PostgreSQL (DB_CONNECTION=pgsql)\n";
        $connection = 'pgsql';
    } catch (PDOException $e) {
        $left[] = 'PostgreSQL answers on 127.0.0.1:5432 but refused '.$credentials()[2].', so this app uses SQLite. Set DB_CONNECTION=pgsql, DB_USERNAME and DB_PASSWORD in .env and run composer setup again.';
    }
}
if ($connection === 'pgsql') {
    [$host, $port, $user, $password] = $credentials();
    $set('DB_HOST', $host);
    $set('DB_PORT', $port);
    $set('DB_DATABASE', $database);
    $set('DB_USERNAME', $user);
    $set('DB_PASSWORD', $password);
    $name = (string) $get('DB_DATABASE');
    try {
        $admin = $connect($host, $port, $user, $password);
        $exists = $admin->prepare('select 1 from pg_database where datname = ?');
        $exists->execute([$name]);
        if ($exists->fetchColumn() === false) {
            $admin->exec('create database "'.str_replace('"', '""', $name).'"');
            echo "Created the PostgreSQL database {$name}\n";
        }
    } catch (PDOException $e) {
        fwrite(STDERR, "setup stopped: PostgreSQL at {$host}:{$port} refused {$user} (".$e->getMessage()."). Fix DB_USERNAME and DB_PASSWORD in .env, or run composer setup -- --sqlite.\n");
        exit(1);
    }
    // Migrations run as the owner, which owns every table; requests run as the runtime role, which RLS binds (docs/deploy.md, Row-level security).
    $run($php.' artisan migrate --force --database=pgsql_admin');
    if (($get('DB_RUNTIME_USERNAME') ?? '') === '') {
        $role = $name.'_runtime';
        $password = bin2hex(random_bytes(16));
        $run($php.' artisan kit:rls:role '.escapeshellarg($role).' --database=pgsql_admin', ['DB_RUNTIME_PASSWORD' => $password]);
        $set('DB_RUNTIME_USERNAME', $role);
        $set('DB_RUNTIME_PASSWORD', $password);
    }
} else {
    if (($get('DB_DATABASE') ?? '') === '' && ! is_file($root.'/database/database.sqlite')) {
        touch($root.'/database/database.sqlite');
        echo "Created database/database.sqlite\n";
    }
    $run($php.' artisan migrate --force');
    $left[] = 'The database is SQLite. Production runs PostgreSQL with row-level security: start PostgreSQL in Herd (Services) and run composer setup again to move to it.';
}

// 4. Passport's signing keys for MCP connections: storage/oauth-*.key, written once.
if (! is_file($root.'/storage/oauth-private.key')) {
    $run($php.' artisan passport:keys --no-interaction');
}

// 5. Mail stays on this machine: Herd's mail catcher (SMTP on 2525) when it runs, else the log (storage/logs/laravel.log).
if (($get('MAIL_MAILER') ?? '') === '') {
    if ($answers('127.0.0.1', 2525)) {
        $set('MAIL_MAILER', 'smtp');
        $set('MAIL_HOST', '127.0.0.1');
        $set('MAIL_PORT', '2525');
    } else {
        $set('MAIL_MAILER', 'log');
    }
}
if ($get('MAIL_MAILER') === 'log') {
    $left[] = 'Mail goes to storage/logs/laravel.log. To see it as mail, turn on Herd\'s mail service (Herd Pro) and set MAIL_MAILER=smtp, MAIL_HOST=127.0.0.1 and MAIL_PORT=2525.';
}

// 6. The Herd site: linked under the folder's name and served over HTTPS, at https://<site>.test.
$url = 'http://localhost:8000';
if ($which('herd')) {
    $links = (string) shell_exec('cd '.escapeshellarg($root).' && herd links 2>/dev/null');
    if (! str_contains($links, $site)) {
        $run('herd link '.escapeshellarg($site));
    }
    $secured = (string) shell_exec('herd secured 2>/dev/null');
    if (! str_contains($secured, $site.'.test')) {
        $run('herd secure '.escapeshellarg($site));
    }
    $url = 'https://'.$site.'.test';
    if (in_array($get('APP_URL'), [null, '', 'http://localhost'], true)) {
        $file = (string) preg_replace('/^APP_URL=.*$/m', 'APP_URL='.$url, $env(), 1);
        file_put_contents($root.'/.env', $file);
        echo "Set APP_URL={$url}\n";
    }
} else {
    $left[] = 'Herd is not installed, so there is no .test site: serve the app with php artisan serve, or install Herd (herd.laravel.com) and run composer setup again.';
}

// 7. Assets, then the first owner and their workspace (an operator, so Telescope and the Setup page's app rows are theirs).
$run('npm ci && npm run build');
$run($php.' artisan starter:first-owner'.$interaction);

echo "\nReady: {$url}/login (one click signs the owner in, locally only). Settings → Setup shows what is set up.\n";
echo "Scheduled tasks and Vite's hot reload: composer dev. Jobs run inline locally (QUEUE_CONNECTION=sync), so no worker is needed.\n";
if ($left !== []) {
    echo "\nLeft for you:\n";
    foreach ($left as $item) {
        echo '- '.$item."\n";
    }
}
