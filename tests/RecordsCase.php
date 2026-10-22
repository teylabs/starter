<?php

namespace Tests;

require_once __DIR__.'/TestCase.php';

/** The base of the tests that adopt the test-only Records fixture, which the kit itself does not ship. */
abstract class RecordsCase extends TestCase
{
    public function createApplication()
    {
        require_once __DIR__.'/records_fixture.php';

        return starterAppWithRecords();
    }
}
