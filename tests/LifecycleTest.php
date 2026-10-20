<?php

use App\Kit\KitHost;
use Composer\InstalledVersions;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Schema;
use Tey\Kit\Blueprint;
use Tey\Kit\Catalog;
use Tey\Kit\LifecycleHost;
use Tey\Kit\Lineages;
use Tey\Mod\Maintenance\Maintenance;

require dirname(__DIR__).'/vendor/autoload.php';

/** Adopt every tey/kit feature through the released tey/kit and tey/mod in disposable checkouts of the template's host binding. */
final class LifecycleTest extends TestCase
{
    /** @var list<string> */
    private array $roots = [];

    public function createApplication()
    {
        return require __DIR__.'/application.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertContains(config('database.default') === 'sqlite' ? config('database.connections.sqlite.database') : 'pgsql', [':memory:', 'pgsql']);
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
        $this->assertSame('v0.9.1', InstalledVersions::getPrettyVersion('tey/mod'));
        $this->assertSame('v0.7.4', InstalledVersions::getPrettyVersion('tey/concierge'));
        $this->assertSame('dev-feat/shell-live', InstalledVersions::getPrettyVersion('tey/kit')); // TEMP: the local path repo
        $this->assertStringEndsWith('/vendor/tey/mod/src/Maintenance/Maintenance.php', (new ReflectionClass(Maintenance::class))->getFileName());
        $host = app(LifecycleHost::class);
        $this->assertInstanceOf(KitHost::class, $host);
        $this->assertSame('workspace-starter', $host->appId());
        $this->assertSame([], $host->bindings());
        foreach ($host->inputPaths() as $path) {
            $this->assertFileExists(base_path($path));
        }
        $this->assertSame(json_decode(file_get_contents(base_path('config/kit-executor.json')), true), $host->lifecycle()->runtime());
        $this->assertTrue(($host->operationCheck())());
        $this->assertSame(Catalog::package()->featureIds(), config('kit.features'));
    }

    public function test_adding_every_feature_writes_the_template_files_then_reruns_as_a_no_op(): void
    {
        $root = $this->checkout();
        $preview = $this->lineages()->preview($root, 'workspace-starter', $this->all(), [], KitHost::INPUTS);
        $this->assertTrue($preview['executable']);
        $this->assertSame($this->all(), array_column($preview['features'], 'feature'));
        $this->assertSame([], $this->records($root));

        $result = $this->lineages()->add($root, 'workspace-starter', $this->all(), [], KitHost::INPUTS, $preview['plan'], 'install', $this->check($root));
        $this->assertSame('completed', $result['status']);
        $this->assertSame($this->all(), $result['done']);
        $this->assertSame($this->all(), array_keys(Lineages::adopted($root, 'workspace-starter')));
        $this->assertSame($this->templateFiles(), $this->files($root));

        // A second request adopts nothing new and writes nothing.
        $records = $this->records($root);
        $again = $this->lineages()->preview($root, 'workspace-starter', $this->all(), [], KitHost::INPUTS);
        $this->assertSame(array_fill(0, 6, 'adopted'), array_column($again['features'], 'action'));
        $this->refuses(fn () => $this->lineages()->resume($root, 'workspace-starter', $this->all(), [], KitHost::INPUTS, 'install', $this->check($root)), 'already adopted');
        $this->assertSame($records, $this->records($root));
        $this->assertSame($this->templateFiles(), $this->files($root));
    }

    public function test_a_checkout_of_the_template_adopts_its_own_files_by_reviewed_claim(): void
    {
        $root = $this->checkout(withTemplateFiles: true);
        $paths = array_keys($this->templateFiles());
        $preview = $this->lineages()->preview($root, 'workspace-starter', $this->all(), [], KitHost::INPUTS);
        $this->assertFalse($preview['executable']);
        $this->assertEqualsCanonicalizing($paths, $preview['claimable']);
        $this->refuses(fn () => $this->lineages()->add($root, 'workspace-starter', $this->all(), [], KitHost::INPUTS, $preview['plan'], null, $this->check($root)), 'not executable');
        $this->assertSame([], $this->records($root));

        $claimed = $this->lineages()->preview($root, 'workspace-starter', $this->all(), [], KitHost::INPUTS, $paths);
        $this->assertTrue($claimed['executable']);
        $result = $this->lineages()->add($root, 'workspace-starter', $this->all(), [], KitHost::INPUTS, $claimed['plan'], 'adopt', $this->check($root), $paths);
        $this->assertSame('completed', $result['status']);
        $this->assertSame($this->all(), array_keys(Lineages::adopted($root, 'workspace-starter')));
        $this->assertSame($this->templateFiles(), $this->files($root));
    }

