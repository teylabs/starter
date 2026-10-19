<?php

use App\Support\StarterLifecycle;
use Composer\InstalledVersions;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Schema;
use Tey\Mod\Maintenance\Maintenance;
use Tey\StarterBlueprint\Blueprint;
use Tey\StarterBlueprint\LifecycleHost;

require dirname(__DIR__).'/vendor/autoload.php';

/** Install and upgrade through the released tey/mod and tey/starter-blueprint in disposable checkouts. */
final class LifecycleTest extends TestCase
{
    private const POLICY = 'app/Support/Starter/BlueprintPolicy.php';

    private const DEFAULTS = 'app/Support/Starter/Defaults/BlueprintDefaults.php';

    private const RECORD = '.mod-maintenance/installed.json';

    /** @var list<string> */
    private array $roots = [];

    public function createApplication()
    {
        require __DIR__.'/environment.php';

        return require dirname(__DIR__).'/bootstrap/app.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame(0, app(Kernel::class)->call('migrate', ['--force' => true]));
    }

    protected function tearDown(): void
    {
        foreach ($this->roots as $root) {
            (new Filesystem)->deleteDirectory($root);
        }
        parent::tearDown();
    }

    public function test_released_packages_and_trusted_host_binding(): void
    {
        $this->assertSame('v0.8.0', InstalledVersions::getPrettyVersion('tey/mod'));
        $this->assertSame('v0.7.2', InstalledVersions::getPrettyVersion('tey/concierge'));
        $this->assertSame('v0.1.0', InstalledVersions::getPrettyVersion('tey/starter-blueprint'));
        $this->assertStringEndsWith('/vendor/tey/mod/src/Maintenance/Maintenance.php', (new ReflectionClass(Maintenance::class))->getFileName());
        $host = app(LifecycleHost::class);
        $this->assertInstanceOf(StarterLifecycle::class, $host);
        $this->assertSame('tey-starter', $host->appId());
        $this->assertSame(['frontend' => 'inertia-vue'], $host->bindings());
        foreach ($host->inputPaths() as $path) {
            $this->assertFileExists(base_path($path));
        }
        $this->assertSame(json_decode(file_get_contents(base_path('config/lifecycle-executor.json')), true), $host->lifecycle()->runtime());
        $this->assertTrue(($host->operationCheck())());
        $this->assertSame(Blueprint::all()->sourceReference(), Blueprint::features(['defaults', 'foundation'])->sourceReference());
    }

