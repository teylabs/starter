<?php

use App\Support\KitMigrationRepository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Passport;
use Laravel\Pennant\Feature;
use Tests\TestCase;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Settings\Integrations\Mcp\ReadSettings;
use Tey\Kit\Features\Settings\Integrations\Mcp\UpdateSettings;

require dirname(__DIR__).'/vendor/autoload.php';
require_once __DIR__.'/TestCase.php';

/** The template on tey/kit reproduces the 0.2.1 app exactly: schema, routes, tools, navigation, scopes, capabilities and error codes. */
final class FeatureSeamsTest extends TestCase
{
    public function test_fresh_schema_equals_the_released_schema(): void
    {
        $this->assertSame(0, app(Kernel::class)->call('migrate', ['--force' => true]));
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('The released schema fixture is a SQLite dump; on PostgreSQL only the migrations are checked.');
        }
        // Notes is an opt-in kit example: a fresh starter has no notes tables.
        $this->assertSame($this->released(withNotes: false), $this->schema());
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
        $this->assertSame([ReadSettings::class, UpdateSettings::class], $registry->mcpTools());
        $this->assertSame([['ability' => 'dashboard.read', 'label' => 'Dashboard', 'suffix' => 'dashboard', 'group' => 'workspace', 'order' => -10, 'active' => null, 'slot' => 'primary']], $registry->navigationLinks());
        $scopes = ['mcp:use' => 'Use workspace MCP', 'settings.read' => 'Read workspace settings', 'settings.write' => 'Update workspace settings'];
        $this->assertSame($scopes, $registry->tokenScopes());
        $this->assertSame(array_keys($scopes), Passport::scopeIds());
        $this->assertSame(['workspace.settings', 'workspace.dashboard'], $registry->workspaceCapabilities());
        $this->assertEqualsCanonicalizing(['workspace.settings', 'workspace.dashboard'], Feature::defined());
        $statuses = [
            // A capability that is off is absent, not forbidden: a 404 like a page that is not there.
            404 => ['workspace_not_found', 'workspace_mismatch', 'capability_unavailable'],
            403 => ['account_inactive', 'verification_required', 'admission_required', 'permission_denied', 'connection_denied', 'entitlement_required'],
            409 => ['stale_settings', 'intent_conflict', 'connection_intent_conflict', 'connection_revoked', 'connection_active'],
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
        // The route table, with the workspace pages under `/@{workspace}` (and short, without it), the chooser at /me/workspaces and the old /workspaces URLs redirecting.
        $this->assertSame(json_decode(file_get_contents(__DIR__.'/Fixtures/routes.json'), true), $routes);
    }

    /** @param list<string> $legacy the template ids the database recorded */
    private function assertKeptOnUpgrade(array $legacy): void
    {
        DB::table('workspaces')->insert(['id' => 1, 'name' => 'Legacy']);
        DB::table('notes')->insert(['workspace_id' => 1, 'body' => 'Kept']);
        $this->assertSame(0, app(Kernel::class)->call('migrate', ['--force' => true]));
        // The released schema fixture is a SQLite dump; on PostgreSQL the migrations and data are checked.
        if (DB::getDriverName() === 'sqlite') {
            // An upgraded database keeps the notes tables it had, unused.
            $this->assertSame($this->released(withNotes: true), $this->schema());
        }
        $this->assertSame(['Kept'], DB::table('notes')->pluck('body')->all());
        $ran = DB::table('migrations')->orderBy('id')->pluck('migration')->all();
        foreach ($legacy as $id) {
            $this->assertContains($id, $ran);
            foreach (KitMigrationRepository::MAP[$id] as $kit) {
                $this->assertContains($kit, $ran, $id);
            }
        }
        $kit = array_map(fn (string $path): string => basename($path, '.php'), glob(base_path('database/migrations/kit/*/*.php')));
        $this->assertCount(16, $kit);
        $this->assertSame([], array_diff($kit, $ran));
        $this->assertSame(0, app(Kernel::class)->call('migrate', ['--force' => true]));
        $this->assertSame($ran, DB::table('migrations')->orderBy('id')->pluck('migration')->all());
    }

