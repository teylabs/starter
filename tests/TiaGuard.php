<?php

/** TIA settings shared by tests/Pest.php. The local --tia/--no-tia guard itself lives inline in Pest.php, where standards:check reads its shape. */
final class TiaGuard
{
    /** The branch TIA diffs against: `TIA_DEFAULT_BRANCH` when the host sets it (a repository whose default is not `main`), else `main`. @param  array<string, string>|false  $env */
    public static function defaultBranch(array|false $env): string
    {
        $branch = $env === false ? '' : (string) ($env['TIA_DEFAULT_BRANCH'] ?? '');

        return $branch !== '' ? $branch : 'main';
    }
}
