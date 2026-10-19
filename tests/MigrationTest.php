<?php

use App\Models\User;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Notes\Operations\WorkspaceNotes;
use Tey\Kit\Features\Settings\Operations\WorkspaceProfile;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\Operations\AcceptWorkspaceInvitation;
use Tey\Kit\Features\Workspaces\TenantBoundary;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Tey\Concierge\Actions\InviteTenant;
use Tey\Concierge\Concierge;

require dirname(__DIR__).'/vendor/autoload.php';

final class MigrationTest extends TestCase
{
    public function createApplication()
    {
        require __DIR__.'/environment.php';

        return require dirname(__DIR__).'/bootstrap/app.php';
    }

    public function test_clean_framework_schema_and_explicit_synthetic_package_onboarding(): void
    {
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertFalse(Schema::hasTable('users'));
        $this->assertSame(0, app(Kernel::class)->call('migrate', ['--force' => true]));
        foreach (['users', 'media', 'workspaces', 'settings', 'notes', 'connections', 'entitlements', 'operators', 'complimentary_grants', 'settings_intents', 'note_intents', 'audit_events', 'permissions', 'roles', 'model_has_roles', 'concierge_memberships', 'concierge_ownerships', 'concierge_invitations', 'concierge_accounts', 'concierge_tenant_invitations', 'oauth_clients', 'oauth_auth_codes', 'oauth_access_tokens', 'oauth_refresh_tokens', 'oauth_device_codes'] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);
            $this->assertSame(0, DB::table($table)->count(), 'Migrate must not seed '.$table);
        }
        $migrations = DB::table('migrations')->orderBy('id')->pluck('migration')->all();
        $this->assertSame([
            '0001_01_01_000000_create_users_table',
            '0001_01_01_000001_create_media_table',
            '0001_01_02_000100_create_kit_core_tables',
            '0001_01_02_000200_create_kit_workspaces_table',
            '0001_01_02_000201_create_permission_tables',
            '0001_01_02_000202_create_kit_operators_table',
            '0001_01_02_000300_create_kit_access_tables',
            '0001_01_02_000400_create_settings_table',
            '0001_01_02_000401_create_kit_settings_tables',
            '0001_01_02_000600_create_kit_mcp_tables',
            '0001_01_02_000700_create_kit_notes_tables',
            '2016_06_01_000001_create_oauth_auth_codes_table',
            '2016_06_01_000002_create_oauth_access_tokens_table',
            '2016_06_01_000003_create_oauth_refresh_tokens_table',
            '2016_06_01_000004_create_oauth_clients_table',
            '2024_06_01_000001_create_oauth_device_codes_table',
            'create_concierge_tables',
            'create_concierge_tenant_invitations_table',
            'create_concierge_user_accounts_table',
        ], $migrations);
        $this->assertSame(0, app(Kernel::class)->call('migrate', ['--force' => true]));
        $this->assertSame($migrations, DB::table('migrations')->orderBy('id')->pluck('migration')->all());
        $this->assertSame(0, User::count());
        $this->get('/register')->assertNotFound();
        $this->postJson('/local-login')->assertNotFound();

        // Explicit synthetic test data only; migrations/public bootstrap never run this.
        $this->assertSame(0, app(Kernel::class)->call('concierge:sync-roles', ['--no-interaction' => true]));
        foreach ([1 => 'Schema Alpha', 2 => 'Schema Beta', 99 => 'Synthetic operator'] as $id => $name) {
            User::create(['id' => $id, 'name' => $name, 'email' => 'schema-'.$id.'@example.test', 'email_verified_at' => now()]);
        }
        DB::table('operators')->insert(['actor_id' => 99]);
        $platform = User::findOrFail(99);
        $workspaces = [];
        foreach ([1, 2] as $id) {
            $owner = User::findOrFail($id);
            $invitation = InviteTenant::run($platform, $owner->email);
            $accepted = app(AcceptWorkspaceInvitation::class)->handle(new Principal($id), $invitation->token);
            $workspaces[$id] = $accepted['workspace_id'];
        }
        $this->assertNotSame($workspaces[1], $workspaces[2]);
        $this->assertSame(2, DB::table('concierge_memberships')->count());
        $this->assertSame(2, DB::table('concierge_ownerships')->count());
        $this->assertSame(2, DB::table('concierge_tenant_invitations')->where('status', 'accepted')->count());
        $boundary = app(TenantBoundary::class);
        foreach ([1, 2] as $id) {
            $boundary->run($workspaces[$id], function () use ($id): void {
                $principal = new Principal($id);
                $profile = app(WorkspaceProfile::class);
                $original = $profile->read($principal);
                $updated = $profile->update($principal, ['display_name' => 'Migrated '.$id, 'timezone' => 'America/Toronto', 'expected_version' => $original->version, 'intent_key' => 'schema-profile-'.$id]);
                $this->assertSame('Migrated '.$id, $updated->display_name);
                $this->assertSame(2, $updated->version);
                $notes = app(WorkspaceNotes::class);
                $notes->mutate($principal, 'create', ['body' => 'Private schema '.$id, 'intent_key' => 'schema-note-'.$id]);
                $this->assertCount(1, $notes->listing($principal));
                try {
                    $profile->read(new Principal($id === 1 ? 2 : 1));
                    $this->fail('Foreign owner was accepted');
                } catch (RuntimeException $exception) {
                    $this->assertSame('workspace_not_found', $exception->getMessage());
                }
            });
            $this->assertNull(Workspace::current());
            $this->assertNull(app(Concierge::class)->context()->current());
            $this->assertNull(app(PermissionRegistrar::class)->getPermissionsTeamId());
        }
        $before = [];
        foreach (['users', 'workspaces', 'notes', 'settings', 'audit_events', 'concierge_memberships', 'concierge_ownerships'] as $table) {
            $before[$table] = DB::table($table)->get()->toJson();
        }
        $this->assertSame(0, app(Kernel::class)->call('migrate', ['--force' => true]));
        foreach ($before as $table => $bytes) {
            $this->assertSame($bytes, DB::table($table)->get()->toJson(), 'Repeat migrate preserves '.$table);
        }
    }
}