    private function schema(): array
    {
        return json_decode(json_encode((require __DIR__.'/schema.php')()), true);
    }

    /** The 0.2.1 schema plus the media table 0.3 adds for spatie/laravel-medialibrary, and the slug the kit adds to workspaces. */
    private function released(bool $withNotes): array
    {
        $schema = json_decode(file_get_contents(__DIR__.'/Fixtures/schema-v0.2.1.json'), true);
        $this->assertArrayNotHasKey('media', $schema);
        $schema['media'] = json_decode(file_get_contents(__DIR__.'/Fixtures/schema-media.json'), true);
        if (! $withNotes) {
            unset($schema['notes'], $schema['note_intents']);
        }
        // The kit's permission-defaults table, which the sync records its applied grants in.
        // Concierge 0.8: an optional note from the inviter on an invitation.
        // Concierge 0.8.1: whether the accepting account's email differed from the invited one.
        $schema['concierge_invitations']['columns'][] = ['accepted_with_other_email', 'tinyint(1)', 1, "'0'", 0];
        $schema['concierge_invitations']['columns'][] = ['message', 'varchar', 0, null, 0];
        // The kit's own bookkeeping table: the fingerprint the deploy's role and permission syncs are gated by.
        $schema['kit_meta'] = json_decode(file_get_contents(__DIR__.'/Fixtures/schema-kit-meta.json'), true);
        // A capability switched for one person in one workspace.
        $schema['kit_capability_overrides'] = json_decode(file_get_contents(__DIR__.'/Fixtures/schema-kit-capability-overrides.json'), true);
        $schema['kit_permission_defaults'] = json_decode(file_get_contents(__DIR__.'/Fixtures/schema-kit-permission-defaults.json'), true);
        // The account pages: Fortify's two-factor columns on users (the starter's own migration) and the kit's per-user security log.
        $schema['users']['columns'] = [...$schema['users']['columns'], ['two_factor_secret', 'text', 0, null, 0], ['two_factor_recovery_codes', 'text', 0, null, 0], ['two_factor_confirmed_at', 'datetime', 0, null, 0]];
        $schema['sessions'] = ['columns' => [['id', 'varchar', 1, null, 1], ['user_id', 'integer', 0, null, 0], ['ip_address', 'varchar', 0, null, 0], ['user_agent', 'text', 0, null, 0], ['payload', 'text', 1, null, 0], ['last_activity', 'integer', 1, null, 0]], 'indexes' => [[0, 'c', ['last_activity']], [0, 'c', ['user_id']], [1, 'pk', ['id']]], 'foreign_keys' => []];
        $schema['password_reset_tokens'] = ['columns' => [['email', 'varchar', 1, null, 1], ['token', 'varchar', 1, null, 0], ['created_at', 'datetime', 0, null, 0]], 'indexes' => [[1, 'pk', ['email']]], 'foreign_keys' => []];
        $schema['kit_security_events'] = json_decode(file_get_contents(__DIR__.'/Fixtures/schema-kit-security-events.json'), true);
        // What the kit remembers about a person: timezone, onboarding steps and dismissed welcome cards.
        $schema['kit_user_state'] = json_decode(file_get_contents(__DIR__.'/Fixtures/schema-kit-user-state.json'), true);
        // The kit's slug migration: a workspace's slug is the segment after the `@` in its pages' URLs.
        $schema['workspaces']['columns'][] = ['slug', 'varchar', 0, null, 0];
        $schema['workspaces']['indexes'][] = [1, 'c', ['slug']];
        sort($schema['workspaces']['indexes']);
        ksort($schema);

        return $schema;
    }
}
