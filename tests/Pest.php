<?php

// Pest runs only the browser smoke test (composer test:browser); the PHPUnit suites run through vendor/bin/phpunit.
require_once __DIR__.'/Browser/BrowserCase.php';

pest()->extend(BrowserCase::class)->in('Browser');
