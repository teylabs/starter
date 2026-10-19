<?php

use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Notes\Integrations\Mcp\CreateNote;
use Tey\Kit\Features\Notes\Integrations\Mcp\DeleteNote;
use Tey\Kit\Features\Notes\Integrations\Mcp\ListNotes;
use Tey\Kit\Features\Notes\Integrations\Mcp\ReadNote;
use Tey\Kit\Features\Notes\Integrations\Mcp\UpdateNote;
use Tey\Kit\Features\Settings\Integrations\Mcp\ReadSettings;
use Tey\Kit\Features\Settings\Integrations\Mcp\UpdateSettings;
use App\Support\KitMigrationRepository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Passport;
use Laravel\Pennant\Feature;

require dirname(__DIR__).'/vendor/autoload.php';

/** The template on tey/kit reproduces the 0.2.1 app exactly: schema, routes, tools, navigation, scopes, capabilities and error codes. */
final class FeatureSeamsTest extends TestCase
{
    public function createApplication()
    {
        require __DIR__.'/environment.php';

        return require dirname(__DIR__).'/bootstrap/app.php';
    }

    public function test_fresh_schema_equals_the_released_schema(): void
    {
        $this->assertSame(0, app(Kernel::class)->call('migrate', ['--force' => true]));
        $this->assertSame($this->released(), $this->schema());
    }

    public function test_databases_migrated_by_0_2_move_onto_the_kit_migrations_with_their_schema_and_data(): void
    {
        $this->assertSame(0, app(Kernel::class)->call('migrate', ['--force' => true, '--path' => 'tests/Fixtures/v0.2.1-migrations']));
        $this->assertKeptOnUpgrade(array_keys(array_diff_key(KitMigrationRepository::MAP, ['create_starter_domain_tables' => true])));
    }

    public function test_databases_migrated_before_the_split_keep_their_schema_and_data(): void
    {
        $paths = ['0001_01_01_000000_create_starter_identity_tables', '0001_01_01_000001_create_permission_tables', '0001_01_01_000002_create_settings_table'];
        $this->assertSame(0, app(Kernel::class)->call('migrate', ['--force' => true, '--path' => [...array_map(fn (string $id): string => 'tests/Fixtures/v0.2.1-migrations/'.$id.'.php', $paths), 'tests/Fixtures/legacy-migrations/create_starter_domain_tables.php']]));
        $this->assertKeptOnUpgrade([...$paths, 'create_starter_domain_tables']);
    }

    public function test_registry_contributions_match_the_released_wiring(): void
    {
        $registry = app(Registry::class);
        $this->assertSame([ReadSettings::class, UpdateSettings::class, ListNotes::class, ReadNote::class, CreateNote::class, UpdateNote::class, DeleteNote::class], $registry->mcpTools());
        $this->assertSame([['ability' => 'settings.read', 'label' => 'Settings', 'suffix' => 'settings'], ['ability' => 'notes.read', 'label' => 'Notes', 'suffix' => 'notes']], $registry->navigationLinks());
        $scopes = ['mcp:use' => 'Use workspace MCP', 'settings.read' => 'Read workspace settings', 'settings.write' => 'Update workspace settings', 'notes.read' => 'Read workspace notes', 'notes.write' => 'Create, update and delete workspace notes'];
        $this->assertSame($scopes, $registry->tokenScopes());
        $this->assertSame(array_keys($scopes), Passport::scopeIds());
        $this->assertSame(['workspace.settings', 'workspace.notes'], $registry->workspaceCapabilities());
        $this->assertEqualsCanonicalizing(['workspace.settings', 'workspace.notes'], Feature::defined());
        $statuses = [
            404 => ['workspace_not_found', 'workspace_mismatch', 'note_not_found'],
            403 => ['account_inactive', 'verification_required', 'admission_required', 'permission_denied', 'connection_denied', 'capability_unavailable', 'entitlement_required'],
            409 => ['stale_settings', 'intent_conflict', 'stale_note', 'note_intent_conflict', 'connection_intent_conflict', 'connection_revoked', 'connection_active'],
            422 => ['unknown_arguments'],
        ];
        foreach ($statuses as $status => $codes) {
            foreach ($codes as $code) {
                $this->assertSame($status, $registry->status($code), $code);
            }
        }
        $this->assertNull($registry->status('something_else'));

        $routes = [];
        foreach (Route::getRoutes() as $route) {
            $routes[] = implode('|', $route->methods()).' '.$route->uri().' '.implode(',', $route->gatherMiddleware());
        }
        sort($routes);
        // 0.3 moved the starter's classes into tey/kit; nothing else about the routes changed.
        $released = str_replace('App\\\\Starter\\\\', 'Tey\\\\Kit\\\\Features\\\\', file_get_contents(__DIR__.'/Fixtures/routes-v0.2.1.json'));
        $this->assertSame(json_decode($released, true), $routes);
    }

    /** @param list<string> $legacy the template ids the database recorded */
    private function assertKeptOnUpgrade(array $legacy): void
    {
        DB::table('workspaces')->insert(['id' => 1, 'name' => 'Legacy']);
        DB::table('notes')->insert(['workspace_id' => 1, 'body' => 'Kept']);
        $this->assertSame(0, app(Kernel::class)->call('migrate', ['--force' => true]));
        $this->assertSame($this->released(), $this->schema());
        $this->assertSame(['Kept'], DB::table('notes')->pluck('body')->all());
        $ran = DB::table('migrations')->pluck('migration')->all();
        foreach ($legacy as $id) {
            $this->assertContains($id, $ran);
            foreach (KitMigrationRepository::MAP[$id] as $kit) {
                $this->assertContains($kit, $ran, $id);
            }
        }
        $kit = array_map(fn (string $path): string => basename($path, '.php'), glob(base_path('database/migrations/kit/*/*.php')));
        $this->assertCount(9, $kit);
        $this->assertSame([], array_diff($kit, $ran));
        $this->assertSame(0, app(Kernel::class)->call('migrate', ['--force' => true]));
        $this->assertSame($ran, DB::table('migrations')->pluck('migration')->all());
    }

    /** The 0.2.1 schema plus the media table 0.3 adds for spatie/laravel-medialibrary. */
    private function released(): array
    {
        $schema = json_decode(file_get_contents(__DIR__.'/Fixtures/schema-v0.2.1.json'), true);
        $this->assertArrayNotHasKey('media', $schema);
        $schema['media'] = json_decode(file_get_contents(__DIR__.'/Fixtures/schema-media.json'), true);
        ksort($schema);

        return $schema;
    }

    private function schema(): array
    {
        return json_decode(json_encode((require __DIR__.'/schema.php')()), true);
    }
}
