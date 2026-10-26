<?php

/** @return array{0: int, 1: string} */
function artisanArtisan(string ...$arguments): array
{
    // A subprocess, so Collision is active as it is for a developer or a deploy.
    $process = proc_open([PHP_BINARY, 'artisan', ...$arguments, '--no-ansi'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__), [...getenv(), 'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:']);
    $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [proc_close($process), $output];
}
test('artisan commands boot under collision', function () {
    [$code, $output] = artisanArtisan('about');
    expect($code)->toBe(0, $output);
    $this->assertStringContainsString('Laravel Version', $output);
    [$code, $output] = artisanArtisan('key:generate', '--show');
    expect($code)->toBe(0, $output);
    $this->assertStringContainsString('base64:', $output);
    [$code, $output] = artisanArtisan('route:list', '--json');
    expect($code)->toBe(0, $output);
    $this->assertStringNotContainsString('undefined method', $output);
});
