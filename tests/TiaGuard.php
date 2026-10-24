<?php

/** Local test runs must say how they run: `--tia` (only the tests a change can reach) or `--no-tia` (browser specs). CI and parallel workers are exempt. */
final class TiaGuard
{
    /** @param  list<string>  $argv  @param  array<string, string>|false  $env */
    public static function refusal(array $argv, array|false $env): ?string
    {
        $env = $env === false ? [] : $env;
        foreach (['CI', 'PARATEST', 'PEST_TIA'] as $bypass) {
            if (array_key_exists($bypass, $env)) {
                return null;
            }
        }

        return array_intersect(['--tia', '--no-tia'], $argv) === [] ? "Run tests with TIA: composer test (or vendor/bin/pest --tia …). Use --no-tia only for browser specs.\n" : null;
    }
}
