<?php

/** Local test runs must say how they run: `--tia` (only the tests a change can reach) or `--no-tia` (browser specs). CI and parallel workers are exempt: CI, PARATEST or PEST_TIA set to a non-empty value (an empty `CI=` does not count). */
final class TiaGuard
{
    /** @param  list<string>  $argv  @param  array<string, string>|false  $env */
    public static function refusal(array $argv, array|false $env): ?string
    {
        $env = $env === false ? [] : $env;
        foreach (['CI', 'PARATEST', 'PEST_TIA'] as $bypass) {
            if (($env[$bypass] ?? '') !== '') {
                return null;
            }
        }

        return array_intersect(['--tia', '--no-tia'], $argv) === [] ? "Run tests with TIA: composer test (or vendor/bin/pest --tia …). Use --no-tia only for browser specs.\n" : null;
    }

    /** The branch TIA diffs against: `TIA_DEFAULT_BRANCH` when the host sets it (a repository whose default is not `main`), else `main`. @param  array<string, string>|false  $env */
    public static function defaultBranch(array|false $env): string
    {
        $branch = $env === false ? '' : (string) ($env['TIA_DEFAULT_BRANCH'] ?? '');

        return $branch !== '' ? $branch : 'main';
    }
}
