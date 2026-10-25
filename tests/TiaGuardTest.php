<?php

// The guard refuses a bare local run, accepts either flag, and a non-empty CI, PARATEST or PEST_TIA bypasses it; the base branch is the host's.
test('a bare local run is refused', function (): void {
    expect(TiaGuard::refusal(['pest'], []))->toContain('--tia')
        ->and(TiaGuard::refusal(['pest', 'tests/AccountTest.php'], []))->not->toBeNull();
});

test('either flag lets a local run through', function (): void {
    expect(TiaGuard::refusal(['pest', '--tia'], []))->toBeNull()
        ->and(TiaGuard::refusal(['pest', '--no-tia'], []))->toBeNull();
});

test('CI, PARATEST and PEST_TIA bypass the guard', function (string $name): void {
    expect(TiaGuard::refusal(['pest'], [$name => '1']))->toBeNull();
})->with(['CI', 'PARATEST', 'PEST_TIA']);

test('an empty bypass variable does not bypass the guard', function (string $name): void {
    expect(TiaGuard::refusal(['pest'], [$name => '']))->toContain('--tia');
})->with(['CI', 'PARATEST', 'PEST_TIA']);

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
