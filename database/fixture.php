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
use Tey\Kit\Features\Notes\Models\Note;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\Operations\AcceptWorkspaceInvitation;

Schema::create('workspaces', function (Blueprint $t): void {
    $t->id();
    $t->string('name');
    $t->unsignedBigInteger('provisioning_invitation_id')->nullable()->unique();
    $t->unsignedInteger('settings_version')->default(1);
    $t->boolean('admitted')->default(true);
});
Schema::create('users', function (Blueprint $t): void {
    $t->id();
    $t->string('name');
    $t->string('email')->unique();
    $t->timestamp('email_verified_at')->nullable();
    $t->string('password')->nullable();
    $t->rememberToken();
    $t->timestamps();
});
Schema::create('notes', function (Blueprint $t): void {
    $t->id();
    $t->foreignId('workspace_id')->constrained();
    $t->string('body', 2000);
    $t->unsignedInteger('version')->default(1);
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
Schema::create('connections', function (Blueprint $t): void {
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
});
Schema::create('entitlements', function (Blueprint $t): void {
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
Schema::create('note_intents', function (Blueprint $t): void {
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
    $t->foreignId('workspace_id')->constrained();
    $t->string('event');
    $t->json('details');
    $t->timestamp('created_at');
});

// Execute pinned package migrations rather than copying membership/role internals.
(require base_path('vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub'))->up();
foreach (['create_concierge_tables.php', 'create_concierge_user_accounts_table.php', 'create_concierge_tenant_invitations_table.php'] as $migration) {
    (require base_path('vendor/tey/concierge/database/migrations/'.$migration))->up();
}
app(Kernel::class)->call('concierge:sync-roles', ['--no-interaction' => true]);
foreach ([1 => 'Alpha', 2 => 'Beta', 3 => 'Viewer', 99 => 'Platform operator'] as $id => $name) {
    User::create(['id' => $id, 'name' => $name, 'email' => 'actor'.$id.'@example.test', 'email_verified_at' => now()]);
}
DB::table('operators')->insert(['actor_id' => 99]);
$platform = User::findOrFail(99);
foreach ([1, 2] as $id) {
    $owner = User::findOrFail($id);
    $issued = InviteTenant::run($platform, $owner->email);
    $accepted = app(AcceptWorkspaceInvitation::class)->handle(new Principal($id), $issued->token);
    Workspace::findOrFail($accepted['workspace_id'])->execute(fn () => Note::create(['body' => $owner->name.' private']));
}
$viewer = InviteMember::run(User::findOrFail(1), Workspace::findOrFail(1), 'actor3@example.test', new GrantSet(['viewer']));
AcceptInvitation::run(User::findOrFail(3), $viewer->token);
DB::table('connections')->insert(['id' => 1, 'workspace_id' => 1, 'actor_id' => 1, 'scopes' => json_encode(['settings.read', 'settings.write'])]);
DB::table('connections')->insert(['id' => 2, 'workspace_id' => 1, 'actor_id' => 1, 'scopes' => json_encode(['settings.read'])]);
// PostgreSQL sequences do not follow explicit ids; move them past the synthetic rows.
if (DB::getDriverName() === 'pgsql') {
    foreach (['users', 'connections'] as $table) {
        DB::statement("select setval(pg_get_serial_sequence('{$table}', 'id'), (select max(id) from {$table}))");
    }
}
