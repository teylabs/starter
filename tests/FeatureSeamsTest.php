<?php

use App\Mcp\CreateNote;
use App\Mcp\DeleteNote;
use App\Mcp\ListNotes;
use App\Mcp\ReadNote;
use App\Mcp\ReadSettings;
use App\Mcp\UpdateNote;
use App\Mcp\UpdateSettings;
use App\Support\StarterRegistry;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Passport;
use Laravel\Pennant\Feature;

require dirname(__DIR__).'/vendor/autoload.php';

/** The per-feature seams reproduce the 0.2.1 app exactly: schema, routes, tools, navigation, scopes, capabilities and error codes. */
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

    public function test_databases_migrated_before_the_split_keep_their_schema_and_data(): void
    {
        $this->assertSame(0, app(Kernel::class)->call('migrate', ['--force' => true, '--path' => 'database/migrations/0001_01_01_000000_create_starter_identity_tables.php']));
        $this->assertSame(0, app(Kernel::class)->call('migrate', ['--force' => true, '--path' => 'tests/Fixtures/legacy-migrations/create_starter_domain_tables.php']));
        DB::table('workspaces')->insert(['id' => 1, 'name' => 'Legacy']);
        DB::table('notes')->insert(['workspace_id' => 1, 'body' => 'Kept']);
        $this->assertSame(0, app(Kernel::class)->call('migrate', ['--force' => true]));
        $this->assertSame($this->released(), $this->schema());
        $this->assertSame(['Kept'], DB::table('notes')->pluck('body')->all());
        foreach (['access', 'core', 'mcp', 'notes', 'settings', 'workspaces'] as $feature) {
            $this->assertTrue(DB::table('migrations')->where('migration', 'create_starter_'.$feature.'_tables')->exists(), $feature);
        }
    }

    public function test_registry_contributions_match_the_released_wiring(): void
    {
        $registry = app(StarterRegistry::class);
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
        $this->assertSame(json_decode(file_get_contents(__DIR__.'/Fixtures/routes-v0.2.1.json'), true), $routes);
    }

    private function released(): array
    {
        return json_decode(file_get_contents(__DIR__.'/Fixtures/schema-v0.2.1.json'), true);
    }

    private function schema(): array
    {
        return json_decode(json_encode((require __DIR__.'/schema.php')()), true);
    }
}
