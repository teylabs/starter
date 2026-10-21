<?php

namespace Tests\Concerns;

/**
 * Whether these tests run in the starter template itself rather than in an app built from it. An app renames its package, so template-only
 * checks (the template's own .env.example values, its placeholder brand assets) are skipped there and the app is checked on its own terms.
 */
final class Template
{
    public static function isTemplate(): bool
    {
        $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true);

        return ($composer['name'] ?? null) === 'tey/starter';
    }
}
