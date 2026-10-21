<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

require_once dirname(__DIR__).'/vendor/autoload.php';

/**
 * The base of every PHP test: the application the checks boot, and no Vite. The suite must pass on a fresh checkout with no built assets (`public/build`), so a test never
 * asks for the manifest; browser tests are separate and use their own built assets.
 */
abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        return require __DIR__.'/application.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }
}
