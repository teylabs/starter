<?php

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;
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

require dirname(__DIR__).'/vendor/autoload.php';

/** Settings › People: the members and open invitations, invite, change role, remove, resend and revoke, with Concierge's rules enforced on the server. */
final class PeopleTest extends TestCase
{
    public function createApplication()
    {
        return require __DIR__.'/application.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
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
    }

    private function membership(int $workspace, int $user): Membership
    {
        return Membership::query()->where('workspace_id', $workspace)->where('user_id', $user)->firstOrFail();
    }

    private function people(): array
    {
        return $this->getJson('/@alpha/settings/people')->assertOk()->json();
    }

    public function test_the_owner_sees_the_members_and_the_pending_invitations(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->signIn(1);
        $page = $this->get('/@alpha/settings/people', $this->inertia())->assertOk()->assertJsonPath('component', 'Workspace/Settings/People/Page')
            ->assertJsonPath('props.title', 'People')->assertJsonPath('props.layout.breadcrumbs.2.label', 'People')->assertJsonPath('props.layout.documentTitle', 'People · Settings · Product name')->json('props');
        $this->assertSame(['Alpha', 'Viewer'], array_column($page['members'], 'name'));
        $this->assertSame(['Owner', 'Viewer'], array_column($page['members'], 'role_label'));
        $this->assertSame([null, 'put'], array_map(fn (array $member) => $member['change_role']['method'] ?? null, $page['members']), 'the owner cannot be managed; the viewer can');
        $this->assertSame([null, 'delete'], array_map(fn (array $member) => $member['remove']['method'] ?? null, $page['members']));
        $this->assertSame([true, false], array_column($page['members'], 'is_you'));
        $this->assertNull($page['members'][0]['avatar']);
        $this->assertNotNull($page['members'][1]['joined_at']);
        // The grantable roles come from Concierge's `grantable_by`: an owner grants admin and viewer, never owner.
        $this->assertSame(['admin', 'viewer'], array_column($page['roles'], 'value'));
        $this->assertFalse($page['invite']['disabled']);
        $this->assertSame('post', $page['invite']['method']);
        $this->post('/@alpha/settings/people/invitations', ['email' => 'new@example.test', 'role' => 'admin'], ['Accept' => 'application/json'])->assertCreated();
        $this->assertSame([['new@example.test', 'Admin']], array_map(fn (array $invitation): array => [$invitation['email'], $invitation['role_label']], $this->people()['invitations']));
        // Search and sort are the server's, through the query string.
        $this->getJson('/@alpha/settings/people?filter[search]=view')->assertJsonCount(1, 'members')->assertJsonPath('members.0.name', 'Viewer');
        $this->getJson('/@alpha/settings/people?sort=-name')->assertJsonPath('members.0.name', 'Viewer');
        $this->getJson('/@alpha/settings/people?sort=password')->assertStatus(400);
        // The section is listed on the settings landing page, in the Workspace group, between General and Connections.
        $keys = array_column($this->get('/@alpha/settings', $this->inertia())->json('props.groups.0.sections'), 'key');
        $this->assertSame(['general', 'people', 'connections'], $keys);
    }

    public function test_a_viewer_is_refused_and_does_not_see_the_section(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->signIn(3);
        $this->getJson('/@alpha/settings/people')->assertForbidden();
        $this->postJson('/@alpha/settings/people/invitations', ['email' => 'x@example.test', 'role' => 'viewer'])->assertForbidden();
        $owner = $this->membership(1, 1);
        $this->putJson('/@alpha/settings/people/members/'.$owner->id, ['role' => 'viewer'])->assertForbidden();
        $this->deleteJson('/@alpha/settings/people/members/'.$owner->id)->assertForbidden();
        $this->assertSame(MembershipStatus::Active, $this->membership(1, 1)->fresh()->status);
        $keys = array_column($this->get('/@alpha/settings', $this->inertia())->json('props.groups.0.sections'), 'key');
        $this->assertNotContains('people', $keys);
        $this->assertArrayNotHasKey('can', $this->get('/@alpha/settings/general', $this->inertia())->json('props'));
    }

