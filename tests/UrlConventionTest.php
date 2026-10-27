<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Fixtures\RecordsFixtureProvider;
use Tey\Concierge\Actions\AcceptInvitation;
use Tey\Concierge\Actions\InviteMember;
use Tey\Concierge\Support\GrantSet;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\Slugs;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

function urlConventionSignIn(int $id): void
{
    $password = Str::random(40);
    User::findOrFail($id)->update(['password' => Hash::make($password)]);
    test()->postJson('/login', ['email' => 'actor'.$id.'@example.test', 'password' => $password])->assertOk();
    test()->withCookie(config('session.cookie'), app('session')->getId());
    Auth::forgetGuards();
}
/** Actor 1 joins actor 2's workspace, so they have two. */
function urlConventionAddSecondWorkspace(): void
{
    $invite = InviteMember::run(User::findOrFail(2), Workspace::findOrFail(2), 'actor1@example.test', new GrantSet(['viewer']));
    AcceptInvitation::run(User::findOrFail(1), $invite->token);
}
test('the tiers are the root me and the tenant marker', function () {
    require dirname(__DIR__).'/database/fixture.php';
    RecordsFixtureProvider::install();

    // Root: the app's own, public. /me: personal and cross-workspace, behind sign-in. /@slug: a workspace.
    $this->get('/login')->assertOk();
    $this->get('/me/workspaces')->assertRedirect('/login');
    $this->get('/@alpha/records')->assertRedirect('/login');

    // The root belongs to the app: a first segment is never taken for a workspace without the @.
    $this->get('/alpha/records')->assertNotFound();
    $this->get('/admin')->assertNotFound();
    urlConventionSignIn(1);
    $this->getJson('/me/workspaces')->assertOk()->assertJsonPath('workspaces.0.id', 1);
});
test('the at sign is emitted literally and accepted encoded', function () {
    require dirname(__DIR__).'/database/fixture.php';
    RecordsFixtureProvider::install();
    expect(route('workspaces.records.index', ['workspace' => 'alpha']))->toBe(url('/@alpha/records'));
    $this->assertStringNotContainsString('%40', route('workspaces.records.index', ['workspace' => 'alpha']));
    urlConventionSignIn(1);
    $this->getJson('/@alpha/records')->assertOk()->assertJsonPath('records.0.title', 'Alpha private');
    $this->getJson('/%40alpha/records')->assertOk()->assertJsonPath('records.0.title', 'Alpha private');
});
test('slugs are validated and made from names', function () {
    require dirname(__DIR__).'/database/fixture.php';
    RecordsFixtureProvider::install();
    expect(Workspace::findOrFail(1)->slug)->toBe('alpha');
    expect(Slugs::unique('Acme Co'))->toBe('acme-co');
    expect(Slugs::unique('Alpha'))->toBe('alpha-2');
    expect(Slugs::unique('日本'))->toBe('workspace');

    // No reserved list: the @ marks the tenant, so a slug may be any well-formed word.
    expect(Slugs::unique('Login'))->toBe('login');
    expect(Slugs::valid(''))->toBeFalse();
    foreach (['Bad Slug', 'under_score', '-lead', 'trail-', 'a--b'] as $slug) {
        expect(Slugs::valid($slug))->toBeFalse($slug);
        urlConventionExpectsRefusal(fn () => Workspace::create(['name' => 'X', 'slug' => $slug]), 'slug_invalid');
    }
    urlConventionExpectsRefusal(fn () => Workspace::create(['name' => 'Other', 'slug' => 'alpha']), 'slug_taken');
    urlConventionExpectsRefusal(fn () => Workspace::findOrFail(2)->update(['slug' => 'alpha']), 'slug_taken');
    Workspace::findOrFail(2)->update(['slug' => 'beta-renamed']);
    expect(Workspace::findOrFail(2)->slug)->toBe('beta-renamed');
});
function urlConventionExpectsRefusal(callable $change, string $code): void
{
    try {
        $change();
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe($code);

        return;
    }
    test()->fail('Expected '.$code);
}
test('short and full paths resolve to the same workspace', function () {
    require dirname(__DIR__).'/database/fixture.php';
    RecordsFixtureProvider::install();
    urlConventionSignIn(1);

    // One workspace: the short path is the only one's.
    $this->getJson('/records')->assertOk()->assertJsonPath('records.0.title', 'Alpha private');
    $this->getJson('/@alpha/records')->assertOk()->assertJsonPath('records.0.title', 'Alpha private');
    $this->getJson('/settings/general')->assertOk()->assertJsonPath('workspace_id', 1);
    $this->getJson('/@nobody/records')->assertNotFound();
});
test('with several workspaces and none current a short path goes to the chooser', function () {
    require dirname(__DIR__).'/database/fixture.php';
    RecordsFixtureProvider::install();
    urlConventionAddSecondWorkspace();
    urlConventionSignIn(1);
    $this->get('/records')->assertRedirect('/me/workspaces');

    // Visiting a workspace makes it the session's current one, and short paths follow it.
    $this->getJson('/@alpha/records')->assertOk();
    $this->getJson('/records')->assertOk()->assertJsonPath('records.0.title', 'Alpha private');
    $this->getJson('/@beta/records')->assertOk()->assertJsonPath('records.0.title', 'Beta private');
    $this->getJson('/records')->assertOk()->assertJsonPath('records.0.title', 'Beta private');
});
test('links flip from short to full when a second workspace appears', function () {
    require dirname(__DIR__).'/database/fixture.php';
    RecordsFixtureProvider::install();
    $inertia = ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
    urlConventionSignIn(1);

    // One workspace: the shell's links, the page's own URLs and the redirects after a write are short; nothing is switchable.
    $page = $this->get('/records', $inertia)->assertOk()
        ->assertJsonPath('props.shell.navigation.0.url', '/')->assertJsonPath('props.shell.navigation.1.url', '/notes')->assertJsonPath('props.shell.navigation.2.url', '/records')->assertJsonPath('props.shell.navigation.3.label', 'Settings')->assertJsonPath('props.shell.navigation.3.url', '/settings')
        ->assertJsonPath('props.shell.workspace.url', '/')->assertJsonCount(1, 'props.shell.switcher.choices')
        ->assertJsonPath('props.create_url', '/records')->json('props');
    expect(array_values($page['actions'])[0]['url'])->toStartWith('/records/');
    $this->get('/me/workspaces', $inertia)->assertRedirect('/');
    $this->post('/records', ['title' => 'Short'], $inertia)->assertRedirect('/records');

    // A second workspace appears mid-session: nothing else changes, but the switcher has choices and every link is the full form.
    urlConventionAddSecondWorkspace();
    $page = $this->get('/@alpha/records', $inertia)->assertOk()
        ->assertJsonPath('props.shell.navigation.0.url', '/@alpha')->assertJsonPath('props.shell.navigation.1.url', '/@alpha/notes')->assertJsonPath('props.shell.navigation.2.url', '/@alpha/records')->assertJsonPath('props.shell.navigation.3.url', '/@alpha/settings')
        ->assertJsonCount(2, 'props.shell.switcher.choices')->assertJsonPath('props.shell.switcher.choices.0.url', '/@alpha')
        ->assertJsonPath('props.shell.switcher.choices.1.url', '/@beta')->assertJsonPath('props.create_url', '/@alpha/records')->json('props');
    expect(array_values($page['actions'])[0]['url'])->toStartWith('/@alpha/records/');
    $this->get('/me/workspaces', $inertia)->assertOk()->assertJsonPath('component', 'Workspace/Index/Page');
    $this->post('/@alpha/records', ['title' => 'Full'], $inertia)->assertRedirect('/@alpha/records');

    // The short path still works, for the current workspace.
    $this->get('/records', $inertia)->assertOk()->assertJsonPath('props.create_url', '/@alpha/records');
});
test('anything shared outside a session uses the full form', function () {
    require dirname(__DIR__).'/database/fixture.php';
    RecordsFixtureProvider::install();
    urlConventionSignIn(1);

    // The session is in the short form (one workspace), and the shareable form is still full and absolute.
    $this->get('/records')->assertOk();
    expect(WorkspaceUrl::to(1, 'records', null, request()->duplicate()->setUserResolver(fn () => User::findOrFail(1))))->toBe('/records');
    expect(WorkspaceUrl::shareable(1, 'records/7'))->toBe(url('/@alpha/records/7'));
    expect(WorkspaceUrl::full(Workspace::findOrFail(1), 'records'))->toBe('/@alpha/records');

    // With no signed-in user (a queued mail, an export) `to` has no short form to give.
    Auth::forgetGuards();
    expect(WorkspaceUrl::to(1, 'records', null, Request::create('/')))->toBe('/@alpha/records');
});
test('the old workspace urls move permanently', function () {
    require dirname(__DIR__).'/database/fixture.php';
    RecordsFixtureProvider::install();
    urlConventionSignIn(1);
    $this->get('/workspaces')->assertStatus(301)->assertRedirect('/me/workspaces');
    $this->get('/workspaces/1/records')->assertStatus(301)->assertRedirect('/@alpha/records');
    $this->get('/workspaces/alpha/records/3?x=1')->assertStatus(301)->assertRedirect('/@alpha/records/3?x=1');
    $this->get('/workspaces/1/settings')->assertStatus(301)->assertRedirect('/@alpha/settings');
    $this->get('/workspaces/1/settings/general')->assertStatus(301)->assertRedirect('/@alpha/settings/general');
    $this->get('/workspaces/99/records')->assertNotFound();
});
test('invitations live under me and the old accept url is moved permanently', function () {
    require dirname(__DIR__).'/database/fixture.php';
    RecordsFixtureProvider::install();
    urlConventionSignIn(1);

    // The accept URL has no verb: a user's invitations are at /me/invitations. The old URL moves, keeping a POST's method and body (308) and a GET's 301.
    $this->post('/tenant-invitations/accept', ['token' => 'x'])->assertStatus(308)->assertRedirect('/me/invitations');
    $this->get('/tenant-invitations/accept')->assertStatus(301)->assertRedirect('/me/invitations');
    $this->postJson('/me/invitations', ['token' => ''])->assertUnprocessable();
    $this->get('/me/invitations')->assertStatus(405);
});
test('the workspace list is a query builder listing', function () {
    require dirname(__DIR__).'/database/fixture.php';
    RecordsFixtureProvider::install();
    $invite = InviteMember::run(User::findOrFail(2), Workspace::findOrFail(2), 'actor1@example.test', new GrantSet(['viewer']));
    AcceptInvitation::run(User::findOrFail(1), $invite->token);
    urlConventionSignIn(1);

    // Sorted by name by default, and by `sort` where the listing allows it; only workspaces the user belongs to.
    $this->getJson('/me/workspaces')->assertOk()->assertJsonPath('workspaces.0.name', 'Alpha')->assertJsonPath('workspaces.1.name', 'Beta')->assertJsonCount(2, 'workspaces');
    $this->getJson('/me/workspaces?sort=-name')->assertOk()->assertJsonPath('workspaces.0.name', 'Beta');
    $this->getJson('/me/workspaces?sort=slug')->assertStatus(400);
});
