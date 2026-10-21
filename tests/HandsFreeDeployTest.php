<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tey\Kit\Brand;
use Tey\Kit\Features\Core\Registry;

require dirname(__DIR__).'/vendor/autoload.php';

/**
 * A hands-free deploy: `php artisan migrate --force` and `php artisan optimize`, nothing else. The role and permission syncs run when migrations end (or when there was nothing to
 * migrate), gated by a fingerprint of the catalog; /up is the monitoring check.
 */
final class HandsFreeDeployTest extends TestCase
{
    public function createApplication()
    {
        return require __DIR__.'/application.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        // The syncs skip under unit tests unless a test opts in; these tests do.
        config(['kit.deploy.sync_in_tests' => true]);
        Cache::flush();
    }

    private function migrate(string $command = 'migrate', array $options = []): int
    {
        return app(Kernel::class)->call($command, ['--force' => true, ...$options]);
    }

    private function fingerprint(): ?string
    {
        return DB::table('kit_meta')->where('key', 'catalog_fingerprint')->value('value');
    }

    /** @return list<string> the data-changing statements a callback ran */
    private function writes(callable $run): array
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

    public function test_migrating_a_fresh_database_syncs_roles_and_permissions_and_a_second_migrate_writes_nothing(): void
    {
        $this->assertSame(0, $this->migrate());
        $this->assertEqualsCanonicalizing(['owner', 'admin', 'viewer'], Role::query()->pluck('name')->all(), 'the role sync ran, before the permission sync needed it');
        $this->assertGreaterThan(0, Permission::query()->count());
        $this->assertNotNull($this->fingerprint());
        $grants = DB::table('role_has_permissions')->count();
        $this->assertGreaterThan(0, $grants, 'the default grants were made');
        // A second migrate has nothing to migrate and an unchanged catalog: no writes at all.
        $this->assertSame([], $this->writes(fn () => $this->assertSame(0, $this->migrate())));
        $this->assertSame($grants, DB::table('role_has_permissions')->count());
    }

    public function test_a_changed_catalog_is_synced_by_a_migrate_that_has_nothing_to_migrate(): void
    {
        $this->assertSame(0, $this->migrate());
        $before = $this->fingerprint();
        $this->assertFalse(Permission::query()->where('name', 'reports.export')->exists());
        // A release adds a permission and no migration: the deploy's migrate still picks it up.
        app(Registry::class)->permission('reports.export', ['owner', 'admin']);
        $this->assertSame(0, $this->migrate());
        $this->assertTrue(Permission::query()->where('name', 'reports.export')->exists());
        $this->assertNotSame($before, $this->fingerprint());
        $this->assertTrue(Role::findByName('admin')->hasPermissionTo('reports.export'));
    }

    public function test_a_rollback_does_not_sync(): void
    {
        $this->assertSame(0, $this->migrate());
        DB::table('kit_meta')->where('key', 'catalog_fingerprint')->delete();
        Role::findByName('viewer')->delete();
        $this->assertSame(0, app(Kernel::class)->call('migrate:rollback', ['--force' => true, '--step' => 1]));
        $this->assertFalse(Role::query()->where('name', 'viewer')->exists(), 'a rollback leaves the roles alone');
        $this->assertNull($this->fingerprint());
    }

    public function test_the_syncs_skip_under_unit_tests_unless_a_test_opts_in(): void
    {
        config(['kit.deploy.sync_in_tests' => false]);
        $this->assertSame(0, $this->migrate());
        $this->assertSame(0, Role::query()->count());
        $this->assertNull($this->fingerprint());
    }

    public function test_a_failing_sync_makes_migrate_fail_loudly(): void
    {
        config(['concierge.roles' => 'not-an-array']);
        $this->expectException(Throwable::class);
        $this->migrate();
    }

    /** Every brand key set to a real value, as a deployed app has. */
    private function realBrand(): void
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

    public function test_up_is_healthy_and_fails_for_a_missing_key_or_a_brand_placeholder_in_production(): void
    {
        $this->assertSame(0, $this->migrate());
        $this->realBrand();
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
    }

    public function test_the_deploy_guide_and_ci_use_the_standard_lines_and_no_deploy_command(): void
    {
        $guide = (string) file_get_contents(dirname(__DIR__).'/docs/deploy.md');
        $ci = (string) file_get_contents(dirname(__DIR__).'/.github/workflows/ci.yml');
        foreach ([$guide, $ci] as $source) {
            $this->assertStringContainsString('php artisan migrate --force', $source);
            $this->assertStringContainsString('php artisan optimize', $source);
            $this->assertStringNotContainsString('kit:deploy', $source);
            $this->assertStringNotContainsString('kit:permissions:sync', preg_replace('/^.*asserts?.*$/mi', '', $source) ?? $source, 'the syncs run when migrations end, not as deploy lines');
        }
        $this->assertStringContainsString('php artisan kit:preflight', $guide, 'the blocking gate is optional and named');
        $this->assertMatchesRegularExpression('/php artisan migrate --force\s*&&\s*php artisan optimize/', $ci, 'CI runs exactly the two required lines');
        $this->assertStringContainsString('/up', $ci);
        $this->assertStringContainsString("health: '/up'", (string) file_get_contents(dirname(__DIR__).'/bootstrap/app.php'));
    }
}
