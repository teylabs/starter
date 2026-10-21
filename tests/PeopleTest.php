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

    private function people(string $query = ''): array
    {
        return $this->getJson('/@alpha/settings/people'.$query)->assertOk()->json();
    }

    /** @return list<array<string, mixed>> */
    private function rows(string $query = ''): array
    {
        return $this->people($query)['table']['rows'];
    }

    private function names(array $rows): array
    {
        return array_column(array_column($rows, 'member'), 'name');
    }

    /** @return list<?string> the keys of the actions a row carries, by row */
    private function actionKeys(array $rows): array
    {
        return array_map(fn (array $row) => implode(',', array_column($row['actions'], 'key')) ?: null, $rows);
    }

    public function test_the_owner_sees_the_members_and_the_pending_invitations(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->signIn(1);
        $page = $this->get('/@alpha/settings/people', $this->inertia())->assertOk()->assertJsonPath('component', 'Workspace/Settings/People/Page')
            ->assertJsonPath('props.title', 'People')->assertJsonPath('props.layout.breadcrumbs.2.label', 'People')->assertJsonPath('props.layout.documentTitle', 'People · Settings · Product name')->json('props');
        $rows = $page['table']['rows'];
        $this->assertSame('members', $page['tab']);
        $this->assertSame(['Alpha', 'Viewer'], $this->names($rows));
        $this->assertSame(['Owner', 'Viewer'], array_map(fn (array $row) => $row['roles'][0]['label'], $rows));
        $this->assertSame([null, 'change-role,transfer-ownership,remove'], $this->actionKeys($rows), 'the owner has no actions; the viewer can be re-roled, made owner and removed (Concierge decides, by rank)');
        $this->assertSame([true, false], array_map(fn (array $row) => $row['member']['is_you'], $rows));
        $this->assertNull($rows[0]['member']['avatar']);
        $this->assertNotNull($rows[1]['joined']);
        $this->assertSame(['put', 'post', 'delete'], array_column($rows[1]['actions'], 'method'));
        $this->assertNull($rows[1]['actions'][0]['confirm'], 'a role change does not ask');
        $this->assertSame('Remove member', $rows[1]['actions'][2]['confirm']['label'], 'removing asks first');
        $this->assertTrue($rows[1]['actions'][2]['destructive']);
        // The grantable roles come from Concierge's `grantable_by`: an owner grants admin and viewer, never owner.
        $this->assertSame(['admin', 'viewer'], array_column($page['roles'], 'value'));
        $this->assertSame('Invite member', $page['invite']['label']);
        $this->assertSame('get', $page['invite']['method']);
        $this->post('/@alpha/settings/people/invitations', ['email' => 'new@example.test', 'role' => 'admin'], ['Accept' => 'application/json'])->assertCreated();
        $invitations = $this->people('?tab=invitations');
        $this->assertSame([['new@example.test', 'Admin']], array_map(fn (array $row): array => [$row['email'], $row['roles'][0]['label']], $invitations['table']['rows']));
        $this->assertSame(['resend,revoke'], $this->actionKeys($invitations['table']['rows']));
        // Search and sort are the server's, through the query string, and come back in the table.
        $this->getJson('/@alpha/settings/people?filter[search]=view')->assertJsonCount(1, 'table.rows')->assertJsonPath('table.rows.0.member.name', 'Viewer')->assertJsonPath('table.search', 'view');
        $this->getJson('/@alpha/settings/people?sort=-name')->assertJsonPath('table.rows.0.member.name', 'Viewer')->assertJsonPath('table.sort.direction', 'desc');
        $this->getJson('/@alpha/settings/people?sort=password')->assertStatus(400);
        // The section is listed on the settings landing page, in the Workspace group, between General and Connections.
        $keys = array_column($this->get('/@alpha/settings', $this->inertia())->json('props.groups.0.sections'), 'key');
        $this->assertSame(['general', 'people'], $keys);
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
        $id = $this->rows('?tab=invitations')[0]['id'];
        // Resend is Concierge's: a fresh link, after its cooldown.
        $resent = $this->putJson('/@alpha/settings/people/invitations/'.$id);
        $this->assertContains($resent->getStatusCode(), [200, 403], 'resent, or held by the cooldown');
        $this->deleteJson('/@alpha/settings/people/invitations/'.$id)->assertOk()->assertJsonPath('revoked', true);
        $this->assertSame([], $this->rows('?tab=invitations'));
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
        $this->assertSame(['Owner', 'Admin'], array_map(fn (array $row) => $row['roles'][0]['label'], $this->rows()));
        $this->putJson($url, ['role' => 'owner'])->assertForbidden();
        $this->putJson($url, ['role' => 'nonsense'])->assertForbidden();
        $this->putJson($url, [])->assertUnprocessable();
        // A membership of another workspace is not found here.
        $this->putJson('/@alpha/settings/people/members/'.$this->membership(2, 2)->id, ['role' => 'viewer'])->assertNotFound();
        $this->deleteJson($url)->assertOk()->assertJsonPath('removed', true);
        $this->assertSame(MembershipStatus::Removed, $viewer->fresh()->status);
        $this->assertSame(['Alpha'], $this->names($this->rows()));
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
        $this->assertSame([null, null], $this->actionKeys($page['table']['rows']), 'an admin has no action on the owner, or on themselves');
        $this->assertSame(['viewer'], array_column($page['roles'], 'value'), 'an admin grants viewer only');
        $this->assertSame('get', $page['invite']['method']);
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
        $this->assertSame([null, null, null], $this->actionKeys($this->rows()), 'no viewer is left to act on: the owner, the other admin and themselves have none');
    }

    public function test_the_tabs_carry_server_counts_that_follow_the_search_and_the_tab_is_in_the_url(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->signIn(1);
        $this->postJson('/@alpha/settings/people/invitations', ['email' => 'zed@example.test', 'role' => 'viewer'])->assertCreated();
        $this->postJson('/@alpha/settings/people/invitations', ['email' => 'amy@example.test', 'role' => 'viewer'])->assertCreated();
        $page = $this->people();
        $this->assertSame([['members', 'Members', 2, true], ['invitations', 'Invitations', 2, false]], array_map(fn (array $tab) => [$tab['key'], $tab['label'], $tab['count'], $tab['current']], $page['table']['tabs']));
        $this->assertSame('/settings/people?tab=invitations', $page['table']['tabs'][1]['url']);
        // The invitations tab is `?tab=invitations`: its own columns and rows, and the current mark moves.
        $invitations = $this->people('?tab=invitations');
        $this->assertSame('invitations', $invitations['tab']);
        $this->assertSame(['amy@example.test', 'zed@example.test'], array_column($this->people('?tab=invitations&sort=email')['table']['rows'], 'email'));
        $this->assertSame(['email', 'roles', 'invitedBy', 'sent', 'expires', 'actions'], array_column($invitations['table']['columns'], 'key'));
        $this->assertSame([false, true], array_column($invitations['table']['tabs'], 'current'));
        // Counts follow the search: "zed" matches one invitation and no member, and the tab links keep the search.
        $searched = $this->people('?tab=invitations&filter[search]=zed');
        $this->assertSame(['zed@example.test'], array_column($searched['table']['rows'], 'email'));
        $this->assertSame([0, 1], array_column($searched['table']['tabs'], 'count'));
        $this->assertStringContainsString('filter%5Bsearch%5D=zed', $searched['table']['tabs'][0]['url']);
        $this->getJson('/@alpha/settings/people?tab=nonsense')->assertStatus(400);
        // Sorting an invitations column is honoured; a members-only sort is refused there.
        $this->assertSame(['zed@example.test', 'amy@example.test'], array_column($this->people('?tab=invitations&sort=-email')['table']['rows'], 'email'));
        $this->getJson('/@alpha/settings/people?tab=invitations&sort=joined')->assertStatus(400);
        $this->getJson('/@alpha/settings/people?filter[nonsense]=1')->assertStatus(400);
    }

    public function test_the_empty_states_are_decided_by_the_server_and_the_page_is_capped(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->signIn(1);
        $this->assertSame('first-use', $this->people('?tab=invitations')['table']['emptyState']);
        $this->assertNull($this->people()['table']['emptyState']);
        $noResults = $this->people('?filter[search]=zzz');
        $this->assertSame('no-results', $noResults['table']['emptyState']);
        $this->assertSame([], $noResults['table']['rows']);
        $pagination = $this->people('?per_page=1&page=2')['table']['pagination'];
        $this->assertSame([2, 1, 2, 2, 2, 2], [$pagination['page'], $pagination['perPage'], $pagination['total'], $pagination['from'], $pagination['to'], $pagination['lastPage']]);
        $this->assertSame(100, $this->people('?per_page=5000')['table']['pagination']['perPage']);
    }

    public function test_the_table_never_lists_another_workspaces_people_or_invitations(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->signIn(2);
        $this->postJson('/@beta/settings/people/invitations', ['email' => 'b-only@example.test', 'role' => 'viewer'])->assertCreated();
        $this->signOut();
        $this->signIn(1);
        $this->assertSame([], $this->rows('?tab=invitations'));
        $this->assertNotContains('Beta', $this->names($this->rows()));
        $this->assertSame([2, 0], array_column($this->people()['table']['tabs'], 'count'));
        $this->getJson('/@alpha/settings/people?filter[search]=b-only')->assertJsonPath('table.tabs.1.count', 0);
    }

    public function test_the_invite_and_change_role_modals_are_server_routes_with_the_pages_authorisation(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->signIn(1);
        // Opened directly, the modal URL renders People with the modal's data on it; fetched as a modal it is the modal page alone.
        $direct = $this->get('/@alpha/settings/people/invitations', $this->inertia())->assertOk()->assertJsonPath('component', 'Workspace/Settings/People/Page')->assertJsonPath('props._inertiaui_modal.component', 'Workspace/Settings/People/Invite/Page');
        $this->assertSame('/settings/people', $direct->json('props._inertiaui_modal.baseUrl'));
        $modal = $this->get('/@alpha/settings/people/invitations', [...$this->inertia(), 'X-InertiaUI-Modal' => 'x'])->assertOk()->assertJsonPath('component', 'Workspace/Settings/People/Invite/Page');
        $this->assertSame(['admin', 'viewer'], array_column($modal->json('props.roles'), 'value'));
        $this->assertSame(['Send invitation', 'post'], [$modal->json('props.submit.label'), $modal->json('props.submit.method')]);
        // The opener is the server's descriptor: a GET to the same URL the invitation is posted to.
        $this->assertSame('get', $this->people()['invite']['method']);
        // Change role: the member's current role, the roles the viewer may give, and the PUT the existing endpoint takes.
        $viewer = $this->membership(1, 3);
        $role = $this->get('/@alpha/settings/people/members/'.$viewer->id, [...$this->inertia(), 'X-InertiaUI-Modal' => 'x'])->assertOk()->assertJsonPath('component', 'Workspace/Settings/People/ChangeRole/Page');
        $this->assertSame(['viewer', 'put'], [$role->json('props.role'), $role->json('props.submit.method')]);
        // Refusals are the page's own: the owner's row has no role change, another workspace's member is not found, a viewer cannot open either modal.
        $this->get('/@alpha/settings/people/members/'.$this->membership(1, 1)->id, [...$this->inertia(), 'X-InertiaUI-Modal' => 'x'])->assertForbidden();
        $this->get('/@alpha/settings/people/members/'.$this->membership(2, 2)->id, [...$this->inertia(), 'X-InertiaUI-Modal' => 'x'])->assertNotFound();
        $this->signOut();
        $this->signIn(3);
        $this->get('/@alpha/settings/people/invitations', [...$this->inertia(), 'X-InertiaUI-Modal' => 'x'])->assertForbidden();
        $this->get('/@alpha/settings/people/members/'.$viewer->id, [...$this->inertia(), 'X-InertiaUI-Modal' => 'x'])->assertForbidden();
    }
}