    public function test_invite_resend_and_revoke(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->signIn(1);
        $this->postJson('/@alpha/settings/people/invitations', ['email' => 'New@Example.test', 'role' => 'viewer'])->assertCreated()->assertJsonPath('email', 'new@example.test');
        // Already pending, already a member, an unknown or ungrantable role, and a bad address are all refused.
        $this->postJson('/@alpha/settings/people/invitations', ['email' => 'new@example.test', 'role' => 'viewer'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/@alpha/settings/people/invitations', ['email' => 'actor3@example.test', 'role' => 'viewer'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/@alpha/settings/people/invitations', ['email' => 'o@example.test', 'role' => 'owner'])->assertForbidden();
        $this->postJson('/@alpha/settings/people/invitations', ['email' => 'o@example.test', 'role' => 'nonsense'])->assertForbidden();
        $this->postJson('/@alpha/settings/people/invitations', ['email' => 'not-an-email', 'role' => 'viewer'])->assertUnprocessable();
        $id = $this->people()['invitations'][0]['id'];
        // Resend is Concierge's: a fresh link, after its cooldown.
        $resent = $this->putJson('/@alpha/settings/people/invitations/'.$id);
        $this->assertContains($resent->getStatusCode(), [200, 403], 'resent, or held by the cooldown');
        $this->deleteJson('/@alpha/settings/people/invitations/'.$id)->assertOk()->assertJsonPath('revoked', true);
        $this->assertSame([], $this->people()['invitations']);
        $this->assertSame(InvitationStatus::Revoked, Invitation::query()->where('public_id', $id)->firstOrFail()->status);
        // An invitation of another workspace is not found here.
        $this->signOut();
        $this->signIn(2);
        $this->postJson('/@beta/settings/people/invitations', ['email' => 'b@example.test', 'role' => 'viewer'])->assertCreated();
        $other = Invitation::query()->where('workspace_id', 2)->firstOrFail();
        $this->signOut();
        $this->signIn(1);
        $this->deleteJson('/@alpha/settings/people/invitations/'.$other->public_id)->assertNotFound();
    }

    public function test_change_role_and_remove(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->signIn(1);
        $viewer = $this->membership(1, 3);
        $url = '/@alpha/settings/people/members/'.$viewer->id;
        $this->putJson($url, ['role' => 'admin'])->assertOk()->assertJsonPath('role', 'admin');
        $this->assertSame(['Owner', 'Admin'], array_column($this->people()['members'], 'role_label'));
        $this->putJson($url, ['role' => 'owner'])->assertForbidden();
        $this->putJson($url, ['role' => 'nonsense'])->assertForbidden();
        $this->putJson($url, [])->assertUnprocessable();
        // A membership of another workspace is not found here.
        $this->putJson('/@alpha/settings/people/members/'.$this->membership(2, 2)->id, ['role' => 'viewer'])->assertNotFound();
        $this->deleteJson($url)->assertOk()->assertJsonPath('removed', true);
        $this->assertSame(MembershipStatus::Removed, $viewer->fresh()->status);
        $this->assertSame(['Alpha'], array_column($this->people()['members'], 'name'));
        // The removed user no longer reaches the workspace.
        $this->signOut();
        $this->signIn(3);
        $this->assertContains($this->getJson('/@alpha/settings/people')->getStatusCode(), [403, 404]);
    }

    public function test_the_last_owner_cannot_be_removed_or_demoted_and_ranks_are_respected(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->signIn(1);
        $owner = $this->membership(1, 1);
        // The owner cannot manage their own membership, so the only owner can neither leave nor be demoted through the list.
        $this->deleteJson('/@alpha/settings/people/members/'.$owner->id)->assertForbidden();
        $this->putJson('/@alpha/settings/people/members/'.$owner->id, ['role' => 'viewer'])->assertForbidden();
        $this->assertSame(['owner'], app(Concierge::class)->roleNames($owner->fresh()));
        $this->assertSame(MembershipStatus::Active, $owner->fresh()->status);
        // An admin manages people below them: Concierge's rank rules still stop them touching the owner (or another admin).
        $this->putJson('/@alpha/settings/people/members/'.$this->membership(1, 3)->id, ['role' => 'admin'])->assertOk();
        $this->signOut();
        $this->signIn(3);
        $page = $this->getJson('/@alpha/settings/people')->assertOk()->json();
        $this->assertSame([null, null], array_column($page['members'], 'change_role'), 'an admin cannot manage the owner, or themselves');
        $this->assertSame([null, null], array_column($page['members'], 'remove'));
        $this->assertSame(['viewer'], array_column($page['roles'], 'value'), 'an admin grants viewer only');
        $this->assertFalse($page['invite']['disabled']);
        $this->assertSame('post', $page['invite']['method']);
        $this->deleteJson('/@alpha/settings/people/members/'.$owner->id)->assertForbidden();
        $this->putJson('/@alpha/settings/people/members/'.$owner->id, ['role' => 'viewer'])->assertForbidden();
        $this->assertSame(['owner'], app(Concierge::class)->roleNames($owner->fresh()));
        $this->assertSame(MembershipStatus::Active, $owner->fresh()->status);
    }

    public function test_an_admin_can_invite_and_remove_a_viewer_but_not_change_an_owner_or_another_admin(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        foreach ([4 => 'admin', 5 => 'viewer'] as $id => $role) {
            User::create(['id' => $id, 'name' => 'Person '.$id, 'email' => 'actor'.$id.'@example.test', 'email_verified_at' => now()]);
            $invite = InviteMember::run(User::findOrFail(1), Workspace::findOrFail(1), 'actor'.$id.'@example.test', new GrantSet([$role]));
            AcceptInvitation::run(User::findOrFail($id), $invite->token);
        }
        $this->signIn(1);
        $this->putJson('/@alpha/settings/people/members/'.$this->membership(1, 3)->id, ['role' => 'admin'])->assertOk();
        $this->signOut();
        $this->signIn(3);
        // An admin invites a viewer, and removes one.
        $this->postJson('/@alpha/settings/people/invitations', ['email' => 'fresh@example.test', 'role' => 'viewer'])->assertCreated();
        $viewer = $this->membership(1, 5);
        $this->deleteJson('/@alpha/settings/people/members/'.$viewer->id)->assertOk();
        $this->assertSame(MembershipStatus::Removed, $viewer->fresh()->status);
        // They cannot grant admin or owner, change the owner, or manage another admin (equal rank).
        $this->postJson('/@alpha/settings/people/invitations', ['email' => 'second@example.test', 'role' => 'admin'])->assertForbidden();
        $this->postJson('/@alpha/settings/people/invitations', ['email' => 'second@example.test', 'role' => 'owner'])->assertForbidden();
        $owner = $this->membership(1, 1);
        $otherAdmin = $this->membership(1, 4);
        foreach ([$owner, $otherAdmin] as $target) {
            $this->putJson('/@alpha/settings/people/members/'.$target->id, ['role' => 'viewer'])->assertForbidden();
            $this->deleteJson('/@alpha/settings/people/members/'.$target->id)->assertForbidden();
        }
        $this->assertSame(['owner'], app(Concierge::class)->roleNames($owner->fresh()));
        $this->assertSame(['admin'], app(Concierge::class)->roleNames($otherAdmin->fresh()));
        // The page offers an admin what they may do: remove and re-role only the viewers.
        $manageable = collect($this->getJson('/@alpha/settings/people')->json('members'))->where('manageable', true)->pluck('name')->all();
        $this->assertSame([], $manageable, 'no viewer is left to manage');
    }
}
