<?php

use Composer\InstalledVersions;
use Composer\Semver\Semver;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Tey\Kit\Catalog;

// Since kit 0.18 the template's kit pages are its own files, written once by `kit:scaffold` (no installed records, no kit:add or kit:update). The template keeps
// every one of them at the bytes the locked kit scaffolds, so an app that forks it starts from the kit's pages, and an edit to one is caught here.
beforeEach(function () {
    $this->roots = [];
});
afterEach(function () {
    foreach ($this->roots as $root) {
        (new Filesystem)->deleteDirectory($root);
    }
});
test('released packages and the adopted features', function () {
    // The released packages: the installed version (from the lock) satisfies the constraint composer.json asks for, whatever the patch release is; tey/mod comes in
    // through them and is a release too.
    $require = json_decode((string) file_get_contents(dirname(__DIR__).'/composer.json'), true)['require'];
    foreach (['tey/concierge', 'tey/kit'] as $package) {
        expect(Semver::satisfies((string) InstalledVersions::getVersion($package), $require[$package]))->toBeTrue($package.' '.InstalledVersions::getPrettyVersion($package).' satisfies '.$require[$package]);
    }
    foreach (['tey/concierge', 'tey/mod', 'tey/kit'] as $package) {
        expect((string) InstalledVersions::getPrettyVersion($package))->toMatch('/^v\d+\.\d+\.\d+$/', $package.' is a release, not a branch');
    }
    // The template adopts every catalog feature except the opt-in notes, finalize and deliver, in the catalog's order.
    expect(config('kit.features'))->toBe(array_values(array_diff(Catalog::package()->featureIds(), ['notes', 'finalize', 'deliver'])));
    // Nothing of the kit:add lifecycle is left: no records, no host binding.
    expect(base_path('.mod-maintenance'))->not->toBeDirectory();
    expect(base_path('app/Kit'))->not->toBeDirectory();
});
test('the template kit files are what kit scaffold writes for every adopted feature', function () {
    $kernel = app(Kernel::class);
    $checked = 0;
    foreach (lifecycleAll() as $feature) {
        expect($kernel->call('kit:scaffold', ['feature' => $feature, '--diff' => true]))->toBe(0);
        foreach (array_filter(explode("\n", trim($kernel->output()))) as $line) {
            if ($line === $feature.' has no files to scaffold.') {
                continue;
            }
            expect($line)->toStartWith('unchanged ', $feature.': '.$line.' (take the kit\'s file with kit:scaffold '.$feature.' --force=<path>)');
            $checked++;
        }
    }
    expect($checked)->toBe(count(lifecycleScaffolded()));
});
test('kit scaffold writes the template files into an empty checkout and a second run writes nothing', function () {
    $root = lifecycleCheckout();
    lifecycleIn($root, function (Kernel $kernel): void {
        foreach (lifecycleAll() as $feature) {
            expect($kernel->call('kit:scaffold', ['feature' => $feature]))->toBe(0);
        }
    });
    expect(lifecycleFiles($root))->toBe(lifecycleFiles(base_path()));
    lifecycleIn($root, function (Kernel $kernel): void {
        foreach (lifecycleAll() as $feature) {
            expect($kernel->call('kit:scaffold', ['feature' => $feature]))->toBe(0);
            expect($kernel->output())->not->toContain('wrote ');
        }
    });
    expect(lifecycleFiles($root))->toBe(lifecycleFiles(base_path()));
});
test('an edited kit file is reported and kept until it is forced', function () {
    $root = lifecycleCheckout();
    $edited = 'resources/js/pages/Auth/Login/Page.vue';
    lifecycleIn($root, function (Kernel $kernel) use ($root, $edited): void {
        expect($kernel->call('kit:scaffold', ['feature' => 'session-ui']))->toBe(0);
        file_put_contents($root.'/'.$edited, "<template><p>customised</p></template>\n");
        expect($kernel->call('kit:scaffold', ['feature' => 'session-ui', '--diff' => true]))->toBe(0);
        expect($kernel->output())->toContain('+++ b/'.$edited)->not->toContain('unchanged '.$edited);
        expect($kernel->call('kit:scaffold', ['feature' => 'session-ui']))->toBe(0);
        expect(file_get_contents($root.'/'.$edited))->toBe("<template><p>customised</p></template>\n");
        expect($kernel->call('kit:scaffold', ['feature' => 'session-ui', '--force' => [$edited]]))->toBe(0);
    });
    expect(file_get_contents($root.'/'.$edited))->toBe(file_get_contents(base_path($edited)));
});
/** @return list<string> the features the template adopts (config/kit.php), in the catalog's order */
function lifecycleAll(): array
{
    return config('kit.features');
}
/** @return list<string> the files kit:scaffold writes for the adopted features */
function lifecycleScaffolded(): array
{
    return kitOwnedFiles();
}
/** An empty disposable checkout. */
function lifecycleCheckout(): string
{
    $root = sys_get_temp_dir().'/starter-kit-'.bin2hex(random_bytes(8));
    mkdir($root, 0700, true);
    $root = realpath($root);
    test()->roots = [...test()->roots, $root];

    return $root;
}
/** Runs $run with the app's base path at $root, so kit:scaffold reads and writes there. */
function lifecycleIn(string $root, Closure $run): void
{
    $app = app();
    $base = $app->basePath();
    try {
        $app->setBasePath($root);
        $run(app(Kernel::class));
    } finally {
        $app->setBasePath($base);
    }
}
/** @return array<string, string> path => bytes of the scaffolded files under $root that exist */
function lifecycleFiles(string $root): array
{
    $files = [];
    foreach (lifecycleScaffolded() as $path) {
        if (is_file($root.'/'.$path)) {
            $files[$path] = (string) file_get_contents($root.'/'.$path);
        }
    }
    ksort($files);

    return $files;
}
