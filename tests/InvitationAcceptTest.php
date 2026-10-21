<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;
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

require dirname(__DIR__).'/vendor/autoload.php';
require_once __DIR__.'/TestCase.php';

/** The invitation link: a guest sees the workspace and who invited them, then signs in or creates the account the invitation is for (registration stays closed), then accepts. */
final class InvitationAcceptTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.name' => 'Product name']);
    }

    private function inertia(): array
    {
        return ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
    }

    private function signIn(int $id): void
    {
        Auth::forgetGuards();
        $password = Str::random(40);
        User::findOrFail($id)->update(['password' => Hash::make($password)]);
        $this->postJson('/login', ['email' => 'actor'.$id.'@example.test', 'password' => $password])->assertOk();
        $this->withCookie(config('session.cookie'), app('session')->getId());
        Auth::forgetGuards();
    }

    private function signOut(): void
    {
        $this->postJson('/logout')->assertNoContent();
        $this->flushSession();
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
        Auth::forgetGuards();
    }

    /** An invitation to Alpha from its owner: the model and the token. */
    private function invite(string $email, string $role = 'viewer'): array
    {
        $issued = InviteMember::run(User::findOrFail(1), Workspace::findOrFail(1), $email, new GrantSet([$role]));

        return [$issued->invitation, $issued->token];
    }

    private function page(string $token): array
    {
        return $this->get('/invitations/'.$token, $this->inertia())->assertOk()->assertJsonPath('component', 'Auth/Invitation/Show/Page')->json('props');
    }

    public function test_a_guest_sees_the_workspace_and_inviter_and_a_new_person_creates_the_account_through_the_closed_registration(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->assertSame('closed', config('concierge.registration.mode'));
        [$invitation, $token] = $this->invite('newcomer@example.test', 'admin');
        $props = $this->page($token);
        $this->assertSame('register_required', $props['state']);
        $this->assertSame(['workspace' => 'Alpha', 'inviter' => 'Alpha', 'role' => 'Admin', 'email' => 'newcomer@example.test', 'message' => null], $props['invitation']);
        $this->assertSame('Join Alpha on '.config('app.name'), $props['title']);
        $this->assertSame('Join Alpha on '.config('app.name').' · '.config('app.name'), $this->get('/invitations/'.$token, $this->inertia())->json('props.layout.documentTitle'));
        // The open registration door stays shut; the invitation is the way in, for its own address only.
        $this->postJson('/register', ['name' => 'X', 'email' => 'newcomer@example.test', 'password' => 'password', 'password_confirmation' => 'password'])->assertNotFound();
        $this->postJson('/invitations/'.$token.'/account', ['name' => 'Newcomer'])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/invitations/'.$token.'/account', ['name' => 'Newcomer', 'email' => 'someone-else@example.test', 'password' => 'a-long-enough-password-1', 'password_confirmation' => 'a-long-enough-password-1'])->assertCreated();
        $user = User::query()->where('email', 'newcomer@example.test')->firstOrFail();
        $this->assertNull(User::query()->where('email', 'someone-else@example.test')->first(), 'the address comes from the invitation, not the request');
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Auth::guard('web')->check());
        // Now signed in as the invited address: ready to accept.
        $this->withCookie(config('session.cookie'), app('session')->getId());
        $this->assertSame('ready', $this->page($token)['state']);
        $accepted = $this->postJson('/invitations/'.$token)->assertCreated()->assertJsonPath('outcome', 'joined')->assertJsonPath('workspace_id', 1)->json();
        // A new member goes through the welcome steps before their first dashboard.
        $this->assertStringEndsWith('/welcome', $accepted['redirect']);
        $membership = Membership::query()->where('workspace_id', 1)->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(MembershipStatus::Active, $membership->status);
        $this->assertSame(['admin'], app(Concierge::class)->roleNames($membership));
        // The link is used up.
        $this->signOut();
        $this->assertSame('accepted', $this->page($token)['state']);
        $this->postJson('/invitations/'.$token.'/account', ['name' => 'Again', 'password' => 'a-long-enough-password-1', 'password_confirmation' => 'a-long-enough-password-1'])->assertUnprocessable();
    }

    public function test_an_existing_person_signs_in_then_accepts(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        [, $token] = $this->invite('actor2@example.test');
        $props = $this->page($token);
        $this->assertSame('sign_in_required', $props['state']);
        $this->assertSame('/login', $props['login_url']);
        $this->assertSame(url('/invitations/'.$token), session('url.intended'), 'signing in comes back to the invitation');
        // They cannot create a second account for an address that has one.
        $this->postJson('/invitations/'.$token.'/account', ['name' => 'Dup', 'password' => 'a-long-enough-password-1', 'password_confirmation' => 'a-long-enough-password-1'])->assertUnprocessable();
        $this->signIn(2);
        $this->assertSame('ready', $this->page($token)['state']);
        $this->postJson('/invitations/'.$token)->assertCreated();
        // Joined through an invitation: the first dashboard visit goes through the welcome steps.
        $this->get('/@alpha/dashboard', $this->inertia())->assertRedirect('/welcome');
    }

    public function test_the_wrong_account_cannot_accept(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        // Strict matching (KIT_INVITES_MATCH_EMAIL=true): only the invited address may accept. By default another verified account may join as itself.
        config(['concierge.invitations.match_email' => true]);
        [, $token] = $this->invite('someone@example.test');
        $this->signIn(2);
        $props = $this->page($token);
        $this->assertSame('wrong_account', $props['state']);
        $this->assertSame('actor2@example.test', $props['signed_in_as']);
        $this->postJson('/invitations/'.$token)->assertStatus(409)->assertJsonPath('error', 'wrong_account');
        $this->assertNull(Membership::query()->where('workspace_id', 1)->where('user_id', 2)->first());
        // A signed-in person cannot use the link to create another account.
        $this->postJson('/invitations/'.$token.'/account', ['name' => 'X', 'password' => 'a-long-enough-password-1', 'password_confirmation' => 'a-long-enough-password-1'])->assertForbidden();
    }

    public function test_expired_revoked_used_and_unknown_links_say_so(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        [$expired, $expiredToken] = $this->invite('late@example.test');
        $expired->forceFill(['expires_at' => now()->subDay()])->save();
        $props = $this->page($expiredToken);
        $this->assertSame('expired', $props['state']);
        $this->assertStringContainsString('expired', $props['message']);
        $this->postJson('/invitations/'.$expiredToken.'/account', ['name' => 'L', 'password' => 'a-long-enough-password-1', 'password_confirmation' => 'a-long-enough-password-1'])->assertUnprocessable();
        [$revoked, $revokedToken] = $this->invite('gone@example.test');
        $revoked->forceFill(['status' => InvitationStatus::Revoked, 'pending_key' => null])->save();
        $this->assertSame('revoked', $this->page($revokedToken)['state']);
        $this->assertStringContainsString('withdrawn', $this->page($revokedToken)['message']);
        [, $usedToken] = $this->invite('actor3x@example.test');
        User::create(['id' => 7, 'name' => 'Seven', 'email' => 'actor3x@example.test', 'email_verified_at' => now()]);
        AcceptInvitation::run(User::findOrFail(7), $usedToken);
        $this->assertSame('accepted', $this->page($usedToken)['state']);
        $unknown = $this->page(Str::random(43));
        $this->assertSame('invalid', $unknown['state']);
        $this->assertNull($unknown['invitation'], 'an unknown link reveals nothing');
        $this->postJson('/invitations/'.Str::random(43))->assertUnauthorized();
        $this->signIn(2);
        $this->postJson('/invitations/'.$expiredToken)->assertStatus(409)->assertJsonPath('error', 'expired');
        $this->postJson('/invitations/'.Str::random(43))->assertStatus(409)->assertJsonPath('error', 'invalid');
    }

    public function test_people_shows_the_invitation_link_and_it_works(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->signIn(1);
        $created = $this->postJson('/@alpha/settings/people/invitations', ['email' => 'link@example.test', 'role' => 'viewer'])->assertCreated()->json();
        $this->assertMatchesRegularExpression('#/invitations/[A-Za-z0-9_-]{43}$#', $created['link']);
        $this->signOut();
        $this->assertSame('register_required', $this->page(basename($created['link']))['state']);
    }
}
