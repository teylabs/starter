<?php

use PHPUnit\Framework\TestCase;

require dirname(__DIR__).'/vendor/autoload.php';

/** The artisan commands run: in a console Collision wraps the exception handler, and the kit must boot under it (a regression killed every command). */
final class ArtisanTest extends TestCase
{
    /** @return array{0: int, 1: string} */
    private function artisan(string ...$arguments): array
    {
        // A subprocess, so Collision is active as it is for a developer or a deploy.
        $process = proc_open([PHP_BINARY, 'artisan', ...$arguments, '--no-ansi'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__), [...getenv(), 'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:']);
        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $output];
    }

    public function test_artisan_commands_boot_under_collision(): void
    {
        [$code, $output] = $this->artisan('about');
        $this->assertSame(0, $code, $output);
        $this->assertStringContainsString('Laravel Version', $output);
        [$code, $output] = $this->artisan('key:generate', '--show');
        $this->assertSame(0, $code, $output);
        $this->assertStringContainsString('base64:', $output);
        [$code, $output] = $this->artisan('route:list', '--json');
        $this->assertSame(0, $code, $output);
        $this->assertStringNotContainsString('undefined method', $output);
    }
}