    public function test_plan_apply_install_then_no_op_rerun(): void
    {
        [$root, $host] = $this->checkout();
        $lifecycle = $host->lifecycle();
        $all = Blueprint::all();
        $plan = $lifecycle->plan($root, $host->appId(), null, $all, $host->bindings(), $host->inputPaths());
        $this->assertTrue($plan->executable());
        $this->assertNull($this->record($root));
        $this->assertFileDoesNotExist($root.'/'.self::POLICY);

        $result = $lifecycle->apply($plan, null, $all, $host->inputPaths(), 'install', $host->operationCheck(), true);
        $this->assertSame('completed', $result['status']);
        $this->assertSame(['installed_mod_version' => 'v0.8.0'], $result['runtime']);
        $installed = json_decode($this->record($root), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(['foundation', 'defaults'], $installed['artifact']['metadata']['blueprint_features']);
        $this->assertSame([], $installed['artifact']['metadata']['applied_migration_ids']);
        $this->assertSame($all->snapshot()['files'][self::POLICY], file_get_contents($root.'/'.self::POLICY));
        $this->assertSame($all->snapshot()['files'][self::DEFAULTS], file_get_contents($root.'/'.self::DEFAULTS));

        // A cold retry of the completed intent reads the receipt and never reruns the host check.
        $record = $this->record($root);
        $files = $this->files($root);
        $again = $lifecycle->resume($root, $host->appId(), null, $all, $host->bindings(), $host->inputPaths(), 'install', $host->operationCheck(), true);
        $this->assertTrue($again['no_op']);
        $this->assertSame('completed', $again['status']);
        $this->assertSame($record, $this->record($root));
        $this->assertSame($files, $this->files($root));
    }

    public function test_upgrade_preserves_local_edits(): void
    {
        // Mod 0.8 treats any edit to an owned generated file as a conflict; local edits live in host-owned code.
        foreach ([[Blueprint::features(['foundation']), Blueprint::all()], [Blueprint::release('C'), Blueprint::release('D')]] as [$original, $target]) {
            [$root, $host] = $this->checkout();
            $this->install($root, $host, null, $original, 'install');
            mkdir($root.'/app/Domain', 0700, true);
            file_put_contents($root.'/app/Domain/Local.php', '<?php // host-owned');
            file_put_contents($root.'/config/branding.php', "<?php return ['name' => 'Local'];");
            $before = $this->record($root);

            $this->install($root, $host, $original, $target, 'upgrade');
            $this->assertSame('<?php // host-owned', file_get_contents($root.'/app/Domain/Local.php'));
            $this->assertSame("<?php return ['name' => 'Local'];", file_get_contents($root.'/config/branding.php'));
            $this->assertSame($target->snapshot()['files'][self::POLICY], file_get_contents($root.'/'.self::POLICY));
            $this->assertSame($target->snapshot()['files'][self::DEFAULTS], file_get_contents($root.'/'.self::DEFAULTS));
            $this->assertNotSame($before, $this->record($root));
            $this->assertSame($target->manifest['version'], json_decode($this->record($root), true)['artifact']['version']);
        }
    }

    public function test_conflicting_local_edit_refuses_upgrade(): void
    {
        [$root, $host] = $this->checkout();
        $this->install($root, $host, null, Blueprint::release('C'), 'install');
        file_put_contents($root.'/'.self::POLICY, '<?php // customised');
        $record = $this->record($root);

        $lifecycle = $host->lifecycle();
        $c = Blueprint::release('C');
        $d = Blueprint::release('D');
        $plan = $lifecycle->plan($root, $host->appId(), $c, $d, $host->bindings(), $host->inputPaths());
        $this->assertFalse($plan->executable());
        $this->refuses(fn () => $lifecycle->apply($plan, $c, $d, $host->inputPaths(), 'upgrade', $host->operationCheck(), true));
        $this->assertSame('<?php // customised', file_get_contents($root.'/'.self::POLICY));
        $this->assertSame($c->snapshot()['files'][self::DEFAULTS], file_get_contents($root.'/'.self::DEFAULTS));
        $this->assertSame($record, $this->record($root));
    }

    public function test_stale_or_mismatched_original_refuses(): void
    {
        [$root, $host] = $this->checkout();
        $this->install($root, $host, null, Blueprint::release('C'), 'install');
        $record = $this->record($root);
        $files = $this->files($root);
        $lifecycle = $host->lifecycle();
        $d = Blueprint::release('D');
        foreach ([Blueprint::release('A'), Blueprint::release('B')] as $original) {
            $this->refuses(fn () => $lifecycle->plan($root, $host->appId(), $original, $d, $host->bindings(), $host->inputPaths()), 'exactly the installed artifact');
        }
        // Same release, other bindings: not the installed artifact either.
        $this->refuses(fn () => $lifecycle->plan($root, $host->appId(), Blueprint::release('C'), $d, ['frontend' => 'other'], $host->inputPaths()), 'exactly the installed artifact');
        // A reviewed plan goes stale when a bound input changes before apply.
        $plan = $lifecycle->plan($root, $host->appId(), Blueprint::release('C'), $d, $host->bindings(), $host->inputPaths());
        file_put_contents($root.'/composer.lock', '{}');
        $this->refuses(fn () => $lifecycle->apply($plan, Blueprint::release('C'), $d, $host->inputPaths(), 'stale', $host->operationCheck(), true));
        $this->assertSame($record, $this->record($root));
        $this->assertSame($files, $this->files($root));

        // A feature install names its original features exactly: a smaller selection is not what is installed.
        [$root, $host] = $this->checkout();
        $this->install($root, $host, null, Blueprint::all(), 'install');
        $record = $this->record($root);
        $this->refuses(fn () => $lifecycle->plan($root, $host->appId(), Blueprint::features(['foundation']), Blueprint::all(), $host->bindings(), $host->inputPaths()), 'exactly the installed artifact');
        $this->refuses(fn () => $lifecycle->plan($root, $host->appId(), Blueprint::release('C'), Blueprint::all(), $host->bindings(), $host->inputPaths()), 'cannot be upgraded');
        $this->assertSame($record, $this->record($root));
    }

    public function test_operation_check_fails_closed(): void
    {
        [$root, $host] = $this->checkout();
        $all = Blueprint::all();
        $lifecycle = $host->lifecycle();
        Schema::drop('notes');
        $this->assertFalse(($host->operationCheck())());
        $plan = $lifecycle->plan($root, $host->appId(), null, $all, $host->bindings(), $host->inputPaths());
        $result = $lifecycle->apply($plan, null, $all, $host->inputPaths(), 'install', $host->operationCheck(), true);
        $this->assertSame('verification_failed', $result['status']);
        $this->assertNull($this->record($root));

        // A closure declared outside the bound host-operations file is rejected before effects.
        [$root, $host] = $this->checkout();
        $plan = $lifecycle->plan($root, $host->appId(), null, $all, $host->bindings(), $host->inputPaths());
        $this->refuses(fn () => $lifecycle->apply($plan, null, $all, $host->inputPaths(), 'install', fn () => true, true), 'host-operations');
        $this->assertNull($this->record($root));
        $this->assertFileDoesNotExist($root.'/'.self::POLICY);

        // A Mod executor that differs from the committed provenance refuses at plan time.
        file_put_contents($root.'/config/lifecycle-executor.json', '{"installed_mod_version":"v0.8.1"}');
        $this->refuses(fn () => $lifecycle->plan($root, $host->appId(), null, $all, $host->bindings(), $host->inputPaths()), 'Executor provenance');
    }

    public function test_starter_maintain_command(): void
    {
        [$root, $host] = $this->checkout();
        $base = $this->app->basePath();
        $previous = app(LifecycleHost::class);
        $kernel = app(Kernel::class);
        try {
            $this->app->setBasePath($root);
            $this->app->instance(LifecycleHost::class, $host);
            $this->assertSame(0, $kernel->call('starter:maintain', ['action' => 'plan', '--features' => 'foundation,defaults']));
            $preview = json_decode(trim($kernel->output()), true, flags: JSON_THROW_ON_ERROR);
            $this->assertNotSame(0, $kernel->call('starter:maintain', ['action' => 'apply', '--features' => 'foundation,defaults', '--intent' => 'install', '--exclusive-checkout' => true, '--expected-plan' => 'sha256:wrong']));
            $this->assertNull($this->record($root));
            $this->assertNotSame(0, $kernel->call('starter:maintain', ['action' => 'apply', '--features' => 'foundation,defaults', '--intent' => 'install', '--expected-plan' => $preview['plan']]));
            $this->assertNull($this->record($root));
            $this->assertSame(0, $kernel->call('starter:maintain', ['action' => 'apply', '--features' => 'foundation,defaults', '--intent' => 'install', '--exclusive-checkout' => true, '--expected-plan' => $preview['plan']]));
            $this->assertNotNull($this->record($root));
            $this->assertSame(0, $kernel->call('starter:maintain', ['action' => 'resume', '--features' => 'foundation,defaults', '--intent' => 'install', '--exclusive-checkout' => true]));
            $this->assertTrue(json_decode(trim($kernel->output()), true)['no_op']);
            $this->assertNotSame(0, $kernel->call('starter:maintain', ['action' => 'plan', '--original-features' => 'foundation', '--features' => 'foundation,defaults']));
        } finally {
            $this->app->setBasePath($base);
            $this->app->instance(LifecycleHost::class, $previous);
        }
    }

    /** A disposable checkout holding copies of the starter's real lifecycle inputs. */
    private function checkout(): array
    {
        $root = sys_get_temp_dir().'/starter-lifecycle-'.bin2hex(random_bytes(8));
        foreach (['app/Support', 'bootstrap/cache', 'storage/logs', 'config'] as $dir) {
            mkdir($root.'/'.$dir, 0700, true);
        }
        foreach (StarterLifecycle::INPUTS as $path) {
            copy(base_path($path), $root.'/'.$path);
        }
        $root = realpath($root);
        $this->roots[] = $root;

        return [$root, new StarterLifecycle($root)];
    }

    private function install(string $root, LifecycleHost $host, ?Blueprint $original, Blueprint $target, string $intent): void
    {
        $lifecycle = $host->lifecycle();
        $plan = $lifecycle->plan($root, $host->appId(), $original, $target, $host->bindings(), $host->inputPaths());
        $this->assertTrue($plan->executable());
        $this->assertSame('completed', $lifecycle->apply($plan, $original, $target, $host->inputPaths(), $intent, $host->operationCheck(), true)['status']);
    }

    private function record(string $root): ?string
    {
        return is_file($root.'/'.self::RECORD) ? file_get_contents($root.'/'.self::RECORD) : null;
    }

    /** @return array<string, string> */
    private function files(string $root): array
    {
        $files = [];
        foreach ([self::POLICY, self::DEFAULTS] as $path) {
            $files[$path] = is_file($root.'/'.$path) ? hash_file('sha256', $root.'/'.$path) : 'absent';
        }

        return $files;
    }

    private function refuses(Closure $operation, string $message = ''): void
    {
        try {
            $operation();
        } catch (Throwable $error) {
            $this->assertStringContainsString($message, $error->getMessage());

            return;
        }
        $this->fail('Expected a refusal.');
    }
}
