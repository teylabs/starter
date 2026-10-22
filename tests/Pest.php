<?php

use Tests\RecordsCase;
use Tests\StorageCase;
use Tests\TestCase;

// Browser tests keep their own base case and configuration (composer test:browser); every other test extends the application base case.
require_once __DIR__.'/Browser/BrowserCase.php';
require_once __DIR__.'/RecordsCase.php';
require_once __DIR__.'/StorageCase.php';

pest()->extend(BrowserCase::class)->in('Browser');

// Two groups boot the application differently: with the test-only Records fixture, and with an object-storage bucket in the environment.
$records = ['Breadcrumbs', 'InertiaTransport', 'PageTitle', 'UrlConvention'];
$storage = ['Storage'];
// These need no application: they stay plain PHPUnit cases.
$plain = ['Artisan', 'FeatureGraph', 'MailSender', 'NightwatchConfig'];
$tests = static fn (array $names): array => array_map(static fn (string $name): string => __DIR__.'/'.$name.'Test.php', $names);

pest()->extend(RecordsCase::class)->in(...$tests($records));
pest()->extend(StorageCase::class)->in(...$tests($storage));
pest()->extend(TestCase::class)->in(...array_values(array_diff(glob(__DIR__.'/*Test.php'), $tests([...$records, ...$storage, ...$plain]))));
