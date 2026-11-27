<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tey\Concierge\Actions\AcceptInvitation;
use Tey\Concierge\Actions\InviteMember;
use Tey\Concierge\Actions\InviteTenant;
use Tey\Concierge\Support\GrantSet;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\Operations\AcceptWorkspaceInvitation;

Schema::create('workspaces', function (Blueprint $t): void {
    $t->id();
    $t->string('name');
    $t->string('slug', 63)->nullable()->unique();
    $t->unsignedBigInteger('provisioning_invitation_id')->nullable()->unique();
    $t->unsignedInteger('settings_version')->default(1);
    $t->boolean('admitted')->default(true);
    $t->timestamp('deleting_at')->nullable();
    $t->unsignedBigInteger('deleting_by')->nullable();
});
Schema::create('users', function (Blueprint $t): void {
    $t->id();
    $t->string('name');
    $t->string('email')->unique();
    $t->timestamp('email_verified_at')->nullable();
    $t->string('password')->nullable();
    $t->text('two_factor_secret')->nullable();
    $t->text('two_factor_recovery_codes')->nullable();
    $t->timestamp('two_factor_confirmed_at')->nullable();
    $t->rememberToken();
    $t->timestamps();
});
Schema::create('settings', function (Blueprint $t): void {
    $t->id();
    $t->string('group');
    $t->string('name');
    $t->boolean('locked')->default(false);
    $t->json('payload');
    $t->timestamps();
    $t->unique(['group', 'name']);
});
Schema::create('mcp_connections', function (Blueprint $t): void {
    $t->id();
    $t->foreignId('workspace_id')->constrained();
    $t->unsignedBigInteger('actor_id');
    $t->json('scopes');
    $t->string('oauth_token_id', 100)->nullable();
    $t->string('intent_key')->nullable();
    $t->string('fingerprint')->nullable();
    $t->unique(['actor_id', 'intent_key']);
    $t->timestamp('expires_at')->nullable();
    $t->timestamp('revoked_at')->nullable();
    $t->string('oauth_client_id')->nullable();
});
Schema::create('workspace_entitlements', function (Blueprint $t): void {
    $t->id();
    $t->foreignId('workspace_id')->constrained();
    $t->string('kind');
    $t->timestamp('revoked_at')->nullable();
});
Schema::create('operators', function (Blueprint $t): void {
    $t->unsignedBigInteger('actor_id')->primary();
});
Schema::create('complimentary_grants', function (Blueprint $t): void {
    $t->id();
    $t->foreignId('workspace_id')->constrained();
    $t->unsignedBigInteger('granted_by');
    $t->string('intent_key');
    $t->string('fingerprint');
    $t->unsignedBigInteger('revoked_by')->nullable();
    $t->string('revocation_reason')->nullable();
    $t->unique(['granted_by', 'intent_key']);
    $t->string('reason');
    $t->timestamp('expires_at');
    $t->timestamp('revoked_at')->nullable();
});
Schema::create('settings_intents', function (Blueprint $t): void {
    $t->id();
    $t->foreignId('workspace_id')->constrained();
    $t->unsignedBigInteger('actor_id');
    $t->string('intent_key');
    $t->string('fingerprint');
    $t->json('result');
    $t->unique(['workspace_id', 'actor_id', 'intent_key']);
});
Schema::create('audit_events', function (Blueprint $t): void {
    $t->id();
    $t->unsignedBigInteger('actor_id');
    $t->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
    $t->string('event');
    $t->json('details');
    $t->timestamp('created_at');
    $t->string('workspace_name')->nullable();
    $t->index('workspace_id');
});

