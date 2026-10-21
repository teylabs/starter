<?php

use Illuminate\Config\Repository;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use Tey\Kit\PassportKeys;

require dirname(__DIR__).'/vendor/autoload.php';
require_once __DIR__.'/TestCase.php';

/**
 * The production rehearsal: the checks that only show on a real deploy (the mail transport's package, the cache tables, the key forms) run locally and in CI against a throwaway
 * environment built from .env.production.example, with the production-only checks ON, and nothing from the environment file is ever printed.
 */
final class ProductionRehearsalTest extends TestCase
{
    /** @var list<string> files to remove */
    private array $made = [];

    protected function tearDown(): void
    {
        foreach ($this->made as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    /** A throwaway production environment on a temp SQLite database, with overrides, as a file path. @return array{0: string, 1: string} the env file and the database file */
    private function environment(array $overrides = [], string $form = '--pem'): array
    {
        $database = tempnam(sys_get_temp_dir(), 'rehearsal-db-');
        $process = new Process([PHP_BINARY, dirname(__DIR__).'/bin/rehearsal-env.php', $form, 'DB_CONNECTION=sqlite', 'DB_DATABASE='.$database, ...array_map(fn ($k, $v) => $k.'='.$v, array_keys($overrides), $overrides)], dirname(__DIR__));
        $process->mustRun();
        $file = tempnam(sys_get_temp_dir(), 'rehearsal-env-');
        file_put_contents($file, $process->getOutput());
        $this->made[] = $file;
        $this->made[] = $database;

        return [$file, $database];
    }

    /** Runs an artisan command in a child that reads the given environment file as its whole environment. */
    private function runArtisan(string $envFile, array $arguments): array
    {
        $name = 'rehearsal-test-'.bin2hex(random_bytes(3));
        $copy = dirname(__DIR__).'/.env.'.$name;
        copy($envFile, $copy);
        try {
            $process = new Process([PHP_BINARY, dirname(__DIR__).'/artisan', ...$arguments, '--env='.$name, '--no-interaction'], dirname(__DIR__), array_map('strval', Dotenv\Dotenv::parse((string) file_get_contents($envFile))), timeout: 120);
            $process->run();
        } finally {
            @unlink($copy);
        }

        return [$process->getExitCode(), $process->getOutput().$process->getErrorOutput()];
    }

    private function migrate(string $envFile): void
    {
        [$exit] = $this->runArtisan($envFile, ['migrate', '--force']);
        $this->assertSame(0, $exit, 'the rehearsal database migrates');
    }

    /** The preflight against an environment file, through the kit's own rehearsal option. */
    private function preflight(string $envFile, array $options = []): array
    {
        $process = new Process([PHP_BINARY, dirname(__DIR__).'/artisan', 'kit:preflight', '--env-file='.$envFile, '--skip=install.vite', ...$options], dirname(__DIR__), timeout: 120);
        $process->run();

        return [$process->getExitCode(), $process->getOutput().$process->getErrorOutput()];
    }

    public function test_a_throwaway_production_environment_passes_the_preflight_with_the_production_checks_on(): void
    {
        [$env] = $this->environment();
        $this->migrate($env);
        [$exit, $output] = $this->preflight($env);
        $this->assertSame(0, $exit, $output);
        $this->assertStringContainsString('install.tables', $output);
        $this->assertStringNotContainsString('skipped-in-ci', preg_replace('/"skipped-in-ci":0/', '', $output));
        $this->assertStringContainsString('Passport keys: PASSPORT_PRIVATE_KEY as pem, PASSPORT_PUBLIC_KEY as pem', $output);
        // Nothing from the file is printed, and the copy the child read is gone.
        foreach (Dotenv\Dotenv::parse((string) file_get_contents($env)) as $name => $value) {
            if (preg_match('/KEY|PASSWORD/', $name) === 1 && strlen($value) >= 12) {
                foreach (preg_split('/\R/', $value) as $line) {
                    if (! str_starts_with($line, '-----') && strlen($line) >= 8) {
                        $this->assertStringNotContainsString($line, $output, $name.' is never printed');
                    }
                }
            }
        }
        $this->assertSame([], glob(dirname(__DIR__).'/.env.kit-rehearsal-*') ?: []);
    }

    public function test_the_passport_keys_pass_in_every_form_a_dashboard_can_hold_and_the_form_is_reported(): void
    {
        foreach (['--escaped' => 'escaped-newlines', '--base64' => 'base64'] as $flag => $form) {
            [$env] = $this->environment([], $flag);
            $this->migrate($env);
            [$exit, $output] = $this->preflight($env);
            $this->assertSame(0, $exit, $form.': '.$output);
            $this->assertStringContainsString('PASSPORT_PRIVATE_KEY as '.$form, $output);
        }
    }

    public function test_a_malformed_key_a_missing_mail_package_and_a_missing_cache_table_each_fail_by_name_and_pass_once_fixed(): void
    {
        // A key that is no PEM at all.
        [$env] = $this->environment(['PASSPORT_PRIVATE_KEY' => 'not-a-key']);
        $this->migrate($env);
        [$exit, $output] = $this->preflight($env);
        $this->assertSame(1, $exit);
        $this->assertStringContainsString('install.secrets', $output);
        $this->assertStringContainsString('not a PEM key', $output);

        // A mailer whose transport package is not installed names the package; Resend (required by the starter) passes.
        [$env] = $this->environment(['MAIL_MAILER' => 'postmark']);
        $this->migrate($env);
        [$exit, $output] = $this->preflight($env);
        $this->assertSame(1, $exit);
        $this->assertStringContainsString('symfony/postmark-mailer', $output);
        [$fixed] = $this->environment(['MAIL_MAILER' => 'resend']);
        $this->migrate($fixed);
        $this->assertSame(0, $this->preflight($fixed)[0]);

        // The database cache store with its table missing (an unmigrated database) fails; migrated, it passes.
        [$env] = $this->environment();
        [$exit, $output] = $this->preflight($env);
        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Missing tables', $output);
        $this->assertStringContainsString('cache', $output);
        $this->migrate($env);
        $this->assertSame(0, $this->preflight($env)[0]);
    }

    public function test_the_environment_example_mirrors_the_platform_and_the_starter_requires_resend(): void
    {
        $example = Dotenv\Dotenv::parse((string) file_get_contents(dirname(__DIR__).'/.env.production.example'));
        foreach (['APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'DB_CONNECTION' => 'pgsql', 'CACHE_STORE' => 'database', 'SESSION_DRIVER' => 'database', 'MAIL_MAILER' => 'resend'] as $key => $value) {
            $this->assertSame($value, $example[$key], $key);
        }
        $this->assertContains($example['QUEUE_CONNECTION'], ['sync', 'database']);
        $this->assertArrayHasKey('RESEND_KEY', $example);
        // The key placeholders are real-format PEM blocks with real newlines.
        $this->assertMatchesRegularExpression('/-----BEGIN PRIVATE KEY-----\n.+\n-----END PRIVATE KEY-----/s', $example['PASSPORT_PRIVATE_KEY']);
        $composer = json_decode((string) file_get_contents(dirname(__DIR__).'/composer.json'), true);
        $this->assertArrayHasKey('resend/resend-php', $composer['require']);
    }

    public function test_the_kit_turns_each_form_of_a_passport_key_into_the_pem_and_never_reports_the_value(): void
    {
        $pem = "-----BEGIN PRIVATE KEY-----\nMIIBVQIBADANBgkqhkiG9w0BAQEFAASCAT8wggE7AgEAAkEA\n-----END PRIVATE KEY-----";
        $this->assertSame([$pem, 'pem'], PassportKeys::normalize($pem));
        $this->assertSame([$pem, 'escaped-newlines'], PassportKeys::normalize(str_replace("\n", '\\n', $pem)));
        $this->assertSame([$pem, 'base64'], PassportKeys::normalize(base64_encode($pem)));
        $this->assertSame([null, 'unrecognised'], PassportKeys::normalize('not-a-key'));
        $this->assertSame([null, 'unrecognised'], PassportKeys::normalize(base64_encode('just text')));
        $this->assertSame([null, 'missing'], PassportKeys::normalize(''));
        $this->assertSame([null, 'missing'], PassportKeys::normalize(null));

        // At boot: the config holds the real PEM whichever form came in, and only the form is remembered.
        $config = new Repository(['passport' => ['private_key' => str_replace("\n", '\\n', $pem), 'public_key' => null]]);
        PassportKeys::apply($config);
        $this->assertSame($pem, $config->get('passport.private_key'));
        $this->assertSame(['PASSPORT_PRIVATE_KEY' => 'escaped-newlines', 'PASSPORT_PUBLIC_KEY' => 'missing'], PassportKeys::forms());
        $this->assertStringNotContainsString('MIIBVQ', json_encode(PassportKeys::forms()));
    }
}
