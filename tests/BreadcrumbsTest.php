<?php

use App\Models\User;
use Glhd\Gretel\Registry;
use Glhd\Gretel\Support\Cache;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tey\Concierge\Actions\AcceptInvitation;
use Tey\Concierge\Actions\InviteMember;
use Tey\Concierge\Support\GrantSet;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\Workspaces\Models\Workspace;

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
        $note = ['Notes', 'Alpha private'];
        // One workspace: the crumbs link in the short form, whichever form the page was reached by.
        foreach (['/notes/1', '/@alpha/notes/1'] as $path) {
            $this->get($path, $this->inertia())->assertOk()->assertJsonPath('component', 'Workspace/Note/Show/Page')
                ->assertJsonPath('props.breadcrumbs', [['title' => 'Notes', 'url' => '/notes', 'is_current_page' => false], ['title' => 'Alpha private', 'url' => '/notes/1', 'is_current_page' => true]]);
        }
        // A list page is its own, single crumb.
        $this->get('/notes', $this->inertia())->assertOk()->assertJsonPath('props.breadcrumbs', [['title' => 'Notes', 'url' => '/notes', 'is_current_page' => true]]);
        // Two workspaces: the same trail, in the full form.
        $this->addSecondWorkspace();
        $this->get('/@alpha/notes/1', $this->inertia())->assertOk()
            ->assertJsonPath('props.breadcrumbs', [['title' => 'Notes', 'url' => '/@alpha/notes', 'is_current_page' => false], ['title' => 'Alpha private', 'url' => '/@alpha/notes/1', 'is_current_page' => true]]);
        $this->get('/notes/1', $this->inertia())->assertOk()
            ->assertJsonPath('props.breadcrumbs.0.url', '/@alpha/notes');
        // The personal page is a trail of its own, with no workspace in it.
        $this->get('/me/workspaces', $this->inertia())->assertOk()->assertJsonPath('props.breadcrumbs', [['title' => 'Workspaces', 'url' => '/me/workspaces', 'is_current_page' => true]]);
    }

    public function test_every_page_route_has_a_breadcrumb(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $registry = app(Registry::class);
        $checked = [];
        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();
            if (in_array('GET', $route->methods(), true) && (str_starts_with($action, 'Tey\\Kit\\Features\\') || str_starts_with($action, 'App\\')) && ! str_contains($action, '@moved')) {
                $this->assertNotNull($registry->get($route), $route->uri().' declares no breadcrumb');
                $checked[] = $route->uri();
            }
        }
        // Every kit page, in both URL forms, and the personal one.
        foreach (['@{workspace}/notes', 'notes', '@{workspace}/notes/{note}', 'notes/{note}', '@{workspace}/settings', 'settings', '@{workspace}/connections', 'connections', 'me/workspaces'] as $uri) {
            $this->assertContains($uri, $checked);
        }
    }

    public function test_breadcrumbs_and_routes_cache(): void
    {
        // Breadcrumbs first, then the routes, as the deploy does; each to a temporary file, never the app's.
        $crumbs = sys_get_temp_dir().'/starter-crumbs-'.bin2hex(random_bytes(4)).'.php';
        try {
            $this->app->instance(Cache::class, new Cache(app(Filesystem::class), $crumbs));
            $this->assertSame(0, app(Kernel::class)->call('breadcrumbs:cache'));
            $this->assertFileExists($crumbs);
            $this->assertSame(0, app(Kernel::class)->call('route:cache'));
            $this->assertFileExists($this->routeCache);
            $this->assertStringContainsString('@{workspace}/notes', (string) file_get_contents($this->routeCache));
        } finally {
            @unlink($crumbs);
        }
    }
}
