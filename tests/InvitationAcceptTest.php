<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tey\Concierge\Actions\AcceptInvitation;
use Tey\Concierge\Actions\InviteMember;
use Tey\Concierge\Concierge;
use Tey\Concierge\Enums\InvitationStatus;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Models\Invitation;
use Tey\Concierge\Models\Membership;
use Tey\Concierge\Support\GrantSet;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\Workspaces\Models\Workspace;

beforeEach(function () {
    config(['app.name' => 'Product name']);
});
function invitationAcceptInertia(): array
{
    return ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
}
function invitationAcceptSignIn(int $id): void
{
    Auth::forgetGuards();
    $password = Str::random(40);
    User::findOrFail($id)->update(['password' => Hash::make($password)]);
    test()->postJson('/login', ['email' => 'actor'.$id.'@example.test', 'password' => $password])->assertOk();
    test()->withCookie(config('session.cookie'), app('session')->getId());
    Auth::forgetGuards();
}
function invitationAcceptSignOut(): void
{
    test()->postJson('/logout')->assertNoContent();
    test()->flushSession();
    test()->forgetCookies();
    Auth::forgetGuards();
}
/** An invitation to Alpha from its owner: the model and the token. */
function invitationAcceptInvite(string $email, string $role = 'viewer'): array
{
    $issued = InviteMember::run(User::findOrFail(1), Workspace::findOrFail(1), $email, new GrantSet([$role]));

    return [$issued->invitation, $issued->token];
}
function invitationAcceptPage(string $token): array
{
    return test()->get('/invitations/'.$token, invitationAcceptInertia())->assertOk()->assertJsonPath('component', 'Auth/Invitation/Show/Page')->json('props');
}
test('a guest sees the workspace and inviter and a new person creates the account through the closed registration', function () {
    require dirname(__DIR__).'/database/fixture.php';
    expect(config('concierge.registration.mode'))->toBe('closed');
    [$invitation, $token] = invitationAcceptInvite('newcomer@example.test', 'admin');
    $props = invitationAcceptPage($token);
    expect($props['state'])->toBe('register_required');
    expect($props['invitation'])->toBe(['workspace' => 'Alpha', 'inviter' => 'Alpha', 'role' => 'Admin', 'email' => 'newcomer@example.test', 'message' => null]);
    expect($props['title'])->toBe('Join Alpha on '.config('app.name'));
    expect($this->get('/invitations/'.$token, invitationAcceptInertia())->json('props.layout.documentTitle'))->toBe('Join Alpha on '.config('app.name').' · '.config('app.name'));

    // The open registration door stays shut; the invitation is the way in, for its own address only.
    $this->postJson('/register', ['name' => 'X', 'email' => 'newcomer@example.test', 'password' => 'password', 'password_confirmation' => 'password'])->assertNotFound();
    $this->postJson('/invitations/'.$token.'/account', ['name' => 'Newcomer'])->assertUnprocessable()->assertJsonValidationErrors('password');
    $this->postJson('/invitations/'.$token.'/account', ['name' => 'Newcomer', 'email' => 'someone-else@example.test', 'password' => 'a-long-enough-password-1', 'password_confirmation' => 'a-long-enough-password-1'])->assertCreated();
    $user = User::query()->where('email', 'newcomer@example.test')->firstOrFail();
    expect(User::query()->where('email', 'someone-else@example.test')->first())->toBeNull('the address comes from the invitation, not the request');
    expect($user->email_verified_at)->not->toBeNull();
    expect(Auth::guard('web')->check())->toBeTrue();

    // Now signed in as the invited address: ready to accept.
    $this->withCookie(config('session.cookie'), app('session')->getId());
    expect(invitationAcceptPage($token)['state'])->toBe('ready');
    $accepted = $this->postJson('/invitations/'.$token)->assertCreated()->assertJsonPath('outcome', 'joined')->assertJsonPath('workspace_id', 1)->json();

    // A new member goes through the welcome steps before their first dashboard.
    expect($accepted['redirect'])->toEndWith('/welcome');
    $membership = Membership::query()->where('workspace_id', 1)->where('user_id', $user->id)->firstOrFail();
    expect($membership->status)->toBe(MembershipStatus::Active);
    expect(app(Concierge::class)->roleNames($membership))->toBe(['admin']);

    // The link is used up.
    invitationAcceptSignOut();
    expect(invitationAcceptPage($token)['state'])->toBe('accepted');
    $this->postJson('/invitations/'.$token.'/account', ['name' => 'Again', 'password' => 'a-long-enough-password-1', 'password_confirmation' => 'a-long-enough-password-1'])->assertUnprocessable();
});
test('an existing person signs in then accepts', function () {
    require dirname(__DIR__).'/database/fixture.php';
    [, $token] = invitationAcceptInvite('actor2@example.test');
    $props = invitationAcceptPage($token);
    expect($props['state'])->toBe('sign_in_required');
    expect($props['login_url'])->toBe('/login');
    expect(session('url.intended'))->toBe(url('/invitations/'.$token), 'signing in comes back to the invitation');

    // They cannot create a second account for an address that has one.
    $this->postJson('/invitations/'.$token.'/account', ['name' => 'Dup', 'password' => 'a-long-enough-password-1', 'password_confirmation' => 'a-long-enough-password-1'])->assertUnprocessable();
    invitationAcceptSignIn(2);
    expect(invitationAcceptPage($token)['state'])->toBe('ready');
    $this->postJson('/invitations/'.$token)->assertCreated();

    // Joined through an invitation: the first dashboard visit goes through the welcome steps.
    $this->get('/@alpha/dashboard', invitationAcceptInertia())->assertRedirect('/welcome');
});
test('the wrong account cannot accept', function () {
    require dirname(__DIR__).'/database/fixture.php';

    // Strict matching (KIT_INVITES_MATCH_EMAIL=true): only the invited address may accept. By default another verified account may join as itself.
    config(['concierge.invitations.match_email' => true]);
    [, $token] = invitationAcceptInvite('someone@example.test');
    invitationAcceptSignIn(2);
    $props = invitationAcceptPage($token);
    expect($props['state'])->toBe('wrong_account');
    expect($props['signed_in_as'])->toBe('actor2@example.test');
    $this->postJson('/invitations/'.$token)->assertStatus(409)->assertJsonPath('error', 'wrong_account');
    expect(Membership::query()->where('workspace_id', 1)->where('user_id', 2)->first())->toBeNull();

    // A signed-in person cannot use the link to create another account.
    $this->postJson('/invitations/'.$token.'/account', ['name' => 'X', 'password' => 'a-long-enough-password-1', 'password_confirmation' => 'a-long-enough-password-1'])->assertForbidden();
});
test('expired revoked used and unknown links say so', function () {
    require dirname(__DIR__).'/database/fixture.php';
    [$expired, $expiredToken] = invitationAcceptInvite('late@example.test');
    $expired->forceFill(['expires_at' => now()->subDay()])->save();
    $props = invitationAcceptPage($expiredToken);
    expect($props['state'])->toBe('expired');
    $this->assertStringContainsString('expired', $props['message']);
    $this->postJson('/invitations/'.$expiredToken.'/account', ['name' => 'L', 'password' => 'a-long-enough-password-1', 'password_confirmation' => 'a-long-enough-password-1'])->assertUnprocessable();
    [$revoked, $revokedToken] = invitationAcceptInvite('gone@example.test');
    $revoked->forceFill(['status' => InvitationStatus::Revoked, 'pending_key' => null])->save();
    expect(invitationAcceptPage($revokedToken)['state'])->toBe('revoked');
    $this->assertStringContainsString('withdrawn', invitationAcceptPage($revokedToken)['message']);
    [, $usedToken] = invitationAcceptInvite('actor3x@example.test');
    User::create(['id' => 7, 'name' => 'Seven', 'email' => 'actor3x@example.test', 'email_verified_at' => now()]);
    AcceptInvitation::run(User::findOrFail(7), $usedToken);
    expect(invitationAcceptPage($usedToken)['state'])->toBe('accepted');
    $unknown = invitationAcceptPage(Str::random(43));
    expect($unknown['state'])->toBe('invalid');
    expect($unknown['invitation'])->toBeNull('an unknown link reveals nothing');
    $this->postJson('/invitations/'.Str::random(43))->assertUnauthorized();
    invitationAcceptSignIn(2);
    $this->postJson('/invitations/'.$expiredToken)->assertStatus(409)->assertJsonPath('error', 'expired');
    $this->postJson('/invitations/'.Str::random(43))->assertStatus(409)->assertJsonPath('error', 'invalid');
});
test('people shows the invitation link and it works', function () {
    require dirname(__DIR__).'/database/fixture.php';
    invitationAcceptSignIn(1);
    $created = $this->postJson('/@alpha/settings/people/invitations', ['email' => 'link@example.test', 'role' => 'viewer'])->assertCreated()->json();
    expect($created['link'])->toMatch('#/invitations/[A-Za-z0-9_-]{43}$#');
    invitationAcceptSignOut();
    expect(invitationAcceptPage(basename($created['link']))['state'])->toBe('register_required');
});
