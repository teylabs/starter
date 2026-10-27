<?php

// Pest.php refuses a bare local run, and any set CI, PARATEST or PEST_TIA bypasses it; the base branch is the host's.
test('the TIA base branch defaults to main and follows TIA_DEFAULT_BRANCH', function (): void {
    expect(TiaGuard::defaultBranch([]))->toBe('main')
        ->and(TiaGuard::defaultBranch(['TIA_DEFAULT_BRANCH' => '']))->toBe('main')
        ->and(TiaGuard::defaultBranch(['TIA_DEFAULT_BRANCH' => 'trunk']))->toBe('trunk');
});

test('Pest.php exits nonzero for a bare run', function (): void {
    $env = array_diff_key(getenv(), array_flip(['CI', 'PARATEST', 'PEST_TIA']));
    $process = proc_open([PHP_BINARY, dirname(__DIR__).'/vendor/bin/pest', '--filter=nothing-matches-this'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__), $env);
    $stderr = stream_get_contents($pipes[2]);
    stream_get_contents($pipes[1]);

    expect(proc_close($process))->toBe(1)->and($stderr)->toContain('Run tests with TIA');
});

test('CI, PARATEST and PEST_TIA let a bare run through', function (string $name): void {
    $env = [...array_diff_key(getenv(), array_flip(['CI', 'PARATEST', 'PEST_TIA'])), $name => '1'];
    $process = proc_open([PHP_BINARY, dirname(__DIR__).'/vendor/bin/pest', '--filter=nothing-matches-this'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__), $env);
    $stderr = stream_get_contents($pipes[2]);
    stream_get_contents($pipes[1]);
    proc_close($process);

    expect($stderr)->not->toContain('Run tests with TIA');
})->with(['CI', 'PARATEST', 'PEST_TIA']);

test('an empty CI= does not let a bare run through', function (): void {
    $env = [...array_diff_key(getenv(), array_flip(['CI', 'PARATEST', 'PEST_TIA'])), 'CI' => ''];
    $process = proc_open([PHP_BINARY, dirname(__DIR__).'/vendor/bin/pest', '--filter=nothing-matches-this'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__), $env);
    $stderr = stream_get_contents($pipes[2]);
    stream_get_contents($pipes[1]);

    expect(proc_close($process))->toBe(1)->and($stderr)->toContain('Run tests with TIA');
});
