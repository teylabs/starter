<?php

use App\Kit\KitHost;
use Composer\InstalledVersions;
use Composer\Semver\Semver;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use Tey\Kit\Blueprint;
use Tey\Kit\Catalog;
use Tey\Kit\LifecycleHost;
use Tey\Kit\Lineages;
use Tey\Mod\Maintenance\Maintenance;

beforeEach(function () {
    $this->roots = [];
    expect([':memory:', 'pgsql'])->toContain(config('database.default') === 'sqlite' ? config('database.connections.sqlite.database') : 'pgsql');
    expect(app(Kernel::class)->call('migrate', ['--force' => true]))->toBe(0);
});
afterEach(function () {
    foreach ($this->roots as $root) {
        (new Filesystem)->deleteDirectory($root);
    }
});
test('released packages and trusted host binding', function () {
    // The released packages: the installed version (from the lock) satisfies the constraint composer.json asks for, whatever the patch release is; tey/mod comes in
    // through them and is a release too.
    $constraint = json_decode((string) file_get_contents(dirname(__DIR__).'/composer.json'), true)['require']['tey/concierge'];
    expect(Semver::satisfies((string) InstalledVersions::getVersion('tey/concierge'), $constraint))->toBeTrue('tey/concierge '.InstalledVersions::getPrettyVersion('tey/concierge').' satisfies '.$constraint);
    foreach (['tey/concierge', 'tey/mod', 'tey/kit'] as $package) {
        expect((string) InstalledVersions::getPrettyVersion($package))->toMatch('/^v\d+\.\d+\.\d+$/', $package.' is a release, not a branch');
    }
    $kit = json_decode((string) file_get_contents(dirname(__DIR__).'/composer.json'), true)['require']['tey/kit'];
    expect(Semver::satisfies((string) InstalledVersions::getVersion('tey/kit'), $kit))->toBeTrue('tey/kit '.InstalledVersions::getPrettyVersion('tey/kit').' satisfies '.$kit);
    // TEMP: the local path repo
    expect((new ReflectionClass(Maintenance::class))->getFileName())->toEndWith('/vendor/tey/mod/src/Maintenance/Maintenance.php');
    $host = app(LifecycleHost::class);
    expect($host)->toBeInstanceOf(KitHost::class);
    expect($host->appId())->toBe('workspace-starter');
    expect($host->bindings())->toBe([]);
    foreach ($host->inputPaths() as $path) {
        expect(base_path($path))->toBeFile();
    }
    expect($host->lifecycle()->runtime())->toBe(json_decode(file_get_contents(base_path('config/kit-executor.json')), true));
    expect(($host->operationCheck())())->toBeTrue();
    expect(config('kit.features'))->toBe(Catalog::package()->featureIds());
});
test('adding every feature writes the template files then reruns as a no op', function () {
    $root = lifecycleCheckout();
    $preview = lifecycleLineages()->preview($root, 'workspace-starter', lifecycleAll(), [], KitHost::INPUTS);
    expect($preview['executable'])->toBeTrue();
    expect(array_column($preview['features'], 'feature'))->toBe(lifecycleAll());
    expect(lifecycleRecords($root))->toBe([]);

    $result = lifecycleLineages()->add($root, 'workspace-starter', lifecycleAll(), [], KitHost::INPUTS, $preview['plan'], 'install', lifecycleCheck($root));
    expect($result['status'])->toBe('completed');
    expect($result['done'])->toBe(lifecycleAll());
    expect(array_keys(Lineages::adopted($root, 'workspace-starter')))->toBe(lifecycleAll());
    expect(lifecycleFiles($root))->toBe(lifecycleTemplateFiles());

    // A second request adopts nothing new and writes nothing.
    $records = lifecycleRecords($root);
    $again = lifecycleLineages()->preview($root, 'workspace-starter', lifecycleAll(), [], KitHost::INPUTS);
    expect(array_column($again['features'], 'action'))->toBe(array_fill(0, 8, 'adopted'));
    lifecycleRefuses(fn () => lifecycleLineages()->resume($root, 'workspace-starter', lifecycleAll(), [], KitHost::INPUTS, 'install', lifecycleCheck($root)), 'already adopted');
    expect(lifecycleRecords($root))->toBe($records);
    expect(lifecycleFiles($root))->toBe(lifecycleTemplateFiles());
});
test('a checkout of the template adopts its own files by reviewed claim', function () {
    $root = lifecycleCheckout(withTemplateFiles: true);
    $paths = array_keys(lifecycleTemplateFiles());
    $preview = lifecycleLineages()->preview($root, 'workspace-starter', lifecycleAll(), [], KitHost::INPUTS);
    expect($preview['executable'])->toBeFalse();
    expect($preview['claimable'])->toEqualCanonicalizing($paths);
    lifecycleRefuses(fn () => lifecycleLineages()->add($root, 'workspace-starter', lifecycleAll(), [], KitHost::INPUTS, $preview['plan'], null, lifecycleCheck($root)), 'not executable');
    expect(lifecycleRecords($root))->toBe([]);

    $claimed = lifecycleLineages()->preview($root, 'workspace-starter', lifecycleAll(), [], KitHost::INPUTS, $paths);
    expect($claimed['executable'])->toBeTrue();
    $result = lifecycleLineages()->add($root, 'workspace-starter', lifecycleAll(), [], KitHost::INPUTS, $claimed['plan'], 'adopt', lifecycleCheck($root), $paths);
    expect($result['status'])->toBe('completed');
    expect(array_keys(Lineages::adopted($root, 'workspace-starter')))->toBe(lifecycleAll());
    expect(lifecycleFiles($root))->toBe(lifecycleTemplateFiles());
});
test('an edited template file refuses its claim', function () {
    $root = lifecycleCheckout(withTemplateFiles: true);
    $paths = array_keys(lifecycleTemplateFiles());
    $edited = 'database/migrations/kit/mcp/0001_01_02_000600_create_kit_mcp_tables.php';
    file_put_contents($root.'/'.$edited, "<?php // customised\n");
    $preview = lifecycleLineages()->preview($root, 'workspace-starter', lifecycleAll(), [], KitHost::INPUTS, $paths);
    expect($preview['executable'])->toBeFalse();
    lifecycleRefuses(fn () => lifecycleLineages()->add($root, 'workspace-starter', lifecycleAll(), [], KitHost::INPUTS, $preview['plan'], null, lifecycleCheck($root), $paths), 'not executable');
    expect(file_get_contents($root.'/'.$edited))->toBe("<?php // customised\n");
    expect(lifecycleRecords($root))->toBe([]);
});
test('operation check fails closed', function () {
    $root = lifecycleCheckout();
    $preview = lifecycleLineages()->preview($root, 'workspace-starter', lifecycleAll(), [], KitHost::INPUTS);

    // A closure declared outside the bound host-operations file is rejected before effects.
    lifecycleRefuses(fn () => lifecycleLineages()->add($root, 'workspace-starter', lifecycleAll(), [], KitHost::INPUTS, $preview['plan'], null, fn () => true), 'host-operations');
    expect(lifecycleRecords($root))->toBe([]);
    expect(lifecycleFiles($root))->toBe([]);

    // Without the host schema the check fails, so the first feature stays pending and nothing is recorded.
    Schema::rename('users', 'users_moved');
    expect((lifecycleCheck($root))())->toBeFalse();
    $result = lifecycleLineages()->add($root, 'workspace-starter', lifecycleAll(), [], KitHost::INPUTS, $preview['plan'], 'install', lifecycleCheck($root));
    $this->assertNotSame('completed', $result['status']);
    expect($result['failed'])->toBe('core');
    expect(array_keys(Lineages::adopted($root, 'workspace-starter')))->toBe([]);

    // A Mod executor that differs from the committed provenance refuses at plan time, named (kit 0.4.11): executor_stale, with the fix.
    $root = lifecycleCheckout();
    file_put_contents($root.'/config/kit-executor.json', '{"installed_mod_version":"v0.9.0"}');
    lifecycleRefuses(fn () => lifecycleLineages()->preview($root, 'workspace-starter', lifecycleAll(), [], KitHost::INPUTS), 'executor_stale');
});
test('kit add command', function () {
    $root = lifecycleCheckout();
    $base = $this->app->basePath();
    $kernel = app(Kernel::class);
    $features = ['features' => lifecycleAll()];
    try {
        // KitHost reads its operation check from the checkout it runs in.
        $this->app->setBasePath($root);
        expect($kernel->call('kit:add', $features + ['--json' => true]))->toBe(0);
        $preview = json_decode(trim($kernel->output()), true, flags: JSON_THROW_ON_ERROR);
        $this->assertNotSame(0, $kernel->call('kit:add', $features + ['--apply' => true, '--expected-plan' => 'sha256:wrong']));
        expect(lifecycleRecords($root))->toBe([]);
        expect($kernel->call('kit:add', $features + ['--apply' => true, '--expected-plan' => $preview['plan']]))->toBe(0);
        expect(lifecycleRecords($root))->toHaveCount(8);
        expect(lifecycleFiles($root))->toBe(lifecycleTemplateFiles());
    } finally {
        $this->app->setBasePath($base);
    }
});
/** A disposable checkout holding copies of the template's real kit host inputs, and optionally its feature files. */
function lifecycleCheckout(bool $withTemplateFiles = false): string
{
    $root = sys_get_temp_dir().'/starter-kit-'.bin2hex(random_bytes(8));
    foreach (KitHost::INPUTS as $path) {
        if (! is_dir(dirname($root.'/'.$path))) {
            mkdir(dirname($root.'/'.$path), 0700, true);
        }
        copy(base_path($path), $root.'/'.$path);
    }
    foreach ($withTemplateFiles ? lifecycleTemplateFiles() : [] as $path => $bytes) {
        if (! is_dir(dirname($root.'/'.$path))) {
            mkdir(dirname($root.'/'.$path), 0700, true);
        }
        file_put_contents($root.'/'.$path, $bytes);
    }
    $root = realpath($root);
    test()->roots = [...test()->roots, $root];

    return $root;
}
function lifecycleLineages(): Lineages
{
    return new Lineages(app(LifecycleHost::class)->lifecycle(), Catalog::package());
}
/** @return list<string> */
function lifecycleAll(): array
{
    return Catalog::package()->featureIds();
}
/** The checkout's own operation check, declared in its host-operations input. */
function lifecycleCheck(string $root): Closure
{
    return require $root.'/'.KitHost::INPUTS['host-operations'];
}
/** @return array<string, string> path => bytes of the template's feature files, which are exactly what the catalog renders */
function lifecycleTemplateFiles(): array
{
    $rendered = array_merge(...array_map(fn (string $id): array => Blueprint::lineage($id, Catalog::package())['files'], lifecycleAll()));
    ksort($rendered);
    $files = lifecycleFiles(base_path());
    expect($files)->toBe($rendered);

    return $files;
}
/** @return array<string, string> path => bytes under the features' owned paths */
function lifecycleFiles(string $root): array
{
    $files = [];
    foreach (Catalog::package()->featureIds() as $id) {
        foreach (Blueprint::lineage($id, Catalog::package())['feature']['owns'] as $owned) {
            if (is_file($root.'/'.$owned)) {
                $files[$owned] = file_get_contents($root.'/'.$owned);
            }
            if (! is_dir($root.'/'.$owned)) {
                continue;
            }
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$owned, FilesystemIterator::SKIP_DOTS)) as $file) {
                $files[substr($file->getPathname(), strlen($root) + 1)] = file_get_contents($file->getPathname());
            }
        }
    }
    ksort($files);

    return $files;
}
/** @return list<string> the installed records */
function lifecycleRecords(string $root): array
{
    return glob($root.'/.mod-maintenance/installed/*.json') ?: [];
}
function lifecycleRefuses(Closure $operation, string $message = ''): void
{
    try {
        $operation();
    } catch (Throwable $error) {
        test()->assertStringContainsString($message, $error->getMessage());

        return;
    }
    test()->fail('Expected a refusal.');
}
