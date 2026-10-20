<?php

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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
        return require __DIR__.'/application.php';
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
        // Root: the app's own, public. /me: personal and cross-workspace, behind sign-in. /@slug: a workspace.
        $this->get('/login')->assertOk();
        $this->get('/me/workspaces')->assertRedirect('/login');
        $this->get('/@alpha/notes')->assertRedirect('/login');
        // The root belongs to the app: a first segment is never taken for a workspace without the @.
        $this->get('/alpha/notes')->assertNotFound();
        $this->get('/admin')->assertNotFound();
        $this->signIn(1);
        $this->getJson('/me/workspaces')->assertOk()->assertJsonPath('workspaces.0.id', 1);
    }

    public function test_the_at_sign_is_emitted_literally_and_accepted_encoded(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->assertSame(url('/@alpha/notes'), route('workspaces.notes.index', ['workspace' => 'alpha']));
        $this->assertStringNotContainsString('%40', route('workspaces.notes.index', ['workspace' => 'alpha']));
        $this->signIn(1);
        $this->getJson('/@alpha/notes')->assertOk()->assertJsonPath('notes.0.body', 'Alpha private');
        $this->getJson('/%40alpha/notes')->assertOk()->assertJsonPath('notes.0.body', 'Alpha private');
    }

    public function test_slugs_are_validated_and_made_from_names(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
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
        $this->signIn(1);
        // One workspace: the short path is the only one's.
        $this->getJson('/notes')->assertOk()->assertJsonPath('notes.0.body', 'Alpha private');
        $this->getJson('/@alpha/notes')->assertOk()->assertJsonPath('notes.0.body', 'Alpha private');
        $this->getJson('/settings')->assertOk()->assertJsonPath('workspace_id', 1);
        $this->getJson('/@nobody/notes')->assertNotFound();
    }

    public function test_with_several_workspaces_and_none_current_a_short_path_goes_to_the_chooser(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->addSecondWorkspace();
        $this->signIn(1);
        $this->get('/notes')->assertRedirect('/me/workspaces');
        // Visiting a workspace makes it the session's current one, and short paths follow it.
        $this->getJson('/@alpha/notes')->assertOk();
        $this->getJson('/notes')->assertOk()->assertJsonPath('notes.0.body', 'Alpha private');
        $this->getJson('/@beta/notes')->assertOk()->assertJsonPath('notes.0.body', 'Beta private');
        $this->getJson('/notes')->assertOk()->assertJsonPath('notes.0.body', 'Beta private');
    }

    public function test_links_flip_from_short_to_full_when_a_second_workspace_appears(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $inertia = ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
        $this->signIn(1);
        // One workspace: the shell's links, the page's own URLs and the redirects after a write are short; nothing is switchable.
        $page = $this->get('/notes', $inertia)->assertOk()
            ->assertJsonPath('props.layout.navigation.0.url', '/notes')->assertJsonPath('props.layout.settings.items.0.url', '/settings')
            ->assertJsonPath('props.layout.switcher.choices.0.url', '/notes')->assertJsonCount(1, 'props.layout.switcher.choices')
            ->assertJsonPath('props.create_url', '/notes')->json('props');
        $this->assertStringStartsWith('/notes/', array_values($page['actions'])[0]['url']);
        $this->get('/me/workspaces', $inertia)->assertRedirect('/notes');
        $this->post('/notes', ['body' => 'Short', 'intent_key' => 'short-1'], $inertia)->assertRedirect('/notes');

        // A second workspace appears mid-session: nothing else changes, but the switcher has choices and every link is the full form.
        $this->addSecondWorkspace();
        $page = $this->get('/@alpha/notes', $inertia)->assertOk()
            ->assertJsonPath('props.layout.navigation.0.url', '/@alpha/notes')->assertJsonPath('props.layout.settings.items.0.url', '/@alpha/settings')
            ->assertJsonCount(2, 'props.layout.switcher.choices')->assertJsonPath('props.layout.switcher.choices.0.url', '/@alpha/notes')
            ->assertJsonPath('props.layout.switcher.choices.1.url', '/@beta/notes')->assertJsonPath('props.create_url', '/@alpha/notes')->json('props');
        $this->assertStringStartsWith('/@alpha/notes/', array_values($page['actions'])[0]['url']);
        $this->get('/me/workspaces', $inertia)->assertOk()->assertJsonPath('component', 'Workspace/Index/Page');
        $this->post('/@alpha/notes', ['body' => 'Full', 'intent_key' => 'full-1'], $inertia)->assertRedirect('/@alpha/notes');
        // The short path still works, for the current workspace.
        $this->get('/notes', $inertia)->assertOk()->assertJsonPath('props.create_url', '/@alpha/notes');
    }

    public function test_anything_shared_outside_a_session_uses_the_full_form(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->signIn(1);
        // The session is in the short form (one workspace), and the shareable form is still full and absolute.
        $this->get('/notes')->assertOk();
        $this->assertSame('/notes', WorkspaceUrl::to(1, 'notes', null, request()->duplicate()->setUserResolver(fn () => User::findOrFail(1))));
        $this->assertSame(url('/@alpha/notes/7'), WorkspaceUrl::shareable(1, 'notes/7'));
        $this->assertSame('/@alpha/notes', WorkspaceUrl::full(Workspace::findOrFail(1), 'notes'));
        // With no signed-in user (a queued mail, an export) `to` has no short form to give.
        Auth::forgetGuards();
        $this->assertSame('/@alpha/notes', WorkspaceUrl::to(1, 'notes', null, Request::create('/')));
    }

    public function test_the_old_workspace_urls_move_permanently(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->signIn(1);
        $this->get('/workspaces')->assertStatus(301)->assertRedirect('/me/workspaces');
        $this->get('/workspaces/1/notes')->assertStatus(301)->assertRedirect('/@alpha/notes');
        $this->get('/workspaces/alpha/notes/3?x=1')->assertStatus(301)->assertRedirect('/@alpha/notes/3?x=1');
        $this->get('/workspaces/1/settings')->assertStatus(301)->assertRedirect('/@alpha/settings');
        $this->get('/workspaces/99/notes')->assertNotFound();
    }
}