    public function test_an_edited_template_file_refuses_its_claim(): void
    {
        $root = $this->checkout(withTemplateFiles: true);
        $paths = array_keys($this->templateFiles());
        $edited = 'database/migrations/kit/mcp/0001_01_02_000600_create_kit_mcp_tables.php';
        file_put_contents($root.'/'.$edited, "<?php // customised\n");
        $preview = $this->lineages()->preview($root, 'workspace-starter', $this->all(), [], KitHost::INPUTS, $paths);
        $this->assertFalse($preview['executable']);
        $this->refuses(fn () => $this->lineages()->add($root, 'workspace-starter', $this->all(), [], KitHost::INPUTS, $preview['plan'], null, $this->check($root), $paths), 'not executable');
        $this->assertSame("<?php // customised\n", file_get_contents($root.'/'.$edited));
        $this->assertSame([], $this->records($root));
    }

    public function test_operation_check_fails_closed(): void
    {
        $root = $this->checkout();
        $preview = $this->lineages()->preview($root, 'workspace-starter', $this->all(), [], KitHost::INPUTS);

        // A closure declared outside the bound host-operations file is rejected before effects.
        $this->refuses(fn () => $this->lineages()->add($root, 'workspace-starter', $this->all(), [], KitHost::INPUTS, $preview['plan'], null, fn () => true), 'host-operations');
        $this->assertSame([], $this->records($root));
        $this->assertSame([], $this->files($root));

        // Without the host schema the check fails, so the first feature stays pending and nothing is recorded.
        Schema::rename('users', 'users_moved');
        $this->assertFalse(($this->check($root))());
        $result = $this->lineages()->add($root, 'workspace-starter', $this->all(), [], KitHost::INPUTS, $preview['plan'], 'install', $this->check($root));
        $this->assertNotSame('completed', $result['status']);
        $this->assertSame('core', $result['failed']);
        $this->assertSame([], array_keys(Lineages::adopted($root, 'workspace-starter')));

        // A Mod executor that differs from the committed provenance refuses at plan time.
        $root = $this->checkout();
        file_put_contents($root.'/config/kit-executor.json', '{"installed_mod_version":"v0.9.0"}');
        $this->refuses(fn () => $this->lineages()->preview($root, 'workspace-starter', $this->all(), [], KitHost::INPUTS), 'Executor provenance');
    }

    public function test_kit_add_command(): void
    {
        $root = $this->checkout();
        $base = $this->app->basePath();
        $kernel = app(Kernel::class);
        $features = ['features' => $this->all()];
        try {
            // KitHost reads its operation check from the checkout it runs in.
            $this->app->setBasePath($root);
            $this->assertSame(0, $kernel->call('kit:add', $features + ['--json' => true]));
            $preview = json_decode(trim($kernel->output()), true, flags: JSON_THROW_ON_ERROR);
            $this->assertNotSame(0, $kernel->call('kit:add', $features + ['--apply' => true, '--expected-plan' => 'sha256:wrong']));
            $this->assertSame([], $this->records($root));
            $this->assertSame(0, $kernel->call('kit:add', $features + ['--apply' => true, '--expected-plan' => $preview['plan']]));
            $this->assertCount(6, $this->records($root));
            $this->assertSame($this->templateFiles(), $this->files($root));
        } finally {
            $this->app->setBasePath($base);
        }
    }

    /** A disposable checkout holding copies of the template's real kit host inputs, and optionally its feature files. */
    private function checkout(bool $withTemplateFiles = false): string
    {
        $root = sys_get_temp_dir().'/starter-kit-'.bin2hex(random_bytes(8));
        foreach (KitHost::INPUTS as $path) {
            if (! is_dir(dirname($root.'/'.$path))) {
                mkdir(dirname($root.'/'.$path), 0700, true);
            }
            copy(base_path($path), $root.'/'.$path);
        }
        foreach ($withTemplateFiles ? $this->templateFiles() : [] as $path => $bytes) {
            if (! is_dir(dirname($root.'/'.$path))) {
                mkdir(dirname($root.'/'.$path), 0700, true);
            }
            file_put_contents($root.'/'.$path, $bytes);
        }
        $root = realpath($root);
        $this->roots[] = $root;

        return $root;
    }

    private function lineages(): Lineages
    {
        return new Lineages(app(LifecycleHost::class)->lifecycle(), Catalog::package());
    }

    /** @return list<string> */
    private function all(): array
    {
        return Catalog::package()->featureIds();
    }

    /** The checkout's own operation check, declared in its host-operations input. */
    private function check(string $root): Closure
    {
        return require $root.'/'.KitHost::INPUTS['host-operations'];
    }

    /** @return array<string, string> path => bytes of the template's feature files, which are exactly what the catalog renders */
    private function templateFiles(): array
    {
        $rendered = array_merge(...array_map(fn (string $id): array => Blueprint::lineage($id, Catalog::package())['files'], $this->all()));
        ksort($rendered);
        $files = $this->files(base_path());
        $this->assertSame($rendered, $files);

        return $files;
    }

    /** @return array<string, string> path => bytes under the features' owned paths */
    private function files(string $root): array
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
    private function records(string $root): array
    {
        return glob($root.'/.mod-maintenance/installed/*.json') ?: [];
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
