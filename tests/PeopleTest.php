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
function peopleInertia(): array
{
    return ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
}
function peopleSignIn(int $id): void
{
    Auth::forgetGuards();
    $password = Str::random(40);
    User::findOrFail($id)->update(['password' => Hash::make($password)]);
    test()->postJson('/login', ['email' => 'actor'.$id.'@example.test', 'password' => $password])->assertOk();
    test()->withCookie(config('session.cookie'), app('session')->getId());
    Auth::forgetGuards();
}
function peopleSignOut(): void
{
    test()->postJson('/logout')->assertNoContent();
    test()->flushSession();
}
function peopleMembership(int $workspace, int $user): Membership
{
    return Membership::query()->where('workspace_id', $workspace)->where('user_id', $user)->firstOrFail();
}
function peoplePeople(string $query = ''): array
{
    return test()->getJson('/@alpha/settings/people'.$query)->assertOk()->json();
}
/** @return list<array<string, mixed>> */
function peopleRows(string $query = ''): array
{
    return peoplePeople($query)['table']['rows'];
}
function peopleNames(array $rows): array
{
    return array_column(array_column($rows, 'member'), 'name');
}
/** @return list<?string> the keys of the actions a row carries, by row */
function peopleActionKeys(array $rows): array
{
    return array_map(fn (array $row) => implode(',', array_column($row['actions'], 'key')) ?: null, $rows);
}
test('the owner sees the members and the pending invitations', function () {
    require dirname(__DIR__).'/database/fixture.php';
    peopleSignIn(1);
    $page = $this->get('/@alpha/settings/people', peopleInertia())->assertOk()->assertJsonPath('component', 'Workspace/Settings/People/Page')
        ->assertJsonPath('props.title', 'People')->assertJsonPath('props.layout.breadcrumbs.2.label', 'People')->assertJsonPath('props.layout.documentTitle', 'People · Settings · Product name')->json('props');
    $rows = $page['table']['rows'];
    expect($page['tab'])->toBe('members');
    expect(peopleNames($rows))->toBe(['Alpha', 'Viewer']);
    expect(array_map(fn (array $row) => $row['roles'][0]['label'], $rows))->toBe(['Owner', 'Viewer']);
    expect(peopleActionKeys($rows))->toBe([null, 'change-role,transfer-ownership,remove'], 'the owner has no actions; the viewer can be re-roled, made owner and removed (Concierge decides, by rank)');
    expect(array_map(fn (array $row) => $row['member']['is_you'], $rows))->toBe([true, false]);
    expect($rows[0]['member']['avatar'])->toBeNull();
    expect($rows[1]['joined'])->not->toBeNull();
    expect(array_column($rows[1]['actions'], 'method'))->toBe(['put', 'post', 'delete']);
    expect($rows[1]['actions'][0]['confirm'])->toBeNull('a role change does not ask');
    expect($rows[1]['actions'][2]['confirm']['label'])->toBe('Remove member', 'removing asks first');
    expect($rows[1]['actions'][2]['destructive'])->toBeTrue();

    // The grantable roles come from Concierge's `grantable_by`: an owner grants admin and viewer, never owner.
    expect(array_column($page['roles'], 'value'))->toBe(['admin', 'viewer']);
    expect($page['invite']['label'])->toBe('Invite member');
    expect($page['invite']['method'])->toBe('get');
    $this->post('/@alpha/settings/people/invitations', ['email' => 'new@example.test', 'role' => 'admin'], ['Accept' => 'application/json'])->assertCreated();
    $invitations = peoplePeople('?tab=invitations');
    expect(array_map(fn (array $row): array => [$row['email'], $row['roles'][0]['label']], $invitations['table']['rows']))->toBe([['new@example.test', 'Admin']]);
    expect(peopleActionKeys($invitations['table']['rows']))->toBe(['resend,revoke']);

    // Search and sort are the server's, through the query string, and come back in the table.
    $this->getJson('/@alpha/settings/people?filter[search]=view')->assertJsonCount(1, 'table.rows')->assertJsonPath('table.rows.0.member.name', 'Viewer')->assertJsonPath('table.search', 'view');
    $this->getJson('/@alpha/settings/people?sort=-name')->assertJsonPath('table.rows.0.member.name', 'Viewer')->assertJsonPath('table.sort.direction', 'desc');
    $this->getJson('/@alpha/settings/people?sort=password')->assertStatus(400);

    // The section is listed on the settings landing page, in the Workspace group, between General and Connections.
    $keys = array_column($this->get('/@alpha/settings', peopleInertia())->json('props.groups.0.sections'), 'key');
    expect($keys)->toBe(['general', 'people']);
});
test('a viewer is refused and does not see the section', function () {
    require dirname(__DIR__).'/database/fixture.php';
    peopleSignIn(3);
    $this->getJson('/@alpha/settings/people')->assertForbidden();
    $this->postJson('/@alpha/settings/people/invitations', ['email' => 'x@example.test', 'role' => 'viewer'])->assertForbidden();
    $owner = peopleMembership(1, 1);
    $this->putJson('/@alpha/settings/people/members/'.$owner->id, ['role' => 'viewer'])->assertForbidden();
    $this->deleteJson('/@alpha/settings/people/members/'.$owner->id)->assertForbidden();
    expect(peopleMembership(1, 1)->fresh()->status)->toBe(MembershipStatus::Active);
    $keys = array_column($this->get('/@alpha/settings', peopleInertia())->json('props.groups.0.sections'), 'key');
    expect($keys)->not->toContain('people');
    $this->assertArrayNotHasKey('can', $this->get('/@alpha/settings/general', peopleInertia())->json('props'));
});
test('invite resend and revoke', function () {
    require dirname(__DIR__).'/database/fixture.php';
    peopleSignIn(1);
    $this->postJson('/@alpha/settings/people/invitations', ['email' => 'New@Example.test', 'role' => 'viewer'])->assertCreated()->assertJsonPath('email', 'new@example.test');

    // Already pending, already a member, an unknown or ungrantable role, and a bad address are all refused.
    $this->postJson('/@alpha/settings/people/invitations', ['email' => 'new@example.test', 'role' => 'viewer'])->assertUnprocessable()->assertJsonValidationErrors('email');
    $this->postJson('/@alpha/settings/people/invitations', ['email' => 'actor3@example.test', 'role' => 'viewer'])->assertUnprocessable()->assertJsonValidationErrors('email');
    $this->postJson('/@alpha/settings/people/invitations', ['email' => 'o@example.test', 'role' => 'owner'])->assertForbidden();
    $this->postJson('/@alpha/settings/people/invitations', ['email' => 'o@example.test', 'role' => 'nonsense'])->assertForbidden();
    $this->postJson('/@alpha/settings/people/invitations', ['email' => 'not-an-email', 'role' => 'viewer'])->assertUnprocessable();
    $id = peopleRows('?tab=invitations')[0]['id'];

    // Resend is Concierge's: a fresh link, after its cooldown.
    $resent = $this->putJson('/@alpha/settings/people/invitations/'.$id);
    expect([200, 403])->toContain($resent->getStatusCode());
    $this->deleteJson('/@alpha/settings/people/invitations/'.$id)->assertOk()->assertJsonPath('revoked', true);
    expect(peopleRows('?tab=invitations'))->toBe([]);
    expect(Invitation::query()->where('public_id', $id)->firstOrFail()->status)->toBe(InvitationStatus::Revoked);

    // An invitation of another workspace is not found here.
    peopleSignOut();
    peopleSignIn(2);
    $this->postJson('/@beta/settings/people/invitations', ['email' => 'b@example.test', 'role' => 'viewer'])->assertCreated();
    $other = Invitation::query()->where('workspace_id', 2)->firstOrFail();
    peopleSignOut();
    peopleSignIn(1);
    $this->deleteJson('/@alpha/settings/people/invitations/'.$other->public_id)->assertNotFound();
});
test('change role and remove', function () {
    require dirname(__DIR__).'/database/fixture.php';
    peopleSignIn(1);
    $viewer = peopleMembership(1, 3);
    $url = '/@alpha/settings/people/members/'.$viewer->id;
    $this->putJson($url, ['role' => 'admin'])->assertOk()->assertJsonPath('role', 'admin');
    expect(array_map(fn (array $row) => $row['roles'][0]['label'], peopleRows()))->toBe(['Owner', 'Admin']);
    $this->putJson($url, ['role' => 'owner'])->assertForbidden();
    $this->putJson($url, ['role' => 'nonsense'])->assertForbidden();
    $this->putJson($url, [])->assertUnprocessable();

    // A membership of another workspace is not found here.
    $this->putJson('/@alpha/settings/people/members/'.peopleMembership(2, 2)->id, ['role' => 'viewer'])->assertNotFound();
    $this->deleteJson($url)->assertOk()->assertJsonPath('removed', true);
    expect($viewer->fresh()->status)->toBe(MembershipStatus::Removed);
    expect(peopleNames(peopleRows()))->toBe(['Alpha']);

    // The removed user no longer reaches the workspace.
    peopleSignOut();
    peopleSignIn(3);
    expect([403, 404])->toContain($this->getJson('/@alpha/settings/people')->getStatusCode());
});
test('the last owner cannot be removed or demoted and ranks are respected', function () {
    require dirname(__DIR__).'/database/fixture.php';
    peopleSignIn(1);
    $owner = peopleMembership(1, 1);

    // The owner cannot manage their own membership, so the only owner can neither leave nor be demoted through the list.
    $this->deleteJson('/@alpha/settings/people/members/'.$owner->id)->assertForbidden();
    $this->putJson('/@alpha/settings/people/members/'.$owner->id, ['role' => 'viewer'])->assertForbidden();
    expect(app(Concierge::class)->roleNames($owner->fresh()))->toBe(['owner']);
    expect($owner->fresh()->status)->toBe(MembershipStatus::Active);

    // An admin manages people below them: Concierge's rank rules still stop them touching the owner (or another admin).
    $this->putJson('/@alpha/settings/people/members/'.peopleMembership(1, 3)->id, ['role' => 'admin'])->assertOk();
    peopleSignOut();
    peopleSignIn(3);
    $page = $this->getJson('/@alpha/settings/people')->assertOk()->json();
    expect(peopleActionKeys($page['table']['rows']))->toBe([null, null], 'an admin has no action on the owner, or on themselves');
    expect(array_column($page['roles'], 'value'))->toBe(['viewer'], 'an admin grants viewer only');
    expect($page['invite']['method'])->toBe('get');
    $this->deleteJson('/@alpha/settings/people/members/'.$owner->id)->assertForbidden();
    $this->putJson('/@alpha/settings/people/members/'.$owner->id, ['role' => 'viewer'])->assertForbidden();
    expect(app(Concierge::class)->roleNames($owner->fresh()))->toBe(['owner']);
    expect($owner->fresh()->status)->toBe(MembershipStatus::Active);
});
test('an admin can invite and remove a viewer but not change an owner or another admin', function () {
    require dirname(__DIR__).'/database/fixture.php';
    foreach ([4 => 'admin', 5 => 'viewer'] as $id => $role) {
        User::create(['id' => $id, 'name' => 'Person '.$id, 'email' => 'actor'.$id.'@example.test', 'email_verified_at' => now()]);
        $invite = InviteMember::run(User::findOrFail(1), Workspace::findOrFail(1), 'actor'.$id.'@example.test', new GrantSet([$role]));
        AcceptInvitation::run(User::findOrFail($id), $invite->token);
    }
    peopleSignIn(1);
    $this->putJson('/@alpha/settings/people/members/'.peopleMembership(1, 3)->id, ['role' => 'admin'])->assertOk();
    peopleSignOut();
    peopleSignIn(3);

    // An admin invites a viewer, and removes one.
    $this->postJson('/@alpha/settings/people/invitations', ['email' => 'fresh@example.test', 'role' => 'viewer'])->assertCreated();
    $viewer = peopleMembership(1, 5);
    $this->deleteJson('/@alpha/settings/people/members/'.$viewer->id)->assertOk();
    expect($viewer->fresh()->status)->toBe(MembershipStatus::Removed);

    // They cannot grant admin or owner, change the owner, or manage another admin (equal rank).
    $this->postJson('/@alpha/settings/people/invitations', ['email' => 'second@example.test', 'role' => 'admin'])->assertForbidden();
    $this->postJson('/@alpha/settings/people/invitations', ['email' => 'second@example.test', 'role' => 'owner'])->assertForbidden();
    $owner = peopleMembership(1, 1);
    $otherAdmin = peopleMembership(1, 4);
    foreach ([$owner, $otherAdmin] as $target) {
        $this->putJson('/@alpha/settings/people/members/'.$target->id, ['role' => 'viewer'])->assertForbidden();
        $this->deleteJson('/@alpha/settings/people/members/'.$target->id)->assertForbidden();
    }
    expect(app(Concierge::class)->roleNames($owner->fresh()))->toBe(['owner']);
    expect(app(Concierge::class)->roleNames($otherAdmin->fresh()))->toBe(['admin']);

    // The page offers an admin what they may do: remove and re-role only the viewers.
    expect(peopleActionKeys(peopleRows()))->toBe([null, null, null], 'no viewer is left to act on: the owner, the other admin and themselves have none');
});
test('the tabs carry server counts that follow the search and the tab is in the url', function () {
    require dirname(__DIR__).'/database/fixture.php';
    peopleSignIn(1);
    $this->postJson('/@alpha/settings/people/invitations', ['email' => 'zed@example.test', 'role' => 'viewer'])->assertCreated();
    $this->postJson('/@alpha/settings/people/invitations', ['email' => 'amy@example.test', 'role' => 'viewer'])->assertCreated();
    $page = peoplePeople();
    expect(array_map(fn (array $tab) => [$tab['key'], $tab['label'], $tab['count'], $tab['current']], $page['table']['tabs']))->toBe([['members', 'Members', 2, true], ['invitations', 'Invitations', 2, false]]);
    expect($page['table']['tabs'][1]['url'])->toBe('/settings/people?tab=invitations');

    // The invitations tab is `?tab=invitations`: its own columns and rows, and the current mark moves.
    $invitations = peoplePeople('?tab=invitations');
    expect($invitations['tab'])->toBe('invitations');
    expect(array_column(peoplePeople('?tab=invitations&sort=email')['table']['rows'], 'email'))->toBe(['amy@example.test', 'zed@example.test']);
    expect(array_column($invitations['table']['columns'], 'key'))->toBe(['email', 'roles', 'invitedBy', 'sent', 'expires', 'actions']);
    expect(array_column($invitations['table']['tabs'], 'current'))->toBe([false, true]);

    // Counts follow the search: "zed" matches one invitation and no member, and the tab links keep the search.
    $searched = peoplePeople('?tab=invitations&filter[search]=zed');
    expect(array_column($searched['table']['rows'], 'email'))->toBe(['zed@example.test']);
    expect(array_column($searched['table']['tabs'], 'count'))->toBe([0, 1]);
    $this->assertStringContainsString('filter%5Bsearch%5D=zed', $searched['table']['tabs'][0]['url']);
    $this->getJson('/@alpha/settings/people?tab=nonsense')->assertStatus(400);

    // Sorting an invitations column is honoured; a members-only sort is refused there.
    expect(array_column(peoplePeople('?tab=invitations&sort=-email')['table']['rows'], 'email'))->toBe(['zed@example.test', 'amy@example.test']);
    $this->getJson('/@alpha/settings/people?tab=invitations&sort=joined')->assertStatus(400);
    $this->getJson('/@alpha/settings/people?filter[nonsense]=1')->assertStatus(400);
});
test('the empty states are decided by the server and the page is capped', function () {
    require dirname(__DIR__).'/database/fixture.php';
    peopleSignIn(1);
    expect(peoplePeople('?tab=invitations')['table']['emptyState'])->toBe('first-use');
    expect(peoplePeople()['table']['emptyState'])->toBeNull();
    $noResults = peoplePeople('?filter[search]=zzz');
    expect($noResults['table']['emptyState'])->toBe('no-results');
    expect($noResults['table']['rows'])->toBe([]);
    $pagination = peoplePeople('?per_page=1&page=2')['table']['pagination'];
    expect([$pagination['page'], $pagination['perPage'], $pagination['total'], $pagination['from'], $pagination['to'], $pagination['lastPage']])->toBe([2, 1, 2, 2, 2, 2]);
    expect(peoplePeople('?per_page=5000')['table']['pagination']['perPage'])->toBe(100);
});
test('the table never lists another workspaces people or invitations', function () {
    require dirname(__DIR__).'/database/fixture.php';
    peopleSignIn(2);
    $this->postJson('/@beta/settings/people/invitations', ['email' => 'b-only@example.test', 'role' => 'viewer'])->assertCreated();
    peopleSignOut();
    peopleSignIn(1);
    expect(peopleRows('?tab=invitations'))->toBe([]);
    expect(peopleNames(peopleRows()))->not->toContain('Beta');
    expect(array_column(peoplePeople()['table']['tabs'], 'count'))->toBe([2, 0]);
    $this->getJson('/@alpha/settings/people?filter[search]=b-only')->assertJsonPath('table.tabs.1.count', 0);
});
test('the invite and change role modals are server routes with the pages authorisation', function () {
    require dirname(__DIR__).'/database/fixture.php';
    peopleSignIn(1);

    // Opened directly, the modal URL renders People with the modal's data on it; fetched as a modal it is the modal page alone.
    $direct = $this->get('/@alpha/settings/people/invitations', peopleInertia())->assertOk()->assertJsonPath('component', 'Workspace/Settings/People/Page')->assertJsonPath('props._inertiaui_modal.component', 'Workspace/Settings/People/Invite/Page');
    expect($direct->json('props._inertiaui_modal.baseUrl'))->toBe('/settings/people');
    $modal = $this->get('/@alpha/settings/people/invitations', [...peopleInertia(), 'X-InertiaUI-Modal' => 'x'])->assertOk()->assertJsonPath('component', 'Workspace/Settings/People/Invite/Page');
    expect(array_column($modal->json('props.roles'), 'value'))->toBe(['admin', 'viewer']);
    expect([$modal->json('props.submit.label'), $modal->json('props.submit.method')])->toBe(['Send invitation', 'post']);

    // The opener is the server's descriptor: a GET to the same URL the invitation is posted to.
    expect(peoplePeople()['invite']['method'])->toBe('get');

    // Change role: the member's current role, the roles the viewer may give, and the PUT the existing endpoint takes.
    $viewer = peopleMembership(1, 3);
    $role = $this->get('/@alpha/settings/people/members/'.$viewer->id, [...peopleInertia(), 'X-InertiaUI-Modal' => 'x'])->assertOk()->assertJsonPath('component', 'Workspace/Settings/People/ChangeRole/Page');
    expect([$role->json('props.role'), $role->json('props.submit.method')])->toBe(['viewer', 'put']);

    // Refusals are the page's own: the owner's row has no role change, another workspace's member is not found, a viewer cannot open either modal.
    $this->get('/@alpha/settings/people/members/'.peopleMembership(1, 1)->id, [...peopleInertia(), 'X-InertiaUI-Modal' => 'x'])->assertForbidden();
    $this->get('/@alpha/settings/people/members/'.peopleMembership(2, 2)->id, [...peopleInertia(), 'X-InertiaUI-Modal' => 'x'])->assertNotFound();
    peopleSignOut();
    peopleSignIn(3);
    $this->get('/@alpha/settings/people/invitations', [...peopleInertia(), 'X-InertiaUI-Modal' => 'x'])->assertForbidden();
    $this->get('/@alpha/settings/people/members/'.$viewer->id, [...peopleInertia(), 'X-InertiaUI-Modal' => 'x'])->assertForbidden();
});
test('every sortable column toggles ascending and descending on each tab from its own descriptor', function () {
    require dirname(__DIR__).'/database/fixture.php';
    peopleSignIn(1);
    foreach (['members', 'invitations'] as $tab) {
        $columns = peoplePeople('?tab='.$tab)['table']['columns'];
        $sortable = array_values(array_filter($columns, fn (array $column): bool => $column['sortable']));
        expect($sortable)->not->toBeEmpty($tab.' has sortable columns');
        foreach ($sortable as $column) {
            // The table sends the descriptor's own sort value, so what it sends is always allowed.
            $param = $column['sortBy'] ?? $column['key'];
            foreach ([$param, '-'.$param] as $sort) {
                $table = $this->getJson('/@alpha/settings/people?tab='.$tab.'&sort='.$sort)->assertOk()->json('table');
                expect($table['sort']['column'])->toBe($column['key'], $tab.' '.$sort);
                expect($table['sort']['direction'])->toBe(str_starts_with($sort, '-') ? 'desc' : 'asc', $tab.' '.$sort);
            }
        }
    }

    // The Member column is sorted by the server's "name", not its own key.
    expect(collect(peoplePeople()['table']['columns'])->firstWhere('key', 'member')['sortBy'])->toBe('name');
});
test('a bad sort or filter never reaches the client as a raw 400', function () {
    require dirname(__DIR__).'/database/fixture.php';
    peopleSignIn(1);

    // An Inertia visit goes back to the page without the bad parameter, and flashes a toast; the cleared page then loads.
    $this->get('/@alpha/settings/people?tab=invitations&sort=bogus', peopleInertia())->assertRedirect('/@alpha/settings/people?tab=invitations')->assertStatus(303);
    $this->get('/@alpha/settings/people?filter%5Bbogus%5D=1', peopleInertia())->assertStatus(303);
    $this->get('/@alpha/settings/people', peopleInertia())->assertOk();

    // JSON keeps a readable 400; a plain browser request gets the kit's error page with the status, not Symfony's bare default.
    $this->getJson('/@alpha/settings/people?sort=bogus')->assertStatus(400)->assertJsonPath('error', 'invalid_query');
    $html = $this->get('/@alpha/settings/people?sort=bogus', ['Accept' => 'text/html'])->assertStatus(400);
    expect($html->getContent())->toMatch('#Workspace\\\\?/Error\\\\?/Page#');
    $missing = $this->get('/this-page-does-not-exist', ['Accept' => 'text/html'])->assertNotFound();
    expect($missing->getContent())->toMatch('#Workspace\\\\?/Error\\\\?/Page#', 'a missing page is the kit error page');
    $this->assertStringNotContainsString('Symfony', $missing->getContent());
});
