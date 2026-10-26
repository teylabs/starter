<?php

require __DIR__.'/application.php';
require_once __DIR__.'/failures.php';
require dirname(__DIR__).'/database/fixture.php';
require_once __DIR__.'/Fixtures/RecordsFixture.php';

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;
use Tests\Fixtures\Record;
use Tests\Fixtures\RecordsFixtureProvider;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Access\Operations\ComplimentaryAccess;
use Tey\Kit\Features\Core\Audit;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Settings\Adapters\SettingsUi;
use Tey\Kit\Features\Settings\Integrations\Mcp\SettingsMcp;
use Tey\Kit\Features\Settings\WorkspaceSettings;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\TenantBoundary;
use Tey\Kit\Features\Workspaces\WorkspaceCache;

RecordsFixtureProvider::install();

$checks = [];
function check(string $name, callable $test): void
{
    global $checks;
    $test();
    $checks[] = $name;
    echo "PASS $name\n";
}
function same(mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException('Expected '.var_export($expected, true).' got '.var_export($actual, true));
    }
}
function refused(string $message, callable $operation): void
{
    try {
        $operation();
    } catch (Throwable $e) {
        if ($e->getMessage() === $message) {
            return;
        }
        throw $e;
    }
    throw new RuntimeException('Expected refusal '.$message);
}
$ui = app(SettingsUi::class);
$mcp = app(SettingsMcp::class);
$boundary = app(TenantBoundary::class);
$alpha = new Principal(1);
$connection = new Principal(1, 1);
$operator = new Principal(99);

