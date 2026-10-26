<?php

use Tests\RecordsCase;
use Tests\StorageCase;
use Tests\TestCase;

// A local run needs --tia (impacted tests only) or --no-tia (browser specs); CI, PARATEST and PEST_TIA bypass. The rule lives in tests/TiaGuard.php.
require_once __DIR__.'/TiaGuard.php';

if (($refusal = TiaGuard::refusal($_SERVER['argv'] ?? [], getenv())) !== null) {
    fwrite(STDERR, $refusal);
    exit(1);
}

// Browser tests keep their own base case and configuration (composer test:browser); every other test extends the application base case.
require_once __DIR__.'/Browser/BrowserCase.php';
require_once __DIR__.'/RecordsCase.php';
require_once __DIR__.'/StorageCase.php';

pest()->extend(BrowserCase::class)->in('Browser');

// Two groups boot the application differently: with the test-only Records fixture, and with an object-storage bucket in the environment.
$records = ['Breadcrumbs', 'InertiaTransport', 'PageTitle', 'UrlConvention'];
$storage = ['Storage'];
// These need no application: they stay plain PHPUnit cases.
$plain = ['Artisan', 'FeatureGraph', 'MailSender', 'NightwatchConfig', 'TiaGuard'];
$tests = static fn (array $names): array => array_map(static fn (string $name): string => __DIR__.'/'.$name.'Test.php', $names);

pest()->extend(RecordsCase::class)->in(...$tests($records));
pest()->extend(StorageCase::class)->in(...$tests($storage));
pest()->extend(TestCase::class)->in(...array_values(array_diff(glob(__DIR__.'/*Test.php'), $tests([...$records, ...$storage, ...$plain]))));

// Runtime coverage cannot prove client behaviour or discover every dynamic dependency, so files it cannot see re-run the suite.
// database/** covers the factories and seeders. The arch tests read source and workflow files rather than executing them, so coverage never links them;
// the application-free checks are re-run on any change under tests/ (the guard and helpers they load are not covered files).
$arch = ['FeatureGraph', 'HandsFreeDeploy', 'KitUi', 'Lifecycle', 'SettingsForms', 'ViewModelStandard'];
$tia = pest()->tia()->defaultBranch(TiaGuard::defaultBranch(getenv()))->directory('.phpunit.cache/tia')->watch([
    'config/**' => 'tests',
    'routes/**' => 'tests',
    'resources/js/**' => 'tests',
    'resources/css/**' => 'tests',
    'database/**' => 'tests',
]);
foreach ([...$tests($arch), ...$tests($plain)] as $index => $file) {
    $target = 'tests/'.basename($file);
    $tia->watch($index < count($arch) ? ['app/**' => $target, '.github/workflows/**' => $target] : ['tests/**' => $target]);
}
