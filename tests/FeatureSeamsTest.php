<?php

use App\Support\KitMigrationRepository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Passport;
use Laravel\Pennant\Feature;
use Tey\Kit\Dashboard\ReadDashboard;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Notes\Integrations\Mcp\DeleteNote;
use Tey\Kit\Features\Notes\Integrations\Mcp\ListNotes;
use Tey\Kit\Features\Notes\Integrations\Mcp\ReadNote;
use Tey\Kit\Features\Notes\Integrations\Mcp\WriteNote;
use Tey\Kit\Features\Settings\Integrations\Mcp\ReadSettings;
use Tey\Kit\Features\Settings\Integrations\Mcp\UpdateSettings;
use Tey\Kit\Search\SearchWorkspace;

test('fresh schema equals the released schema', function () {
    expect(app(Kernel::class)->call('migrate', ['--force' => true]))->toBe(0);
    if (DB::getDriverName() !== 'sqlite') {
        $this->markTestSkipped('The released schema fixture is a SQLite dump; on PostgreSQL only the migrations are checked.');
    }

    // A fresh starter has the notes feature's kit_notes tables, and none of the legacy notes tables an earlier kit release made.
    // A fresh install has core's index on audit_events.workspace_id under the workspaces foreign key; the 0.2.1 dump had the key alone.
    $released = featureSeamsReleased(withNotes: false);
    $released['audit_events']['indexes'] = [[0, 'c', ['workspace_id']]];
    expect(featureSeamsSchema())->toBe($released);
});
test('databases migrated by 0 2 move onto the kit migrations with their schema and data', function () {
    expect(app(Kernel::class)->call('migrate', ['--force' => true, '--path' => 'tests/Fixtures/v0.2.1-migrations']))->toBe(0);
    featureSeamsAssertKeptOnUpgrade(array_keys(array_diff_key(KitMigrationRepository::MAP, ['create_starter_domain_tables' => true])));
});
test('databases migrated before the split keep their schema and data', function () {
    $paths = ['0001_01_01_000000_create_starter_identity_tables', '0001_01_01_000001_create_permission_tables', '0001_01_01_000002_create_settings_table'];
    expect(app(Kernel::class)->call('migrate', ['--force' => true, '--path' => [...array_map(fn (string $id): string => 'tests/Fixtures/v0.2.1-migrations/'.$id.'.php', $paths), 'tests/Fixtures/legacy-migrations/create_starter_domain_tables.php']]))->toBe(0);
    featureSeamsAssertKeptOnUpgrade([...$paths, 'create_starter_domain_tables']);
});
test('registry contributions match the released wiring', function () {
    $registry = app(Registry::class);
    expect($registry->mcpTools())->toBe([ReadSettings::class, UpdateSettings::class, ListNotes::class, ReadNote::class, WriteNote::class, DeleteNote::class, ReadDashboard::class, SearchWorkspace::class]);
    expect($registry->navigationLinks())->toBe([['ability' => 'dashboard.read', 'label' => 'Dashboard', 'suffix' => '', 'group' => 'workspace', 'order' => -10, 'active' => '', 'slot' => 'primary'], ['ability' => 'notes.read', 'label' => 'Notes', 'suffix' => 'notes', 'group' => 'workspace', 'order' => 10, 'active' => null, 'slot' => 'primary']]);
    $scopes = ['mcp:use' => 'Act as you in the workspaces you approve, with what your role there allows', 'mcp:act' => 'Act as you in the workspaces you approve', 'settings.read' => 'Read workspace settings', 'settings.write' => 'Update workspace settings', 'dashboard.read' => 'Read the workspace dashboard', 'search.read' => 'Search the workspace', 'notes.read' => 'Read workspace notes', 'notes.write' => 'Create, change and delete your workspace notes'];
    expect($registry->tokenScopes())->toBe($scopes);
    expect(Passport::scopeIds())->toBe(array_keys($scopes));
    expect($registry->workspaceCapabilities())->toBe(['workspace.settings', 'workspace.dashboard', 'workspace.search', 'workspace.notes']);
    expect(Feature::defined())->toEqualCanonicalizing(['workspace.settings', 'workspace.dashboard', 'workspace.search', 'workspace.notes']);
    $statuses = [
        // A capability that is off is absent, not forbidden: a 404 like a page that is not there.
        404 => ['workspace_not_found', 'workspace_mismatch', 'capability_unavailable'],
        403 => ['account_inactive', 'verification_required', 'admission_required', 'permission_denied', 'connection_denied', 'entitlement_required'],
        409 => ['stale_settings', 'intent_conflict', 'connection_intent_conflict', 'connection_revoked', 'connection_active'],
        422 => ['unknown_arguments'],
        429 => ['rate_limited'],
    ];
    foreach ($statuses as $status => $codes) {
        foreach ($codes as $code) {
            expect($registry->status($code))->toBe($status, $code);
        }
    }
    expect($registry->status('something_else'))->toBeNull();

    $routes = [];
    foreach (Route::getRoutes() as $route) {
        $routes[] = implode('|', $route->methods()).' '.$route->uri().' '.implode(',', $route->gatherMiddleware());
    }
    sort($routes);

    // The route table, with the workspace pages under `/@{workspace}` (and short, without it), the chooser at /me/workspaces and the old /workspaces URLs redirecting.
    expect($routes)->toBe(json_decode(file_get_contents(__DIR__.'/Fixtures/routes.json'), true));
});
/** @param list<string> $legacy the template ids the database recorded */
function featureSeamsAssertKeptOnUpgrade(array $legacy): void
{
    DB::table('workspaces')->insert(['id' => 1, 'name' => 'Legacy']);
    DB::table('notes')->insert(['workspace_id' => 1, 'body' => 'Kept']);
    expect(app(Kernel::class)->call('migrate', ['--force' => true]))->toBe(0);

    // The released schema fixture is a SQLite dump; on PostgreSQL the migrations and data are checked.
    if (DB::getDriverName() === 'sqlite') {
        // An upgraded database keeps the notes tables it had, unused.
        expect(featureSeamsSchema())->toBe(featureSeamsReleased(withNotes: true));
    }
    expect(DB::table('notes')->pluck('body')->all())->toBe(['Kept']);
    $ran = DB::table('migrations')->orderBy('id')->pluck('migration')->all();
    foreach ($legacy as $id) {
        expect($ran)->toContain($id);
        foreach (KitMigrationRepository::MAP[$id] as $kit) {
            expect($ran)->toContain($kit);
        }
    }
    $kit = array_map(fn (string $path): string => basename($path, '.php'), glob(base_path('database/migrations/kit/*/*.php')));
    expect($kit)->toHaveCount(20);
    expect(array_diff($kit, $ran))->toBe([]);
    expect(app(Kernel::class)->call('migrate', ['--force' => true]))->toBe(0);
    expect(DB::table('migrations')->orderBy('id')->pluck('migration')->all())->toBe($ran);
}
function featureSeamsSchema(): array
{
    return json_decode(json_encode((require __DIR__.'/schema.php')()), true);
}
/** The 0.2.1 schema plus the media table 0.3 adds for spatie/laravel-medialibrary, and the slug the kit adds to workspaces. */
function featureSeamsReleased(bool $withNotes): array
{
    $schema = json_decode(file_get_contents(__DIR__.'/Fixtures/schema-v0.2.1.json'), true);
    test()->assertArrayNotHasKey('media', $schema);
    $schema['media'] = json_decode(file_get_contents(__DIR__.'/Fixtures/schema-media.json'), true);
    if (! $withNotes) {
        unset($schema['notes'], $schema['note_intents']);
    }

    // The kit's permission-defaults table, which the sync records its applied grants in.
    // Concierge 0.8: an optional note from the inviter on an invitation.
    // Concierge 0.8.1: whether the accepting account's email differed from the invited one.
    $schema['concierge_invitations']['columns'][] = ['accepted_with_other_email', 'tinyint(1)', 1, "'0'", 0];
    $schema['concierge_invitations']['columns'][] = ['message', 'varchar', 0, null, 0];

    // Laravel's own cache and queue tables (the database cache store and the database queue).
    $schema = [...$schema, ...json_decode(file_get_contents(__DIR__.'/Fixtures/schema-cache.json'), true), ...json_decode(file_get_contents(__DIR__.'/Fixtures/schema-queue.json'), true)];

    // The kit's own bookkeeping table: the fingerprint the deploy's role and permission syncs are gated by.
    $schema['kit_meta'] = json_decode(file_get_contents(__DIR__.'/Fixtures/schema-kit-meta.json'), true);

    // The kit's MCP intent ledger (0.4): one remembered result per actor, connection and intent key.
    $schema['kit_mcp_intents'] = json_decode(file_get_contents(__DIR__.'/Fixtures/schema-kit-mcp-intents.json'), true);

    // A capability switched for one person in one workspace.
    $schema['kit_capability_overrides'] = json_decode(file_get_contents(__DIR__.'/Fixtures/schema-kit-capability-overrides.json'), true);
    $schema['kit_permission_defaults'] = json_decode(file_get_contents(__DIR__.'/Fixtures/schema-kit-permission-defaults.json'), true);
    // Entitlements per feature: what a workspace's plan includes, and its limits.
    $schema['kit_entitlements'] = json_decode(file_get_contents(__DIR__.'/Fixtures/schema-kit-entitlements.json'), true);

    // The account pages: Fortify's two-factor columns on users (the starter's own migration) and the kit's per-user security log.
    $schema['users']['columns'] = [...$schema['users']['columns'], ['two_factor_secret', 'text', 0, null, 0], ['two_factor_recovery_codes', 'text', 0, null, 0], ['two_factor_confirmed_at', 'datetime', 0, null, 0]];
    $schema['sessions'] = ['columns' => [['id', 'varchar', 1, null, 1], ['user_id', 'integer', 0, null, 0], ['ip_address', 'varchar', 0, null, 0], ['user_agent', 'text', 0, null, 0], ['payload', 'text', 1, null, 0], ['last_activity', 'integer', 1, null, 0]], 'indexes' => [[0, 'c', ['last_activity']], [0, 'c', ['user_id']], [1, 'pk', ['id']]], 'foreign_keys' => []];
    $schema['password_reset_tokens'] = ['columns' => [['email', 'varchar', 1, null, 1], ['token', 'varchar', 1, null, 0], ['created_at', 'datetime', 0, null, 0]], 'indexes' => [[1, 'pk', ['email']]], 'foreign_keys' => []];
    $schema['kit_security_events'] = json_decode(file_get_contents(__DIR__.'/Fixtures/schema-kit-security-events.json'), true);

    // What the kit remembers about a person: timezone, onboarding steps and dismissed welcome cards.
    $schema['kit_user_state'] = json_decode(file_get_contents(__DIR__.'/Fixtures/schema-kit-user-state.json'), true);

    // The kit's notes feature (0.4.9): its own kit_notes and kit_note_intents, beside any legacy notes tables, which it never touches.
    $schema = [...$schema, ...json_decode(file_get_contents(__DIR__.'/Fixtures/schema-kit-notes.json'), true)];

    // The kit's slug migration: a workspace's slug is the segment after the `@` in its pages' URLs.
    $schema['workspaces']['columns'][] = ['slug', 'varchar', 0, null, 0];
    $schema['workspaces']['indexes'][] = [1, 'c', ['slug']];
    sort($schema['workspaces']['indexes']);
    ksort($schema);

    return $schema;
}
