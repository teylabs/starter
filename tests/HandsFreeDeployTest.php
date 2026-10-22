<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\DefaultBrand;
use Tey\Kit\Brand;
use Tey\Kit\Features\Core\Registry;

require_once __DIR__.'/Concerns/DefaultBrand.php';
uses(DefaultBrand::class);

beforeEach(function () {
    $this->defaultBrand();

    // The syncs skip under unit tests unless a test opts in; these tests do.
    config(['kit.deploy.sync_in_tests' => true]);
    Cache::flush();
});
function handsFreeDeployMigrate(string $command = 'migrate', array $options = []): int
{
    return app(Kernel::class)->call($command, ['--force' => true, ...$options]);
}
function handsFreeDeployFingerprint(): ?string
{
    return DB::table('kit_meta')->where('key', 'catalog_fingerprint')->value('value');
}
/** @return list<string> the data-changing statements a callback ran */
function handsFreeDeployWrites(callable $run): array
{
    $statements = [];
    DB::listen(function ($query) use (&$statements): void {
        if (preg_match('/^\s*(insert|update|delete)\b/i', $query->sql) === 1) {
            $statements[] = $query->sql;
        }
    });
    $run();

    return $statements;
}
test('migrating a fresh database syncs roles and permissions and a second migrate writes nothing', function () {
    expect(handsFreeDeployMigrate())->toBe(0);
    expect(Role::query()->pluck('name')->all())->toEqualCanonicalizing(['owner', 'admin', 'viewer'], 'the role sync ran, before the permission sync needed it');
    expect(Permission::query()->count())->toBeGreaterThan(0);
    expect(handsFreeDeployFingerprint())->not->toBeNull();
    $grants = DB::table('role_has_permissions')->count();
    expect($grants)->toBeGreaterThan(0, 'the default grants were made');

    // A second migrate has nothing to migrate and an unchanged catalog: no writes at all.
    expect(handsFreeDeployWrites(fn () => expect(handsFreeDeployMigrate())->toBe(0)))->toBe([]);
    expect(DB::table('role_has_permissions')->count())->toBe($grants);
});
test('a changed catalog is synced by a migrate that has nothing to migrate', function () {
    expect(handsFreeDeployMigrate())->toBe(0);
    $before = handsFreeDeployFingerprint();
    expect(Permission::query()->where('name', 'reports.export')->exists())->toBeFalse();

    // A release adds a permission and no migration: the deploy's migrate still picks it up.
    app(Registry::class)->permission('reports.export', ['owner', 'admin']);
    expect(handsFreeDeployMigrate())->toBe(0);
    expect(Permission::query()->where('name', 'reports.export')->exists())->toBeTrue();
    $this->assertNotSame($before, handsFreeDeployFingerprint());
    expect(Role::findByName('admin')->hasPermissionTo('reports.export'))->toBeTrue();
});
test('a rollback does not sync', function () {
    expect(handsFreeDeployMigrate())->toBe(0);
    DB::table('kit_meta')->where('key', 'catalog_fingerprint')->delete();
    Role::findByName('viewer')->delete();
    expect(app(Kernel::class)->call('migrate:rollback', ['--force' => true, '--step' => 1]))->toBe(0);
    expect(Role::query()->where('name', 'viewer')->exists())->toBeFalse('a rollback leaves the roles alone');
    expect(handsFreeDeployFingerprint())->toBeNull();
});
test('the syncs skip under unit tests unless a test opts in', function () {
    config(['kit.deploy.sync_in_tests' => false]);
    expect(handsFreeDeployMigrate())->toBe(0);
    expect(Role::query()->count())->toBe(0);
    expect(handsFreeDeployFingerprint())->toBeNull();
});
test('a failing sync makes migrate fail loudly', function () {
    config(['concierge.roles' => 'not-an-array']);
    $this->expectException(Throwable::class);
    handsFreeDeployMigrate();
});
/** Every brand key set to a real value, as a deployed app has. */
function handsFreeDeployRealBrand(): void
{
    $brand = config('kit.brand');
    foreach (Brand::placeholders() as $placeholder) {
        $key = str_replace('brand.', '', $placeholder);
        if ($key === 'name') {
            config(['app.name' => 'Real Product']);
        } else {
            $brand[$key] = match ($key) {
                'logomark' => '/real-mark.svg',
                'accent' => '#0f766e',
                default => str_contains($key, 'email') || str_contains($key, 'address') && str_contains($key, 'mail') ? 'real@real.test' : 'Real '.$key,
            };
        }
    }
    config(['kit.brand' => $brand]);
}
test('up is healthy and fails for a missing key or a brand placeholder in production', function () {
    expect(handsFreeDeployMigrate())->toBe(0);
    handsFreeDeployRealBrand();
    $this->get('/up')->assertOk();

    // In production: debug on, no key, and unresolved brand placeholders each fail it.
    $this->app['env'] = 'production';
    config(['app.debug' => true]);
    Cache::flush();
    $this->get('/up')->assertStatus(500);
    config(['app.debug' => false]);
    Cache::flush();
    $this->get('/up')->assertOk();
    $key = config('app.key');
    config(['app.key' => '']);
    Cache::flush();
    $this->get('/up')->assertStatus(500);
    config(['app.key' => $key, 'kit.brand.legal_name' => 'Example Company']);
    Cache::flush();
    $this->get('/up')->assertStatus(500);
    config(['kit.brand.legal_name' => 'Real legal_name']);
    Cache::flush();
    $this->get('/up')->assertOk();

    // The sessions table is required when sessions are in the database.
    config(['session.driver' => 'database']);
    Schema::dropIfExists('sessions');
    Cache::flush();
    $this->get('/up')->assertStatus(500);
});
test('the deploy guide and ci use the standard lines and no deploy command', function () {
    $guide = (string) file_get_contents(dirname(__DIR__).'/docs/deploy.md');
    $ci = (string) file_get_contents(dirname(__DIR__).'/.github/workflows/ci.yml');
    foreach ([$guide, $ci] as $source) {
        $this->assertStringContainsString('php artisan migrate --force', $source);
        $this->assertStringContainsString('php artisan optimize', $source);
        $this->assertStringNotContainsString('kit:deploy', $source);
        $this->assertStringNotContainsString('kit:permissions:sync', preg_replace('/^.*asserts?.*$/mi', '', $source) ?? $source, 'the syncs run when migrations end, not as deploy lines');
    }
    $this->assertStringContainsString('php artisan kit:preflight', $guide, 'the blocking gate is optional and named');
    expect($ci)->toMatch('/php artisan migrate --force\s*&&\s*php artisan optimize/', 'CI runs exactly the two required lines');
    $this->assertStringContainsString('/up', $ci);
    $this->assertStringContainsString("health: '/up'", (string) file_get_contents(dirname(__DIR__).'/bootstrap/app.php'));
});
