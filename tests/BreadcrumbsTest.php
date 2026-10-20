<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tey\Concierge\Actions\AcceptInvitation;
use Tey\Concierge\Actions\InviteMember;
use Tey\Concierge\Support\GrantSet;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\SessionUi\Breadcrumbs;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\TenantBoundary;

require dirname(__DIR__).'/vendor/autoload.php';

/** Breadcrumbs are the kit's own: a trail from the registered pages and nav, in the URL form the session uses, filtered by what the user may open. */
final class BreadcrumbsTest extends TestCase
{
    private ?string $routeCache = null;

    public function createApplication()
    {
        return require __DIR__.'/application.php';
    }

    protected function setUp(): void
    {
        // The route cache check writes to its own file, never the app's.
        $this->routeCache = sys_get_temp_dir().'/starter-routes-'.bin2hex(random_bytes(4)).'.php';
        putenv('APP_ROUTES_CACHE='.$this->routeCache);
        $_ENV['APP_ROUTES_CACHE'] = $this->routeCache;
        parent::setUp();
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        putenv('APP_ROUTES_CACHE');
        unset($_ENV['APP_ROUTES_CACHE']);
        if ($this->routeCache !== null && is_file($this->routeCache)) {
            unlink($this->routeCache);
        }
    }

    private function inertia(): array
    {
        return ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
    }

    private function signIn(int $id): void
    {
        $password = Str::random(40);
        User::findOrFail($id)->update(['password' => Hash::make($password)]);
        $this->postJson('/login', ['email' => 'actor'.$id.'@example.test', 'password' => $password])->assertOk();
        $this->withCookie(config('session.cookie'), app('session')->getId());
        Auth::forgetGuards();
    }

    private function addSecondWorkspace(): void
    {
        $invite = InviteMember::run(User::findOrFail(2), Workspace::findOrFail(2), 'actor1@example.test', new GrantSet(['viewer']));
        AcceptInvitation::run(User::findOrFail(1), $invite->token);
    }

    public function test_the_trail_to_a_record_page_in_both_url_forms(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->signIn(1);
        // One workspace: the crumbs link in the short form, whichever form the page was reached by.
        foreach (['/notes/1', '/@alpha/notes/1'] as $path) {
            $this->get($path, $this->inertia())->assertOk()->assertJsonPath('component', 'Workspace/Note/Show/Page')
                ->assertJsonPath('props.layout.breadcrumbs', [['label' => 'Notes', 'url' => '/notes', 'current' => false], ['label' => 'Alpha private', 'url' => '/notes/1', 'current' => true]]);
        }
        // A list page is its own, single crumb.
        $this->get('/notes', $this->inertia())->assertOk()->assertJsonPath('props.layout.breadcrumbs', [['label' => 'Notes', 'url' => '/notes', 'current' => true]]);
        // Two workspaces: the same trail, in the full form.
        $this->addSecondWorkspace();
        $this->get('/@alpha/notes/1', $this->inertia())->assertOk()
            ->assertJsonPath('props.layout.breadcrumbs', [['label' => 'Notes', 'url' => '/@alpha/notes', 'current' => false], ['label' => 'Alpha private', 'url' => '/@alpha/notes/1', 'current' => true]]);
        // The personal page is a trail of its own, with no workspace in it.
        $this->get('/me/workspaces', $this->inertia())->assertOk()->assertJsonPath('props.layout.breadcrumbs', [['label' => 'Workspaces', 'url' => '/me/workspaces', 'current' => true]]);
    }

    public function test_the_document_title_comes_from_the_same_trail(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->signIn(1);
        $app = config('app.name');
        $this->get('/@alpha/notes/1', $this->inertia())->assertJsonPath('props.layout.title', 'Alpha private · Alpha · '.$app);
        $this->get('/@alpha/notes', $this->inertia())->assertJsonPath('props.layout.title', 'Notes · Alpha · '.$app);
        // The chooser is for a user with several workspaces: with one it opens the workspace instead.
        $this->addSecondWorkspace();
        $this->get('/me/workspaces', $this->inertia())->assertJsonPath('props.layout.title', 'Workspaces · '.$app);
    }

    public function test_a_crumb_never_links_to_a_page_the_user_cannot_open(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        // An owner-only parent (writing settings) of a page a viewer can read.
        $registry = app(Registry::class);
        $registry->page('settings.write', 'Owner only', 'owner-only')->page('notes.read', 'Child', 'owner-only/child', 'owner-only');
        $trail = function (int $actor): array {
            return app(TenantBoundary::class)->run(1, fn () => array_map(fn ($crumb) => $crumb->toArray(), app(Breadcrumbs::class)->trail(Request::create('/@alpha/owner-only/child'), Workspace::current(), new Principal($actor))));
        };
        $this->assertSame([['label' => 'Owner only', 'url' => '/@alpha/owner-only', 'current' => false], ['label' => 'Child', 'url' => '/@alpha/owner-only/child', 'current' => true]], $trail(1));
        // The viewer can open the child but not its parent: the parent reads as plain text.
        $this->assertSame([['label' => 'Owner only', 'url' => null, 'current' => false], ['label' => 'Child', 'url' => '/@alpha/owner-only/child', 'current' => true]], $trail(3));
    }

    public function test_every_shell_page_resolves_a_trail(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $registry = app(Registry::class);
        $checked = [];
        app(TenantBoundary::class)->run(1, function () use (&$checked): void {
            foreach (Route::getRoutes() as $route) {
                $action = $route->getActionName();
                $page = in_array('GET', $route->methods(), true) && str_starts_with($action, 'Tey\\Kit\\Features\\') && ! str_contains($action, '@moved') && ! str_contains($action, '\\Mcp\\Http\\Middleware');
                if (! $page || ! str_starts_with($route->uri(), '@{workspace}/') && $route->uri() !== 'me/workspaces' && str_contains($route->uri(), '{workspace}')) {
                    continue;
                }
                $path = preg_replace('#\{[a-z]+\}#', '1', preg_replace('#\A@\{workspace\}/#', '@alpha/', $route->uri()));
                $path = str_starts_with($path, '@') || str_starts_with($path, 'me/') ? $path : '@alpha/'.$path;
                $trail = app(Breadcrumbs::class)->trail(Request::create('/'.$path), str_starts_with($path, 'me/') ? null : Workspace::current(), new Principal(1));
                $this->assertNotSame([], $trail, $route->uri().' has no breadcrumb trail');
                $checked[] = $route->uri();
            }
        });
        // Every registered nav link, page and record page is among them.
        $this->assertContains('@{workspace}/notes', $checked);
        $this->assertContains('@{workspace}/notes/{note}', $checked);
        $this->assertContains('@{workspace}/settings', $checked);
        $this->assertContains('@{workspace}/connections', $checked);
        $this->assertContains('me/workspaces', $checked);
        foreach ($registry->navigationLinks() as $link) {
            $this->assertContains('@{workspace}/'.$link['suffix'], $checked, $link['label']);
        }
    }

    public function test_routes_cache(): void
    {
        // No route closures: the whole route list serialises. The cache is a temporary file, and no page is served from it here.
        $this->assertSame(0, app(Kernel::class)->call('route:cache'));
        $this->assertFileExists($this->routeCache);
        $this->assertStringContainsString('@{workspace}/notes', (string) file_get_contents($this->routeCache));
    }
}