Schema::create('password_reset_tokens', function (Blueprint $t): void {
    $t->string('email')->primary();
    $t->string('token');
    $t->timestamp('created_at')->nullable();
});
// Avatars are media on the user row.
(require base_path('database/migrations/0001_01_01_000001_create_media_table.php'))->up();
// The database cache store's tables and the database queue's.
(require base_path('database/migrations/0001_01_01_000005_create_cache_table.php'))->up();
(require base_path('database/migrations/0001_01_01_000006_create_jobs_table.php'))->up();
// The kit's migrations load from the package (0.18), with its 0.18 renames of the infrastructure tables, its 0.24 signed-in devices table, its 0.39 flag and
// tag tables and its 0.43 renames (kit_meta to app_meta; tags, taggables and flag_dismissals).
foreach ([
    'session-ui/0001_01_02_000500_create_kit_security_events_table.php',
    'session-ui/0001_01_02_000501_add_user_foreign_key_to_kit_security_events_table.php',
    'access/0001_01_02_000301_create_kit_permission_defaults_table.php',
    'access/0001_01_02_000302_create_kit_capability_overrides_table.php',
    'access/2026_11_14_000304_rename_access_tables.php',
    'core/0001_01_02_000101_create_kit_meta_table.php',
    'core/0001_01_02_000102_unprefix_kit_tables.php',
    'session-ui/0001_01_02_000502_create_kit_user_state_table.php',
    'session-ui/0001_01_02_000503_add_user_foreign_key_to_kit_user_state_table.php',
    'settings/0001_01_02_000403_create_workspace_mail_identities_table.php',
    'session-ui/0001_01_02_000504_create_kit_account_deletions_table.php',
    'session-ui/0001_01_02_000505_add_finished_at_to_kit_account_deletions_table.php',
    'session-ui/2026_11_14_000506_rename_session_ui_tables.php',
    'session-ui/2026_11_14_000507_create_signed_in_devices_table.php',
    'session-ui/2026_11_14_000508_add_user_foreign_key_to_signed_in_devices_table.php',
    'workspaces/0001_01_02_000211_create_kit_tags_tables.php',
    'workspaces/0001_01_02_000212_unprefix_and_protect_tags_tables.php',
    'settings/0001_01_02_000404_create_workspace_secrets_table.php',
] as $migration) {
    (require base_path('vendor/tey/kit/database/migrations/'.$migration))->up();
}
// Execute pinned package migrations rather than copying membership/role internals.
(require base_path('vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub'))->up();
foreach (['create_concierge_tables.php', 'create_concierge_user_accounts_table.php', 'create_concierge_tenant_invitations_table.php', 'update_concierge_invitations_with_acceptor.php', 'update_concierge_invitations_with_message.php', 'update_concierge_invitations_with_scope.php', 'update_concierge_tables_to_plain_names.php', 'update_concierge_tenant_invitations_to_owner_invitations.php'] as $migration) {
    (require base_path('vendor/tey/concierge/database/migrations/'.$migration))->up();
}
app(Kernel::class)->call('concierge:sync-roles', ['--no-interaction' => true]);
app(Kernel::class)->call('kit:permissions:sync', ['--no-interaction' => true]);
foreach ([1 => 'Alpha', 2 => 'Beta', 3 => 'Viewer', 99 => 'Platform operator'] as $id => $name) {
    User::create(['id' => $id, 'name' => $name, 'email' => 'actor'.$id.'@example.test', 'email_verified_at' => now()]);
}
DB::table('operators')->insert(['actor_id' => 99]);
$platform = User::findOrFail(99);
foreach ([1, 2] as $id) {
    $owner = User::findOrFail($id);
    $issued = InviteTenant::run($platform, $owner->email);
    $accepted = app(AcceptWorkspaceInvitation::class)->handle(new Principal($id), $issued->token);
}
$viewer = InviteMember::run(User::findOrFail(1), Workspace::findOrFail(1), 'actor3@example.test', new GrantSet(['viewer']));
AcceptInvitation::run(User::findOrFail(3), $viewer->token);
DB::table('mcp_connections')->insert(['id' => 1, 'workspace_id' => 1, 'actor_id' => 1, 'scopes' => json_encode(['settings.read', 'settings.write'])]);
DB::table('mcp_connections')->insert(['id' => 2, 'workspace_id' => 1, 'actor_id' => 1, 'scopes' => json_encode(['settings.read'])]);
// PostgreSQL sequences do not follow explicit ids; move them past the synthetic rows.
if (DB::getDriverName() === 'pgsql') {
    foreach (['users', 'mcp_connections'] as $table) {
        DB::statement("select setval(pg_get_serial_sequence('{$table}', 'id'), (select max(id) from {$table}))");
    }
}
