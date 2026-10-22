<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tey\Concierge\Concierge;
use Tey\Concierge\Models\Membership;
use Tey\Concierge\Models\Ownership;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;

function ownershipTransferInertia(): array
{
    return ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
}
function ownershipTransferSignIn(int $id): void
{
    Auth::forgetGuards();
    test()->password = Str::random(40);
    User::findOrFail($id)->update(['password' => Hash::make(test()->password)]);
    test()->postJson('/login', ['email' => 'actor'.$id.'@example.test', 'password' => test()->password])->assertOk();
    test()->withCookie(config('session.cookie'), app('session')->getId());
    Auth::forgetGuards();
}
function ownershipTransferSignOut(): void
{
    test()->postJson('/logout')->assertNoContent();
    test()->flushSession();
    test()->forgetCookies();
    Auth::forgetGuards();
}
function ownershipTransferMembership(int $workspace, int $user): Membership
{
    return Membership::query()->where('workspace_id', $workspace)->where('user_id', $user)->firstOrFail();
}
function ownershipTransferRoles(int $user): array
{
    return app(Concierge::class)->roleNames(ownershipTransferMembership(1, $user)->fresh());
}
/** The action keys People offers on a user's row, for the signed-in viewer. */
function ownershipTransferRowActions(int $user): array
{
    $rows = test()->getJson('/@alpha/settings/people')->assertOk()->json('table.rows');
    $row = collect($rows)->firstWhere('id', (string) ownershipTransferMembership(1, $user)->id);

    return array_column($row['actions'] ?? [], 'key');
}
function ownershipTransferTransfer(int $to, ?string $password = null): TestResponse
{
    return test()->putJson('/@alpha/settings/ownership', ['membership' => ownershipTransferMembership(1, $to)->id, 'password' => $password ?? test()->password]);
}
test('the owner passes ownership and becomes an admin', function () {
    require dirname(__DIR__).'/database/fixture.php';
    ownershipTransferSignIn(1);

    // Contextual: General has no danger zone; Make owner is on a verified member's row in People, for the owner only, and the owner has no Leave anywhere.
    $general = $this->get('/@alpha/settings/general', ownershipTransferInertia())->assertOk()->json('props');
    $this->assertArrayNotHasKey('transfer', $general);
    $this->assertArrayNotHasKey('leave', $general);
    expect(ownershipTransferRowActions(3))->toContain('transfer-ownership');
    $make = collect($this->getJson('/@alpha/settings/people')->json('table.rows'))->firstWhere('id', (string) ownershipTransferMembership(1, 3)->id)['actions'];
    $make = collect($make)->firstWhere('key', 'transfer-ownership');
    expect([$make['label'], $make['url']])->toBe(['Make owner', '/settings/ownership']);
    expect($make['confirm'])->not->toBeNull();
    $this->get('/@alpha/dashboard', ownershipTransferInertia())->assertJsonPath('props.shell.workspace.leave', null);
    ownershipTransferTransfer(3)->assertOk()->assertJsonPath('owner_membership_id', ownershipTransferMembership(1, 3)->id);
    expect(ownershipTransferRoles(3))->toBe(['owner']);
    expect(ownershipTransferRoles(1))->toBe(['admin']);
    $ownership = Ownership::query()->where('workspace_id', 1)->firstOrFail();
    expect($ownership->membership_id)->toBe(ownershipTransferMembership(1, 3)->id);
    expect($ownership->previous_membership_id)->toBe(ownershipTransferMembership(1, 1)->id);

    // The password confirmation does not linger: the same session cannot transfer again without it.
    expect(session('auth.password_confirmed_at'))->toBeNull();

    // The former owner is an admin now: no transfer, and the new owner is protected from them.
    expect(ownershipTransferRowActions(3))->not->toContain('transfer-ownership');
    ownershipTransferTransfer(3)->assertForbidden();
    $this->deleteJson('/@alpha/settings/people/members/'.ownershipTransferMembership(1, 3)->id)->assertForbidden();
    ownershipTransferSignOut();
    ownershipTransferSignIn(3);
    expect(ownershipTransferRowActions(1))->toContain('transfer-ownership');
    $this->deleteJson('/@alpha/settings/people/members/'.ownershipTransferMembership(1, 1)->id)->assertOk();
});
test('the password must be confirmed', function () {
    require dirname(__DIR__).'/database/fixture.php';
    ownershipTransferSignIn(1);
    ownershipTransferTransfer(3, 'not-the-password')->assertUnprocessable()->assertJsonValidationErrors('password');
    $this->putJson('/@alpha/settings/ownership', ['membership' => ownershipTransferMembership(1, 3)->id])->assertUnprocessable()->assertJsonValidationErrors('password');
    expect(ownershipTransferRoles(1))->toBe(['owner']);
    expect(ownershipTransferRoles(3))->toBe(['viewer']);
});
test('only the owner may transfer', function () {
    require dirname(__DIR__).'/database/fixture.php';
    ownershipTransferSignIn(1);
    $this->putJson('/@alpha/settings/people/members/'.ownershipTransferMembership(1, 3)->id, ['role' => 'admin'])->assertOk();
    ownershipTransferSignOut();

    // An admin, who may manage viewers, still cannot take ownership; nor can a viewer.
    ownershipTransferSignIn(3);
    expect(ownershipTransferRowActions(1))->not->toContain('transfer-ownership');
    $this->putJson('/@alpha/settings/ownership', ['membership' => ownershipTransferMembership(1, 3)->id, 'password' => $this->password])->assertForbidden();
    $this->putJson('/@alpha/settings/ownership', ['membership' => ownershipTransferMembership(1, 1)->id, 'password' => $this->password])->assertForbidden();
    expect(ownershipTransferRoles(1))->toBe(['owner']);
    expect(ownershipTransferRoles(3))->toBe(['admin']);
    expect(Ownership::query()->where('workspace_id', 1)->count())->toBe(1);
});
test('the target must be an existing active member of this workspace', function () {
    require dirname(__DIR__).'/database/fixture.php';
    ownershipTransferSignIn(1);

    // Yourself, a membership of another workspace, one that does not exist, and a member who has been removed.
    $this->putJson('/@alpha/settings/ownership', ['membership' => ownershipTransferMembership(1, 1)->id, 'password' => $this->password])->assertForbidden();
    $this->putJson('/@alpha/settings/ownership', ['membership' => ownershipTransferMembership(2, 2)->id, 'password' => $this->password])->assertNotFound();
    $this->putJson('/@alpha/settings/ownership', ['membership' => 99999, 'password' => $this->password])->assertNotFound();
    $this->deleteJson('/@alpha/settings/people/members/'.ownershipTransferMembership(1, 3)->id)->assertOk();
    $this->putJson('/@alpha/settings/ownership', ['membership' => ownershipTransferMembership(1, 3)->id, 'password' => $this->password])->assertForbidden();
    expect(ownershipTransferRoles(1))->toBe(['owner']);
    expect(DB::table('concierge_ownerships')->where('workspace_id', 1)->count())->toBe(1);
});
test('an owner cannot leave until ownership moves and a member can leave', function () {
    require dirname(__DIR__).'/database/fixture.php';
    ownershipTransferSignIn(1);
    $leave = '/me/workspaces/alpha/membership';

    // The owner has no Leave in the switcher menu or on the chooser (absent, not disabled), and the server refuses it with the reason.
    $this->get('/@alpha/dashboard', ownershipTransferInertia())->assertJsonPath('props.shell.workspace.leave', null);
    $this->getJson('/me/workspaces')->assertOk()->assertJsonPath('workspaces.0.leave', null);
    $this->deleteJson($leave)->assertUnprocessable()->assertJsonValidationErrors('leave');
    expect(Membership::query()->where('workspace_id', 1)->where('user_id', 1)->exists())->toBeTrue();

    // After the transfer the former owner (an admin now) is offered Leave in both places, and leaving removes access.
    ownershipTransferTransfer(3)->assertOk();
    $this->get('/@alpha/dashboard', ownershipTransferInertia())->assertJsonPath('props.shell.workspace.leave.url', $leave)->assertJsonPath('props.shell.workspace.leave.method', 'delete')->assertJsonPath('props.shell.workspace.leave.disabled', false);
    $this->getJson('/me/workspaces')->assertJsonPath('workspaces.0.leave.url', $leave)->assertJsonPath('workspaces.0.leave.disabled', false);
    $this->delete($leave)->assertRedirect('/me/workspaces')->assertStatus(303);
    $this->get('/@alpha/settings/general', ownershipTransferInertia())->assertNotFound();
});
test('leaving a workspace you do not belong to is not found', function () {
    require dirname(__DIR__).'/database/fixture.php';
    ownershipTransferSignIn(3);
    $this->deleteJson('/me/workspaces/missing/membership')->assertNotFound();
    $this->deleteJson('/me/workspaces/beta/membership')->assertNotFound();
});
