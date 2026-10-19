<?php

require __DIR__.'/FoundationCheck.php';
use App\Models\User;
use App\Starter\Access\Operations\ComplimentaryAccess;
use App\Starter\Core\Principal;
use App\Starter\Workspaces\Models\Workspace;
use App\Starter\Workspaces\Operations\AcceptWorkspaceInvitation;
use Composer\InstalledVersions;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Tey\Concierge\Actions\InviteTenant;
use Tey\Concierge\Actions\ReinstateMembership;
use Tey\Concierge\Actions\SuspendMembership;
use Tey\Concierge\Concierge;
use Tey\Concierge\Enums\AccountStatus;
use Tey\Concierge\Enums\InvitationStatus;
use Tey\Concierge\Exceptions\InvitationRejected;
use Tey\Concierge\LocalAuth\LocalLogin;
use Tey\Concierge\Registration\Registration;

check('Concierge released native membership is the only membership engine', function (): void {
    same('0.7.2.0', InstalledVersions::getVersion('tey/concierge'));
    same(false, Schema::hasTable('memberships'));
    same(2, DB::table('concierge_ownerships')->count());
    same(3, DB::table('concierge_memberships')->count());
    same(2, DB::table('concierge_tenant_invitations')->where('status', 'accepted')->count());
});
check('Concierge and Spatie contexts agree and restore after nested exception', function () use ($boundary): void {
    $boundary->run(1, function () use ($boundary): void {
        same(1, app(Concierge::class)->context()->current()->getKey());
        same(1, app(PermissionRegistrar::class)->getPermissionsTeamId());
        refused('context_probe', fn () => $boundary->run(2, function (): void {
            same(2, Workspace::required()->id);
            same(2, app(PermissionRegistrar::class)->getPermissionsTeamId());
            throw new RuntimeException('context_probe');
        }));
        same(1, app(Concierge::class)->context()->current()->getKey());
        same(1, app(PermissionRegistrar::class)->getPermissionsTeamId());
    });
    same(null, Workspace::current());
    same(null, app(Concierge::class)->context()->current());
    same(null, app(PermissionRegistrar::class)->getPermissionsTeamId());
});
check('held User role relations refresh when crossing workspace contexts', function (): void {
    $held = User::findOrFail(1);
    $context = app(Concierge::class)->context();
    $context->run(Workspace::findOrFail(1), function () use ($held, $context): void {
        same(['owner'], $held->roles->pluck('name')->all());
        $context->run(Workspace::findOrFail(2), fn () => same([], $held->roles->pluck('name')->all()), [$held]);
        same(['owner'], $held->roles->pluck('name')->all());
    }, [$held]);
    same(false, $held->relationLoaded('roles'));
});
check('package suspension immediately denies both UI and MCP; reinstatement restores read', function () use ($ui, $mcp): void {
    DB::table('connections')->insert(['id' => 3, 'workspace_id' => 1, 'actor_id' => 3, 'scopes' => json_encode(['settings.read'])]);
    $viewer = User::findOrFail(3);
    $member = app(Concierge::class)->membership($viewer, Workspace::findOrFail(1));
    same('Alpha MCP', $mcp->call(new Principal(3, 3), 1, 'workspace_settings_get')['display_name']);
    SuspendMembership::run(User::findOrFail(1), $member, 'Synthetic suspension');
    refused('workspace_not_found', fn () => $ui->show(new Principal(3), 1));
    refused('workspace_not_found', fn () => $mcp->call(new Principal(3, 3), 1, 'workspace_settings_get'));
    ReinstateMembership::run(User::findOrFail(1), $member->fresh());
    same('Alpha MCP', $ui->show(new Principal(3), 1)['display_name']);
});
check('deactivated account and unverified identity cannot use either adapter', function () use ($ui, $mcp, $alpha, $connection): void {
    $account = app(Concierge::class)->account(User::findOrFail(1));
    $account->forceFill(['status' => AccountStatus::Deactivated])->save();
    refused('account_inactive', fn () => $ui->show($alpha, 1));
    refused('account_inactive', fn () => $mcp->call($connection, 1, 'workspace_settings_get'));
    $account->forceFill(['status' => AccountStatus::Active])->save();
    DB::table('users')->where('id', 1)->update(['email_verified_at' => null]);
    refused('verification_required', fn () => $ui->show($alpha, 1));
    refused('verification_required', fn () => $mcp->call($connection, 1, 'workspace_settings_get'));
    DB::table('users')->where('id', 1)->update(['email_verified_at' => now()]);
});
check('closed registration and disabled local login are package decisions', function (): void {
    same(false, Registration::decide('stranger@example.test')->allowed);
    same(null, app(LocalLogin::class)->offer());
    same(false, config('concierge.invitations.deliver'));
});
check('repeat tenant acceptance returns same workspace without replacing roles', function (): void {
    $user = User::create(['name' => 'Gamma', 'email' => 'gamma@example.test', 'email_verified_at' => now()]);
    $issued = InviteTenant::run(User::findOrFail(99), $user->email);
    $principal = new Principal($user->id);
    $first = app(AcceptWorkspaceInvitation::class)->handle($principal, $issued->token);
    $before = DB::table('model_has_roles')->get()->toJson();
    $repeat = app(AcceptWorkspaceInvitation::class)->handle($principal, $issued->token);
    same($first['workspace_id'], $repeat['workspace_id']);
    same('already_provisioned', $repeat['outcome']);
    same($before, DB::table('model_has_roles')->get()->toJson());
});
check('wrong unverified expired and revoked invitations cannot provision', function (): void {
    $user = User::create(['name' => 'Delta', 'email' => 'delta@example.test', 'email_verified_at' => null]);
    $issued = InviteTenant::run(User::findOrFail(99), $user->email);
    $before = Workspace::count();
    foreach ([new Principal(1), new Principal($user->id)] as $principal) {
        try {
            app(AcceptWorkspaceInvitation::class)->handle($principal, $issued->token);
            throw new RuntimeException('Invalid invitation accepted');
        } catch (InvitationRejected $e) {
        }
    }
    $user->forceFill(['email_verified_at' => now()])->save();
    $issued->invitation->forceFill(['expires_at' => now()->subMinute()])->save();
    try {
        app(AcceptWorkspaceInvitation::class)->handle(new Principal($user->id), $issued->token);
        throw new RuntimeException('Expired invitation accepted');
    } catch (InvitationRejected $e) {
    }
    $issued->invitation->forceFill(['expires_at' => now()->addDay(), 'status' => InvitationStatus::Revoked])->save();
    try {
        app(AcceptWorkspaceInvitation::class)->handle(new Principal($user->id), $issued->token);
        throw new RuntimeException('Revoked invitation accepted');
    } catch (InvitationRejected $e) {
    }
    same($before, Workspace::count());
});
check('host settings failure rolls back package onboarding atomically', function (): void {
    $user = User::create(['name' => 'Epsilon', 'email' => 'epsilon@example.test', 'email_verified_at' => now()]);
    $issued = InviteTenant::run(User::findOrFail(99), $user->email);
    $counts = [Workspace::count(), DB::table('settings')->count(), DB::table('concierge_memberships')->count()];
    DB::statement("CREATE TRIGGER fail_settings BEFORE INSERT ON settings WHEN NEW.name='timezone' BEGIN SELECT RAISE(ABORT, 'synthetic_settings_failure'); END");
    try {
        app(AcceptWorkspaceInvitation::class)->handle(new Principal($user->id), $issued->token);
        throw new RuntimeException('Expected settings failure');
    } catch (QueryException $e) {
        if (! str_contains($e->getMessage(), 'synthetic_settings_failure')) {
            throw $e;
        }
    } finally {
        DB::statement('DROP TRIGGER fail_settings');
    }
    same($counts, [Workspace::count(), DB::table('settings')->count(), DB::table('concierge_memberships')->count()]);
    same('pending', $issued->invitation->fresh()->status->value);
    same(null, Workspace::current());
    $result = app(AcceptWorkspaceInvitation::class)->handle(new Principal($user->id), $issued->token);
    same('provisioned', $result['outcome']);
});
check('inactive platform operator cannot grant or revoke complimentary access', function () use ($operator): void {
    $account = app(Concierge::class)->account(User::findOrFail(99));
    $account->forceFill(['status' => AccountStatus::Deactivated])->save();
    refused('operator_required', fn () => app(ComplimentaryAccess::class)->grant($operator, 1, 'must refuse', now()->addDay(), 'inactive-operator'));
    refused('operator_required', fn () => app(ComplimentaryAccess::class)->revoke($operator, 1, 'must refuse'));
    $account->forceFill(['status' => AccountStatus::Active])->save();
});
