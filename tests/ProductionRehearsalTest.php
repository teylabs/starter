<?php

use Dotenv\Dotenv;
use Illuminate\Config\Repository;
use Symfony\Component\Process\Process;
use Tey\Kit\PassportKeys;

beforeEach(function () {
    $this->made = [];
});
afterEach(function () {
    foreach ($this->made as $file) {
        @unlink($file);
    }
});
/** A throwaway production environment on a temp SQLite database, with overrides, as a file path. @return array{0: string, 1: string} the env file and the database file */
function productionRehearsalEnvironment(array $overrides = [], string $form = '--pem'): array
{
    $database = tempnam(sys_get_temp_dir(), 'rehearsal-db-');
    $process = new Process([PHP_BINARY, dirname(__DIR__).'/bin/rehearsal-env.php', $form, 'DB_CONNECTION=sqlite', 'DB_DATABASE='.$database, ...array_map(fn ($k, $v) => $k.'='.$v, array_keys($overrides), $overrides)], dirname(__DIR__));
    $process->mustRun();
    $file = tempnam(sys_get_temp_dir(), 'rehearsal-env-');
    file_put_contents($file, $process->getOutput());
    test()->made = [...test()->made, $file];
    test()->made = [...test()->made, $database];

    return [$file, $database];
}
/** Runs an artisan command in a child that reads the given environment file as its whole environment. */
function productionRehearsalRunArtisan(string $envFile, array $arguments): array
{
    $name = 'rehearsal-test-'.bin2hex(random_bytes(3));
    $copy = dirname(__DIR__).'/.env.'.$name;
    copy($envFile, $copy);
    try {
        $process = new Process([PHP_BINARY, dirname(__DIR__).'/artisan', ...$arguments, '--env='.$name, '--no-interaction'], dirname(__DIR__), array_map('strval', Dotenv::parse((string) file_get_contents($envFile))), timeout: 120);
        $process->run();
    } finally {
        @unlink($copy);
    }

    return [$process->getExitCode(), $process->getOutput().$process->getErrorOutput()];
}
function productionRehearsalMigrate(string $envFile): void
{
    [$exit] = productionRehearsalRunArtisan($envFile, ['migrate', '--force']);
    expect($exit)->toBe(0, 'the rehearsal database migrates');
}
/** The preflight against an environment file, through the kit's own rehearsal option. */
function productionRehearsalPreflight(string $envFile, array $options = []): array
{
    $process = new Process([PHP_BINARY, dirname(__DIR__).'/artisan', 'kit:preflight', '--env-file='.$envFile, '--skip=install.vite', ...$options], dirname(__DIR__), timeout: 120);
    $process->run();

    return [$process->getExitCode(), $process->getOutput().$process->getErrorOutput()];
}
test('a throwaway production environment passes the preflight with the production checks on', function () {
    [$env] = productionRehearsalEnvironment();
    productionRehearsalMigrate($env);
    [$exit, $output] = productionRehearsalPreflight($env);
    expect($exit)->toBe(0, $output);
    $this->assertStringContainsString('install.tables', $output);
    $this->assertStringNotContainsString('skipped-in-ci', preg_replace('/"skipped-in-ci":0/', '', $output));
    $this->assertStringContainsString('Passport keys: PASSPORT_PRIVATE_KEY as pem, PASSPORT_PUBLIC_KEY as pem', $output);

    // Nothing from the file is printed, and the copy the child read is gone.
    foreach (Dotenv::parse((string) file_get_contents($env)) as $name => $value) {
        if (preg_match('/KEY|PASSWORD/', $name) === 1 && strlen($value) >= 12) {
            foreach (preg_split('/\R/', $value) as $line) {
                if (! str_starts_with($line, '-----') && strlen($line) >= 8) {
                    $this->assertStringNotContainsString($line, $output, $name.' is never printed');
                }
            }
        }
    }
    expect(glob(dirname(__DIR__).'/.env.kit-rehearsal-*') ?: [])->toBe([]);
});
test('the passport keys pass in every form a dashboard can hold and the form is reported', function () {
    foreach (['--escaped' => 'escaped-newlines', '--base64' => 'base64'] as $flag => $form) {
        [$env] = productionRehearsalEnvironment([], $flag);
        productionRehearsalMigrate($env);
        [$exit, $output] = productionRehearsalPreflight($env);
        expect($exit)->toBe(0, $form.': '.$output);
        $this->assertStringContainsString('PASSPORT_PRIVATE_KEY as '.$form, $output);
    }
});
test('a malformed key a missing mail package and a missing cache table each fail by name and pass once fixed', function () {
    // A key that is no PEM at all.
    [$env] = productionRehearsalEnvironment(['PASSPORT_PRIVATE_KEY' => 'not-a-key']);
    productionRehearsalMigrate($env);
    [$exit, $output] = productionRehearsalPreflight($env);
    expect($exit)->toBe(1);
    $this->assertStringContainsString('install.secrets', $output);
    $this->assertStringContainsString('not a PEM key', $output);

    // A mailer whose transport package is not installed names the package; Resend (required by the starter) passes.
    [$env] = productionRehearsalEnvironment(['MAIL_MAILER' => 'postmark']);
    productionRehearsalMigrate($env);
    [$exit, $output] = productionRehearsalPreflight($env);
    expect($exit)->toBe(1);
    $this->assertStringContainsString('symfony/postmark-mailer', $output);
    [$fixed] = productionRehearsalEnvironment(['MAIL_MAILER' => 'resend']);
    productionRehearsalMigrate($fixed);
    expect(productionRehearsalPreflight($fixed)[0])->toBe(0);

    // The database cache store with its table missing (an unmigrated database) fails; migrated, it passes.
    [$env] = productionRehearsalEnvironment();
    [$exit, $output] = productionRehearsalPreflight($env);
    expect($exit)->toBe(1);
    $this->assertStringContainsString('Missing tables', $output);
    $this->assertStringContainsString('cache', $output);
    productionRehearsalMigrate($env);
    expect(productionRehearsalPreflight($env)[0])->toBe(0);
});
test('the environment example mirrors the platform and the starter requires resend', function () {
    $example = Dotenv::parse((string) file_get_contents(dirname(__DIR__).'/.env.production.example'));
    foreach (['APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'DB_CONNECTION' => 'pgsql', 'CACHE_STORE' => 'database', 'SESSION_DRIVER' => 'database', 'MAIL_MAILER' => 'resend'] as $key => $value) {
        expect($example[$key])->toBe($value, $key);
    }
    expect(['sync', 'database'])->toContain($example['QUEUE_CONNECTION']);
    expect($example)->toHaveKey('RESEND_KEY');

    // The key placeholders are real-format PEM blocks with real newlines.
    expect($example['PASSPORT_PRIVATE_KEY'])->toMatch('/-----BEGIN PRIVATE KEY-----\n.+\n-----END PRIVATE KEY-----/s');
    $composer = json_decode((string) file_get_contents(dirname(__DIR__).'/composer.json'), true);
    expect($composer['require'])->toHaveKey('resend/resend-php');
});
test('the kit turns each form of a passport key into the pem and never reports the value', function () {
    $pem = "-----BEGIN PRIVATE KEY-----\nMIIBVQIBADANBgkqhkiG9w0BAQEFAASCAT8wggE7AgEAAkEA\n-----END PRIVATE KEY-----";
    expect(PassportKeys::normalize($pem))->toBe([$pem, 'pem']);
    expect(PassportKeys::normalize(str_replace("\n", '\\n', $pem)))->toBe([$pem, 'escaped-newlines']);
    expect(PassportKeys::normalize(base64_encode($pem)))->toBe([$pem, 'base64']);
    expect(PassportKeys::normalize('not-a-key'))->toBe([null, 'unrecognised']);
    expect(PassportKeys::normalize(base64_encode('just text')))->toBe([null, 'unrecognised']);
    expect(PassportKeys::normalize(''))->toBe([null, 'missing']);
    expect(PassportKeys::normalize(null))->toBe([null, 'missing']);

    // At boot: the config holds the real PEM whichever form came in, and only the form is remembered.
    $config = new Repository(['passport' => ['private_key' => str_replace("\n", '\\n', $pem), 'public_key' => null]]);
    PassportKeys::apply($config);
    expect($config->get('passport.private_key'))->toBe($pem);
    expect(PassportKeys::forms())->toBe(['PASSPORT_PRIVATE_KEY' => 'escaped-newlines', 'PASSPORT_PUBLIC_KEY' => 'missing']);
    $this->assertStringNotContainsString('MIIBVQ', json_encode(PassportKeys::forms()));
});
test('the file takes precedence over the shell and nothing the shell set leaks in', function () {
    // The shell has a mailer whose package is not installed (the file sets no mailer) and a cache store (the file sets another).
    [$env] = productionRehearsalEnvironment();
    $lines = array_filter(explode("\n", (string) file_get_contents($env)), fn (string $line): bool => ! str_starts_with($line, 'MAIL_MAILER='));
    file_put_contents($env, implode("\n", $lines)."\n");
    productionRehearsalMigrate($env);
    putenv('MAIL_MAILER=postmark');
    putenv('CACHE_STORE=array');
    try {
        [$exit, $output] = productionRehearsalPreflight($env);
    } finally {
        putenv('MAIL_MAILER');
        putenv('CACHE_STORE=array');
    }

    // MAIL_MAILER is not in the file, so the shell's postmark never reaches the child (it would have failed the mail check); CACHE_STORE is, and the file's value is the one used.
    expect($exit)->toBe(0, $output);
    $this->assertStringNotContainsString('symfony/postmark-mailer', $output);
    expect($output)->toMatch('/Your shell sets [A-Z_, ]*CACHE_STORE/', 'a shell variable that overrides a key in the file is warned about, by name');
    $this->assertDoesNotMatchRegularExpression('/Your shell sets [A-Z_, ]*MAIL_MAILER/', $output, 'a shell variable the file does not set is not in the warning: it is simply not passed on');
});