check('settings read matches across UI and MCP', fn () => same($ui->show($alpha, 1), $mcp->call($connection, 1, 'workspace_settings_get')));
check('UI settings write is immediately visible through MCP', function () use ($ui, $mcp, $alpha, $connection): void {
    $input = ['expected_version' => (int) DB::table('workspaces')->where('id', 1)->value('settings_version'), 'intent_key' => 'profile-'.DB::table('workspaces')->where('id', 1)->value('settings_version'), 'display_name' => 'Alpha configured', 'timezone' => 'America/Toronto'];
    same($ui->update($alpha, 1, $input), $mcp->call($connection, 1, 'workspace_settings_get'));
});
check('MCP settings write is immediately visible through UI', function () use ($ui, $mcp, $alpha, $connection): void {
    $input = ['expected_version' => (int) DB::table('workspaces')->where('id', 1)->value('settings_version'), 'intent_key' => 'profile-'.DB::table('workspaces')->where('id', 1)->value('settings_version'), 'display_name' => 'Alpha MCP', 'timezone' => 'UTC'];
    same($mcp->call($connection, 1, 'workspace_settings_update', $input), $ui->show($alpha, 1));
});
check('second tenant settings remain unchanged with settings cache on', fn () => same('Beta', $ui->show(new Principal(2), 2)['display_name']));
check('foreign workspace is indistinguishable from absent membership', fn () => refused('workspace_not_found', fn () => $ui->show($alpha, 2)));
check('viewer reads but cannot configure', function () use ($ui): void {
    $viewer = new Principal(3);
    same('Alpha MCP', $ui->show($viewer, 1)['display_name']);
    refused('permission_denied', fn () => $ui->update($viewer, 1, ['display_name' => 'x', 'timezone' => 'UTC', 'expected_version' => 1, 'intent_key' => 'denied']));
});
check('read-only delegated scope cannot write', fn () => refused('connection_denied', fn () => $mcp->call(new Principal(1, 2), 1, 'workspace_settings_update', ['display_name' => 'x', 'timezone' => 'UTC', 'expected_version' => 1, 'intent_key' => 'denied'])));
check('connection identity cannot be borrowed by another actor', fn () => refused('connection_denied', fn () => $mcp->call(new Principal(3, 1), 1, 'workspace_settings_get')));
check('revocation is rechecked at execution', function () use ($mcp, $connection): void {
    DB::table('connections')->where('id', 1)->update(['revoked_at' => now()]);
    refused('connection_denied', fn () => $mcp->call($connection, 1, 'workspace_settings_get'));
    DB::table('connections')->where('id', 1)->update(['revoked_at' => null]);
});
check('MCP has no SQL or arbitrary execute escape', function () use ($mcp, $connection): void {
    refused('unknown_tool', fn () => $mcp->call($connection, 1, 'execute', ['sql' => 'select * from fixture_records']));
    refused('unknown_arguments', fn () => $mcp->call($connection, 1, 'workspace_settings_update', ['display_name' => 'x', 'timezone' => 'UTC', 'actor_id' => 99]));
});
check('no-context reads and writes fail closed', function (): void {
    same(null, Workspace::current());
    refused('workspace_required', fn () => Record::query()->get());
    refused('workspace_required', fn () => Record::create(['title' => 'orphan']));
    refused('workspace_required', fn () => WorkspaceSettings::current());
});
check('rows and route binding are scoped to current workspace', function () use ($boundary): void {
    $boundary->run(1, function (): void {
        same(['Alpha private'], Record::query()->pluck('title')->all());
        same(null, (new Record)->resolveRouteBinding(2));
    });
});
check('held model cannot save into a different tenant', function () use ($boundary): void {
    $record = $boundary->run(1, fn () => Record::query()->firstOrFail());
    $boundary->run(2, fn () => refused('workspace_mismatch', fn () => $record->save()));
});
check('tenant context restores across nested failure', function () use ($boundary): void {
    $boundary->run(1, function () use ($boundary): void {
        refused('synthetic_failure', fn () => $boundary->run(2, fn () => throw new RuntimeException('synthetic_failure')));
        same(1, Workspace::required()->id);
    });
    same(null, Workspace::current());
});
check('cache keys remain isolated in the same worker', function () use ($boundary): void {
    $boundary->run(1, fn () => app(WorkspaceCache::class)->put('probe', 'alpha'));
    $boundary->run(2, function (): void {
        same(null, app(WorkspaceCache::class)->get('probe'));
        app(WorkspaceCache::class)->put('probe', 'beta');
    });
    same('alpha', $boundary->run(1, fn () => app(WorkspaceCache::class)->get('probe')));
});
check('capability does not imply entitlement', function () use ($boundary, $alpha): void {
    $boundary->run(1, function () use ($alpha): void {
        same(true, Feature::for(Workspace::required())->active('workspace.settings'));
        refused('entitlement_required', fn () => app(Access::class)->authorize($alpha, 'settings.read', paid: true));
    });
});
check('complimentary grants require operator authority and reason', function () use ($alpha, $operator): void {
    refused('operator_required', fn () => app(ComplimentaryAccess::class)->grant($alpha, 1, 'test', now()->addDay(), 'test-grant'));
    refused('reason_required', fn () => app(ComplimentaryAccess::class)->grant($operator, 1, '', now()->addDay(), 'test-grant'));
});
check('complimentary grant is auditable and revocable without modifying paid rows', function () use ($operator, $boundary, $alpha): void {
    DB::table('entitlements')->insert(['workspace_id' => 2, 'kind' => 'paid']);
    $before = DB::table('entitlements')->get()->toJson();
    $id = app(ComplimentaryAccess::class)->grant($operator, 1, 'Synthetic acceptance', now()->addDay(), 'test-grant');
    $boundary->run(1, fn () => app(Access::class)->authorize($alpha, 'settings.read', paid: true));
    app(ComplimentaryAccess::class)->revoke($operator, $id, 'Synthetic revoke');
    app(ComplimentaryAccess::class)->revoke($operator, $id, 'Duplicate revoke');
    $boundary->run(1, fn () => refused('entitlement_required', fn () => app(Access::class)->authorize($alpha, 'settings.read', paid: true)));
    same($before, DB::table('entitlements')->get()->toJson());
    same(2, DB::table('audit_events')->whereIn('event', ['complimentary.granted', 'complimentary.revoked'])->count());
});
check('entitlement does not bypass admission', function () use ($ui): void {
    DB::table('workspaces')->where('id', 2)->update(['admitted' => false]);
    refused('admission_required', fn () => $ui->show(new Principal(2), 2));
    DB::table('workspaces')->where('id', 2)->update(['admitted' => true]);
});
check('audit failure rolls back settings and complimentary grant', function () use ($ui, $alpha, $operator): void {
    $before = DB::table('settings')->get()->toJson();
    $grants = DB::table('complimentary_grants')->count();
    // An actual database audit failure, not a mock of the application operation.
    failInserts('fail_audit', 'audit_events', 'synthetic_audit_failure');
    foreach ([fn () => $ui->update($alpha, 1, ['display_name' => 'must rollback', 'timezone' => 'UTC', 'expected_version' => 3, 'intent_key' => 'rollback']), fn () => app(ComplimentaryAccess::class)->grant($operator, 1, 'must rollback', now()->addDay(), 'rollback-grant')] as $operation) {
        try {
            $operation();
            throw new RuntimeException('Expected audit failure');
        } catch (QueryException $e) {
            if (! str_contains($e->getMessage(), 'synthetic_audit_failure')) {
                throw $e;
            }
        }
    }
    allowInserts('fail_audit', 'audit_events');
    same($before, DB::table('settings')->get()->toJson());
    same('Alpha MCP', $ui->show($alpha, 1)['display_name']);
    same($grants, DB::table('complimentary_grants')->count());
});
check('failed request leaves no workspace context', fn () => same(null, Workspace::current()));
echo json_encode(['fixture' => 'starter-foundation', 'checks' => count($checks), 'database' => DB::getDriverName() === 'pgsql' ? 'pgsql' : 'sqlite::memory:', 'evidence' => 'focused synthetic checks only', 'passed' => $checks], JSON_PRETTY_PRINT)."\n";
