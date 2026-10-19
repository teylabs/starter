<?php

use Illuminate\Foundation\Testing\TestCase;

/** The real app on synthetic SQLite memory, as the PHPUnit suites boot it; Pest's browser plugin serves it in-process. */
abstract class BrowserCase extends TestCase
{
    public function createApplication()
    {
        return require dirname(__DIR__).'/application.php';
    }
}
