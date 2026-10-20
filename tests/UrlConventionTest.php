<?php

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;
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

require dirname(__DIR__).'/vendor/autoload.php';

/** The URL convention: `/@{workspace}/...` pages, short paths, `/me/*`, the root left to the app, and one helper that renders them. */
final class UrlConventionTest extends TestCase
{
    public function createApplication()
    {
        // The kit ships no sample resource: this test adopts the test-only Records fixture.
        require_once __DIR__.'/records_fixture.php';

        return starterAppWithRecords();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function signIn(int $id): void
    {
        $password = Str::random(40);
        User::findOrFail($id)->update(['password' => Hash::make($password)]);
        $this->postJson('/login', ['email' => 'actor'.$id.'@example.test', 'password' => $password])->assertOk();
        $this->withCookie(config('session.cookie'), app('session')->getId());
        Auth::forgetGuards();
    }

    /** Actor 1 joins actor 2's workspace, so they have two. */
    private function addSecondWorkspace(): void
    {
        $invite = InviteMember::run(User::findOrFail(2), Workspace::findOrFail(2), 'actor1@example.test', new GrantSet(['viewer']));
        AcceptInvitation::run(User::findOrFail(1), $invite->token);
    }

    public function test_the_tiers_are_the_root_me_and_the_tenant_marker(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        RecordsFixtureProvider::install();
        // Root: the app's own, public. /me: personal and cross-workspace, behind sign-in. /@slug: a workspace.
        $this->get('/login')->assertOk();
        $this->get('/me/workspaces')->assertRedirect('/login');
        $this->get('/@alpha/records')->assertRedirect('/login');
        // The root belongs to the app: a first segment is never taken for a workspace without the @.
        $this->get('/alpha/records')->assertNotFound();
        $this->get('/admin')->assertNotFound();
        $this->signIn(1);
        $this->getJson('/me/workspaces')->assertOk()->assertJsonPath('workspaces.0.id', 1);
    }

    public function test_the_at_sign_is_emitted_literally_and_accepted_encoded(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        RecordsFixtureProvider::install();
        $this->assertSame(url('/@alpha/records'), route('workspaces.records.index', ['workspace' => 'alpha']));
        $this->assertStringNotContainsString('%40', route('workspaces.records.index', ['workspace' => 'alpha']));
        $this->signIn(1);
        $this->getJson('/@alpha/records')->assertOk()->assertJsonPath('records.0.title', 'Alpha private');
        $this->getJson('/%40alpha/records')->assertOk()->assertJsonPath('records.0.title', 'Alpha private');
    }

    public function test_slugs_are_validated_and_made_from_names(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        RecordsFixtureProvider::install();
        $this->assertSame('alpha', Workspace::findOrFail(1)->slug);
        $this->assertSame('acme-co', Slugs::unique('Acme Co'));
        $this->assertSame('alpha-2', Slugs::unique('Alpha'));
        $this->assertSame('workspace', Slugs::unique('日本'));
        // No reserved list: the @ marks the tenant, so a slug may be any well-formed word.
        $this->assertSame('login', Slugs::unique('Login'));
        $this->assertFalse(Slugs::valid(''));
        foreach (['Bad Slug', 'under_score', '-lead', 'trail-', 'a--b'] as $slug) {
            $this->assertFalse(Slugs::valid($slug), $slug);
            $this->expectsRefusal(fn () => Workspace::create(['name' => 'X', 'slug' => $slug]), 'slug_invalid');
        }
        $this->expectsRefusal(fn () => Workspace::create(['name' => 'Other', 'slug' => 'alpha']), 'slug_taken');
        $this->expectsRefusal(fn () => Workspace::findOrFail(2)->update(['slug' => 'alpha']), 'slug_taken');
        Workspace::findOrFail(2)->update(['slug' => 'beta-renamed']);
        $this->assertSame('beta-renamed', Workspace::findOrFail(2)->slug);
    }

    private function expectsRefusal(callable $change, string $code): void
    {
        try {
            $change();
        } catch (RuntimeException $exception) {
            $this->assertSame($code, $exception->getMessage());

            return;
        }
        $this->fail('Expected '.$code);
    }

    public function test_short_and_full_paths_resolve_to_the_same_workspace(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        RecordsFixtureProvider::install();
        $this->signIn(1);
        // One workspace: the short path is the only one's.
        $this->getJson('/records')->assertOk()->assertJsonPath('records.0.title', 'Alpha private');
        $this->getJson('/@alpha/records')->assertOk()->assertJsonPath('records.0.title', 'Alpha private');
        $this->getJson('/settings/general')->assertOk()->assertJsonPath('workspace_id', 1);
        $this->getJson('/@nobody/records')->assertNotFound();
    }

    public function test_with_several_workspaces_and_none_current_a_short_path_goes_to_the_chooser(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        RecordsFixtureProvider::install();
        $this->addSecondWorkspace();
        $this->signIn(1);
        $this->get('/records')->assertRedirect('/me/workspaces');
        // Visiting a workspace makes it the session's current one, and short paths follow it.
        $this->getJson('/@alpha/records')->assertOk();
        $this->getJson('/records')->assertOk()->assertJsonPath('records.0.title', 'Alpha private');
        $this->getJson('/@beta/records')->assertOk()->assertJsonPath('records.0.title', 'Beta private');
        $this->getJson('/records')->assertOk()->assertJsonPath('records.0.title', 'Beta private');
    }

    public function test_links_flip_from_short_to_full_when_a_second_workspace_appears(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        RecordsFixtureProvider::install();
        $inertia = ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
        $this->signIn(1);
        // One workspace: the shell's links, the page's own URLs and the redirects after a write are short; nothing is switchable.
        $page = $this->get('/records', $inertia)->assertOk()
            ->assertJsonPath('props.shell.navigation.0.url', '/dashboard')->assertJsonPath('props.shell.navigation.1.url', '/records')->assertJsonPath('props.shell.navigation.2.label', 'Settings')->assertJsonPath('props.shell.navigation.2.url', '/settings')
            ->assertJsonPath('props.shell.switcher.choices.0.url', '/dashboard')->assertJsonCount(1, 'props.shell.switcher.choices')
            ->assertJsonPath('props.create_url', '/records')->json('props');
        $this->assertStringStartsWith('/records/', array_values($page['actions'])[0]['url']);
        $this->get('/me/workspaces', $inertia)->assertRedirect('/dashboard');
        $this->post('/records', ['title' => 'Short'], $inertia)->assertRedirect('/records');

        // A second workspace appears mid-session: nothing else changes, but the switcher has choices and every link is the full form.
        $this->addSecondWorkspace();
        $page = $this->get('/@alpha/records', $inertia)->assertOk()
            ->assertJsonPath('props.shell.navigation.0.url', '/@alpha/dashboard')->assertJsonPath('props.shell.navigation.1.url', '/@alpha/records')->assertJsonPath('props.shell.navigation.2.url', '/@alpha/settings')
            ->assertJsonCount(2, 'props.shell.switcher.choices')->assertJsonPath('props.shell.switcher.choices.0.url', '/@alpha/dashboard')
            ->assertJsonPath('props.shell.switcher.choices.1.url', '/@beta/dashboard')->assertJsonPath('props.create_url', '/@alpha/records')->json('props');
        $this->assertStringStartsWith('/@alpha/records/', array_values($page['actions'])[0]['url']);
        $this->get('/me/workspaces', $inertia)->assertOk()->assertJsonPath('component', 'Workspace/Index/Page');
        $this->post('/@alpha/records', ['title' => 'Full'], $inertia)->assertRedirect('/@alpha/records');
        // The short path still works, for the current workspace.
        $this->get('/records', $inertia)->assertOk()->assertJsonPath('props.create_url', '/@alpha/records');
    }

    public function test_anything_shared_outside_a_session_uses_the_full_form(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        RecordsFixtureProvider::install();
        $this->signIn(1);
        // The session is in the short form (one workspace), and the shareable form is still full and absolute.
        $this->get('/records')->assertOk();
        $this->assertSame('/records', WorkspaceUrl::to(1, 'records', null, request()->duplicate()->setUserResolver(fn () => User::findOrFail(1))));
        $this->assertSame(url('/@alpha/records/7'), WorkspaceUrl::shareable(1, 'records/7'));
        $this->assertSame('/@alpha/records', WorkspaceUrl::full(Workspace::findOrFail(1), 'records'));
        // With no signed-in user (a queued mail, an export) `to` has no short form to give.
        Auth::forgetGuards();
        $this->assertSame('/@alpha/records', WorkspaceUrl::to(1, 'records', null, Request::create('/')));
    }

    public function test_the_old_workspace_urls_move_permanently(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        RecordsFixtureProvider::install();
        $this->signIn(1);
        $this->get('/workspaces')->assertStatus(301)->assertRedirect('/me/workspaces');
        $this->get('/workspaces/1/records')->assertStatus(301)->assertRedirect('/@alpha/records');
        $this->get('/workspaces/alpha/records/3?x=1')->assertStatus(301)->assertRedirect('/@alpha/records/3?x=1');
        $this->get('/workspaces/1/settings')->assertStatus(301)->assertRedirect('/@alpha/settings');
        $this->get('/workspaces/1/settings/general')->assertStatus(301)->assertRedirect('/@alpha/settings/general');
        $this->get('/workspaces/99/records')->assertNotFound();
    }

    public function test_invitations_live_under_me_and_the_old_accept_url_is_moved_permanently(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        RecordsFixtureProvider::install();
        $this->signIn(1);
        // The accept URL has no verb: a user's invitations are at /me/invitations. The old URL moves, keeping a POST's method and body (308) and a GET's 301.
        $this->post('/tenant-invitations/accept', ['token' => 'x'])->assertStatus(308)->assertRedirect('/me/invitations');
        $this->get('/tenant-invitations/accept')->assertStatus(301)->assertRedirect('/me/invitations');
        $this->postJson('/me/invitations', ['token' => ''])->assertUnprocessable();
        $this->get('/me/invitations')->assertStatus(405);
    }

    public function test_the_workspace_list_is_a_query_builder_listing(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        RecordsFixtureProvider::install();
        $invite = InviteMember::run(User::findOrFail(2), Workspace::findOrFail(2), 'actor1@example.test', new GrantSet(['viewer']));
        AcceptInvitation::run(User::findOrFail(1), $invite->token);
        $this->signIn(1);
        // Sorted by name by default, and by `sort` where the listing allows it; only workspaces the user belongs to.
        $this->getJson('/me/workspaces')->assertOk()->assertJsonPath('workspaces.0.name', 'Alpha')->assertJsonPath('workspaces.1.name', 'Beta')->assertJsonCount(2, 'workspaces');
        $this->getJson('/me/workspaces?sort=-name')->assertOk()->assertJsonPath('workspaces.0.name', 'Beta');
        $this->getJson('/me/workspaces?sort=slug')->assertStatus(400);
    }
}
