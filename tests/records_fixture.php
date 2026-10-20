<?php

use Illuminate\Foundation\Application;
use Tests\Fixtures\RecordsFixtureProvider;

require_once __DIR__.'/Fixtures/RecordsFixture.php';

/** Boots the app as the starter ships it, plus the test-only Records sample resource, for the tests that need a feature page with data. */
function starterAppWithRecords(): Application
{
    $app = require __DIR__.'/application.php';
    // The app arrives booted (see bootstrap/app.php), so the fixture registers and boots itself at once.
    $app->register(RecordsFixtureProvider::class);

    return $app;
}
